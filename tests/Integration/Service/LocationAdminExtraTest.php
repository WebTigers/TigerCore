<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace {
    // A global-namespace adapter whose constructor throws — so Tiger_Location::adapters()'s
    // build-and-skip catch (a bad/misconfigured provider never breaks the admin screen) is exercised.
    if (!class_exists('Tiger_Test_ThrowingLocationAdapter')) {
        class Tiger_Test_ThrowingLocationAdapter extends \Tiger_Location_Adapter_Abstract
        {
            public function __construct(array $config = []) { throw new \RuntimeException('adapter boom'); }
        }
    }
}

namespace Tiger\Tests\Integration\Service {

    use PHPUnit\Framework\Attributes\CoversClass;
    use PHPUnit\Framework\Attributes\Test;
    use Tiger\Tests\Support\IntegrationTestCase;
    use Tiger_Crypto;
    use Tiger_Location;
    use Tiger_Model_Config;
    use Zend_Config;
    use Zend_Registry;

    /**
     * Tiger_Location::saveSettings()/adapters() — the admin Location settings surface (the FacadeTest
     * unit suite covers the lookup routing; this reaches the config-writing admin half, which needs the DB).
     *
     * Characterizes:
     *   - saveSettings persists the provider selections + cache TTL + each adapter's declared fields to the
     *     global config tier, sanitizing provider names and flooring the TTL at 0;
     *   - a `secret` field is ENCRYPTED at rest when Tiger_Crypto is configured (stored under `<key>_enc`)
     *     and stored PLAINTEXT when crypto isn't configured;
     *   - a BLANK secret is skipped (keeps the existing one), and a non-submitted adapter is ignored;
     *   - adapters() build-and-skips a provider whose constructor throws (a bad provider never 500s the screen).
     */
    #[CoversClass(Tiger_Location::class)]
    final class LocationAdminExtraTest extends IntegrationTestCase
    {
        private ?Zend_Config $priorConfig = null;

        protected function setUp(): void
        {
            parent::setUp();
            $this->priorConfig = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        }

        protected function tearDown(): void
        {
            if ($this->priorConfig !== null) {
                Zend_Registry::set('Zend_Config', $this->priorConfig);
            } elseif (Zend_Registry::isRegistered('Zend_Config')) {
                Zend_Registry::set('Zend_Config', new Zend_Config([]));
            }
            parent::tearDown();
        }

        private function cfgGet(string $key): ?string
        {
            $v = (new Tiger_Model_Config())->get(Tiger_Model_Config::SCOPE_GLOBAL, '', $key);
            return $v === false ? null : $v;
        }

        #[Test]
        public function save_settings_persists_providers_ttl_and_a_secret_encrypted_when_crypto_is_configured(): void
        {
            // Crypto ON: a valid 32-byte key so Tiger_Crypto::isConfigured() → the secret is stored encrypted.
            Zend_Registry::set('Zend_Config', new Zend_Config([
                'tiger' => ['crypto' => ['key' => base64_encode(str_repeat("\x24", 32))]],
            ]));
            $this->assertTrue(Tiger_Crypto::isConfigured(), 'precondition: crypto configured for the enc branch');

            Tiger_Location::saveSettings([
                'ip_provider'      => 'ip@api!!',      // sanitized to [a-z0-9_-]
                'address_provider' => 'nominatim',
                'cache_ttl'        => '-50',           // floored to 0
                'adapters'         => [
                    'aws'      => [
                        'region'      => '  us-west-2  ',   // text → trimmed
                        'place_index' => 'my-index',
                        'key'         => 'AKIAEXAMPLE',      // secret → encrypted
                        'secret'      => '',                 // blank secret → kept (skipped)
                    ],
                    // 'nominatim' deliberately not submitted → the adapter loop skips it.
                ],
            ]);

            $this->assertSame('ipapi', $this->cfgGet('tiger.location.ip.provider'), 'the provider name is sanitized');
            $this->assertSame('nominatim', $this->cfgGet('tiger.location.address.provider'));
            $this->assertSame('0', $this->cfgGet('tiger.location.cache_ttl'), 'a negative TTL floors to 0');
            $this->assertSame('us-west-2', $this->cfgGet('tiger.location.adapters.aws.region'), 'a text field is trimmed');
            $this->assertSame('my-index', $this->cfgGet('tiger.location.adapters.aws.place_index'));

            // The secret is stored under `_enc` (encrypted), NOT as plaintext, and round-trips.
            $enc = $this->cfgGet('tiger.location.adapters.aws.key_enc');
            $this->assertNotNull($enc, 'the secret is stored encrypted at rest');
            $this->assertNull($this->cfgGet('tiger.location.adapters.aws.key'), 'no plaintext secret is written');
            $this->assertSame('AKIAEXAMPLE', Tiger_Crypto::decrypt($enc));

            // The blank secret was skipped entirely.
            $this->assertNull($this->cfgGet('tiger.location.adapters.aws.secret'));
            $this->assertNull($this->cfgGet('tiger.location.adapters.aws.secret_enc'));
        }

        #[Test]
        public function save_settings_stores_a_secret_in_plaintext_when_crypto_is_not_configured(): void
        {
            // Crypto OFF: no key → Tiger_Crypto::isConfigured() false → the secret falls back to plaintext.
            Zend_Registry::set('Zend_Config', new Zend_Config([]));

            Tiger_Location::saveSettings([
                'cache_ttl' => '3600',
                'adapters'  => ['aws' => ['key' => 'PLAINSECRET']],
            ]);

            $this->assertSame('3600', $this->cfgGet('tiger.location.cache_ttl'));
            $this->assertSame('PLAINSECRET', $this->cfgGet('tiger.location.adapters.aws.key'), 'no crypto → plaintext secret');
            $this->assertNull($this->cfgGet('tiger.location.adapters.aws.key_enc'));
        }

        #[Test]
        public function adapters_build_and_skips_a_provider_whose_constructor_throws(): void
        {
            Zend_Registry::set('Zend_Config', new Zend_Config([]));
            Tiger_Location::register('throwing', 'Tiger_Test_ThrowingLocationAdapter');

            $adapters = Tiger_Location::adapters();

            $this->assertArrayNotHasKey('throwing', $adapters, 'a provider that fails to construct is quietly skipped');
            $this->assertArrayHasKey('aws', $adapters, 'the healthy built-ins still list');
        }
    }
}
