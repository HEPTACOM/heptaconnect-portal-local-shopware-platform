<?php

declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Property\PropertyGroup;
use Shopware\Core\Framework\Uuid\Uuid;

class PropertyGroupUnpacker
{
    private TranslatableUnpacker $translatableUnpacker;

    public function __construct(TranslatableUnpacker $translatableUnpacker)
    {
        $this->translatableUnpacker = $translatableUnpacker;
    }

    public function unpack(PropertyGroup $propertyGroup): array
    {
        // TODO improve id generation
        $id = $propertyGroup->getPrimaryKey() ?? Uuid::randomHex();
        $propertyGroup->setPrimaryKey($id);

        return [
            'id' => $id,
            'translations' => $this->unpackTranslations($propertyGroup),
        ];
    }

    protected function unpackTranslations(PropertyGroup $propertyGroup): array
    {
        return $this->translatableUnpacker->unpack($propertyGroup->getName(), 'name');
    }
}
