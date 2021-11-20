<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Test\Unit\Unpacker;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Product;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Tax\TaxGroupRule;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\ExistingIdentifierCache;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\LocaleMatcher;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\TranslationLocaleCache;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\ManufacturerUnpacker;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\MediaUnpacker;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\ProductPriceUnpacker;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\ProductUnpacker;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\TranslatableUnpacker;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\UnitUnpacker;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Defaults;

/**
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\ProductUnpacker
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\TranslatableUnpacker
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\LocaleMatcher
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\PrimaryKeyGenerator
 */
class ProductUnpackerTest extends TestCase
{
    public function testUnpack(): void
    {
        $cache = $this->createMock(TranslationLocaleCache::class);
        $cache->method('getLocales')->willReturn([
            '7faafd19715542acb601356550f3af9a' => 'de-DE',
            'e40e9fdb1dc547978d88cc629bc04442' => 'nl-NL',
            '4bfcafc124d349239369ef4f8749f48b' => 'en-GB',
        ]);

        $id = 'f952a562cfc348cd9c83f29b0ca53e20';
        $dal = $this->createMock(DalAccess::class);
        $dal->method('idExists')->willReturn(true);

        $unpacker = new ProductUnpacker(
            $dal,
            $this->createMock(ExistingIdentifierCache::class),
            $this->createMock(MediaUnpacker::class),
            $this->createMock(ManufacturerUnpacker::class),
            $this->createMock(UnitUnpacker::class),
            $this->createMock(ProductPriceUnpacker::class),
            new TranslatableUnpacker($cache, new LocaleMatcher(new NullLogger()), new NullLogger()),
        );

        $taxGroupRule = new TaxGroupRule();
        $taxGroupRule->setPrimaryKey('30a13541e68a44a0977bfe2a55eebb74');

        $product = new Product();
        $product->setPrimaryKey($id);
        $product->getName()->setFallback('foobar');
        $product->getName()->setTranslation('de-DE', 'fööbär');
        $product->getDescription()->setFallback('foobar is a good product');
        $product->getDescription()->setTranslation('de-DE', 'fööbär ist ein gutes Produkt');
        $product->getTaxGroup()->getRules()->push([$taxGroupRule]);
        $product->setNumber('cb50a1ef02f84b9eaa63b13d3f9df2bb');

        self::assertEquals([
            'id' => $id,
            'productNumber' => 'cb50a1ef02f84b9eaa63b13d3f9df2bb',
            'taxId' => '30a13541e68a44a0977bfe2a55eebb74',
            'translations' => [
                'de-DE' =>  [
                    'name' => 'fööbär',
                    'description' => 'fööbär ist ein gutes Produkt',
                ],
                'nl-NL' =>  [
                    'name' => 'foobar',
                    'description' => 'foobar is a good product',
                ],
                'en-GB' =>  [
                    'name' => 'foobar',
                    'description' => 'foobar is a good product',
                ],
            ],
            'ean' => '',
            'stock' => 0,
            'minPurchase' => 1,
            'purchaseSteps' => 1,
            'isCloseout' => true,
            'shippingFree' => false,
            'visibilities' => [],
            'unitId' => null,
            'purchaseUnit' => 0.0,
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 0,
                    'net' => 0,
                    'linked' => true,
                ],
            ],
            'prices' => [],
            'coverId' => null,
            'manufacturerId' => null,
            'categories' => [],
            'properties' => [],
            'parentId' => null,
            'active' => false,
            'media' => [],
        ], $unpacker->unpack($product));
    }
}
