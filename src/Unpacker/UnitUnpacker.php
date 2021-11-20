<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker;

use Heptacom\HeptaConnect\Dataset\Base\Translatable\TranslatableString;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Unit;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Ramsey\Uuid\Uuid;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class UnitUnpacker
{
    public const NS_UNIT = '54f3002accfa4b72a4d5102a634824ae';

    private TranslatableUnpacker $translatableUnpacker;

    private DalAccess $dalAccess;

    public function __construct(TranslatableUnpacker $translatableUnpacker, DalAccess $dalAccess)
    {
        $this->translatableUnpacker = $translatableUnpacker;
        $this->dalAccess = $dalAccess;
    }

    public function unpack(Unit $source): array
    {
        $id = $source->getPrimaryKey();
        $defaultName = $source->getName()->getFallback();
        $name = null;

        if ($id === null) {
            $unitCriteria = (new Criteria())
                ->addFilter(new EqualsFilter('name', $name ?? $defaultName))
                ->setLimit(1);

            $id = $this->dalAccess
                ->repository('unit')
                ->searchIds($unitCriteria, $this->dalAccess->getContext())
                ->firstId();
        }

        if ($id === null) {
            $id = (string) Uuid::uuid5(self::NS_UNIT, $source->getSymbol())->getHex();
        }

        $source->setPrimaryKey($id);

        return [
            'id' => $source->getPrimaryKey(),
            'translations' => $this->unpackTranslations($source),
        ];
    }

    protected function unpackTranslations(Unit $unit): array
    {
        $symbol = new TranslatableString();
        $symbol->setFallback($unit->getSymbol());

        return \array_merge_recursive(
            [],
            $this->translatableUnpacker->unpack($unit->getName(), 'name'),
            $this->translatableUnpacker->unpack($symbol, 'shortCode'),
        );
    }
}
