<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Currency\Currency;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Price\Condition;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Price\Price;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Price\PriceCollection;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalSyncer;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\ExistingIdentifierCache;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\PrimaryKeyGenerator;
use Ramsey\Uuid\Uuid;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class ProductPriceUnpacker
{
    public const NS_CONDITION_CONTAINER_OR = '2caad876178011ebbeba832e03190a18';

    public const NS_CONDITION_CONTAINER_AND = 'c51261d8179911ebb4c3f3c3cb39b2d2';

    private DalAccess $dalAccess;

    private ExistingIdentifierCache $existingIdentifierCache;

    private PriceConditionUnpacker $priceConditionUnpacker;

    private ?string $defaultRuleId = null;

    public function __construct(
        DalAccess $dalAccess,
        ExistingIdentifierCache $existingIdentifierCache,
        PriceConditionUnpacker $priceConditionUnpacker
    ) {
        $this->dalAccess = $dalAccess;
        $this->existingIdentifierCache = $existingIdentifierCache;
        $this->priceConditionUnpacker = $priceConditionUnpacker;
    }

    public function unpack(PriceCollection $sourceCollection, string $productNumber): iterable
    {
        $syncer = $this->dalAccess->createSyncer();

        $ruleIds = [];

        /** @var Price $sourcePrice */
        foreach ($sourceCollection as $key => $sourcePrice) {
            $ruleIds[$key] = $this->preparePriceRuleId($sourcePrice, $syncer);
        }

        if ($syncer->getOperations() !== []) {
            $syncer->flush();
        }

        /** @var Price $sourcePrice */
        foreach ($sourceCollection as $key => $sourcePrice) {
            /** @var string $ruleId */
            $ruleId = $ruleIds[$key];

            yield $this->unpackProductPrice($sourcePrice, $productNumber, $ruleId);
        }
    }

    protected function unpackProductPrice(Price $price, string $productNumber, string $ruleId): array
    {
        $priceId = PrimaryKeyGenerator::generatePrimaryKey(
                $price,
                'da210b7c-fd7c-4aa6-a0ee-846a508482db'
            ) ?? Uuid::uuid4()->getHex();

        $price->setPrimaryKey($priceId);
        $price->setPrimaryKey($price->getPrimaryKey() ?? Uuid::uuid5(
                $ruleId,
                $productNumber.'__'.$price->getQuantityStart()
            )->getHex());

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

        if ($price->hasAttached(Price::class)) {
            $listPrice = $price->getAttachment(Price::class);

            if ($listPrice instanceof Price) {
                $targetPrice['price'][0]['listPrice'] = [
                    'currencyId' => $currencyId,
                    'gross' => $listPrice->getGross(),
                    'net' => $listPrice->getNet(),
                    'linked' => true,
                ];
            }
        }

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

    protected function preparePriceRuleId(Price $sourcePrice, DalSyncer $syncer): string
    {
        $targetConditions = [];
        $nameParts = [];

        $targetConditions[] = $orMergeCondition = [
            'id' => static fn(string $ruleId): string => (string) Uuid::uuid5(self::NS_CONDITION_CONTAINER_OR, $ruleId)->getHex(),
            'type' => 'orContainer',
            'position' => 0,
            'value' => [],
        ];

        $targetConditions[] = $andMergeCondition = [
            'id' => static fn(string $ruleId): string => (string) Uuid::uuid5(self::NS_CONDITION_CONTAINER_AND, $ruleId)->getHex(),
            'parentId' => $orMergeCondition['id'],
            'type' => 'andContainer',
            'position' => 0,
            'value' => [],
        ];

        $conditionEssences = [];

        /** @var Condition $sourceCondition */
        foreach ($sourcePrice->getConditions() as $key => $sourceCondition) {
            $conditionResult = $this->priceConditionUnpacker->unpack($sourceCondition);
            $nameParts[] = $conditionResult[PriceConditionUnpacker::NAME];
            $conditionEssence = $conditionResult[PriceConditionUnpacker::ESSENCE];
            unset($conditionResult[PriceConditionUnpacker::NAME], $conditionResult[PriceConditionUnpacker::ESSENCE]);

            \rsort($conditionEssence);
            $targetConditions[] = \array_merge(
                $conditionResult,
                [
                    'id' => static function (string $ruleId) use ($conditionEssence, $sourceCondition): string {
                        $sourceCondition->setPrimaryKey($sourceCondition->getPrimaryKey() ?? Uuid::uuid5('f59587c4-35e4-4474-a95b-e04babe60241', \json_encode([
                                'essence' => $conditionEssence,
                                'ruleId' => $ruleId,
                            ]))->getHex());

                        return $sourceCondition->getPrimaryKey();
                    },
                    'parentId' => $andMergeCondition['id'],
                    'position' => (int) $key,
                ]
            );
            $conditionEssences[] = $conditionEssence;
        }

        if ($conditionEssences === []) {
            return $this->getDefaultRuleId();
        }

        \usort($conditionEssences, static fn (array $a, array $b): int => \json_encode($a) <=> \json_encode($b));

        $ruleId = (string) Uuid::uuid5('a7a0d619-3fc3-40f6-b57c-31f895ae652a', \json_encode($conditionEssences))->getHex();

        foreach ($targetConditions as &$targetCondition) {
            $targetCondition['id'] = \is_callable($targetCondition['id']) ? $targetCondition['id']($ruleId) : $targetCondition['id'];
            $targetCondition['ruleId'] = $ruleId;

            if (\is_callable($targetCondition['parentId'] ?? null)) {
                $targetCondition['parentId'] = $targetCondition['parentId']($ruleId);
            }
        }

        $rule = [
            'id' => $ruleId,
            'name' => \implode(' and ', $nameParts),
            'priority' => 1,
            'moduleTypes' => [
                'types' => ['price'],
            ],
            'conditions' => $targetConditions,
        ];

        if (!$this->dalAccess->idExists('rule', $ruleId)) {
            $syncer->upsert('rule', [$rule]);
        }

        return $ruleId;
    }

    protected function getDefaultRuleId(): string
    {
        if ($this->defaultRuleId === null) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('conditions.type', 'alwaysValid'));
            $criteria->setLimit(1);

            $this->defaultRuleId = \iterable_to_array($this->dalAccess->ids('rule', $criteria, $this->dalAccess->getContext()))[0] ?? null;
        }

        return $this->defaultRuleId;
    }
}
