<?php

declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker;

use Heptacom\HeptaConnect\Dataset\Base\Translatable\Contract\TranslatableInterface;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\LocaleMatcher;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\TranslationLocaleCache;
use Psr\Log\LoggerInterface;

class TranslatableUnpacker
{
    private TranslationLocaleCache $translationLocaleCache;

    private LocaleMatcher $localeMatcher;

    private LoggerInterface $logger;

    public function __construct(
        TranslationLocaleCache $translationLocaleCache,
        LocaleMatcher $localeMatcher,
        LoggerInterface $logger
    ) {
        $this->translationLocaleCache = $translationLocaleCache;
        $this->localeMatcher = $localeMatcher;
        $this->logger = $logger;
    }

    public function unpack(TranslatableInterface $translatable, string $field): array
    {
        $result = [];
        $knownLocales = $this->translationLocaleCache->getLocales();

        foreach ($knownLocales as $localeCode) {
            $result[$localeCode][$field] = $translatable->getTranslation($localeCode, true);
        }

        foreach ($translatable->getLocaleKeys() as $localeCode) {
            $lookedUpLocaleCode = $this->localeMatcher->match($knownLocales, $localeCode);
            $value = $translatable->getTranslation($localeCode);

            if (!\is_string($lookedUpLocaleCode)) {
                $this->logger->error('TranslatableUnpacker: Cannot match locale code for field translation', [
                    'field' => $field,
                    'locale' => $localeCode,
                    'value' => $value,
                    'code' => 1637344184,
                ]);

                continue;
            }

            $result[$lookedUpLocaleCode][$field] = $value;
        }

        return $result;
    }
}
