<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Receiver;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Address\Address;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Address\Salutation;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Customer\Customer;
use Heptacom\HeptaConnect\Portal\Base\Mapping\Contract\MappingInterface;
use Heptacom\HeptaConnect\Portal\Base\Portal\Contract\PortalStorageInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiveContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiverContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\ExistingIdentifierCache;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\PrimaryKeyGenerator;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\StorageHelper;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\Strategy\CustomerSalesChannelStrategyContract;
use Ramsey\Uuid\Uuid;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Salutation\SalutationEntity;

class CustomerReceiver extends ReceiverContract
{
    public function supports(): string
    {
        return Customer::class;
    }

    /**
     * @param Customer $entity
     */
    protected function run(
        MappingInterface $mapping,
        DatasetEntityContract $entity,
        ReceiveContextInterface $context
    ): void {
        $container = $context->getContainer($mapping);
        /** @var ExistingIdentifierCache $existingIdentifierCache */
        $existingIdentifierCache = $container->get(ExistingIdentifierCache::class);
        /** @var DalAccess $dalAccess */
        $dalAccess = $container->get(DalAccess::class);
        $customerRepository = $dalAccess->repository('customer');
        $dalContext = $dalAccess->getContext();
        $entity->setPrimaryKey(
            PrimaryKeyGenerator::generatePrimaryKey($entity, '57854452-bbf4-4ba4-ab27-a52723c2f634') ??
            Uuid::uuid5('36e684e2-e182-4d60-a180-9b61a4cce982', $entity->getNumber())->getHex()
        );

        $customerSalesChannelStrategy = $container->get(CustomerSalesChannelStrategyContract::class);

        if (!$dalAccess->idExists('customer', $entity->getPrimaryKey())) {
            // TODO: support creating customers
            // throw new \Exception('Creating customers is not (yet) supported by this portal.');

            $this->createCustomer(
                $entity,
                $mapping,
                $customerRepository,
                $dalAccess->repository('salutation'),
                $dalAccess->repository('country'),
                $dalAccess->repository('sales_channel'),
                $dalContext,
                $context->getStorage($mapping),
                $existingIdentifierCache,
                $customerSalesChannelStrategy,
                $context
            );
        } else {
            $this->updateCustomer(
                $entity,
                $mapping,
                $customerRepository,
                $dalAccess->repository('customer_tag'),
                $dalContext,
                $context->getStorage($mapping),
                $existingIdentifierCache,
                $customerSalesChannelStrategy,
                $context
            );
        }
    }

    protected function createCustomer(
        Customer $entity,
        MappingInterface $mapping,
        EntityRepositoryInterface $customerRepository,
        EntityRepositoryInterface $salutationRepository,
        EntityRepositoryInterface $countryRepository,
        EntityRepositoryInterface $salesChannelRepository,
        Context $context,
        PortalStorageInterface $storage,
        ExistingIdentifierCache $existingIdentifierCache,
        CustomerSalesChannelStrategyContract $customerSalesChannelStrategy,
        ReceiveContextInterface $receiveContext
    ): void {
        if ($customerGroup = $entity->getCustomerGroup()) {
            // TODO sync customer groups and map their respective primary key
            $customerGroupId = $customerGroup->getPrimaryKey() ?? Defaults::FALLBACK_CUSTOMER_GROUP;
        } else {
            $customerGroupId = Defaults::FALLBACK_CUSTOMER_GROUP;
        }

        $customer = [
            'id' => $entity->getPrimaryKey(),
            'salesChannelId' => $customerSalesChannelStrategy->getCustomerSalesChannelId($entity, $mapping, $receiveContext),
            'groupId' => $customerGroupId,
            'languageId' => $existingIdentifierCache->getLanguageId(self::getLocale($entity), $context), // TODO use enhancer
            'customerNumber' => $entity->getNumber(),
            'salutationId' => $this->getFallbackSalutation($salutationRepository, $context)->getId(),
            'firstName' => $entity->getNames()->first(),
            'lastName' => $entity->getNames()->last(),
            'company' => $entity->getCompany(),
            'email' => $entity->getEmail(),
            'active' => $entity->isActive(),
            'guest' => $entity->isGuest(),
            'addresses' => [],
        ];

        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $salesChannelRepository->search(new Criteria([$customer['salesChannelId']]), $context)->first();
        $customer['defaultPaymentMethodId'] = $salesChannel->getPaymentMethodId();

        if ($customerPriceGroup = $entity->getCustomerPriceGroup()) {
            $customerPriceGroupId = $customerPriceGroup->getPrimaryKey();

            $customerPriceGroup->setPrimaryKey($customerPriceGroupId);

            if (!StorageHelper::isCustomerPriceGroupTagId($customerPriceGroupId, $storage)) {
                StorageHelper::addCustomerPriceGroupTagId($customerPriceGroupId, $storage);
            }

            $customer['tags'] = [[
                'id' => $customerPriceGroupId,
            ]];
        }

        if ($customerDiscountGroup = $entity->getCustomerDiscountGroup()) {
            $customerDiscountGroupId = $customerDiscountGroup->getPrimaryKey();

            $customerDiscountGroup->setPrimaryKey($customerDiscountGroupId);

            if (!StorageHelper::isCustomerDiscountGroupTagId($customerDiscountGroupId, $storage)) {
                StorageHelper::addCustomerDiscountGroupTagId($customerDiscountGroupId, $storage);
            }

            $customer['tags'] = [[
                'id' => $customerDiscountGroupId,
            ]];
        }

        if ($defaultBillingAddress = $entity->getDefaultBillingAddress()) {
            $customer['defaultBillingAddress'] = $this->getAddress(
                $defaultBillingAddress,
                $salutationRepository,
                $countryRepository,
                $context
            );

            $customer['defaultBillingAddressId'] = $customer['defaultBillingAddress']['id'];
        }

        if ($defaultShippingAddress = $entity->getDefaultShippingAddress()) {
            $customer['defaultShippingAddress'] = $this->getAddress(
                $defaultShippingAddress,
                $salutationRepository,
                $countryRepository,
                $context
            );

            $customer['defaultShippingAddressId'] = $customer['defaultShippingAddress']['id'];
        }

        foreach ($entity->getAddresses() as $address) {
            $generatedAddress = $this->getAddress(
                $address,
                $salutationRepository,
                $countryRepository,
                $context
            );

            $customer['addresses'][] = $generatedAddress;

            $customer['defaultBillingAddressId'] ??= $generatedAddress['id'];
            $customer['defaultShippingAddressId'] ??= $generatedAddress['id'];
        }

        $customerRepository->create([$customer], $context);
    }

