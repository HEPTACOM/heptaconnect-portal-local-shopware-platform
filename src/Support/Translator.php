<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Locale\LocaleCollection;

class Translator
{
    private DalAccess $dalAccess;

    private ?array $localeCache = null;

    public function __construct(DalAccess $dalAccess)
    {
        $this->dalAccess = $dalAccess;
    }

    public function filterByValidTranslationKeys(array $translations): array
    {
        $cache = $this->getLocaleCache();

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
        $cache = $this->getLocaleCache();

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

    public function getIngredientTranslation(array $translatableName): array
    {
        $translations = [];
        foreach ($translatableName as $locale => $value) {
            $name = \trim($value);

            if ($name === '') {
                continue;
            }

            $translations[$locale] = [
                'name' => $name,
            ];
        }

        $translations[Defaults::LANGUAGE_SYSTEM] = [
            'name' => $defaultName ?? $name,
        ];

        return $translations;
    }

    private function getLocaleCache(): array
    {
        $result = $this->localeCache;

        if (\is_null($result)) {
            $criteria = new Criteria();
            $criteria->setLimit(50);
            $criteria->addAssociation('languages');
            $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_OR, [
                new EqualsFilter('languages.id', null),
            ]));
            $iterator = new RepositoryIterator(
                $this->dalAccess->repository('locale'),
                $this->dalAccess->getContext(),
                $criteria,
            );

            while (!\is_null($entityResult = $iterator->fetch())) {
                /** @var LocaleCollection $locales */
                $locales = $entityResult->getEntities();

                foreach ($locales->getIterator() as $locale) {
                    $languages = $locale->getLanguages();

                    if (!$languages instanceof LanguageCollection) {
                        continue;
                    }

                    foreach ($languages->getIterator() as $language) {
                        $result[$language->getId()] = $locale->getCode();
                    }
                }
            }

            $this->localeCache = $result;
        }

        return $result;
    }
}
