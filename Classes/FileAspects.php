<?php

namespace Lemming\Imageoptimizer;

use TYPO3\CMS\Core\Imaging\GraphicalFunctions;
use TYPO3\CMS\Core\Resource\Driver\DriverInterface;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\Service\FileProcessingService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FileAspects
{
    /**
     * @var OptimizeImageService
     */
    protected $service;

    public function __construct()
    {
        $this->service = GeneralUtility::makeInstance(OptimizeImageService::class);
    }

    /**
     * Called when a new file is uploaded
     *
     * @param string $targetFileName
     * @param Folder $targetFolder
     * @param string $sourceFilePath
     * @return string Modified target file name
     */
    public function addFile($targetFileName, Folder $targetFolder, $sourceFilePath)
    {
        $this->service->process($sourceFilePath, pathinfo($targetFileName)['extension'], true);
    }

    /**
     * Called when a file is overwritten
     *
     * @param FileInterface $file The file to replace
     * @param string $localFilePath The uploaded file
     */
    public function replaceFile(FileInterface $file, $localFilePath)
    {
        $this->service->process($localFilePath, $file->getExtension(), true);
    }

    /**
     * Called when a file was processed
     *
     * @param FileProcessingService $fileProcessingService
     * @param DriverInterface $driver
     * @param ProcessedFile $processedFile
     *
     * @throws BinaryNotFoundException
     */
    public function processFile($fileProcessingService, $driver, $processedFile)
    {
        if ( ! $processedFile->isUpdated()) {
            return;
        }

        if ($processedFile->usesOriginalFile()) {
            $this->processOriginalFile($processedFile);
        }

        $this->service->process(PATH_site . $processedFile->getPublicUrl(), $processedFile->getExtension());
    }

    /**
     * @param ProcessedFile $processedFile
     */
    protected function processOriginalFile($processedFile)
    {
        $localCopy = $processedFile->getForLocalProcessing();

        $imageDimensions = GeneralUtility::makeInstance(GraphicalFunctions::class)
            ->getImageDimensions($localCopy);
        $properties = [
            'width' => $imageDimensions[0],
            'height' => $imageDimensions[1],
            'size' => filesize($localCopy),
            'checksum' => $processedFile->getTask()->getConfigurationChecksum()
        ];

        $processedFile->updateProperties($properties);
        $processedFile->setName($processedFile->getTask()->getTargetFileName());
        $processedFile->updateWithLocalFile($localCopy);
        $processedFile->getTask()->setExecuted(true);

        $processedFileRepository = GeneralUtility::makeInstance(ProcessedFileRepository::class);
        $processedFileRepository->add($processedFile);
    }
}
