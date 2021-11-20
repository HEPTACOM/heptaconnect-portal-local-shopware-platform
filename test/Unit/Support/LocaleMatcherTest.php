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
        $log = static function (string $l, string $m) use (&$messages) {
            $messages[$l][] = $m;
        };
        $matcher = new LocaleMatcher(new class ($log) extends AbstractLogger {
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
        });
        self::assertSame('de-DE', $matcher->match(['de-DE'], 'de-DE'));
        self::assertEmpty($messages);
    }

    public function testNoMatchDueToEmptyHaystack(): void
    {
        $messages = [];
        $log = static function (string $l, string $m) use (&$messages) {
            $messages[$l][] = $m;
        };
        $matcher = new LocaleMatcher(new class ($log) extends AbstractLogger {
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
        });
        self::assertNull($matcher->match([], 'de-DE'));
        self::assertEmpty($messages);
    }

    public function testNoMatchDueToMissingEntry(): void
    {
        $messages = [];
        $log = static function (string $l, string $m) use (&$messages) {
            $messages[$l][] = $m;
        };
        $matcher = new LocaleMatcher(new class ($log) extends AbstractLogger {
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
        });
        self::assertNull($matcher->match(['en-GB'], 'de-DE'));
        self::assertNotEmpty($messages['error'] ?? []);
    }

    public function testMatchButAmbiguousMatch(): void
    {
        $messages = [];
        $log = static function (string $l, string $m) use (&$messages) {
            $messages[$l][] = $m;
        };
        $matcher = new LocaleMatcher(new class ($log) extends AbstractLogger {
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
        });
        self::assertSame('de-AT', $matcher->match(['en-GB', 'de-AT', 'de-CH'], 'de'));
        self::assertNotEmpty($messages['warning'] ?? []);
        $messages = [];
        self::assertSame('de-AT', $matcher->match(['en-GB', 'de-AT', 'de-CH'], 'de-DE'));
        self::assertNotEmpty($messages['warning'] ?? []);
    }
}
