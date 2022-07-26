<?php

/**
 * This file is part of MetaModels/dropzone_file_upload.
 *
 * (c) 2019 - 2022 The MetaModels team.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    MetaModels/attribute_file
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @author     Ingolf Steinhardt <info@e-spin.de>
 * @copyright  2019 - 2022 The MetaModels team.
 * @license    https://github.com/MetaModels/dropzone_file_upload/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

declare(strict_types=1);

namespace MetaModels\DropzoneFileUploadBundle\EventListener;

use Contao\CoreBundle\Slug\Slug as SlugGenerator;
use Contao\Dbafs;
use Contao\FilesModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Widget;
use ContaoCommunityAlliance\DcGeneral\Contao\RequestScopeDeterminatorAwareTrait;
use ContaoCommunityAlliance\DcGeneral\ContaoFrontend\Event\BuildWidgetEvent;
use ContaoCommunityAlliance\DcGeneral\ContaoFrontend\Widgets\UploadOnSteroids;
use MetaModels\DcGeneral\DataDefinition\MetaModelDataDefinition;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * This handle the file/s, where with dropzone uploaded.
 */
final class HandleDropzoneUpload
{
    use RequestScopeDeterminatorAwareTrait;

    /**
     * The request stack.
     *
     * @var RequestStack
     */
    private RequestStack $request;

    /**
     * The filesystem.
     *
     * @var Filesystem
     */
    private Filesystem $filesystem;

    /**
     * The slug generator.
     *
     * @var SlugGenerator
     */
    private SlugGenerator $slugGenerator;

    /**
     * The project directory.
     *
     * @var string
     */
    private string $projectDir;

    /**
     * The constructor.
     *
     * @param RequestStack       $request       The request.
     * @param Filesystem         $filesystem    The filesystem.
     * @param SlugGenerator|null $slugGenerator The slug generator.
     * @param String             $projectDir    The project directory.
     */
    public function __construct(
        RequestStack $request,
        Filesystem $filesystem,
        SlugGenerator $slugGenerator = null,
        string $projectDir
    ) {
        if (null === $slugGenerator) {
            $slugGenerator = System::getContainer()->get('contao.slug');
        }

        $this->request       = $request;
        $this->filesystem    = $filesystem;
        $this->slugGenerator = $slugGenerator;
        $this->projectDir    = $projectDir;
    }

    /**
     * Handle the file/s, where with dropzone uploaded.
     *
     * @param BuildWidgetEvent $event The event.
     *
     * @return void
     */
    public function __invoke(BuildWidgetEvent $event): void
    {
        if (!$this->wantToHandle($event)) {
            return;
        }

        $this->doHandle($event);
    }

    /**
     * Do handle.
     *
     * @param BuildWidgetEvent $event The event.
     *
     * @return void
     */
    private function doHandle(BuildWidgetEvent $event): void
    {
        $this->handleSingleUpload($event);
        $this->handleMultipleUpload($event);
    }

    /**
     * Handle the single upload.
     *
     * @param BuildWidgetEvent $event The event.
     *
     * @return void
     */
    private function handleSingleUpload(BuildWidgetEvent $event): void
    {
        if ($event->getWidget()->multiple
            || $event->getWidget()->hasErrors()
            || !($tmpFolder = $this->getTempFolder($event))
        ) {
            return;
        }

        $finder = new Finder();
        $files  = $finder
            ->files()
            ->followLinks()
            ->sortByChangedTime()
            ->in($this->projectDir . DIRECTORY_SEPARATOR . $tmpFolder);

        if (!$files->count()) {
            return;
        }

        $uploaded = [];
        /** @var SplFileInfo $fileInfo */
        foreach ($files as $fileInfo) {
            $uploaded[] = $fileInfo->getPathname();
        }

        $uploadFolder = $this->getUploadedFolder($event);
        $widget       = $event->getWidget();

        $uploadedFilePath = \current($uploaded);
        $file             = new File($uploadedFilePath);
        $newFilePath      =
            $this->moveFileToDestination($file, $uploadFolder, (bool) $widget->doNotOverwrite, $widget);

        if (!($newUploadFolder = FilesModel::findByPath($newFilePath))) {
            $newUploadFolder = Dbafs::addResource($newFilePath);
        }

        $widget->value = [$newUploadFolder->uuid];
    }

