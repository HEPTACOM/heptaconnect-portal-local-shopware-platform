<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Packer;

use Heptacom\HeptaConnect\Dataset\Base\ScalarCollection\StringCollection;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Address\Address;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Address\Country;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Address\CountryState;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Address\Salutation;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Customer\Customer;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Customer\CustomerDiscountGroup;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Customer\CustomerGroup;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Customer\CustomerPriceGroup;
use Heptacom\HeptaConnect\Portal\Base\Portal\Contract\PortalStorageInterface;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\StorageHelper;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Tag\TagEntity;

class CustomerPacker
{
    private DalAccess $dalAccess;

    public function __construct(DalAccess $dalAccess)
    {
        $this->dalAccess = $dalAccess;
    }

    public function pack(string $customerId, PortalStorageInterface $portalStorage): Customer
    {
        $sourceCustomer = $this->dalAccess->read('customer', [$customerId], [
                'language.locale',
                'salutation',
                'addresses.salutation',
                'addresses.country',
                'addresses.countryState',
                'defaultBillingAddress.country',
                'defaultBillingAddress.countryState',
                'defaultBillingAddress.salutation',
                'defaultShippingAddress.country',
                'defaultShippingAddress.countryState',
                'defaultShippingAddress.salutation',
            ])->first();

        if (!$sourceCustomer instanceof CustomerEntity) {
            throw new \Exception(\sprintf('Customer with id: %s not found.', $customerId));
        }

        $targetCustomer = new Customer();
        $targetCustomer->setPrimaryKey($sourceCustomer->getId());

        $salutation = new Salutation();

        if ($sourceCustomer->getSalutation()) {
            $salutation->setPrimaryKey($sourceCustomer->getSalutation()->getId());
            $salutation->setSlug($sourceCustomer->getSalutation()->getSalutationKey());
            $targetCustomer->setSalutation($salutation);
        }

        if ($sourceCustomer->getTitle()) {
            $targetCustomer->setTitle($sourceCustomer->getTitle());
        }

        $targetCustomer->setNumber($sourceCustomer->getCustomerNumber())
            ->setNames(new StringCollection([$sourceCustomer->getFirstName(), $sourceCustomer->getLastName()]))
            ->setEmail($sourceCustomer->getEmail())
            ->setCompany($sourceCustomer->getCompany() ?? '')
            ->setActive($sourceCustomer->getActive())
            ->setGuest($sourceCustomer->getGuest());

        // TODO use enhancer
        $targetCustomer->getLanguage()->setLocaleCode([
                'de' => 'DEU',
                'li' => 'DEU',
                'en' => 'ENG',
                'ro' => 'ENG',
                'cn' => 'ENG',
                'pl' => 'POL',
                'jp' => 'JPN',
                'it' => 'ITA',
                'es' => 'ESP',
            ][\explode('-', $sourceCustomer->getLanguage()->getLocale()->getCode())[0]] ?? 'DEU');

        if ($sourceCustomer->getBirthday()) {
            $targetCustomer->setBirthday($sourceCustomer->getBirthday());
        }

        $targetCustomer->setCustomerGroup($this->readCustomerGroup($sourceCustomer->getId()));
        $targetCustomer->setCustomerPriceGroup($this->getCustomerPriceGroup($sourceCustomer, $portalStorage));
        $targetCustomer->setCustomerDiscountGroup($this->getCustomerDiscountGroup($sourceCustomer, $portalStorage));

        foreach ($sourceCustomer->getAddresses()->getElements() as $sourceShippingToAddress) {
            if ($sourceCustomer->getDefaultBillingAddressId() !== $sourceShippingToAddress->getId()) {
                $targetCustomer->getAddresses()->push([$this->getAddress($sourceShippingToAddress)]);
            }
        }

        if ($sourceCustomer->getDefaultBillingAddress()) {
            $targetCustomer->setDefaultBillingAddress($this->getAddress($sourceCustomer->getDefaultBillingAddress()));
        }

        if ($sourceCustomer->getDefaultShippingAddress()) {
            $targetCustomer->setDefaultShippingAddress($this->getAddress($sourceCustomer->getDefaultShippingAddress()));
        }

        return $targetCustomer;
    }

