<?php

/**
 * This file is part of MetaModels/dropzone_file_upload.
 *
 * (c) 2019-2024 The MetaModels team.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    MetaModels/dropzone_file_upload
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @author     Ingolf Steinhardt <info@e-spin.de>
 * @copyright  2019-2024 The MetaModels team.
 * @license    https://github.com/MetaModels/dropzone_file_upload/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

declare(strict_types=1);

namespace MetaModels\DropzoneFileUploadBundle\EventListener;

use Contao\CoreBundle\Slug\Slug as SlugGenerator;
use Contao\Dbafs;
use Contao\FilesModel;
use Contao\Folder;
use Contao\StringUtil;
use Contao\System;
use Contao\Widget;
use ContaoCommunityAlliance\DcGeneral\Contao\RequestScopeDeterminator;
use ContaoCommunityAlliance\DcGeneral\Contao\RequestScopeDeterminatorAwareTrait;
use ContaoCommunityAlliance\DcGeneral\ContaoFrontend\Event\BuildWidgetEvent;
use ContaoCommunityAlliance\DcGeneral\ContaoFrontend\Widgets\UploadOnSteroids;
use ContaoCommunityAlliance\DcGeneral\DataDefinition\ContainerInterface;
use ContaoCommunityAlliance\DcGeneral\InputProviderInterface;
use MetaModels\DcGeneral\DataDefinition\MetaModelDataDefinition;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * This handle the file/s, where with dropzone uploaded.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
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
     * @param string             $projectDir    The project directory.
     * @param SlugGenerator|null $slugGenerator The slug generator.
     */
    public function __construct(
        RequestStack $request,
        Filesystem $filesystem,
        string $projectDir,
        SlugGenerator $slugGenerator = null
    ) {
        if (null === $slugGenerator) {
            // @codingStandardsIgnoreStart
            @trigger_error(
                'SlugGenerator $slugGenerator must be passed in the constructor. Fallback will be dropped.',
                E_USER_DEPRECATED
            );
            // @codingStandardsIgnoreEnd
            $slugGenerator = System::getContainer()->get('contao.slug');
            assert($slugGenerator instanceof SlugGenerator);
        }

        $this->request       = $request;
        $this->filesystem    = $filesystem;
        $this->projectDir    = $projectDir;
        $this->slugGenerator = $slugGenerator;
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
        $widget = $event->getWidget();
        assert($widget instanceof UploadOnSteroids);

        /** @psalm-suppress UndefinedMagicPropertyFetch */
        if (
            $widget->multiple
            || $widget->hasErrors()
            || null === ($tmpFolder = $this->getTempFolder($event))
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

        $uploadFolder     = $this->getUploadedFolder($event);
        $uploadedFilePath = \current($uploaded);
        $file             = new File($uploadedFilePath);
        /**
         * @psalm-suppress UndefinedMagicPropertyFetch
         * @psalm-suppress RedundantCastGivenDocblockType
         */
        $newFilePath =
            $this->moveFileToDestination($file, $uploadFolder, (bool) $widget->doNotOverwrite, $widget);

        if (!($newUploadFolder = FilesModel::findByPath($newFilePath))) {
            $newUploadFolder = Dbafs::addResource($newFilePath);
        }

        $widget->value = $newUploadFolder->uuid;
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
        $widget = $event->getWidget();
        assert($widget instanceof UploadOnSteroids);

        /** @psalm-suppress UndefinedMagicPropertyFetch */
        if (
            !$widget->multiple
            || $widget->hasErrors()
            || null === ($tmpFolder = $this->getTempFolder($event))
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
        $uploaded     = [];
        /** @var SplFileInfo $fileInfo */
        foreach ($files as $fileInfo) {
            $file = new File($fileInfo->getPathname());
            /**
             * @psalm-suppress UndefinedMagicPropertyFetch
             * @psalm-suppress RedundantCastGivenDocblockType
             */
            $newFilePath =
                $this->moveFileToDestination($file, $uploadFolder, (bool) $widget->doNotOverwrite, $widget);

            if (!($newUploadFolder = FilesModel::findByPath($newFilePath))) {
                $newUploadFolder = Dbafs::addResource($newFilePath);
            }

            if (null === ($uuId = $newUploadFolder->uuid)) {
                continue;
            }

            $uploaded[] = StringUtil::binToUuid($uuId);
        }

        $model     = $event->getModel();
        $value     = $model->getProperty($event->getProperty()->getName()) ?? [];
        $values    = \array_map('\Contao\StringUtil::binToUuid', $value);
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
        /** @var UploadOnSteroids $widget */
        $filename = $widget->parseFilename($file->getFilename());
        if (
            !$override
            || !\file_exists($this->projectDir . DIRECTORY_SEPARATOR . $uploadFolder . DIRECTORY_SEPARATOR . $filename)
        ) {
            $newFilePath = $uploadFolder . DIRECTORY_SEPARATOR . $filename;

            $this->filesystem
                ->rename($file->getPathname(), $this->projectDir . DIRECTORY_SEPARATOR . $newFilePath, true);

            return $newFilePath;
        }

        $offset = 1;

        $fileInfo   = \pathinfo($filename);
        $fileExt    = $fileInfo['extension'] ?? '';
        $allFiles   = Folder::scan($this->projectDir . DIRECTORY_SEPARATOR . $uploadFolder);
        $foundFiles = \preg_grep(
            '/^' . \preg_quote($fileInfo['filename'] ?? '', '/') . '.*\.' . \preg_quote($fileExt, '/') . '/',
            $allFiles
        );

        foreach ($foundFiles as $foundFile) {
            if (1 === \preg_match('/__(?<index>[0-9]+)\.' . \preg_quote($fileExt, '/') . '$/', $foundFile, $match)) {
                $foundFile = basename($foundFile, '.' . $fileExt);

                $offset = \max($offset, (int) $match['index']);
            }
        }

        $newFilename = ($fileInfo['filename'] ?? '') . '__' . (++$offset) . '.' . ($fileInfo['extension'] ?? '');
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
        assert($widget instanceof UploadOnSteroids);

        /** @psalm-suppress UndefinedMagicPropertyFetch */
        $uploadFolderPath = FilesModel::findByUuid($widget->uploadFolder)->path ?? '';
        /** @psalm-suppress UndefinedMagicPropertyFetch */
        if (!$widget->extendFolder) {
            /** @psalm-suppress PossiblyNullPropertyFetch */
            return $uploadFolderPath;
        }

        /** @psalm-suppress UndefinedMagicPropertyFetch */
        if ($widget->normalizeExtendFolder) {
            /**
             * @psalm-suppress UndefinedMagicPropertyAssignment
             * @psalm-suppress PossiblyNullPropertyFetch
             * @psalm-suppress UndefinedMagicPropertyFetch
             */
            $widget->extendFolder =
                $this->slugGenerator->generate((string) $widget->extendFolder, $this->getSlugOptions());
        }
        /** @psalm-suppress PossiblyNullPropertyFetch */
        $uploadFolderAbsPath = $uploadFolderPath . DIRECTORY_SEPARATOR . $widget->extendFolder;

        $newUploadFolder = null;
        if (!$this->filesystem->exists($uploadFolderAbsPath)) {
            $this->filesystem->mkdir($uploadFolderAbsPath);
            $newUploadFolder = Dbafs::addResource($uploadFolderAbsPath);
        }

        if (null === $newUploadFolder) {
            $newUploadFolder = FilesModel::findByPath($uploadFolderAbsPath);
        }

        return $newUploadFolder?->path ?? '';
    }

    /**
     * Get the temporary folder.
     *
     * @param BuildWidgetEvent $event The event.
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
        $scopeDeterminator = $this->scopeDeterminator;
        assert($scopeDeterminator instanceof RequestScopeDeterminator);

        return $scopeDeterminator->currentScopeIsFrontend()
               && !$scopeDeterminator->currentScopeIsUnknown()
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
        $submitted = $this->request->getMainRequest()?->request;
        assert($submitted instanceof InputBag);
        $dataDefinition = $event->getEnvironment()->getDataDefinition();
        assert($dataDefinition instanceof ContainerInterface);

        return $submitted->has('FORM_SUBMIT')
               && ($dataDefinition->getName() === $submitted->get('FORM_SUBMIT'));
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
        $widget = $event->getWidget();

        /**
         * @psalm-suppress UndefinedMagicPropertyFetch
         * @psalm-suppress PossiblyNullPropertyFetch
         */
        return true === $widget->dropzoneUpload;
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
        assert($inputProvider instanceof InputProviderInterface);

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
        // FIXME: Make configurable.
        return ['locale' => 'de', 'validChars' => '0-9a-z_-'];
    }
}
