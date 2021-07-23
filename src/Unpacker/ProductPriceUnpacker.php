<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Currency\Currency;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Price\Condition;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Price\Price;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Product;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\ExistingIdentifierCache;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\PrimaryKeyGenerator;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Shopware\Core\Defaults;

class ProductPriceUnpacker
{
    public const NS_RULE_ID = 'cbfb4fc6171911eb895d33ddd3eed5ba';

    public const NS_CONDITION_CONTAINER_OR = '2caad876178011ebbeba832e03190a18';

    public const NS_CONDITION_CONTAINER_AND = 'c51261d8179911ebb4c3f3c3cb39b2d2';

    private DalAccess $dalAccess;

    private ExistingIdentifierCache $existingIdentifierCache;

    private PriceConditionUnpacker $priceConditionUnpacker;

    public function __construct(
        DalAccess $dalAccess,
        ExistingIdentifierCache $existingIdentifierCache,
        PriceConditionUnpacker $priceConditionUnpacker
    ) {
        $this->dalAccess = $dalAccess;
        $this->existingIdentifierCache = $existingIdentifierCache;
        $this->priceConditionUnpacker = $priceConditionUnpacker;
    }

    public function unpack(Price $price, Product $product): array
    {
        $priceId = PrimaryKeyGenerator::generatePrimaryKey(
                $price,
                'da210b7c-fd7c-4aa6-a0ee-846a508482db'
            ) ?? RamseyUuid::uuid4()->getHex();

        $price->setPrimaryKey($priceId);
        $ruleIdSource = RamseyUuid::uuid5(self::NS_RULE_ID, $priceId);
        $price->setPrimaryKey($price->getPrimaryKey() ?? RamseyUuid::uuid5(
                $ruleIdSource->toString(),
                $product->getNumber().'__'.$price->getQuantityStart()
            )->getHex());
        $ruleId = $ruleIdSource->getHex();

        $targetConditions = [];
        $nameParts = [];

        $targetConditions[] = $orMergeCondition = [
            'id' => RamseyUuid::uuid5(self::NS_CONDITION_CONTAINER_OR, $ruleId)->getHex(),
            'ruleId' => $ruleId,
            'type' => 'orContainer',
            'position' => 0,
            'value' => [],
        ];

        $targetConditions[] = $andMergeCondition = [
            'id' => RamseyUuid::uuid5(self::NS_CONDITION_CONTAINER_AND, $ruleId)->getHex(),
            'ruleId' => $ruleId,
            'parentId' => $orMergeCondition['id'],
            'type' => 'andContainer',
            'position' => 0,
            'value' => [],
        ];

        /** @var Condition $sourceCondition */
        foreach ($price->getConditions() as $key => $sourceCondition) {
            $conditionResult = $this->priceConditionUnpacker->unpack($sourceCondition);
            $nameParts[] = $conditionResult[PriceConditionUnpacker::NAME];
            $conditionEssence = $conditionResult[PriceConditionUnpacker::ESSENCE];
            unset($conditionResult[PriceConditionUnpacker::NAME], $conditionResult[PriceConditionUnpacker::ESSENCE]);

            \rsort($conditionEssence);
            $sourceCondition->setPrimaryKey($sourceCondition->getPrimaryKey() ?? RamseyUuid::uuid5('02d88b5b-bacf-4ef9-b17b-6a8a879b88bc', \implode(';', $conditionEssence))->getHex());
            $targetConditions[] = \array_merge(
                $conditionResult,
                [
                    'id' => $sourceCondition->getPrimaryKey(),
                    'ruleId' => $ruleId,
                    'parentId' => $andMergeCondition['id'],
                    'position' => (int) $key,
                ]
            );
        }

        $rule = [
            'id' => $ruleId,
            'name' => \implode(' und ', $nameParts),
            'priority' => 1,
            'moduleTypes' => [
                'types' => ['price'],
            ],
            'conditions' => $targetConditions,
        ];

        $this->dalAccess->repository('rule')->upsert([$rule], $this->dalAccess->getContext());

        if ($price->getCurrency() instanceof Currency) {
            // TODO: Use mapping
            $currencyId = $this->existingIdentifierCache->getCurrencyId($price->getCurrency()->getIso());
        } else {
            $currencyId = Defaults::CURRENCY;
        }

        $targetPrice = [
            'id' => $price->getPrimaryKey(),
            'quantityStart' => $price->getQuantityStart(),
            'price' => [[
                'currencyId' => $currencyId,
                'gross' => $price->getGross(),
                'net' => $price->getNet(),
                'linked' => true,
            ]],
            'ruleId' => $ruleId,
        ];

        if ($currencyId !== Defaults::CURRENCY) {
            $targetPrice['price'][] = [
                'currencyId' => Defaults::CURRENCY,
                'gross' => 0,
                'net' => 0,
                'linked' => true,
            ];
        }

        return $targetPrice;
    }
}
