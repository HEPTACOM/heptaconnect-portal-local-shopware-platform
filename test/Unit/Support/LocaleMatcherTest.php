<?php

declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Test\Unit\Support;

use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\LocaleMatcher;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\LocaleMatcher
 */
class LocaleMatcherTest extends TestCase
{
    public function testInstantMatch(): void
    {
        $messages = [];
        $log = static function (string $l, string $m) use (&$messages): void {
            $messages[$l][] = $m;
        };
        $matcher = new LocaleMatcher(new class($log) extends AbstractLogger {
            private \Closure $onLog;

            public function __construct(\Closure $onLog)
            {
                $this->onLog = $onLog;
            }

            public function log($level, $message, array $context = []): void
            {
                $onLog = $this->onLog;
                $onLog($level, $message);
            }
        });
        static::assertSame('de-DE', $matcher->match(['de-DE'], 'de-DE'));
        static::assertEmpty($messages);
    }

    public function testNoMatchDueToEmptyHaystack(): void
    {
        $messages = [];
        $log = static function (string $l, string $m) use (&$messages): void {
            $messages[$l][] = $m;
        };
        $matcher = new LocaleMatcher(new class($log) extends AbstractLogger {
            private \Closure $onLog;

            public function __construct(\Closure $onLog)
            {
                $this->onLog = $onLog;
            }

            public function log($level, $message, array $context = []): void
            {
                $onLog = $this->onLog;
                $onLog($level, $message);
            }
        });
        static::assertNull($matcher->match([], 'de-DE'));
        static::assertEmpty($messages);
    }

    public function testNoMatchDueToMissingEntry(): void
    {
        $messages = [];
        $log = static function (string $l, string $m) use (&$messages): void {
            $messages[$l][] = $m;
        };
        $matcher = new LocaleMatcher(new class($log) extends AbstractLogger {
            private \Closure $onLog;

            public function __construct(\Closure $onLog)
            {
                $this->onLog = $onLog;
            }

            public function log($level, $message, array $context = []): void
            {
                $onLog = $this->onLog;
                $onLog($level, $message);
            }
        });
        static::assertNull($matcher->match(['en-GB'], 'de-DE'));
        static::assertNotEmpty($messages['error'] ?? []);
    }

    public function testMatchButAmbiguousMatch(): void
    {
        $messages = [];
        $log = static function (string $l, string $m) use (&$messages): void {
            $messages[$l][] = $m;
        };
        $matcher = new LocaleMatcher(new class($log) extends AbstractLogger {
            private \Closure $onLog;

            public function __construct(\Closure $onLog)
            {
                $this->onLog = $onLog;
            }

            public function log($level, $message, array $context = []): void
            {
                $onLog = $this->onLog;
                $onLog($level, $message);
            }
        });
        static::assertSame('de-AT', $matcher->match(['en-GB', 'de-AT', 'de-CH'], 'de'));
        static::assertNotEmpty($messages['warning'] ?? []);
        $messages = [];
        static::assertSame('de-AT', $matcher->match(['en-GB', 'de-AT', 'de-CH'], 'de-DE'));
        static::assertNotEmpty($messages['warning'] ?? []);
    }
}