    /**
     * Handle the multiple upload.
     *
     * @param BuildWidgetEvent $event The event.
     *
     * @return void
     */
    private function handleMultipleUpload(BuildWidgetEvent $event): void
    {
        if (!$event->getWidget()->multiple
            || $event->getWidget()->hasErrors()
            || !($tmpFolder = $this->getTempFolder($event))
        ) {
            return;
        }

        $finder = new Finder();
        $files  = $finder
            ->files()
            ->followLinks()
            ->sortByChangedTime()
            ->in($this->projectDir . DIRECTORY_SEPARATOR . $tmpFolder);

        if (!$files->count()) {
            return;
        }

        $uploadFolder = $this->getUploadedFolder($event);
        $widget       = $event->getWidget();

        $uploaded = [];
        /** @var SplFileInfo $fileInfo */
        foreach ($files as $fileInfo) {
            $file        = new File($fileInfo->getPathname());
            $newFilePath =
                $this->moveFileToDestination($file, $uploadFolder, (bool) $widget->doNotOverwrite, $widget);

            if (!($newUploadFolder = FilesModel::findByPath($newFilePath))) {
                $newUploadFolder = Dbafs::addResource($newFilePath);
            }

            $uploaded[] = StringUtil::binToUuid($newUploadFolder->uuid);
        }

        $model     = $event->getModel();
        $values    = \array_map('\Contao\StringUtil::binToUuid', $model->getProperty($event->getProperty()->getName()));
        $setValues = \array_values(\array_unique(\array_merge($values, $uploaded)));

        $widget->value = \array_map('\Contao\StringUtil::uuidToBin', $setValues);
    }

    /**
     * Move the file to destination.
     *
     * @param File                    $file         The file.
     * @param string                  $uploadFolder The upload folder.
     * @param bool                    $override     Determine for override the file.
     * @param Widget|UploadOnSteroids $widget       The widget.
     *
     * @return string
     */
    private function moveFileToDestination(File $file, string $uploadFolder, bool $override, Widget $widget): string
    {
        $filename = $widget->parseFilename($file->getFilename());
        if (!$override
            || !\file_exists($this->projectDir . DIRECTORY_SEPARATOR . $uploadFolder . DIRECTORY_SEPARATOR . $filename)
        ) {
            $newFilePath = $uploadFolder . DIRECTORY_SEPARATOR . $filename;

            $this->filesystem
                ->rename($file->getPathname(), $this->projectDir . DIRECTORY_SEPARATOR . $newFilePath, true);

            return $newFilePath;
        }

        $offset = 1;

        $fileInfo   = \pathinfo($filename);
        $allFiles   = \scan($this->projectDir . DIRECTORY_SEPARATOR . $uploadFolder);
        $foundFiles = \preg_grep(
            '/^' . \preg_quote($fileInfo['filename'], '/') . '.*\.' . \preg_quote($fileInfo['extension'], '/') . '/',
            $allFiles
        );

        foreach ($foundFiles as $foundFile) {
            if (\preg_match('/__[0-9]+\.' . \preg_quote($fileInfo['extension'], '/') . '$/', $foundFile)) {
                $foundFile = \str_replace('.' . $fileInfo['extension'], '', $foundFile);

                $offset = \max($offset, (int) \substr($foundFile, (\strrpos($foundFile, '_') + 1)));
            }
        }

        $newFilename = $fileInfo['filename'] . '__' . ++$offset . '.' . $fileInfo['extension'];
        $newFilePath = $uploadFolder . DIRECTORY_SEPARATOR . $newFilename;
        if (!\file_exists($this->projectDir . DIRECTORY_SEPARATOR . $newFilePath)) {
            $this->filesystem
                ->rename($file->getPathname(), $this->projectDir . DIRECTORY_SEPARATOR . $newFilePath, true);

            return $newFilePath;
        }

        $renamedPath = $file->getPath() . DIRECTORY_SEPARATOR . $newFilename;
        $this->filesystem->rename($file->getPathname(), $renamedPath, true);

        $file = new File($renamedPath);

        return $this->moveFileToDestination($file, $uploadFolder, $override, $widget);
    }

