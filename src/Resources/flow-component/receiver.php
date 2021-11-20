<?php

declare(strict_types=1);

use Heptacom\HeptaConnect\Dataset\Base\TypedDatasetEntityCollection;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Category;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Manufacturer;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Unit;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Property\PropertyGroup;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Property\PropertyValue;
use Heptacom\HeptaConnect\Portal\Base\Builder\FlowComponent;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker;

FlowComponent::receiver(Category::class)->batch(function (
    DalAccess $dal,
    TypedDatasetEntityCollection $categories,
    Unpacker\CategoryUnpacker $unpacker
): void {
    /** @var array[] $payloads */
    $payloads = \array_values(\iterable_to_array($categories->map([$unpacker, 'unpack'])));

    $dal->repository('category')->upsert($payloads, $dal->getContext());

    /** @var Category $category */
    foreach ($categories as $category) {
        $payload = \array_shift($payloads);
        $category->setPrimaryKey((string) $payload['id']);
    }
});

FlowComponent::receiver(Manufacturer::class)->batch(function (
    DalAccess $dal,
    TypedDatasetEntityCollection $manufacturers,
    Unpacker\ManufacturerUnpacker $unpacker
): void {
    $items = \iterable_to_array($manufacturers->map([$unpacker, 'unpack']));

    $dal->createSyncer()->upsert('product_manufacturer', $items)->flush();
});

FlowComponent::receiver(PropertyGroup::class)->batch(function (
    DalAccess $dal,
    TypedDatasetEntityCollection $propertyGroups,
    Unpacker\PropertyGroupUnpacker $unpacker
): void {
    $items = \iterable_to_array($propertyGroups->map([$unpacker, 'unpack']));

    $dal->createSyncer()->upsert('property_group', $items)->flush();
});

FlowComponent::receiver(PropertyValue::class)->batch(function (
    DalAccess $dal,
    TypedDatasetEntityCollection $propertyValues,
    Unpacker\PropertyValueUnpacker $unpacker
): void {
    $items = \iterable_to_array($propertyValues->map([$unpacker, 'unpack']));

    $dal->createSyncer()->upsert('property_group_option', $items)->flush();
});

FlowComponent::receiver(Unit::class)->batch(function (
    DalAccess $dal,
    TypedDatasetEntityCollection $propertyValues,
    Unpacker\UnitUnpacker $unpacker
): void {
    $items = \iterable_to_array($propertyValues->map([$unpacker, 'unpack']));

    $dal->createSyncer()->upsert('unit', $items)->flush();
});
