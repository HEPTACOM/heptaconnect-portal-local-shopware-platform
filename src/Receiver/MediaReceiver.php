<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Receiver;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Media\Media;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiveContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiverContract;
use Heptacom\HeptaConnect\Portal\Base\Serialization\Contract\DenormalizerInterface;
use Heptacom\HeptaConnect\Portal\Base\Serialization\Contract\NormalizationRegistryContract;
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
    private DalAccess $dal;

    private NormalizationRegistryContract $normalizationRegistry;

    private FileSaver $fileSaver;

    public function __construct(
        DalAccess $dal,
        NormalizationRegistryContract $normalizationRegistry,
        FileSaver $fileSaver
    ) {
        $this->dal = $dal;
        $this->normalizationRegistry = $normalizationRegistry;
        $this->fileSaver = $fileSaver;
    }

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
        $swContext = $this->dal->getContext();
        $source = $this->getMediaStream($entity);
        $mediaRepository = $this->dal->repository('media');
        $mediaFolderRepository = $this->dal->repository('media_folder');

        if (!$source instanceof StreamInterface) {
            throw new \RuntimeException('Could not unpack media stream');
        }

        if (!$this->dal->idExists('media', $entity->getPrimaryKey())) {
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

        $this->fileSaver->persistFileToMedia(
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

    private function getMediaStream(Media $media): ?StreamInterface
    {
        $denormalizer = $this->normalizationRegistry->getDenormalizer('stream');

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
