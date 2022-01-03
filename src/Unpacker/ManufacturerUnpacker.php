<?php

declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Media\Media;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Media\MediaCollection;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Manufacturer;
use Shopware\Core\Framework\Uuid\Uuid;

class ManufacturerUnpacker
{
    private MediaUnpacker $mediaUnpacker;

    private TranslatableUnpacker $translatableUnpacker;

    public function __construct(
        MediaUnpacker $mediaUnpacker,
        TranslatableUnpacker $translatableUnpacker
    ) {
        $this->mediaUnpacker = $mediaUnpacker;
        $this->translatableUnpacker = $translatableUnpacker;
    }

    public function unpack(Manufacturer $source): array
    {
        // TODO improve id generation
        $targetManufacturerId = $source->getPrimaryKey() ?? Uuid::randomHex();
        $source->setPrimaryKey($targetManufacturerId);

        $media = $this->getManufacturerImage($source);

        return [
            'id' => $targetManufacturerId,
            ($media === null ? 'mediaId' : 'media') => $media,
            'translations' => $this->unpackTranslations($source),
        ];
    }

    protected function unpackTranslations(Manufacturer $manufacturer): array
    {
        return $this->translatableUnpacker->unpack($manufacturer->getName(), 'name');
    }

    protected function getManufacturerImage(Manufacturer $manufacturer): ?array
    {
        $manufacturerId = $manufacturer->getPrimaryKey();

        if ($manufacturerId === null) {
            return [];
        }

        $unpackedMedia = [];

        if ($manufacturer->hasAttached(Media::class)) {
            /** @var Media $media */
            $media = $manufacturer->getAttachment(Media::class);
            $unpackedMedia = $this->mediaUnpacker->unpack($media);
        }

        if ($manufacturer->hasAttached(MediaCollection::class)) {
            /** @var MediaCollection $medias */
            $medias = $manufacturer->getAttachment(MediaCollection::class);

            if ($medias->count() > 0) {
                foreach ($medias as $media) {
                    $unpackedMedia = $this->mediaUnpacker->unpack($media);

                    if ($unpackedMedia !== []) {
                        break;
                    }
                }
            }
        }

        if ($unpackedMedia === []) {
            return null;
        }

        return $unpackedMedia;
    }
}