    protected function updateCustomer(
        Customer $entity,
        MappingInterface $mapping,
        EntityRepositoryInterface $customerRepository,
        EntityRepositoryInterface $customerTagRepository,
        Context $context,
        PortalStorageInterface $storage,
        ExistingIdentifierCache $existingIdentifierCache,
        CustomerSalesChannelStrategyContract $customerSalesChannelStrategy,
        ReceiveContextInterface $receiveContext
    ): void {
        $criteria = (new Criteria([$entity->getPrimaryKey()]))->addAssociation('tags');
        $existingCustomer = $customerRepository->search($criteria, $context)->first();

        if (!$existingCustomer instanceof CustomerEntity) {
            throw new \Exception('Tried to update a customer that does not exist.');
        }

        $deleteCustomerTags = [];

        foreach ($existingCustomer->getTags() ?? [] as $tag) {
            $tagId = $tag->getId();
            $deleteCustomerTags[$tagId] = [
                'customerId' => $entity->getPrimaryKey(),
                'tagId' => $tagId,
            ];
        }

        $customer = [
            'id' => $entity->getPrimaryKey(),
            'customerNumber' => $entity->getNumber(),
            'firstName' => $entity->getNames()->first(),
            'lastName' => $entity->getNames()->last(),
            'company' => $entity->getCompany(),
            'email' => $entity->getEmail(),
            'active' => $entity->isActive(),
            'guest' => $entity->isGuest(),
            'languageId' => $existingIdentifierCache->getLanguageId(self::getLocale($entity), $context),
        ];

        if ($existingCustomer->getSalesChannelId() === Defaults::SALES_CHANNEL) {
            $customer['salesChannelId'] = $customerSalesChannelStrategy->getCustomerSalesChannelId($entity, $mapping, $receiveContext);
        }

        if ($entity->getCustomerGroup() && $entity->getCustomerGroup()->getPrimaryKey()) {
            $customer['groupId'] = $entity->getCustomerGroup()->getPrimaryKey();
        }

        if ($customerPriceGroup = $entity->getCustomerPriceGroup()) {
            $customerPriceGroupId = $customerPriceGroup->getPrimaryKey();

            $customerPriceGroup->setPrimaryKey($customerPriceGroupId);

            if (!StorageHelper::isCustomerPriceGroupTagId($customerPriceGroupId, $storage)) {
                StorageHelper::addCustomerPriceGroupTagId($customerPriceGroupId, $storage);
            }

            unset($deleteCustomerTags[$customerPriceGroupId]);

            $customer['tags'][] = [
                'id' => $customerPriceGroupId,
                'name' => $customerPriceGroup->getCode(),
            ];
        }

        if ($customerDiscountGroup = $entity->getCustomerDiscountGroup()) {
            $customerDiscountGroupId = $customerDiscountGroup->getPrimaryKey();

            $customerDiscountGroup->setPrimaryKey($customerDiscountGroupId);

            if (!StorageHelper::isCustomerDiscountGroupTagId($customerDiscountGroupId, $storage)) {
                StorageHelper::addCustomerDiscountGroupTagId($customerDiscountGroupId, $storage);
            }

            unset($deleteCustomerTags[$customerDiscountGroupId]);

            $customer['tags'][] = [
                'id' => $customerDiscountGroupId,
                'name' => $customerDiscountGroup->getCode(),
            ];
        }

        if ($entity->getDefaultBillingAddress()) {
            // TODO: fix errors when updating addresses
            // $customer['defaultBillingAddress'] = $this->getAddress($entity->getDefaultBillingAddress(), $portal);
        }

        if ($entity->getDefaultShippingAddress()) {
            // TODO: fix errors when updating addresses
            // $customer['defaultShippingAddress'] = $this->getAddress($entity->getDefaultShippingAddress(), $portal);
        }

        foreach ($entity->getAddresses() as $address) {
            // TODO: fix errors when updating addresses
            // $customer['addresses'][] = $this->getAddress($address, $portal);
        }

        if (!empty($deleteCustomerTags)) {
            $customerTagRepository->delete(\array_values($deleteCustomerTags), $context);
        }

        $customerRepository->update([$customer], $context);
    }

