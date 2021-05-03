<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker;

use Heptacom\HeptaConnect\Core\Storage\NormalizationRegistry;
use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Currency\Currency;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Media\Media;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Price\Condition;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Price\Condition\ValidityPeriodCondition;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Price\Price;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Category;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Manufacturer;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Product;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Unit;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Tax\TaxGroupRule;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\ExistingIdentifierCache;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\PrimaryKeyGenerator;
use Psr\Http\Message\StreamInterface;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class ProductUnpacker
{
    private const NS_PRODUCT = '5fb023e75e65494f9160b99602ce0587';

    private const NS_UNIT = '07c24254c14d4d648f972413aaab15ba';

    public const NS_RULE_ID = 'cbfb4fc6171911eb895d33ddd3eed5ba';

    public const NS_CONDITION_CONTAINER_OR = '2caad876178011ebbeba832e03190a18';

    public const NS_CONDITION_CONTAINER_AND = 'c51261d8179911ebb4c3f3c3cb39b2d2';

    private DalAccess $dalAccess;

    private ExistingIdentifierCache $existingIdentifierCache;

    private MediaService $mediaService;

    private NormalizationRegistry $normalizationRegistry;

    private ?SalesChannelCollection $salesChannels = null;

    public function __construct(
        DalAccess $dalAccess,
        ExistingIdentifierCache $existingIdentifierCache,
        MediaService $mediaService,
        NormalizationRegistry $normalizationRegistry
    ) {
        $this->dalAccess = $dalAccess;
        $this->existingIdentifierCache = $existingIdentifierCache;
        $this->mediaService = $mediaService;
        $this->normalizationRegistry = $normalizationRegistry;
    }

    public function unpack(Product $source): array
    {
        $productId = $this->unpackProductId($source);

        $source->setPrimaryKey($productId);

        $visibilities = $this->unpackVisibilities($source);
        $unitId = $this->unpackUnitId($source->getUnit());
        $taxId = $this->unpackTaxId($source);
        $prices = iterable_to_array($this->unpackPrices($source));
        $active = $source->isActive();
        $price = [
            [
                'gross' => 0,
                'net' => 0,
                'linked' => true,
            ]
        ];

        if ($source->getPrices()->count() < 1) {
            $unconditionalPrices = \iterable_to_array(
                $source->getPrices()->filter(static fn (Price $price): bool => $price->getConditions()->count() === 0)
            );

            if (empty($unconditionalPrices)) {
                // TODO warning
                $active = false;
            } else {
                \usort($unconditionalPrices, static function (Price $a, Price $b): int {
                    $quantitySign = $a->getQuantityStart() <=> $b->getQuantityStart();

                    if ($quantitySign === 0) {
                        return $b->getNet() <=> $a->getNet();
                    }

                    return $quantitySign;
                });
                /** @var Price $unconditionalPrice */
                $unconditionalPrice = \current($unconditionalPrices);

                $price[0]['gross'] = $unconditionalPrice->getGross();
                $price[0]['net'] = $unconditionalPrice->getNet();
            }
        }

        $target = [
            'id' => $productId,
            'parentId' => null,
            'active' => $active,
            'productNumber' => $source->getNumber(),
            'stock' => (int) $source->getInventory(),
            'minPurchase' => 1,
            'purchaseSteps' => (int) $source->getPurchaseQuantity(),
            'isCloseout' => true,
            'shippingFree' => false,
            'name' => $source->getName()->getTranslation('default'),
            'description' => $source->getDescription()->getTranslation('default'),
            'visibilities' => $visibilities,
            'taxId' => $taxId,
            'unitId' => $unitId,
            'purchaseUnit' => $source->getPurchaseQuantity(),
            'price' => $price,
            'prices' => $prices,
            'categories' => \array_map(
                static fn (Category $category) => [
                    'id' => $category->getPrimaryKey(),
                ],
                \array_filter(
                    iterable_to_array($source->getCategories()),
                    fn (Category $category) => $this->dalAccess->idExists('category', $category->getPrimaryKey())
                )
            ),
        ];

        if ($source->getManufacturer() instanceof Manufacturer) {
            // TODO improve id generation
            $targetManufacturerId = $source->getManufacturer()->getPrimaryKey() ?? Uuid::randomHex();
            $source->getManufacturer()->setPrimaryKey($targetManufacturerId);

            // TODO translations
            $target['manufacturer'] = [
                'id' => $targetManufacturerId,
                'name' => $source->getManufacturer()->getName()->getTranslation('default'),
            ];
        }

        $mediaId = $this->getMediaId($source);

        if ($mediaId !== null) {
            $productMediaId = $this->existingIdentifierCache->getProductMediaId($productId, $mediaId);

            $target['media'] = [
                [
                    'id' => $productMediaId,
                    'mediaId' => $mediaId,
                    'position' => 0,
                ],
            ];

            $target['coverId'] = $productMediaId;
        }

        return $target;
    }

    protected function unpackProductId(Product $source): string
    {
        $productId = $source->getPrimaryKey();

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('productNumber', $source->getNumber()))
            ->setLimit(1);

        $foundProductId = $this->dalAccess->repository('product')
            ->searchIds($criteria, $this->dalAccess->getContext())
            ->firstId();

        if ($alreadyExists = (\is_string($foundProductId) && Uuid::isValid($foundProductId))) {
            $productId = $foundProductId;
        } elseif (\is_string($productId) && Uuid::isValid($productId)) {
            $alreadyExists = $this->dalAccess->idExists('product', $productId);
        }

        if (!$alreadyExists) {
            $productId = PrimaryKeyGenerator::generatePrimaryKey($source, self::NS_PRODUCT) ?? (static function (): void {
                throw new \Exception('Cannot generate primary key: Entity of unknown origin.');
            })();
        }

        return $productId;
    }

    protected function unpackVisibilities(Product $source): array
    {
        if (!$this->salesChannels instanceof SalesChannelCollection) {
            $salesChannels = $this->dalAccess->repository('sales_channel')
                ->search(new Criteria(), $this->dalAccess->getContext())
                ->getEntities();

            if ($salesChannels instanceof SalesChannelCollection) {
                $this->salesChannels = $salesChannels;
            }
        }

        if (!$this->salesChannels instanceof SalesChannelCollection) {
            $this->salesChannels = new SalesChannelCollection();
        }

        return $this->salesChannels->map(fn (SalesChannelEntity $salesChannel) => [
            'id' => $this->existingIdentifierCache->getProductVisibilityId(
                $source->getPrimaryKey(),
                $salesChannel->getId()
            ),
            'salesChannelId' => $salesChannel->getId(),
            'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
        ]);
    }

    protected function unpackUnitId(Unit $source): string
    {
        // TODO support translations
        $id = $source->getPrimaryKey();
        $name = $source->getName()->getTranslation('default');
        $shortCode = $source->getSymbol();
        $unitRepository = $this->dalAccess->repository('unit');

        if ($id === null) {
            $unitCriteria = (new Criteria())->addFilter(new EqualsFilter('name', $name));
            $id = $unitRepository->searchIds($unitCriteria, $this->dalAccess->getContext())->firstId();
        }

        if ($id === null) {
            $id = RamseyUuid::uuid5(self::NS_UNIT, $name)->getHex();

            $unitRepository->create([
                [
                    'id' => $id,
                    'name' => $name,
                    'shortCode' => $shortCode,
                ],
            ], $this->dalAccess->getContext());

            $source->setPrimaryKey($id);
        } else {
            $unitRepository->update([
                [
                    'id' => $id,
                    'name' => $name,
                    'shortCode' => $shortCode,
                ],
            ], $this->dalAccess->getContext());
        }

        return $id;
    }

    protected function unpackTaxId(Product $source): string
    {
        /** @var TaxGroupRule $taxRule */
        foreach ($source->getTaxGroup()->getRules() as $taxRule) {
            $taxRule->setPrimaryKey(
                $taxRule->getPrimaryKey()
                ?? $this->existingIdentifierCache->getTaxId($taxRule->getRate())
            );

            return $taxRule->getPrimaryKey();
        }

        throw new \Exception(\sprintf('The product has no tax rules. Product number: %s', $source->getNumber()));
    }

    /**
     * @return array[]
     */
    protected function unpackPrices(Product $source): iterable
    {
        /** @var Price $sourcePrice */
        foreach ($source->getPrices() as $sourcePrice) {
            $priceId = $sourcePrice->getPrimaryKey();
            $ruleIdSource = RamseyUuid::uuid5(self::NS_RULE_ID, $priceId);
            $sourcePrice->setPrimaryKey($sourcePrice->getPrimaryKey() ?? RamseyUuid::uuid5(
                    $ruleIdSource->toString(),
                    $source->getNumber().'__'.$sourcePrice->getQuantityStart()
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
            foreach ($sourcePrice->getConditions() as $key => $sourceCondition) {
                $conditionEssence = [];
                $targetCondition = [
                    'ruleId' => $ruleId,
                    'parentId' => $andMergeCondition['id'],
                    'position' => (int) $key,
                ];

                if ($sourceCondition instanceof ValidityPeriodCondition) {
                    $begin = $sourceCondition->getBegin();
                    $end = $sourceCondition->getEnd();

                    $conditionEssence['type'] = 'dateRange';
                    $targetCondition['type'] = 'dateRange';
                    $targetCondition['value'] = [
                        'useTime' => false,
                        'fromDate' => ($begin ?? \date_create_from_format('U', '0'))->format(\DateTimeInterface::ATOM),
                        'toDate' => ($end ?? \date_create('1000 year'))->format(\DateTimeInterface::ATOM),
                    ];

                    if ($begin instanceof \DateTimeInterface) {
                        $conditionEssence['fromDate'] = $begin->getTimestamp();
                        $nameParts[] = \sprintf('vom %s', $begin->format('d.m.Y'));
                    }

                    if ($end instanceof \DateTimeInterface) {
                        $conditionEssence['toDate'] = $end->getTimestamp();
                        $nameParts[] = \sprintf('bis zum %s', $end->format('d.m.Y'));
                    }
                } else {
                    continue;
                }

                \rsort($conditionEssence);
                $sourceCondition->setPrimaryKey($sourceCondition->getPrimaryKey() ?? RamseyUuid::uuid5('02d88b5b-bacf-4ef9-b17b-6a8a879b88bc', \implode(';', $conditionEssence))->getHex());
                $targetCondition['id'] = $sourceCondition->getPrimaryKey();
                $targetConditions[] = $targetCondition;
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

            if ($sourcePrice->getCurrency() instanceof Currency) {
                // TODO: Use mapping
                $currencyId = $this->existingIdentifierCache->getCurrencyId($sourcePrice->getCurrency()->getIso());
            } else {
                $currencyId = Defaults::CURRENCY;
            }

            $targetPrice = [
                'id' => $sourcePrice->getPrimaryKey(),
                'quantityStart' => $sourcePrice->getQuantityStart(),
                'price' => [[
                    'currencyId' => $currencyId,
                    'gross' => $sourcePrice->getGross(),
                    'net' => $sourcePrice->getNet(),
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

            yield $targetPrice;
        }
    }

    protected function getMediaId(DatasetEntityContract $entity): ?string
    {
        $media = $entity->getAttachment(Media::class);

        if (!$media instanceof Media) {
            return null;
        }

        if (!$this->dalAccess->idExists('media', $media->getPrimaryKey())) {
            $denormalizer = $this->normalizationRegistry->getDenormalizer('stream');
            $stream = $denormalizer->denormalize($media->getNormalizedStream(), 'stream');

            if (!$stream instanceof StreamInterface) {
                return null;
            }

            $mediaId = $this->mediaService->saveFile(
                $stream->getContents(),
                \explode('/', $media->getMimeType(), 2)[1] ?? 'bin',
                $media->getMimeType(),
                RamseyUuid::uuid4()->toString(),
                $this->dalAccess->getContext(),
                'product',
                null,
                false
            );

            $media->setPrimaryKey($mediaId);
        }

        return $media->getPrimaryKey();
    }

}
