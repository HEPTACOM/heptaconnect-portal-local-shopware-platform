<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support;

use Heptacom\HeptaConnect\Portal\Base\Portal\Contract\PortalStorageInterface;

abstract class StorageHelper
{
    private const CUSTOMER_PRICE_GROUP_TAGS = '8b6084b3d9a74388949f7680c4e7dd7c';

    private const CUSTOMER_DISCOUNT_GROUP_TAGS = '8fcac17618a0430ca028a7fddeccd475';

    private static ?array $customerPriceGroupTagIds = null;

    private static ?array $customerDiscountGroupTagIds = null;

    public static function isCustomerPriceGroupTagId(?string $tagId, PortalStorageInterface $storage): bool
    {
        if ($tagId === null) {
            return false;
        }

        $customerPriceGroupTagIds = self::getCustomerPriceGroupTagIds($storage);

        return isset($customerPriceGroupTagIds[$tagId]);
    }

    public static function addCustomerPriceGroupTagId(string $tagId, PortalStorageInterface $storage): void
    {
        $customerPriceGroupTagIds = self::getCustomerPriceGroupTagIds($storage);

        $customerPriceGroupTagIds[$tagId] = true;
        self::$customerPriceGroupTagIds[$tagId] = true;

        $storage->set(self::CUSTOMER_PRICE_GROUP_TAGS, $customerPriceGroupTagIds);
    }

    public static function isCustomerDiscountGroupTagId(?string $tagId, PortalStorageInterface $storage): bool
    {
        if ($tagId === null) {
            return false;
        }

        $customerDiscountGroupTagIds = self::getCustomerDiscountGroupTagIds($storage);

        return isset($customerDiscountGroupTagIds[$tagId]);
    }

    public static function addCustomerDiscountGroupTagId(string $tagId, PortalStorageInterface $storage): void
    {
        $customerDiscountGroupTagIds = self::getCustomerDiscountGroupTagIds($storage);

        $customerDiscountGroupTagIds[$tagId] = true;
        self::$customerDiscountGroupTagIds[$tagId] = true;

        $storage->set(self::CUSTOMER_DISCOUNT_GROUP_TAGS, $customerDiscountGroupTagIds);
    }

    private static function getCustomerPriceGroupTagIds(PortalStorageInterface $storage): array
    {
        return self::$customerPriceGroupTagIds ??= (array) ($storage->get(self::CUSTOMER_PRICE_GROUP_TAGS) ?? []);
    }

    private static function getCustomerDiscountGroupTagIds(PortalStorageInterface $storage): array
    {
        return self::$customerDiscountGroupTagIds ??= (array) ($storage->get(self::CUSTOMER_DISCOUNT_GROUP_TAGS) ?? []);
    }
}
