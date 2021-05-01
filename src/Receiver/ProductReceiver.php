<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Receiver;

use Heptacom\HeptaConnect\Core\Storage\NormalizationRegistry;
use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Base\Translatable\TranslatableString;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Currency\Currency;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Media\Media;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Price\Condition;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Price\Condition\ValidityPeriodCondition;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Price\Price;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Category;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Manufacturer;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Product;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Unit;
use Heptacom\HeptaConnect\Portal\Base\Mapping\Contract\MappingInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiveContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiverContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\ExistingIdentifierCache;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\Translator;
use Psr\Http\Message\StreamInterface;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class ProductReceiver extends ReceiverContract
{
    public const NS_RULE_ID = 'cbfb4fc6171911eb895d33ddd3eed5ba';

    public const NS_CONDITION_CONTAINER_OR = '2caad876178011ebbeba832e03190a18';

    public const NS_CONDITION_CONTAINER_AND = 'c51261d8179911ebb4c3f3c3cb39b2d2';

    public const NS_MAIN_PRODUCT = 'a797230c17dd11eb86c9ab799d290723';

    public const NS_PROPERTY_GROUP_OPTION = '40b27a8baecb44958575f075fefbfc22';

    private ?SalesChannelCollection $salesChannels = null;

    public function supports(): string
    {
        return Product::class;
    }

    /**
     * @param Product $entity
     */
    protected function run(
        MappingInterface $mapping,
        DatasetEntityContract $entity,
        ReceiveContextInterface $context
    ): void {
        $container = $context->getContainer($mapping);
        /** @var DalAccess $dalAccess */
        $dalAccess = $container->get(DalAccess::class);
        $productRepository = $dalAccess->repository( 'product');
        $dalContext = $dalAccess->getContext();
        $productId = $entity->getPrimaryKey();
        $alreadyExists = $dalAccess->idExists('product', $productId);

        if (!$alreadyExists) {
            $foundProductId = $productRepository
                ->searchIds((new Criteria())->addFilter(new EqualsFilter('productNumber', $entity->getNumber()))->setLimit(1), $dalContext)
                ->firstId();

            if ($alreadyExists = (\is_string($foundProductId) && Uuid::isValid($foundProductId))) {
                $productId = $foundProductId;
            }
        }

        if (!$alreadyExists) {
            $productId ??= RamseyUuid::uuid5('5fb023e7-5e65-494f-9160-b99602ce0587', $entity->getNumber())->getHex();
        }

        $entity->setPrimaryKey($productId);

        /** @var ExistingIdentifierCache $existingIdentifierCache */
        $existingIdentifierCache = $container->get(ExistingIdentifierCache::class);
        $visibilities = $this->getSalesChannels($dalAccess->repository('sales_channel'), $dalContext)->map(
            function (SalesChannelEntity $salesChannel) use ($existingIdentifierCache, $productId, $dalContext) {
                return [
                    'id' => $existingIdentifierCache->getProductVisibilityId($productId, $salesChannel->getId()),
                    'salesChannelId' => $salesChannel->getId(),
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ];
            }
        );

        $sourceUnit = $entity->getUnit();
        $name = $sourceUnit->getName()->getTranslation('default');
        $unitCriteria = (new Criteria())->addFilter(new EqualsFilter('name', $name));
        $unitRepository = $dalAccess->repository('unit');
        $unitId = $unitRepository->searchIds($unitCriteria, $dalContext)->firstId();

        $translations = self::getUnitTranslation($sourceUnit, $unitId);
        /** @var Translator $translator */
        $translator = $container->get(Translator::class);

        if (!$unitId) {
            $unitId ??= RamseyUuid::uuid5('07c24254-c14d-4d64-8f97-2413aaab15ba', $name)->getHex();

            $unitRepository->create([[
                'id' => $unitId,
                'translations' => $translator->exchangeLocaleKeysToLanguageKeys($translations),
            ]], $dalContext);

            $sourceUnit->setPrimaryKey($unitId);
        } else {
            $unitRepository->update([[
                'id' => $unitId,
                'translations' => $translator->exchangeLocaleKeysToLanguageKeys($translations),
            ]], $dalContext);
        }

        $target = [
            'id' => $productId,
            'parentId' => null,
            'active' => $entity->isActive(),
            'productNumber' => $entity->getNumber(),
            'stock' => (int) $entity->getInventory(),
            'minPurchase' => 1,
            'purchaseSteps' => 1,
            'isCloseout' => true,
            'shippingFree' => false,
            'name' => $this->getTranslation($entity->getName()),
            'description' => $this->getTranslation($entity->getDescription()),
            'visibilities' => $visibilities,
            'taxId' => $existingIdentifierCache->getTaxId(19.),
            'unitId' => $unitId,
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 999,
                    'net' => 999,
                    'linked' => false,
                ],
            ],
            'categories' => \array_map(
                fn (Category $category) => [
                    'id' => $category->getPrimaryKey(),
                ],
                \array_filter(
                    iterable_to_array($entity->getCategories()),
                    fn (Category $category) => $dalAccess->idExists('category', $category->getPrimaryKey())
                )
            ),
        ];

        $target['prices'] = $this->getPrices($entity, $existingIdentifierCache, $dalAccess->repository('rule'), $dalContext);

        if (!empty($rawPrices)) {
            $unconditionalPrices = \array_filter($rawPrices, static fn (Price $price): bool => $price->getConditions()->count() === 0);

            if (empty($unconditionalPrices)) {
                $target['price'][0]['gross'] = 999;
                $target['price'][0]['net'] = 999;
            } else {
                \usort($unconditionalPrices, [self::class, 'sortPriceByQuantityLowFirstAndPriceHighSecond']);
                /** @var Price $unconditionalPrice */
                $unconditionalPrice = \current($unconditionalPrices);

                $target['price'][0]['gross'] = $unconditionalPrice->getGross();
                $target['price'][0]['net'] = $unconditionalPrice->getNet();
            }
        }

        $criteria = (new Criteria())->addFilter(new EqualsFilter('productId', $productId));
        $productPriceRepository = $dalAccess->repository('product_price');
        $deleteProductPriceIds = $productPriceRepository->searchIds($criteria, $dalContext)->getIds();
        $deleteProductPriceIds = \array_map(fn (string $id) => ['id' => $id], $deleteProductPriceIds);
        if (!empty($deleteProductPriceIds)) {
            $productPriceRepository->delete($deleteProductPriceIds, $dalContext);
        }

        $target['prices'] = $this->getPrices(
            $entity,
            $existingIdentifierCache,
            $dalAccess->repository('rule'),
            $dalContext
        );

        if ($entity->getManufacturer() instanceof Manufacturer) {
            $targetManufacturerId = $entity->getManufacturer()->getPrimaryKey() ?? Uuid::randomHex();
            $entity->getManufacturer()->setPrimaryKey($targetManufacturerId);

            $target['manufacturer'] = ['id' => $targetManufacturerId];

            foreach ($entity->getManufacturer()->getName()->getLocaleKeys() as $localeKey) {
                $name = $entity->getManufacturer()->getName()->getTranslation($localeKey);

                switch ($localeKey) {
                    case 'de':
                        $localeKey = 'de-DE';

                        break;
                    case 'en':
                        $localeKey = 'en-GB';

                        break;
                    case 'es':
                        $localeKey = 'es-ES';

                        break;
                    case 'fr':
                        $localeKey = 'fr-FR';

                        break;
                    case 'it':
                        $localeKey = 'it-IT';

                        break;
                    case 'pl':
                        $localeKey = 'pl-PL';

                        break;
                    case 'ro':
                        $localeKey = 'ro-RO';

                        break;
                    case 'se':
                        $localeKey = 'sv-SE';

                        break;
                }

                $target['manufacturer']['translations'][$localeKey]['name'] = $name;
            }
        }

        /** @var MediaService $mediaService */
        $mediaService = $container->get(MediaService::class);
        /** @var NormalizationRegistry $normalizationRegistry */
        $normalizationRegistry = $container->get(NormalizationRegistry::class);

        $mediaId = $this->getMediaId(
            $mediaService,
            $normalizationRegistry,
            $dalAccess,
            $entity,
            $dalContext
        );

        if ($mediaId !== null) {
            $productMediaId = $existingIdentifierCache->getProductMediaId($productId, $mediaId);

            $target['media'] = [
                [
                    'id' => $productMediaId,
                    'mediaId' => $mediaId,
                    'position' => 0,
                ],
            ];

            $target['coverId'] = $productMediaId;
        }

        $productRepository->upsert([$target], $dalContext);
    }

    protected function generateVariants(
        array $product,
        array $variations,
        Context $context,
        ExistingIdentifierCache $existingIdentifierCache,
        EntityRepositoryInterface $productRepository
    ): void {
        $variantIds = \array_keys($variations);

        if (empty($variantIds)) {
            return;
        }

        $parentId = $variations[$product['id']] ?? null;

        if ($parentId === null) {
            return;
        }

        $parentEntity = $productRepository->search(new Criteria([$parentId]), $context)->first();

        $parent = [
            'id' => $parentId,
            'active' => false,
            'isCloseout' => $product['isCloseout'] ?? true,
            'shippingFree' => $product['shippingFree'] ?? false,
        ];

        if (isset($product['manufacturer'])) {
            $parent['manufacturer'] = $product['manufacturer'];
        }

        if (isset($product['categories'])) {
            $parent['categories'] = $product['categories'];
        }

        if (isset($product['taxId'])) {
            $parent['taxId'] = $product['taxId'];
        }

        if (isset($product['media'][0]['mediaId'])) {
            $mediaId = $product['media'][0]['mediaId'];
            $productMediaId = $existingIdentifierCache->getProductMediaId($parent['id'], $mediaId);

            $parent['media'] = [
                [
                    'id' => $productMediaId,
                    'mediaId' => $mediaId,
                    'position' => 0,
                ],
            ];

            $parent['coverId'] = $productMediaId;
        }

        foreach ($product['visibilities'] ?? [] as $visibility) {
            $visibilityId = $existingIdentifierCache->getProductVisibilityId($parentId, $visibility['salesChannelId']);

            $parent['visibilities'][] = [
                'id' => $visibilityId,
                'salesChannelId' => $visibility['salesChannelId'],
                'visibility' => $visibility['visibility'],
            ];
        }

        if (!$parentEntity instanceof ProductEntity) {
            $parent['productNumber'] = $product['productNumber'].'.';
            $parent['name'] = $product['name'];
            $parent['stock'] = $product['stock'] ?? 0;
            $parent['price'] = $product['price'];
        }

        $upsertProducts = [];
        $variants = $productRepository->search(new Criteria($variantIds), $context)->getEntities();

        foreach ($variants as $variant) {
            if (!$variant instanceof ProductEntity) {
                continue;
            }

            if ($variant->getActive()) {
                $parent['active'] = true;
            }

            $optionId = RamseyUuid::uuid5(self::NS_PROPERTY_GROUP_OPTION, $variant->getProductNumber())->getHex();

            $upsertProducts[] = [
                'id' => $variant->getId(),
                'parentId' => $parentId,
                'options' => [[
                    'id' => $optionId,
                    'name' => $variant->getProductNumber(),
                    'colorHexCode' => '#000000',
                    'position' => 1,
                    'groupId' => $existingIdentifierCache->getPropertyGroup('Number'),
                    'productConfiguratorSettings' => [[
                        'id' => $optionId,
                        'optionId' => $optionId,
                        'productId' => $parentId,
                    ]],
                ]],
            ];
        }

        \array_unshift($upsertProducts, $parent);

        $productRepository->upsert($upsertProducts, $context);
    }

    protected function getSalesChannels(
        EntityRepositoryInterface $salesChannelRepository,
        Context $context
    ): SalesChannelCollection {
        if (!$this->salesChannels instanceof SalesChannelCollection) {
            $this->salesChannels = $salesChannelRepository->search(new Criteria(), $context)->getEntities();
        }

        if (!$this->salesChannels instanceof SalesChannelCollection) {
            $this->salesChannels = new SalesChannelCollection();
        }

        return $this->salesChannels;
    }

    protected function getMediaId(
        MediaService $mediaService,
        NormalizationRegistry $normalizationRegistry,
        DalAccess $dalAccess,
        DatasetEntityContract $entity,
        Context $context
    ): ?string {
        $media = $entity->getAttachment(Media::class);

        if (!$media instanceof Media) {
            return null;
        }

        if (!$dalAccess->idExists('media', $media->getPrimaryKey())) {
            $denormalizer = $normalizationRegistry->getDenormalizer('stream');
            $stream = $denormalizer->denormalize($media->getNormalizedStream(), 'stream');

            if (!$stream instanceof StreamInterface) {
                return null;
            }

            $mediaId = $mediaService->saveFile(
                $stream->getContents(),
                \explode('/', $media->getMimeType(), 2)[1] ?? 'bin',
                $media->getMimeType(),
                RamseyUuid::uuid4()->toString(),
                $context,
                'product',
                null,
                false
            );

            $media->setPrimaryKey($mediaId);
        }

        return $media->getPrimaryKey();
    }

    protected function getTranslation(TranslatableString $source): string
    {
        foreach ($source->getLocaleKeys() as $localeKey) {
            $translation = $source->getTranslation($localeKey);

            if (\is_string($translation)) {
                return $translation;
            }
        }

        $translation = $source->getTranslation('default');

        if (\is_string($translation)) {
            return $translation;
        }

        return '';
    }

    protected function getPrices(
        Product $sourceProduct,
        ExistingIdentifierCache $existingIdentifierCache,
        EntityRepositoryInterface $ruleRepository,
        Context $context
    ): array {
        $targetPrices = [];

        /** @var Price $sourcePrice */
        foreach ($sourceProduct->getPrices() as $sourcePrice) {
            $priceId = $sourcePrice->getPrimaryKey();
            $ruleIdSource = RamseyUuid::uuid5(self::NS_RULE_ID, $priceId);
            $sourcePrice->setPrimaryKey($sourcePrice->getPrimaryKey() ?? RamseyUuid::uuid5(
                $ruleIdSource->toString(),
                $sourceProduct->getNumber().'__'.$sourcePrice->getQuantityStart()
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
                        'fromDate' => ($begin ?? \date_create_from_format('U', 0))->format(\DateTimeInterface::ATOM),
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

            $ruleRepository->upsert([$rule], $context);

            if ($sourcePrice->getCurrency() instanceof Currency) {
                // TODO: Use mapping
                $currencyId = $existingIdentifierCache->getCurrencyId($sourcePrice->getCurrency()->getIso());
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
                    'gross' => 999,
                    'net' => 999,
                    'linked' => true,
                ];
            }

            $targetPrices[] = $targetPrice;
        }

        return $targetPrices;
    }

    private static function sortPriceByQuantityLowFirstAndPriceHighSecond(Price $a, Price $b): int
    {
        $quantitySign = $a->getQuantityStart() <=> $b->getQuantityStart();

        if ($quantitySign === 0) {
            return $b->getNet() <=> $a->getNet();
        }

        return $quantitySign;
    }

    private static function getUnitTranslation(Unit $unit, ?string $unitId): array
    {
        $defaultName = $unit->getName()
            ->getTranslation($unit->getName()->getLocaleKeys()[0] ?? 'default');

        $translations = [];

        foreach ($unit->getName()->getLocaleKeys() as $localeKey) {
            $name = \trim($unit->getName()->getTranslation($localeKey));

            if ($name === '') {
                continue;
            }

            $translations[$localeKey] = [
                'name' => $name,
                'shortCode' => !empty($unit->getSymbol()) ? $unit->getSymbol() : $name,
            ];
        }

        $translations[Defaults::LANGUAGE_SYSTEM] = [
            'name' => $defaultName ?? ('Unit '.$unitId),
            'shortCode' => !empty($unit->getSymbol()) ? $unit->getSymbol() : $name,
        ];

        return $translations;
    }
}
