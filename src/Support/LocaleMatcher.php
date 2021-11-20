<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support;

use Psr\Log\LoggerInterface;

class LocaleMatcher
{
    private LoggerInterface $logger;

    private int $matchDistance;

    public function __construct(LoggerInterface $logger, int $matchDistance = 8)
    {
        $this->logger = $logger;
        $this->matchDistance = $matchDistance;
    }

    public function match(array $haystack, string $needle): ?string
    {
        if (\in_array($needle, $haystack, true)) {
            return $needle;
        }

        if ($haystack === []) {
            return null;
        }

        $matches = [];

        foreach ($haystack as $straw) {
            $distance = \levenshtein($needle, $straw, 1, 4, 10);
            $this->logger->debug('LocaleMatcher: Language match test result', [
                'test' => $needle,
                'comparison' => $straw,
                'distance' => $distance,
                'considered' => $distance <= $this->matchDistance,
                'code' => 1637342440,
            ]);

            if ($distance <= $this->matchDistance) {
                $matches[] = $straw;
            }
        }

        if ($matches === []) {
            $this->logger->error('LocaleMatcher: Could not find a matching known locale code', [
                'locale' => $needle,
                'options' => $haystack,
                'code' => 1637342441,
            ]);

            return null;
        }

        $result = \array_shift($matches);

        if ($matches === []) {
            $this->logger->info('LocaleMatcher: Matching known locale code', [
                'locale' => $needle,
                'options' => $haystack,
                'result' => $result,
                'code' => 1637342442,
            ]);
        } else {
            $this->logger->warning('LocaleMatcher: Found too many matching locale codes', [
                'locale' => $needle,
                'options' => $haystack,
                'result' => $result,
                'other_matches' => $matches,
                'code' => 1637342443,
            ]);
        }

        return $result;
    }
}