    protected function getAddress(CustomerAddressEntity $sourceCustomerAddress): Address
    {
        $targetAddress = new Address();
        $sourceCustomerCountry = $sourceCustomerAddress->getCountry();

        if (!$sourceCustomerCountry instanceof CountryEntity) {
            throw new \Exception('Address has no country.');
        }

        $targetCountry = (new Country())
            ->setActive($sourceCustomerCountry->getActive())
            ->setIso($sourceCustomerCountry->getIso() ?? '')
            ->setIso3($sourceCustomerCountry->getIso3() ?? '')
            ->setTaxFree($sourceCustomerCountry->getTaxFree());

        $targetCountry->setPrimaryKey($sourceCustomerCountry->getId());
        $targetAddress->setCountry($targetCountry);

        $sourceCustomerCountryState = $sourceCustomerAddress->getCountryState();

        if ($sourceCustomerCountryState instanceof CountryStateEntity) {
            $targetCountryState = (new CountryState())
                ->setName($sourceCustomerCountryState->getName())
                ->setShortCode($sourceCustomerCountryState->getShortCode())
                ->setActive($sourceCustomerCountryState->getActive())
                ->setCountry($targetCountry);

            $targetCountryState->setPrimaryKey($sourceCustomerCountryState->getId());
            $targetAddress->setCountryState($targetCountryState);
        }

        $targetSalutation = (new Salutation())->setSlug($sourceCustomerAddress->getSalutation()->getSalutationKey());

        $targetSalutation->setPrimaryKey($sourceCustomerAddress->getSalutation()->getId());
        $targetAddress->setSalutation($targetSalutation);

        $targetAddress->setCompany($sourceCustomerAddress->getCompany() ?? '');
        $targetAddress->setDepartment($sourceCustomerAddress->getDepartment() ?? '');
        $targetAddress->setTitle($sourceCustomerAddress->getTitle() ?? '');
        $targetAddress->setNames(new StringCollection([
            $sourceCustomerAddress->getFirstName(),
            $sourceCustomerAddress->getLastName(),
        ]));
        $targetAddress->setStreet($sourceCustomerAddress->getStreet());
        $targetAddress->setZipcode($sourceCustomerAddress->getZipcode());
        $targetAddress->setCity($sourceCustomerAddress->getCity());
        $targetAddress->setVatId($sourceCustomerAddress->getVatId() ?? '');
        $targetAddress->setPhoneNumber($sourceCustomerAddress->getPhoneNumber() ?? '');
        $targetAddress->setAdditionalLines(new StringCollection(\array_filter([
            $sourceCustomerAddress->getAdditionalAddressLine1(),
            $sourceCustomerAddress->getAdditionalAddressLine2(),
        ])));

        $targetAddress->setPrimaryKey($sourceCustomerAddress->getId());

        return $targetAddress;
    }

    protected function getCustomerPriceGroup(
        CustomerEntity $sourceCustomer,
        PortalStorageInterface $storage
    ): ?CustomerPriceGroup {
        /** @var TagEntity $sourceCustomerTag */
        foreach ($sourceCustomer->getTags() as $sourceCustomerTag) {
            if (StorageHelper::isCustomerPriceGroupTagId($sourceCustomerTag->getId(), $storage)) {
                $targetPriceGroup = (new CustomerPriceGroup())->setCode($sourceCustomerTag->getName());
                $targetPriceGroup->setPrimaryKey($sourceCustomerTag->getId());

                return $targetPriceGroup;
            }
        }

        return null;
    }

    protected function getCustomerDiscountGroup(
        CustomerEntity $source,
        PortalStorageInterface $storage
    ): ?CustomerDiscountGroup {
        /** @var TagEntity $sourceTag */
        foreach ($source->getTags() as $sourceTag) {
            if (StorageHelper::isCustomerDiscountGroupTagId($sourceTag->getId(), $storage)) {
                $targetDiscountGroup = (new CustomerDiscountGroup())->setCode($sourceTag->getName());
                $targetDiscountGroup->setPrimaryKey($sourceTag->getId());

                return $targetDiscountGroup;
            }
        }

        return null;
    }


    protected function readCustomerGroup(string $customerId): CustomerGroup
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customers.id', $customerId));
        $customerGroup = $this->dalAccess
            ->repository('customer_group')
            ->search($criteria, $this->dalAccess->getContext())
            ->first();

        $result = new CustomerGroup();

        if ($customerGroup instanceof CustomerGroupEntity) {
            $result->setName($customerGroup->getName());
            $result->setPrimaryKey($customerGroup->getId());
        }

        return $result;
    }
}
