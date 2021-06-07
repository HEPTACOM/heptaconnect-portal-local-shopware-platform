<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Unit;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\Translator;
use Ramsey\Uuid\Uuid;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class UnitUnpacker
{
    public const NS_UNIT = '54f3002accfa4b72a4d5102a634824ae';

    private DalAccess $dalAccess;

    private Translator $translator;

    public function __construct(DalAccess $dalAccess, Translator $translator)
    {
        $this->dalAccess = $dalAccess;
        $this->translator = $translator;
    }

    public function unpack(Unit $source): array
    {
        $id = $source->getPrimaryKey();
        $defaultName = $source->getName()->getFallback();
        $name = null;

        $translations = [];

        foreach ($source->getName()->getLocaleKeys() as $localeKey) {
            $name = \trim($source->getName()->getTranslation($localeKey, false));

            if ($name === '') {
                continue;
            }

            $translations[$localeKey] = [
                'name' => $name,
                'shortCode' => !empty($source->getSymbol()) ? $source->getSymbol() : $name,
            ];
        }

        $translations[Defaults::LANGUAGE_SYSTEM] = [
            'name' => $defaultName ?? ('Unit '.$source->getSymbol()),
            'shortCode' => !empty($source->getSymbol()) ? $source->getSymbol() : ($name ?? $defaultName),
        ];

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
            $id = Uuid::uuid5(self::NS_UNIT, $source->getSymbol())->getHex();
        }

        $source->setPrimaryKey($id);

        return [
            'id' => $source->getPrimaryKey(),
            'shortCode' => $source->getSymbol(),
            'translations' => $this->translator->exchangeLocaleKeysToLanguageKeys($translations),
        ];
    }
}
