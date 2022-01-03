<?php

declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support;

use Shopware\Core\System\Language\LanguageLoaderInterface;

class TranslationLocaleCache
{
    private LanguageLoaderInterface $languageLoader;

    private ?array $localeCache = null;

    public function __construct(LanguageLoaderInterface $languageLoader)
    {
        $this->languageLoader = $languageLoader;
    }

    public function getLocales(): array
    {
        return $this->localeCache ??= \array_column(
            $this->languageLoader->loadLanguages(),
            'code',
            'id'
        );
    }
}
