<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Test\Unit\Unpacker;

use Heptacom\HeptaConnect\Dataset\Base\Translatable\TranslatableString;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\LocaleMatcher;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\TranslationLocaleCache;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\TranslatableUnpacker;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;

/**
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\TranslatableUnpacker
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\LocaleMatcher
 */
class TranslatableUnpackerTest extends TestCase
{
    public function testStringFallback(): void
    {
        $cache = $this->createMock(TranslationLocaleCache::class);
        $cache->method('getLocales')->willReturn([
            '7faafd19715542acb601356550f3af9a' => 'de-DE',
            'e40e9fdb1dc547978d88cc629bc04442' => 'nl-NL',
            '4bfcafc124d349239369ef4f8749f48b' => 'en-GB',
        ]);
        $unpacker = new TranslatableUnpacker($cache, new LocaleMatcher(new NullLogger()), new NullLogger());

        $translatable = new TranslatableString();
        $translatable->setFallback('foobar');
        self::assertEquals([
            'de-DE' =>  [
                'name' => 'foobar',
            ],
            'nl-NL' =>  [
                'name' => 'foobar',
            ],
            'en-GB' =>  [
                'name' => 'foobar',
            ],
        ], $unpacker->unpack($translatable, 'name'));
    }

    public function testStringExtractLocaleCodes(): void
    {
        $cache = $this->createMock(TranslationLocaleCache::class);
        $cache->method('getLocales')->willReturn([
            '7faafd19715542acb601356550f3af9a' => 'de-DE',
            'e40e9fdb1dc547978d88cc629bc04442' => 'nl-NL',
            '4bfcafc124d349239369ef4f8749f48b' => 'en-GB',
        ]);
        $unpacker = new TranslatableUnpacker($cache, new LocaleMatcher(new NullLogger()), new NullLogger());

        $translatable = new TranslatableString();
        $translatable->setFallback('foobar');
        $translatable->setTranslation('de-DE', 'fööbär');

        self::assertEquals([
            'de-DE' =>  [
                'name' => 'fööbär',
            ],
            'nl-NL' =>  [
                'name' => 'foobar',
            ],
            'en-GB' =>  [
                'name' => 'foobar',
            ],
        ], $unpacker->unpack($translatable, 'name'));
    }

    public function testStringVagueLocaleCodes(): void
    {
        $cache = $this->createMock(TranslationLocaleCache::class);
        $cache->method('getLocales')->willReturn([
            '7faafd19715542acb601356550f3af9a' => 'de-DE',
            'e40e9fdb1dc547978d88cc629bc04442' => 'nl-NL',
            '4bfcafc124d349239369ef4f8749f48b' => 'en-GB',
        ]);
        $unpacker = new TranslatableUnpacker($cache, new LocaleMatcher(new NullLogger()), new NullLogger());

        $translatable = new TranslatableString();
        $translatable->setFallback('foobar');
        $translatable->setTranslation('de', 'fööbär');

        self::assertEquals([
            'de-DE' =>  [
                'name' => 'fööbär',
            ],
            'nl-NL' =>  [
                'name' => 'foobar',
            ],
            'en-GB' =>  [
                'name' => 'foobar',
            ],
        ], $unpacker->unpack($translatable, 'name'));
    }

    public function testStringUnknownLocaleCodes(): void
    {
        $messages = [];
        $log = static function (string $l, string $m) use (&$messages) {
            $messages[$l][] = $m;
        };
        $logger = new class ($log) extends AbstractLogger {
            private \Closure $onLog;

            public function __construct(\Closure $onLog)
            {
                $this->onLog = $onLog;
            }

            public function log($level, $message, array $context = array())
            {
                $onLog = $this->onLog;
                $onLog($level, $message);
            }
        };
        $cache = $this->createMock(TranslationLocaleCache::class);
        $cache->method('getLocales')->willReturn([
            '7faafd19715542acb601356550f3af9a' => 'de-DE',
            'e40e9fdb1dc547978d88cc629bc04442' => 'nl-NL',
            '4bfcafc124d349239369ef4f8749f48b' => 'en-GB',
        ]);
        $unpacker = new TranslatableUnpacker($cache, new LocaleMatcher(new NullLogger()), $logger);

        $translatable = new TranslatableString();
        $translatable->setFallback('foobar');
        $translatable->setTranslation('fr', 'fôbà');

        self::assertEquals([
            'de-DE' =>  [
                'name' => 'foobar',
            ],
            'nl-NL' =>  [
                'name' => 'foobar',
            ],
            'en-GB' =>  [
                'name' => 'foobar',
            ],
        ], $unpacker->unpack($translatable, 'name'));
        self::assertNotEmpty($messages['error'] ?? []);
    }
}
