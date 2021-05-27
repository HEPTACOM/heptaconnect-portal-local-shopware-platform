<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Property\PropertyGroup;
use Shopware\Core\Framework\Uuid\Uuid;

class PropertyGroupUnpacker
{
    public function unpack(PropertyGroup $propertyGroup): array
    {
        // TODO improve id generation
        $id = $propertyGroup->getPrimaryKey() ?? Uuid::randomHex();
        $propertyGroup->setPrimaryKey($id);

        // TODO translations
        return [
            'id' => $id,
            'name' => $propertyGroup->getName()->getFallback(),
        ];
    }
}
