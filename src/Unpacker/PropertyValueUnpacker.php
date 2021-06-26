<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Property\PropertyValue;
use Shopware\Core\Framework\Uuid\Uuid;

class PropertyValueUnpacker
{
    public function unpack(PropertyValue $propertyValue): array
    {
        // TODO improve id generation
        $id = $propertyValue->getPrimaryKey() ?? Uuid::randomHex();
        $propertyValue->setPrimaryKey($id);

        // TODO translations
        return [
            'id' => $id,
            'propertyGroupId' => $propertyValue->getGroup()->getPrimaryKey(),
            'name' => $propertyValue->getName()->getFallback(),
        ];
    }
}
