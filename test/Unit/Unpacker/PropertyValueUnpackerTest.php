<?php

declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Test\Unit\Unpacker;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Property\PropertyValue;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\LocaleMatcher;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\TranslationLocaleCache;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\PropertyValueUnpacker;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\TranslatableUnpacker;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\PropertyValueUnpacker
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\TranslatableUnpacker
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\LocaleMatcher
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\PrimaryKeyGenerator
 */
class PropertyValueUnpackerTest extends TestCase
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
        $groupId = '5da8972c0c374c9396da4ad00a555e39';
        $unpacker = new PropertyValueUnpacker(new TranslatableUnpacker($cache, new LocaleMatcher(new NullLogger()), new NullLogger()));

        $propertyValue = new PropertyValue();
        $propertyValue->setPrimaryKey($id);
        $propertyValue->getGroup()->setPrimaryKey($groupId);
        $propertyValue->getName()->setFallback('foobar');
        $propertyValue->getName()->setTranslation('de-DE', 'fööbär');

        static::assertEquals([
            'id' => $id,
            'groupId' => $groupId,
            'translations' => [
                'de-DE' => [
                    'name' => 'fööbär',
                ],
                'nl-NL' => [
                    'name' => 'foobar',
                ],
                'en-GB' => [
                    'name' => 'foobar',
                ],
            ],
        ], $unpacker->unpack($propertyValue));
    }
}
