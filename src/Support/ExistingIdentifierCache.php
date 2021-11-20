<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support;

use Ramsey\Uuid\Uuid;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class ExistingIdentifierCache
{
    private const NS_PRODUCT_VISIBILITY = 'a93d6b040fad11ebadcce7640f1a3a23';

    private const NS_PRODUCT_MEDIA = '459e30640fd111ebb1af2349ff3cd736';

    private const NS_TAX = '27631de0829d4c7b99ef11c18d6859b8';

    private const NS_CURRENCY = '804b7bbceadc43eab6939117685af3c2';

    private const NS_PROPERTY_GROUP = '677c8804524e4ca49caea0107953ae9f';

    private array $cache = [
        'productVisibility' => [],
        'customerGroup' => [],
        'tax' => [],
        'currency' => [],
        'propertyGroup' => [],
        'language' => [],
        'productMedia' => [],
    ];

    private DalAccess $dalAccess;

    public function __construct(DalAccess $dalAccess)
    {
        $this->dalAccess = $dalAccess;
    }

    public function getProductVisibilityId(string $productId, string $salesChannelId): string
    {
        if (!\array_key_exists($salesChannelId, $this->cache['productVisibility'])) {
            $this->cache['productVisibility'][$salesChannelId] = [];
        }

        if (!\array_key_exists($productId, $this->cache['productVisibility'][$salesChannelId])) {
            $idCriteria = new Criteria();
            $idCriteria->addFilter(
                new EqualsFilter('salesChannelId', $salesChannelId),
                new EqualsFilter('productId', $productId)
            );

            $this->cache['productVisibility'][$salesChannelId][$productId] = $this->dalAccess
                    ->repository('product_visibility')
                    ->searchIds($idCriteria, $this->dalAccess->getContext())
                    ->firstId() ?? (string) Uuid::uuid5(
                        self::NS_PRODUCT_VISIBILITY,
                        \join(';', [$salesChannelId, $productId])
                    )->getHex();
        }

        return $this->cache['productVisibility'][$salesChannelId][$productId];
    }

    public function getProductMediaId(string $productId, string $mediaId): string
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('productId', $productId))
            ->addFilter(new EqualsFilter('mediaId', $mediaId))
            ->setLimit(1);

        $this->cache['productMedia'][$mediaId][$productId] ??= $this->dalAccess
            ->repository('product_media')
            ->searchIds($criteria, $this->dalAccess->getContext())
            ->firstId();

        return $this->cache['productMedia'][$mediaId][$productId] ??= (string) Uuid::uuid5(
            self::NS_PRODUCT_MEDIA,
            \join(';', [$mediaId, $productId])
        )->getHex();
    }

    public function getTaxId(float $taxRate): string
    {
        $taxRateKey = \sprintf('%0.2f', $taxRate);

        if (!isset($this->cache['tax'][$taxRateKey])) {
            $criteria = (new Criteria())
                ->addFilter(new EqualsFilter('taxRate', $taxRate))
                ->setLimit(1);

            $taxId = $this->dalAccess->repository('tax')
                ->searchIds($criteria, $this->dalAccess->getContext())
                ->firstId();

            if (!\is_string($taxId)) {
                $taxId = (string) Uuid::uuid5(self::NS_TAX, $taxRateKey)->getHex();

                $this->dalAccess->repository('tax')->create([[
                    'id' => $taxId,
                    'taxRate' => $taxRate,
                    'name' => $taxRateKey . '%',
                ]], $this->dalAccess->getContext());
            }

            $criteria = new Criteria([$taxId]);
            $this->cache['tax'][$taxRateKey] = $this->dalAccess->repository('tax')
                ->searchIds($criteria, $this->dalAccess->getContext())
                ->firstId();

            if (!\is_string($this->cache['tax'][$taxRateKey])) {
                throw new \RuntimeException('Could not create new tax entity for ' . $taxRateKey . ' %.');
            }
        }

        return $this->cache['tax'][$taxRateKey];
    }

    public function getCurrencyId(string $isoCode): string
    {
        if (!isset($this->cache['currency'][$isoCode])) {
            $criteria = (new Criteria())
                ->addFilter(new EqualsFilter('isoCode', $isoCode))
                ->setLimit(1);

            $currencyId = $this->dalAccess->repository('currency')
                ->searchIds($criteria, $this->dalAccess->getContext())
                ->firstId();

            if (!\is_string($currencyId)) {
                $currencyId = (string) Uuid::uuid5(self::NS_CURRENCY, $isoCode)->getHex();

                $this->dalAccess->repository('currency')->create([[
                    'id' => $currencyId,
                    'name' => $isoCode,
                    'isoCode' => $isoCode,
                    'factor' => 1,
                    'position' => 1,
                    'decimalPrecision' => $isoCode,
                ]], $this->dalAccess->getContext());
            }

            $criteria = new Criteria([$currencyId]);

            $this->cache['currency'][$isoCode] = $this->dalAccess->repository('currency')
                ->searchIds($criteria, $this->dalAccess->getContext())
                ->firstId();

            if (!\is_string($this->cache['currency'][$isoCode])) {
                throw new \RuntimeException('Could not create new curency entity for ' . $isoCode . '.');
            }
        }

        return $this->cache['currency'][$isoCode];
    }

    public function getPropertyGroup(string $type): string
    {
        if (!isset($this->cache['propertyGroup'][$type])) {
            $expectedPropertyGroupId = (string) Uuid::uuid5(self::NS_PROPERTY_GROUP, $type)->getHex();
            $criteria = (new Criteria([$expectedPropertyGroupId]))
                ->setLimit(1);

            $propertyGroupId = $this->dalAccess->repository('property_group')
                ->searchIds($criteria, $this->dalAccess->getContext())
                ->firstId();

            if (!\is_string($propertyGroupId)) {
                $this->dalAccess->repository('property_group')->create([[
                    'id' => $expectedPropertyGroupId,
                    'name' => $type,
                    'displayType' => 'text',
                    'sortingType' => 'alphanumeric',
                ]], $this->dalAccess->getContext());
            }

            $this->cache['propertyGroup'][$type] = $expectedPropertyGroupId;
        }

        return $this->cache['propertyGroup'][$type];
    }

    public function getLanguageId(string $locale): string
    {
        if (!isset($this->cache['language'][$locale])) {
            $criteria = (new Criteria())->addFilter(new EqualsFilter('locale.code', $locale))->setLimit(1);
            $this->cache['language'][$locale] = $this->dalAccess->repository('language')
                    ->searchIds($criteria, $this->dalAccess->getContext())
                    ->firstId() ?? Defaults::LANGUAGE_SYSTEM;
        }

        return $this->cache['language'][$locale];
    }
}
