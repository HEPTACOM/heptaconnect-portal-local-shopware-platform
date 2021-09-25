<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Media\Media;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Media\MediaCollection;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Price\Price;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Category;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Manufacturer;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Product;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Unit;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Tax\TaxGroupRule;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\ExistingIdentifierCache;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\PrimaryKeyGenerator;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\TranslationLocaleCache;
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

    private DalAccess $dalAccess;

    private ExistingIdentifierCache $existingIdentifierCache;

    private MediaUnpacker $mediaUnpacker;

    private ManufacturerUnpacker $manufacturerUnpacker;

    private UnitUnpacker $unitUnpacker;

    private ProductPriceUnpacker $productPriceUnpacker;

    private TranslationLocaleCache $translationLocaleCache;

    private ?SalesChannelCollection $salesChannels = null;

    public function __construct(
        DalAccess $dalAccess,
        ExistingIdentifierCache $existingIdentifierCache,
        MediaUnpacker $mediaUnpacker,
        ManufacturerUnpacker $manufacturerUnpacker,
        UnitUnpacker $unitUnpacker,
        ProductPriceUnpacker $productPriceUnpacker,
        TranslationLocaleCache $translationLocaleCache
    ) {
        $this->dalAccess = $dalAccess;
        $this->existingIdentifierCache = $existingIdentifierCache;
        $this->mediaUnpacker = $mediaUnpacker;
        $this->manufacturerUnpacker = $manufacturerUnpacker;
        $this->unitUnpacker = $unitUnpacker;
        $this->productPriceUnpacker = $productPriceUnpacker;
        $this->translationLocaleCache = $translationLocaleCache;
    }

    public function unpack(Product $source): array
    {
        $productId = $this->unpackProductId($source);

        $source->setPrimaryKey($productId);

        $visibilities = $this->unpackVisibilities($source);
        $unit = null;

        if ($source->getUnit() instanceof Unit) {
            $unit = $this->unitUnpacker->unpack($source->getUnit());
        }

        $taxId = $this->unpackTaxId($source);
        $prices = \iterable_to_array($this->unpackPrices($source));
        $active = $source->isActive();
        $price = [
            [
                'currencyId' => Defaults::CURRENCY,
                'gross' => 0,
                'net' => 0,
                'linked' => true,
            ],
        ];
        $productMedias = $this->getProductMedias($source);
        $manufacturer = null;

        if ($source->getManufacturer() instanceof Manufacturer) {
            $manufacturer = $this->manufacturerUnpacker->unpack($source->getManufacturer());
        }

        if ($source->getPrices()->count() > 0) {
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

        return [
            'id' => $productId,
            'parentId' => null,
            'active' => $active,
            'productNumber' => $source->getNumber(),
            'ean' => $source->getGtin(),
            'stock' => (int) $source->getInventory(),
            'minPurchase' => 1,
            'purchaseSteps' => (int) \max($source->getPurchaseQuantity() ?? 1, 1),
            'isCloseout' => true,
            'shippingFree' => false,
            'visibilities' => $visibilities,
            'taxId' => $taxId,
            ($unit === null ? 'unitId' : 'unit') => $unit,
            'purchaseUnit' => $source->getPurchaseQuantity(),
            'price' => $price,
            'prices' => $prices,
            'media' => $productMedias,
            'coverId' => $productMedias[0]['id'] ?? null,
            ($manufacturer === null ? 'manufacturerId' : 'manufacturer') => $manufacturer,
            'categories' => \array_map(
                static fn (Category $category) => [
                    'id' => $category->getPrimaryKey(),
                ],
                \array_filter(
                    \iterable_to_array($source->getCategories()),
                    fn (Category $category) => $this->dalAccess->idExists('category', $category->getPrimaryKey())
                )
            ),
            'translations' => $this->unpackTranslations($source),
        ];
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
    protected function unpackPrices(Product $source): array
    {
        return \iterable_to_array($source->getPrices()->map(fn (Price $p): array => $this->productPriceUnpacker->unpack($p, $source)));
    }

    protected function getProductMedias(Product $product): array
    {
        $productId = $product->getPrimaryKey();

        if ($productId === null) {
            return [];
        }

        $unpackedMedias = [];

        if ($product->hasAttached(Media::class)) {
            /** @var Media $media */
            $media = $product->getAttachment(Media::class);
            $unpackedMedia = $this->mediaUnpacker->unpack($media);

            if ($unpackedMedia !== []) {
                $unpackedMedias[] = $unpackedMedia;
            }
        }

        if ($product->hasAttached(MediaCollection::class)) {
            /** @var MediaCollection $medias */
            $medias = $product->getAttachment(MediaCollection::class);

            foreach ($medias as $media) {
                $unpackedMedia = $this->mediaUnpacker->unpack($media);

                if ($unpackedMedia !== []) {
                    $unpackedMedias[] = $unpackedMedia;
                }
            }
        }

        if ($unpackedMedias === []) {
            return [];
        }

        $productMedias = \array_map(fn (array $unpackedMedia): array => [
            'id' => $this->existingIdentifierCache->getProductMediaId($productId, $unpackedMedia['id']),
            'media' => $unpackedMedia,
            'position' => 0,
        ], $unpackedMedias);

        return $productMedias;
    }

    protected function unpackTranslations(Product $source): array
    {
        $result = [];

        foreach ($this->translationLocaleCache->getLocales() as $localeCode) {
            $result[$localeCode]['name'] = null;
            $result[$localeCode]['description'] = null;
        }

        foreach ($source->getName()->getLocaleKeys() as $localeCode) {
            $result[$localeCode]['name'] ??= $source->getName()->getTranslation($localeCode);
        }

        foreach ($source->getDescription()->getLocaleKeys() as $localeCode) {
            $result[$localeCode]['description'] ??= $source->getDescription()->getTranslation($localeCode);
        }

        return [];
    }
}
