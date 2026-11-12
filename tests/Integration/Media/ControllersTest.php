<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Media;

use Media_AdminController;
use Media_CallbackController;
use Media_FileController;
use Media_IndexController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\ModuleControllerTestCase;
use Tiger\Tests\Support\SnsSigner;
use Tiger_Model_Media;
use Tiger_Sns;

/**
 * The four Media controllers:
 *   - Index    — the Media Library shell (uploader + DataTables), computes the client-thumbnail flag.
 *   - Admin    — the settings screen (obfuscation form prefill).
 *   - File     — streams PRIVATE objects through an ACL/org guard; the miss + cross-org branches 404/403
 *                before the byte stream (the stream itself needs a real disk, out of harness scope).
 *   - Callback — the SNS moderation endpoint (TIGER-295): inert unless video moderation + its topic are
 *                configured, and nothing is acted on (least of all a SubscribeURL fetched) unless the
 *                message is for THAT topic and its SNS signature verifies. Bodies via SnsCallbackProbe.
 */
#[CoversClass(Media_IndexController::class)]
#[CoversClass(Media_AdminController::class)]
#[CoversClass(Media_FileController::class)]
#[CoversClass(Media_CallbackController::class)]
final class ControllersTest extends ModuleControllerTestCase
{
    #[Test]
    public function index_renders_the_library_shell_with_thumbnail_config(): void
    {
        $this->loginAs('admin');
        $this->dispatchAction(Media_IndexController::class, 'index', [], 'GET');

        $view = $this->controller()->view;
        $this->assertStringContainsString('Media Library', (string) $view->title);
        $this->assertTrue((bool) $view->useDataTables);
        $this->assertSame(200, (int) $view->thumbPx);
        $this->assertIsBool($view->clientThumb);
    }

    #[Test]
    public function admin_settings_prefills_the_obfuscation_form(): void
    {
        $this->loginAs('admin');
        $this->dispatchAction(Media_AdminController::class, 'settings', [], 'GET');

        $view = $this->controller()->view;
        $this->assertStringContainsString('Media Settings', (string) $view->title);
        $this->assertInstanceOf(\Media_Form_Settings::class, $view->form);
    }

    #[Test]
    public function file_serve_404s_for_a_missing_id(): void
    {
        $res = $this->dispatchAction(Media_FileController::class, 'serve', ['id' => ''], 'GET');
        $this->assertSame(404, $res->getHttpResponseCode());
    }

    #[Test]
    public function file_serve_403s_a_cross_org_private_object(): void
    {
        // A private object owned by org-a; a guest (no org) hits the org-scope guard → 403.
        $id = (new Tiger_Model_Media())->insert([
            'org_id'      => 'org-a',
            'disk'        => 'local',
            'storage_key' => 'org-a/documents/secret.txt',
            'visibility'  => Tiger_Model_Media::VISIBILITY_PRIVATE,
            'kind'        => Tiger_Model_Media::KIND_DOCUMENT,
            'mime_type'   => 'text/plain',
            'extension'   => 'txt',
            'file_size'   => 5,
            'filename'    => 'secret.txt',
            'title'       => 'Secret',
        ]);

        $res = $this->dispatchAction(Media_FileController::class, 'serve', ['id' => $id], 'GET');
        $this->assertSame(403, $res->getHttpResponseCode());
    }

    #[Test]
    public function callback_is_inert_while_video_moderation_is_off(): void
    {
        $res = $this->postToCallback('{"Type":"SubscriptionConfirmation","SubscribeURL":"http://169.254.169.254/latest/meta-data/"}', false);
        $this->assertSame(404, $res->getHttpResponseCode());
        $this->assertSame([], $this->sns->fetched, 'nothing was fetched');
    }

    #[Test]
    public function callback_400s_an_empty_body(): void
    {
        $res = $this->postToCallback('');
        $this->assertSame(400, $res->getHttpResponseCode());
        $this->assertStringContainsString('bad request', $res->getBody());
    }

    #[Test]
    public function an_unsigned_subscribe_url_is_refused_and_never_fetched(): void
    {
        // The pre-fix endpoint fetched this URL for any anonymous caller (server-side request forgery).
        $res = $this->postToCallback(json_encode(['Type' => 'SubscriptionConfirmation', 'TopicArn' => SnsSigner::TOPIC,
                                            'SubscribeURL' => 'http://169.254.169.254/latest/meta-data/']));
        $this->assertSame(403, $res->getHttpResponseCode());
        $this->assertNotContains('http://169.254.169.254/latest/meta-data/', $this->sns->fetched);
    }

    #[Test]
    public function a_genuine_message_for_another_topic_is_refused(): void
    {
        $msg = $this->sns->message('SubscriptionConfirmation', ['TopicArn' => 'arn:aws:sns:us-east-1:999999999999:someone-else']);
        $this->assertSame(403, $this->postToCallback(json_encode($msg))->getHttpResponseCode());
        $this->assertSame([], $this->sns->fetched, 'refused before the certificate was even fetched');
    }

    #[Test]
    public function a_genuine_subscription_for_our_topic_is_confirmed_at_its_sns_url(): void
    {
        $msg = $this->sns->message('SubscriptionConfirmation');
        $res = $this->postToCallback(json_encode($msg));
        $this->assertSame(200, $res->getHttpResponseCode());
        $this->assertSame('confirmed', $res->getBody());
        $this->assertContains($msg['SubscribeURL'], $this->sns->fetched);
    }

    #[Test]
    public function a_signed_subscription_pointing_off_sns_is_not_followed(): void
    {
        $msg = $this->sns->message('SubscriptionConfirmation', ['SubscribeURL' => 'https://evil.example/collect']);
        $res = $this->postToCallback(json_encode($msg));
        $this->assertSame(502, $res->getHttpResponseCode());
        $this->assertNotContains('https://evil.example/collect', $this->sns->fetched);
    }

    #[Test]
    public function a_genuine_notification_is_accepted_and_a_tampered_one_is_not(): void
    {
        $msg = $this->sns->message('Notification');
        $this->assertSame(200, $this->postToCallback(json_encode($msg))->getHttpResponseCode());

        $msg['Message'] = '{"JobId":"forged","Status":"SUCCEEDED"}';
        $this->assertSame(403, $this->postToCallback(json_encode($msg))->getHttpResponseCode());
    }

    private SnsSigner $sns;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sns = new SnsSigner();
        $this->sns->install();
    }

    protected function tearDown(): void
    {
        Tiger_Sns::reset();
        SnsCallbackProbe::$body = '';
        parent::tearDown();
    }

    /** POST $body to the callback with video moderation on (and our topic configured) or off. */
    private function postToCallback(string $body, bool $on = true): \Zend_Controller_Response_Http
    {
        $this->setConfig(['media' => ['scan' => ['video' => $on ? '1' : '0', 'video_sns_topic' => SnsSigner::TOPIC]]]);
        SnsCallbackProbe::$body = $body;
        return $this->dispatchAction(SnsCallbackProbe::class, 'index', [], 'POST');
    }
}

/** The callback with its request body supplied by the test (php://input can't be written under CLI). */
final class SnsCallbackProbe extends Media_CallbackController
{
    /** @var string */
    public static $body = '';

    protected function _rawBody()
    {
        return self::$body;
    }
}
