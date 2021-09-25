<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support;

class Translator
{
    private TranslationLocaleCache $translationLocaleCache;

    public function __construct(TranslationLocaleCache $translationLocaleCache)
    {
        $this->translationLocaleCache = $translationLocaleCache;
    }

    public function filterByValidTranslationKeys(array $translations): array
    {
        $cache = $this->translationLocaleCache->getLocales();

        return \array_intersect_ukey(
            $translations,
            $cache,
            static function ($transKey, string $localeKey) use ($cache): int {
                return ($transKey === $localeKey || \in_array($transKey, $cache, true)) ? 0 : 1;
            }
        );
    }

    public function exchangeLocaleKeysToLanguageKeys(array $translations): array
    {
        $result = [];
        $cache = $this->translationLocaleCache->getLocales();

        foreach ($translations as $localeKey => $translation) {
            $languageId = \array_search($localeKey, $cache, true);

            if ($languageId === false) {
                if (\array_key_exists($localeKey, $cache)) {
                    $languageId = $localeKey;
                } else {
                    continue;
                }
            }

            $result[$languageId] = \array_merge($result[$languageId] ?? [], $translation);
        }

        return $result;
    }
}
