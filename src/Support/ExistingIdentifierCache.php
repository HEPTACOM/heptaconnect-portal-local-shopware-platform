<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support;

use Ramsey\Uuid\Uuid;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class ExistingIdentifierCache
{
    private array $cache = [
        'productVisibility' => [],
        'customerGroup' => [],
        'tax' => [],
        'currency' => [],
        'propertyGroup' => [],
        'language' => [],
    ];

    private EntityRepositoryInterface $productVisibilityRepository;

    private EntityRepositoryInterface $productMediaRepository;

    private EntityRepositoryInterface $taxRepository;

    private EntityRepositoryInterface $currencyRepository;

    private EntityRepositoryInterface $propertyGroupRepository;

    private EntityRepositoryInterface $languageRepository;

    public function __construct(
        EntityRepositoryInterface $productVisibilityRepository,
        EntityRepositoryInterface $productMediaRepository,
        EntityRepositoryInterface $taxRepository,
        EntityRepositoryInterface $currencyRepository,
        EntityRepositoryInterface $propertyGroupRepository,
        EntityRepositoryInterface $languageRepository
    ) {
        $this->productVisibilityRepository = $productVisibilityRepository;
        $this->productMediaRepository = $productMediaRepository;
        $this->taxRepository = $taxRepository;
        $this->currencyRepository = $currencyRepository;
        $this->propertyGroupRepository = $propertyGroupRepository;
        $this->languageRepository = $languageRepository;
    }

    public function getProductVisibilityId(string $productId, string $salesChannelId, Context $context): string
    {
        if (empty($this->cache['productVisibility'])) {
            $criteria = (new Criteria())->setLimit(250);
            $repositoryIterator = new RepositoryIterator($this->productVisibilityRepository, $context, $criteria);

            while (($productVisibilities = $repositoryIterator->fetch()) !== null) {
                /** @var ProductVisibilityEntity $productVisibility */
                foreach ($productVisibilities->getIterator() as $productVisibility) {
                    $this->cache['productVisibility'][$productVisibility->getSalesChannelId()][$productVisibility->getProductId()] = $productVisibility->getId();
                }
            }
        }

        $this->cache['productVisibility'][$salesChannelId][$productId] ??= Uuid::uuid5(
            'a93d6b040fad11ebadcce7640f1a3a23',
            \join(';', [$salesChannelId, $productId])
        )->getHex();

        return $this->cache['productVisibility'][$salesChannelId][$productId];
    }

    public function getProductMediaId(string $productId, string $mediaId, Context $context): string
    {
        if (empty($this->cache['productMedia'])) {
            $criteria = (new Criteria())->setLimit(250);
            $repositoryIterator = new RepositoryIterator($this->productMediaRepository, $context, $criteria);

            while (($productMedias = $repositoryIterator->fetch()) !== null) {
                /** @var ProductMediaEntity $productMedia */
                foreach ($productMedias->getIterator() as $productMedia) {
                    $this->cache['productMedia'][$productMedia->getMediaId()][$productMedia->getProductId()] = $productMedia->getId();
                }
            }
        }

        $this->cache['productMedia'][$mediaId][$productId] ??= Uuid::uuid5(
            '459e30640fd111ebb1af2349ff3cd736',
            \join(';', [$mediaId, $productId])
        )->getHex();

        return $this->cache['productMedia'][$mediaId][$productId];
    }

    public function getTaxId(float $taxRate, Context $context): string
    {
        $taxRateKey = \sprintf('%0.2f', $taxRate);

        if (!isset($this->cache['tax'][$taxRateKey])) {
            $criteria = (new Criteria())->addFilter(new EqualsFilter('taxRate', $taxRate))->setLimit(1);
            $taxId = $this->taxRepository->searchIds($criteria, $context)->firstId();

            if (!\is_string($taxId)) {
                $taxId = Uuid::uuid5('27631de0-829d-4c7b-99ef-11c18d6859b8', $taxRateKey)->getHex();

                $this->taxRepository->create([[
                    'id' => $taxId,
                    'taxRate' => $taxRate,
                    'name' => $taxRateKey.'%',
                ]], $context);
            }

            $criteria = new Criteria([$taxId]);
            $this->cache['tax'][$taxRateKey] = $this->taxRepository->searchIds($criteria, $context)->firstId();

            if (!\is_string($this->cache['tax'][$taxRateKey])) {
                throw new \RuntimeException('Could not create new tax entity for '.$taxRateKey.' %.');
            }
        }

        return $this->cache['tax'][$taxRateKey];
    }

    public function getCurrencyId(string $isoCode, Context $context): string
    {
        if (!isset($this->cache['currency'][$isoCode])) {
            $criteria = (new Criteria())->addFilter(new EqualsFilter('isoCode', $isoCode))->setLimit(1);
            $currencyId = $this->currencyRepository->searchIds($criteria, $context)->firstId();

            if (!\is_string($currencyId)) {
                $currencyId = Uuid::uuid5('804b7bbc-eadc-43ea-b693-9117685af3c2', $isoCode)->getHex();

                $this->currencyRepository->create([[
                    'id' => $currencyId,
                    'name' => $isoCode,
                    'isoCode' => $isoCode,
                    'factor' => 1,
                    'position' => 1,
                    'decimalPrecision' => $isoCode,
                ]], $context);
            }

            $criteria = new Criteria([$currencyId]);
            $this->cache['currency'][$isoCode] = $this->currencyRepository->searchIds($criteria, $context)->firstId();

            if (!\is_string($this->cache['currency'][$isoCode])) {
                throw new \RuntimeException('Could not create new curency entity for '.$isoCode.'.');
            }
        }

        return $this->cache['currency'][$isoCode];
    }

    public function getPropertyGroup(string $type, Context $context): string
    {
        if (!isset($this->cache['propertyGroup'][$type])) {
            $expectedPropertyGroupId = Uuid::uuid5('677c8804524e4ca49caea0107953ae9f', $type)->getHex();
            $criteria = (new Criteria([$expectedPropertyGroupId]))->setLimit(1);
            $propertyGroupId = $this->propertyGroupRepository->searchIds($criteria, $context)->firstId();

            if (!\is_string($propertyGroupId)) {
                $this->propertyGroupRepository->create([[
                    'id' => $expectedPropertyGroupId,
                    'name' => $type,
                    'displayType' => 'text',
                    'sortingType' => 'alphanumeric',
                ]], $context);
            }

            $this->cache['propertyGroup'][$type] = $expectedPropertyGroupId;
        }

        return $this->cache['propertyGroup'][$type];
    }

    public function getLanguageId(string $locale, Context $context): string
    {
        if (!isset($this->cache['language'][$locale])) {
            $criteria = (new Criteria())->addFilter(new EqualsFilter('locale.code', $locale))->setLimit(1);
            $this->cache['language'][$locale] = $this->languageRepository->searchIds($criteria, $context)->firstId() ?? Defaults::LANGUAGE_SYSTEM;
        }

        return $this->cache['language'][$locale];
    }
}
