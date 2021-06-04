<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Receiver;

use Heptacom\HeptaConnect\Core\Storage\Contract\DenormalizerInterface;
use Heptacom\HeptaConnect\Core\Storage\NormalizationRegistry;
use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Media\Media;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiveContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiverContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\PrimaryKeyGenerator;
use Psr\Http\Message\StreamInterface;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Mime\MimeTypes;

class MediaReceiver extends ReceiverContract
{
    public function supports(): string
    {
        return Media::class;
    }

    /**
     * @param Media $entity
     */
    protected function run(
        DatasetEntityContract $entity,
        ReceiveContextInterface $context
    ): void {
        $container = $context->getContainer();
        /** @var DalAccess $dalAccess */
        $dalAccess = $container->get(DalAccess::class);
        $swContext = $dalAccess->getContext();
        /** @var NormalizationRegistry $normalizationRegistry */
        $normalizationRegistry = $container->get(NormalizationRegistry::class);
        $source = self::getMediaStream($entity, $normalizationRegistry);
        $mediaRepository = $dalAccess->repository('media');
        $mediaFolderRepository = $dalAccess->repository('media_folder');

        if (!$source instanceof StreamInterface) {
            throw new \RuntimeException('Could not unpack media stream');
        }

        if (!$dalAccess->idExists('media', $entity->getPrimaryKey())) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('defaultFolder.entity', 'import_export_profile'));
            $criteria->setLimit(1);
            $folderId = $mediaFolderRepository->searchIds($criteria, $swContext)->firstId();

            $entity->setPrimaryKey(PrimaryKeyGenerator::generatePrimaryKey($entity, 'c62caef4-4b51-4370-9e89-de9ba41faa54') ?? Uuid::randomHex());
            $mediaRepository->create([[
                'id' => $entity->getPrimaryKey(),
                'mediaFolderId' => $folderId,
            ]], $swContext);
        }

        $mediaRepository->update([[
            'id' => $entity->getPrimaryKey(),
            // TODO improve translations
            'alt' => $entity->getTitle()->getFallback(),
            'title' => $entity->getTitle()->getFallback(),
        ]], $swContext);

        $fileName = \tempnam(\sys_get_temp_dir(), 'local-media-receiver');

        /** @var FileSaver $fileSaver */
        $fileSaver = $container->get(FileSaver::class);

        $fileSaver->persistFileToMedia(
            self::prepareMediaFile($fileName, $entity->getMimeType(), $source),
            $entity->getPrimaryKey(),
            $entity->getPrimaryKey(),
            $swContext
        );

        if (@\is_file($fileName)) {
            @\unlink($fileName);
        }
    }

    private static function prepareMediaFile(string $fileName, string $mimeType, StreamInterface $source): MediaFile
    {
        $sourceStream = $source->detach();
        $file = \fopen($fileName, 'wb+');
        \stream_copy_to_stream($sourceStream, $file);
        \fclose($file);
        \fclose($sourceStream);

        return new MediaFile(
            $fileName,
            $mimeType,
            MimeTypes::getDefault()->getExtensions($mimeType)[0] ?? 'bin',
            \filesize($fileName)
        );
    }

    private static function getMediaStream(Media $media, NormalizationRegistry $normalizationRegistry): ?StreamInterface
    {
        $denormalizer = $normalizationRegistry->getDenormalizer('stream');

        if (!$denormalizer instanceof DenormalizerInterface) {
            return null;
        }

        $stream = $denormalizer->denormalize($media->getNormalizedStream(), 'stream');

        if (!$stream instanceof StreamInterface) {
            return null;
        }

        return $stream;
    }
}
