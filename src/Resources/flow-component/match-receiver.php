<?php

declare(strict_types=1);

use Heptacom\HeptaConnect\Dataset\Base\TypedDatasetEntityCollection;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Address\Country;
use Heptacom\HeptaConnect\Portal\Base\Builder\FlowComponent;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;

FlowComponent::receiver(Country::class)->batch(function (
    DalAccess $dal,
    TypedDatasetEntityCollection $countries
): void {
    $isoCodes = \iterable_to_array($countries->column('getIso'));

    if ($isoCodes === []) {
        return;
    }

    $isoCodes = \array_map('mb_strtoupper', $isoCodes);

    $criteria = new Criteria();
    $criteria->addFilter(new EqualsAnyFilter('iso', $isoCodes));

    $ids = \array_flip($dal->queryValueById('country', 'iso', $criteria));

    /** @var Country $country */
    foreach ($countries as $country) {
        $country->setPrimaryKey($ids[\mb_strtoupper($country->getIso())] ?? null);
    }
});
