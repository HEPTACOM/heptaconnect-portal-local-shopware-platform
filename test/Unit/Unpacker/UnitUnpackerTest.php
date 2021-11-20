<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Test\Unit\Unpacker;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Unit;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\LocaleMatcher;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\TranslationLocaleCache;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\TranslatableUnpacker;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\UnitUnpacker;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\TranslatableUnpacker
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\UnitUnpacker
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\LocaleMatcher
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\PrimaryKeyGenerator
 */
class UnitUnpackerTest extends TestCase
{
    public function testUnpack(): void
    {
        $cache = $this->createMock(TranslationLocaleCache::class);
        $cache->method('getLocales')->willReturn([
            '7faafd19715542acb601356550f3af9a' => 'de-DE',
            'e40e9fdb1dc547978d88cc629bc04442' => 'nl-NL',
            '4bfcafc124d349239369ef4f8749f48b' => 'en-GB',
        ]);

        $parentId = '134c489be0e24734828243244d51d90b';
        $id = 'f952a562cfc348cd9c83f29b0ca53e20';
        $dal = $this->createMock(DalAccess::class);
        $dal->method('idExists')
            ->willReturnCallback(
                static fn (string $r, ?string $pk): bool => [
                    'category' => [
                        $id => true,
                        $parentId => true,
                    ],
                ][$r][$pk ?? ''] ?? false
        );

        $unpacker = new UnitUnpacker(new TranslatableUnpacker($cache, new LocaleMatcher(new NullLogger()), new NullLogger()), $dal);

        $unit = new Unit();
        $unit->setPrimaryKey($id);
        $unit->getName()->setFallback('foobar');
        $unit->getName()->setTranslation('de-DE', 'fööbär');
        $unit->setSymbol('ƒb');

        self::assertEquals([
            'id' => $id,
            'translations' => [
                'de-DE' =>  [
                    'name' => 'fööbär',
                    'shortCode' => 'ƒb',
                ],
                'nl-NL' =>  [
                    'name' => 'foobar',
                    'shortCode' => 'ƒb',
                ],
                'en-GB' =>  [
                    'name' => 'foobar',
                    'shortCode' => 'ƒb',
                ],
            ],
        ], $unpacker->unpack($unit));
    }
}
