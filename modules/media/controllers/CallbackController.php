<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Media_CallbackController — AWS SNS delivery endpoint for ASYNC video moderation.
 *
 * When media.scan.video is on, a video is stored private + `in_review` and Rekognition
 * (StartContentModeration) posts its result to an SNS topic pointed here (POST /media/callback).
 * Guest-accessible (SNS carries no session), so nothing is trusted until it passes, in order
 * (TIGER-295):
 *
 *  1. the feature is on (`media.scan.video`) and a topic is configured (`media.scan.video_sns_topic`)
 *     — otherwise the endpoint is inert (404);
 *  2. the message's TopicArn is THAT topic — a genuine SNS signature only proves SNS sent it, and
 *     anyone can point their own topic here;
 *  3. the SNS signature verifies ({@see Tiger_Sns::verify()}).
 *
 * A subscription is confirmed only through Tiger_Sns::confirmSubscription(), which refuses any
 * SubscribeURL not on an SNS host (fetching a caller-supplied URL would be a server-side request
 * forgery). Processing the moderation verdict is still a scaffold until the S3 disk + SNS topic/role
 * are provisioned (P4/P5).
 */
class Media_CallbackController extends Tiger_Controller_Action
{
    /**
     * Handle an SNS POST — confirm a subscription or process a moderation notification.
     *
     * @return void
     */
    public function indexAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        $resp = $this->getResponse();

        $topic = self::_videoTopic();
        if ($topic === '') {
            $resp->setHttpResponseCode(404)->setBody('not found'); return;
        }

        $msg = json_decode((string) $this->_rawBody(), true);
        if (!is_array($msg)) { $resp->setHttpResponseCode(400)->setBody('bad request'); return; }

        if (!hash_equals($topic, (string) ($msg['TopicArn'] ?? '')) || !Tiger_Sns::verify($msg)) {
            $resp->setHttpResponseCode(403)->setBody('forbidden'); return;
        }

        $type = (string) ($msg['Type'] ?? '');

        // First-time topic handshake — confirm the subscription (SNS host only).
        if ($type === 'SubscriptionConfirmation') {
            $ok = Tiger_Sns::confirmSubscription($msg);
            $resp->setHttpResponseCode($ok ? 200 : 502)->setBody($ok ? 'confirmed' : 'confirmation failed'); return;
        }

        if ($type === 'Notification') {
            // TODO(P4): (1) JSON-decode $msg['Message'] -> JobId; (2) match the media row via
            // scan_meta.job_id; (3) Rekognition getContentModeration(JobId) for the verdict;
            // (4) set scan_status approved|rejected + unlock visibility.
            // Needs aws/aws-sdk-php + the job->media mapping recorded at submit time.
            $resp->setBody('ok'); return;
        }

        $resp->setBody('ignored');
    }

    /** The request body (a seam: php://input can't be written in tests). */
    protected function _rawBody()
    {
        return file_get_contents('php://input');
    }

    /** The configured SNS topic when video moderation is ON, else ''. */
    protected static function _videoTopic()
    {
        $cfg  = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $scan = ($cfg && $cfg->get('media') && $cfg->media->get('scan')) ? $cfg->media->scan : null;
        if (!$scan || (int) $scan->get('video') !== 1) {
            return '';
        }
        return trim((string) $scan->get('video_sns_topic'));
    }
}