    protected static function getLocale(Customer $entity): string
    {
        $locales = [
            'DEU' => 'de-DE',
            'ENG' => 'en-GB',
            'POL' => 'pl-PL',
            'JPN' => 'jp-JP',
            'ITA' => 'it-IT',
            'ESP' => 'es-ES',
        ];

        return $locales[$entity->getLanguage()->getLocaleCode()] ?? 'de-DE';
    }

    protected function getFallbackSalutation(
        EntityRepositoryInterface $salutationRepository,
        Context $context
    ): SalutationEntity {
        $criteria = new Criteria();
        $salutations = $salutationRepository->search($criteria, $context);

        $salutations->sort(function (SalutationEntity $a, SalutationEntity $b): int {
            if ($a->getSalutationKey() === 'not_specified') {
                return -1;
            }

            if ($b->getSalutationKey() === 'not_specified') {
                return 1;
            }

            return 0;
        });

        $salutation = $salutations->first();

        if ($salutation instanceof SalutationEntity) {
            return $salutation;
        }

        throw new \Exception('There are no salutations.');
    }

    protected function getCustomerPriceGroupId(
        string $code,
        EntityRepositoryInterface $tagRepository,
        Context $context
    ): string {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('name', $code));
        $tagId = $tagRepository->searchIds($criteria, $context)->firstId();

        if (!$tagId) {
            $tagRepository->create([
                [
                    'id' => $tagId = Uuid::uuid5('38c0ad1a-a33a-466c-8b33-c72325e5400f', $code)->getHex(),
                    'name' => $code,
                ],
            ], $context);
        }

        return $tagId;
    }

    private function getAddress(
        Address $address,
        EntityRepositoryInterface $salutationRepository,
        EntityRepositoryInterface $countryRepository,
        Context $context
    ): array {
        $address->setPrimaryKey(PrimaryKeyGenerator::generatePrimaryKey($address, 'faa35e6e-27eb-4f0a-bb3d-2e6b03991f9b') ?? Uuid::uuid4()->getHex());

        $targetAddress = [
            'id' => $address->getPrimaryKey(),
            'title' => $address->getTitle(),
            'company' => $address->getCompany(),
            'additionalAddressLine1' => $address->getAdditionalLines()->offsetGet(0) ?? '',
            'additionalAddressLine2' => $address->getAdditionalLines()->offsetGet(1) ?? '',
            'countryId' => $address->getCountry()->getPrimaryKey(),
            'city' => $address->getCity(),
            'zipcode' => $address->getZipcode(),
            'street' => $address->getStreet() ?: 'PLACEHOLDER',
            'firstName' => $address->getNames()->offsetGet(0) ?? '',
            'lastName' => $address->getNames()->offsetGet(1) ?? '',
            'vatId' => $address->getVatId(),
            'phoneNumber' => $address->getPhoneNumber(),
            'customFields' => [
                'housenumber' => $address->getHouseNo(),
            ],
        ];

        $salutation = $address->getSalutation();

        if ($salutation instanceof Salutation) {
            $salutationCriteria = new Criteria();
            $salutationCriteria->addFilter(new EqualsFilter('salutationKey', $salutation->getSlug()));

            $salutationId = $salutationRepository->searchIds($salutationCriteria, $context)->firstId();
        } else {
            $salutationId = $this->getFallbackSalutation($salutationRepository, $context)->getId();
        }

        if ($salutationId) {
            $targetAddress['salutationId'] = $salutationId;
        }

        if (!$targetAddress['countryId']) {
            $countryCriteria = new Criteria();
            $countryCriteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsFilter('iso', $address->getCountry()->getIso()),
                new EqualsFilter('iso3', $address->getCountry()->getIso3()),
            ]));

            $targetAddress['countryId'] = $countryRepository->searchIds($countryCriteria, $context)->firstId();
        }

        return $targetAddress;
    }
}