    /**
     * Get the upload folder.
     *
     * @param BuildWidgetEvent $event The event.
     *
     * @return string
     */
    private function getUploadedFolder(BuildWidgetEvent $event): string
    {
        $widget = $event->getWidget();

        $uploadFolder = FilesModel::findByUuid($widget->uploadFolder);
        if (!$widget->extendFolder) {
            return $uploadFolder->path;
        }

        if ($widget->normalizeExtendFolder) {
            $widget->extendFolder =
                $this->slugGenerator->generate((string) $widget->extendFolder, $this->getSlugOptions());
        }
        $uploadFolderPath = $uploadFolder->path . DIRECTORY_SEPARATOR . $widget->extendFolder;

        $newUploadFolder = null;
        if (!$this->filesystem->exists($uploadFolderPath)) {
            $this->filesystem->mkdir($uploadFolderPath);
            $newUploadFolder = Dbafs::addResource($uploadFolderPath);
        }

        if (!$newUploadFolder) {
            $newUploadFolder = FilesModel::findByPath($uploadFolderPath);
        }

        return $newUploadFolder->path;
    }

    /**
     * Get the temporary folder.
     *
     * @param BuildWidgetEvent $event
     *
     * @return string|null
     */
    private function getTempFolder(BuildWidgetEvent $event): ?string
    {
        $extra     = $event->getProperty()->getExtra();
        $tmpFolder = $extra['tempFolder'];

        return $this->filesystem->exists($this->projectDir . DIRECTORY_SEPARATOR . $tmpFolder) ? $tmpFolder : null;
    }

    /**
     * Detect if in the right scope and the attribute is configured as dropzone upload field.
     *
     * @param BuildWidgetEvent $event The event.
     *
     * @return bool
     */
    private function wantToHandle(BuildWidgetEvent $event): bool
    {
        return $this->scopeDeterminator->currentScopeIsFrontend()
               && !$this->scopeDeterminator->currentScopeIsUnknown()
               && ($event->getEnvironment()->getDataDefinition() instanceof MetaModelDataDefinition)
               && $this->isFormSubmitted($event)
               && $this->isDropzoneUploadField($event)
               && !$this->isAjaxRequest($event);
    }

    /**
     * Detect if the form submitted.
     *
     * @param BuildWidgetEvent $event The event.
     *
     * @return bool
     */
    private function isFormSubmitted(BuildWidgetEvent $event): bool
    {
        $environment = $event->getEnvironment();
        $submitted   = $this->request->getMasterRequest()->request;

        return $submitted->has('FORM_SUBMIT')
               && ($environment->getDataDefinition()->getName() === $submitted->get('FORM_SUBMIT'));
    }

    /**
     * Detect if is configured as dropzone upload field.
     *
     * @param BuildWidgetEvent $event The event.
     *
     * @return bool
     */
    private function isDropzoneUploadField(BuildWidgetEvent $event): bool
    {
        return true === $event->getWidget()->dropzoneUpload;
    }

    /**
     * Detect if is ajax request.
     *
     * @param BuildWidgetEvent $event The event.
     *
     * @return bool
     */
    private function isAjaxRequest(BuildWidgetEvent $event): bool
    {
        $inputProvider = $event->getEnvironment()->getInputProvider();

        return ('dropZoneAjax' === $inputProvider->getValue('action'))
               && $inputProvider->hasValue('id');
    }

    /**
     * Get the slug options.
     *
     * @return array
     */
    protected function getSlugOptions(): array
    {
        // TODO: make configurable.
        return ['locale' => 'de', 'validChars' => '0-9a-z_-'];
    }
}
