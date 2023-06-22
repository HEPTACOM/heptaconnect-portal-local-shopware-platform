<?php

declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Packer;

use Heptacom\HeptaConnect\Dataset\Base\ScalarCollection\StringCollection;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Address\Address;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Address\Country;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Address\CountryState;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Address\Salutation;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Customer\Customer;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Language\LanguageEntity;

class OrderCustomerPacker
{
    public function pack(OrderCustomerEntity $source): Customer
    {
        $targetCustomer = new Customer();
        $targetCustomer->setPrimaryKey('order-customer:' . $source->getId());

        $salutation = new Salutation();

        if ($source->getSalutation()) {
            $salutation->setPrimaryKey($source->getSalutation()->getId());
            $salutation->setSlug($source->getSalutation()->getSalutationKey());
            $targetCustomer->setSalutation($salutation);
        }

        if ($source->getTitle()) {
            $targetCustomer->setTitle($source->getTitle());
        }

        $targetCustomer->setNumber($source->getCustomerNumber())
            ->setNames(new StringCollection([$source->getFirstName(), $source->getLastName()]))
            ->setEmail($source->getEmail())
            ->setCompany($source->getCompany() ?? '')
            ->setActive(false)
            ->setGuest(true);

        $sourceOrder = $source->getOrder();

        if ($sourceOrder instanceof OrderEntity) {
            $orderLanguage = $this->getOrderLanguage($sourceOrder);

            if ($orderLanguage !== null) {
                $orderLocale = $orderLanguage->getLocale();

                if ($orderLocale !== null) {
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
                    ][\explode('-', $orderLocale->getCode())[0]] ?? 'DEU');
                }
            }

            $sourceOrderBillingAddress = $sourceOrder->getBillingAddress();

            if ($sourceOrderBillingAddress !== null) {
                $billingAddress = $this->getAddress($sourceOrderBillingAddress);

                $vatIds = $source->getVatIds() ?? [];
                $vatId = (string) (\array_shift($vatIds) ?? '');
                $billingAddress->setVatId($vatId);

                $targetCustomer->setDefaultBillingAddress($billingAddress);
                $targetCustomer->getAddresses()->push([$billingAddress]);
            }

            /** @var OrderDeliveryEntity $delivery */
            foreach ($sourceOrder->getDeliveries() as $delivery) {
                $sourceOrderShippingAddress = $delivery->getShippingOrderAddress();

                if ($sourceOrderShippingAddress !== null) {
                    $shippingAddress = $this->getAddress($sourceOrderShippingAddress);

                    $targetCustomer->setDefaultShippingAddress($shippingAddress);
                    $targetCustomer->getAddresses()->push([$shippingAddress]);

                    break;
                }
            }
        }

        return $targetCustomer;
    }

    protected function getAddress(OrderAddressEntity $address): Address
    {
        $result = new Address();
        $sourceOrderCountry = $address->getCountry();

        if (!$sourceOrderCountry instanceof CountryEntity) {
            throw new \Exception('Address has no country.');
        }

        $targetCountry = (new Country())
            ->setActive($sourceOrderCountry->getActive())
            ->setIso($sourceOrderCountry->getIso() ?? '')
            ->setIso3($sourceOrderCountry->getIso3() ?? '')
            ->setTaxFree($sourceOrderCountry->getTaxFree());

        $targetCountry->setPrimaryKey($sourceOrderCountry->getId());
        $result->setCountry($targetCountry);

        $sourceCustomerCountryState = $address->getCountryState();

        if ($sourceCustomerCountryState instanceof CountryStateEntity) {
            $targetCountryState = (new CountryState())
                ->setName($sourceCustomerCountryState->getName())
                ->setShortCode($sourceCustomerCountryState->getShortCode())
                ->setActive($sourceCustomerCountryState->getActive())
                ->setCountry($targetCountry);

            $targetCountryState->setPrimaryKey($sourceCustomerCountryState->getId());
            $result->setCountryState($targetCountryState);
        }

        $targetSalutation = (new Salutation())->setSlug($address->getSalutation()->getSalutationKey());

        $targetSalutation->setPrimaryKey($address->getSalutation()->getId());
        $result->setSalutation($targetSalutation);

        $result->setCompany($address->getCompany() ?? '');
        $result->setDepartment($address->getDepartment() ?? '');
        $result->setTitle($address->getTitle() ?? '');
        $result->setNames(new StringCollection([
            $address->getFirstName(),
            $address->getLastName(),
        ]));
        $result->setStreet($address->getStreet());
        $result->setZipcode($address->getZipcode());
        $result->setCity($address->getCity());
        $result->setPhoneNumber($address->getPhoneNumber() ?? '');
        $result->setAdditionalLines(new StringCollection(\array_filter([
            $address->getAdditionalAddressLine1(),
            $address->getAdditionalAddressLine2(),
        ])));

        $result->setPrimaryKey($address->getId());

        return $result;
    }

    private function getOrderLanguage(OrderEntity $sourceOrder): ?LanguageEntity
    {
        try {
            return $sourceOrder->getLanguage();
        } catch (\TypeError $typeError) {
            // OrderEntity::$language is null
            return null;
        }
    }
}
