<?php

declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker;

use Heptacom\HeptaConnect\Dataset\Base\Translatable\TranslatableString;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Customer\CustomerGroup;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\PrimaryKeyGenerator;
use Ramsey\Uuid\Uuid;

class CustomerGroupUnpacker
{
    private TranslatableUnpacker $translatableUnpacker;

    private DalAccess $dal;

    public function __construct(TranslatableUnpacker $translatableUnpacker, DalAccess $dal)
    {
        $this->translatableUnpacker = $translatableUnpacker;
        $this->dal = $dal;
    }

    public function unpack(CustomerGroup $customerGroup): array
    {
        $alreadyExists = $this->dal->idExists('customer_group', $customerGroup->getPrimaryKey());

        $result = [
            'id' => $this->unpackId($customerGroup, $alreadyExists),
            'translations' => $this->unpackTranslations($customerGroup),
        ];

        if (!$alreadyExists) {
            $result['displayGross'] = true;
        }

        return $result;
    }

    protected function unpackId(CustomerGroup $customerGroup, bool $alreadyExists): ?string
    {
        if (!$alreadyExists) {
            return PrimaryKeyGenerator::generatePrimaryKey($customerGroup, 'b72df67c-426d-42e1-846c-10f7815c1761') ?? (string) Uuid::uuid5('7ead4bd5-1d7c-4a5e-be6b-6f5f709dfb3c', $customerGroup->getCode())->getHex();
        }

        return $customerGroup->getPrimaryKey();
    }

    protected function unpackTranslations(CustomerGroup $customerGroup): array
    {
        $translatable = new TranslatableString();
        $translatable->setFallback($customerGroup->getName());

        return $this->translatableUnpacker->unpack($translatable, 'name');
    }
}
