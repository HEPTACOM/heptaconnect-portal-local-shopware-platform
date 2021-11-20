<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Property\PropertyValue;
use Shopware\Core\Framework\Uuid\Uuid;

class PropertyValueUnpacker
{
    private TranslatableUnpacker $translatableUnpacker;

    public function __construct(TranslatableUnpacker $translatableUnpacker)
    {
        $this->translatableUnpacker = $translatableUnpacker;
    }

    public function unpack(PropertyValue $propertyValue): array
    {
        // TODO improve id generation
        $id = $propertyValue->getPrimaryKey() ?? Uuid::randomHex();
        $propertyValue->setPrimaryKey($id);

        return [
            'id' => $id,
            'groupId' => $propertyValue->getGroup()->getPrimaryKey(),
            'translations' => $this->unpackTranslations($propertyValue),
        ];
    }

    protected function unpackTranslations(PropertyValue $propertyValue): array
    {
        return $this->translatableUnpacker->unpack($propertyValue->getName(), 'name');
    }
}
