<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;

class TranslationLocaleCache
{
    private DalAccess $dalAccess;

    private ?array $localeCache = null;

    public function __construct(DalAccess $dalAccess)
    {
        $this->dalAccess = $dalAccess;
    }

    public function getLocales(): array
    {
        $result = $this->localeCache;

        if (\is_array($result)) {
            return $result;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new NotFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('languages.id', null),
        ]));

        $this->localeCache = $this->dalAccess->queryValueById('locale', 'code', $criteria);

        return $this->localeCache;
    }
}
