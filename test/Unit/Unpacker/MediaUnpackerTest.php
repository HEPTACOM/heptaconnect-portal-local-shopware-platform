<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Test\Unit\Unpacker;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Media\Media;
use Heptacom\HeptaConnect\Portal\Base\Serialization\Contract\NormalizationRegistryContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\LocaleMatcher;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\TranslationLocaleCache;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\TranslatableUnpacker;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\MediaUnpacker;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Media\MediaService;

/**
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\MediaUnpacker
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\TranslatableUnpacker
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\LocaleMatcher
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\PrimaryKeyGenerator
 */
class MediaUnpackerTest extends TestCase
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
        $dal->method('idExists')
            ->willReturnCallback(
                static fn (string $r, ?string $pk): bool => [
                    'media' => [
                        $id => true,
                    ],
                ][$r][$pk ?? ''] ?? false
        );

        $unpacker = new MediaUnpacker(
            $this->createMock(MediaService::class),
            $this->createMock(NormalizationRegistryContract::class),
            $dal,
            new TranslatableUnpacker($cache, new LocaleMatcher(new NullLogger()), new NullLogger())
        );

        $media = new Media();
        $media->setPrimaryKey($id);
        $media->getTitle()->setFallback('foobar');
        $media->getTitle()->setTranslation('de-DE', 'fööbär');

        self::assertEquals([
            'id' => $id,
            'translations' => [
                'de-DE' =>  [
                    'title' => 'fööbär',
                    'alt' => 'fööbär',
                ],
                'nl-NL' =>  [
                    'title' => 'foobar',
                    'alt' => 'foobar',
                ],
                'en-GB' =>  [
                    'title' => 'foobar',
                    'alt' => 'foobar',
                ],
            ],
        ], $unpacker->unpack($media));
    }
}
