<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Manufacturer;
use Shopware\Core\Framework\Uuid\Uuid;

class ManufacturerUnpacker
{
    public function unpack(Manufacturer $source): array
    {
        // TODO improve id generation
        $targetManufacturerId = $source->getPrimaryKey() ?? Uuid::randomHex();
        $source->setPrimaryKey($targetManufacturerId);

        // TODO translations
        return [
            'id' => $targetManufacturerId,
            'name' => $source->getName()->getFallback(),
        ];
    }
}
