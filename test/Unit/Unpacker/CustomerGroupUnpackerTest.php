<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Test\Unit\Unpacker;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Customer\CustomerGroup;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\LocaleMatcher;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\TranslationLocaleCache;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\CustomerGroupUnpacker;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\TranslatableUnpacker;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\CustomerGroupUnpacker
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\TranslatableUnpacker
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\LocaleMatcher
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\PrimaryKeyGenerator
 */
class CustomerGroupUnpackerTest extends TestCase
{
    public function testUpdating(): void
    {
        $cache = $this->createMock(TranslationLocaleCache::class);
        $cache->method('getLocales')->willReturn([
            '7faafd19715542acb601356550f3af9a' => 'de-DE',
            'e40e9fdb1dc547978d88cc629bc04442' => 'nl-NL',
            '4bfcafc124d349239369ef4f8749f48b' => 'en-GB',
        ]);

        $id = 'f952a562cfc348cd9c83f29b0ca53e20';
        $dal = $this->createMock(DalAccess::class);
        $dal->method('idExists')
            ->willReturnCallback(
                static fn (string $r, ?string $pk): bool => [
                    'customer_group' => [
                        $id => true,
                    ],
                ][$r][$pk ?? ''] ?? false
        );

        $unpacker = new CustomerGroupUnpacker(new TranslatableUnpacker($cache, new LocaleMatcher(new NullLogger()), new NullLogger()), $dal);

        $customerGroup = new CustomerGroup();
        $customerGroup->setPrimaryKey($id);
        $customerGroup->setName('foobar');

        self::assertEquals([
            'id' => $id,
            'translations' => [
                'de-DE' =>  [
                    'name' => 'foobar',
                ],
                'nl-NL' =>  [
                    'name' => 'foobar',
                ],
                'en-GB' =>  [
                    'name' => 'foobar',
                ],
            ],
        ], $unpacker->unpack($customerGroup));
    }

    public function testCreating(): void
    {
        $cache = $this->createMock(TranslationLocaleCache::class);
        $cache->method('getLocales')->willReturn([
            '7faafd19715542acb601356550f3af9a' => 'de-DE',
            'e40e9fdb1dc547978d88cc629bc04442' => 'nl-NL',
            '4bfcafc124d349239369ef4f8749f48b' => 'en-GB',
        ]);

        $id = 'f952a562cfc348cd9c83f29b0ca53e20';
        $dal = $this->createMock(DalAccess::class);
        $dal->method('idExists')->willReturn(false);

        $unpacker = new CustomerGroupUnpacker(new TranslatableUnpacker($cache, new LocaleMatcher(new NullLogger()), new NullLogger()), $dal);

        $customerGroup = new CustomerGroup();
        $customerGroup->setPrimaryKey($id);
        $customerGroup->setName('foobar');

        self::assertEquals([
            'id' => $id,
            'displayGross' => true,
            'translations' => [
                'de-DE' =>  [
                    'name' => 'foobar',
                ],
                'nl-NL' =>  [
                    'name' => 'foobar',
                ],
                'en-GB' =>  [
                    'name' => 'foobar',
                ],
            ],
        ], $unpacker->unpack($customerGroup));
    }
}
