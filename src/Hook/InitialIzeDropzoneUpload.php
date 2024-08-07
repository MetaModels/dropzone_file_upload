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
 * @package    MetaModels/attribute_file
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @author     Ingolf Steinhardt <info@e-spin.de>
 * @copyright  2019-2024 The MetaModels team.
 * @license    https://github.com/MetaModels/dropzone_file_upload/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

declare(strict_types=1);

namespace MetaModels\DropzoneFileUploadBundle\Hook;

use Contao\System;
use ContaoCommunityAlliance\DcGeneral\ContaoFrontend\Widgets\UploadOnSteroids;
use ContaoCommunityAlliance\DcGeneral\Contao\Compatibility\DcCompat;
use Contao\Config;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\Environment;
use Contao\FileUpload;
use Contao\Folder;
use Contao\FrontendTemplate;
use Contao\Image\ResizeConfiguration;
use Contao\Message;
use Contao\RequestToken;
use Contao\Widget;
use MetaModels\DcGeneral\DataDefinition\MetaModelDataDefinition;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * This initializes the dropzone upload field.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
final class InitialIzeDropzoneUpload
{
    /**
     * The translator.
     *
     * @var TranslatorInterface
     */
    private TranslatorInterface $translator;

    /**
     * The request stack.
     *
     * @var RequestStack
     */
    private RequestStack $requestStack;

    /**
     * The finder.
     *
     * @var Finder
     */
    private Finder $finder;

    /**
     * The filesystem.
     *
     * @var Filesystem
     */
    private Filesystem $filesystem;

    /**
     * The project directory.
     *
     * @var string
     */
    private string $projectDir;

    /**
     * The constructor.
     *
     * @param TranslatorInterface $translator   The translator.
     * @param RequestStack        $requestStack The request stack.
     * @param Finder              $finder       The finder.
     * @param Filesystem          $filesystem   The filesystem.
     * @param string              $projectDir   The project directory.
     */
    public function __construct(
        TranslatorInterface $translator,
        RequestStack $requestStack,
        Finder $finder,
        Filesystem $filesystem,
        string $projectDir
    ) {
        $this->translator   = $translator;
        $this->requestStack = $requestStack;
        $this->finder       = $finder;
        $this->filesystem   = $filesystem;
        $this->projectDir   = $projectDir;
    }

    /**
     * Initialize the dropzone upload field.
     *
     * @param string $content The widget content.
     * @param Widget $widget  The widget.
     *
     * @return string
     */
    public function __invoke(string $content, Widget $widget): string
    {
        if (!$this->isDropzoneUpload($widget)) {
            return $content;
        }

        $this->handleAjaxRequest($widget);

        $this->includeDropZoneAssets();

        /** @psalm-suppress UndefinedMagicPropertyFetch */
        $dropZone = new FrontendTemplate($widget->dropzoneTemplate);
        /**
         * @psalm-suppress UndefinedMagicPropertyAssignment
         * @psalm-suppress DeprecatedClass
         */
        $dropZone->requestToken = RequestToken::get();
        /** @psalm-suppress UndefinedMagicPropertyAssignment */
        $dropZone->url = Environment::get('requestUri');
        /** @psalm-suppress UndefinedMagicPropertyAssignment */
        $dropZone->removeUrl = Environment::get('requestUri');
        /**
         * @psalm-suppress UndefinedMagicPropertyAssignment
         * @psalm-suppress UndefinedMagicPropertyFetch
         */
        $dropZone->uploadDescription = $widget->dropzoneDescription ?: '';
        /** @psalm-suppress UndefinedMagicPropertyAssignment */
        $dropZone->controlInputField = $widget->id;
        /** @psalm-suppress UndefinedMagicPropertyAssignment */
        $dropZone->dropzonePreviews = 'dropzone_previews_' . $widget->id;
        /**
         * @psalm-suppress UndefinedMagicPropertyAssignment
         * @psalm-suppress UndefinedMagicPropertyFetch
         */
        $dropZone->uploadFolder = $widget->uploadFolder;
        /** @psalm-suppress InvalidPropertyAssignmentValue */
        $dropZone->id = $widget->id;
        /** @psalm-suppress UndefinedMagicPropertyAssignment */
        $dropZone->maxFiles = $this->getUploadLimit($widget);
        /**
         * @psalm-suppress UndefinedMagicPropertyAssignment
         * @psalm-suppress UndefinedMagicPropertyFetch
         */
        $dropZone->dropzoneLabel = $widget->dropzoneLabel;
        /** @psalm-suppress UndefinedMagicPropertyAssignment */
        $dropZone->dictMaxFilesExceeded =
            $this->translator->trans(
                'maxFileUpload',
                ['%limit%' => $this->getUploadLimit($widget)],
                'metamodels_default'
            );
        /** @psalm-suppress UndefinedMagicPropertyAssignment */
        $dropZone->dictCancelUpload = $this->translator->trans('dictCancelUpload', [], 'metamodels_default');
        /** @psalm-suppress UndefinedMagicPropertyAssignment */
        $dropZone->dictRemoveFile = $this->translator->trans('dictRemoveFile', [], 'metamodels_default');
        /** @psalm-suppress UndefinedMagicPropertyAssignment */
        $dropZone->acceptedFiles = $this->getAllowedUploadTypes($widget);
        /** @psalm-suppress UndefinedMagicPropertyAssignment */
        $dropZone->uploadedFiles = $this->findUploadedFiles($widget);
        /**
         * @psalm-suppress UndefinedMagicPropertyAssignment
         * @psalm-suppress UndefinedMagicPropertyFetch
         */
        $dropZone->addRemoveLinks = $widget->addRemoveLinks;
        /**
         * @psalm-suppress UndefinedMagicPropertyAssignment
         * @psalm-suppress UndefinedMagicPropertyFetch
         */
        $dropZone->hideLabel = $widget->hideLabel;

        return \substr($content, 0, (int) \strrpos($content, '</div>')) . $dropZone->parse() . '</div>';
    }

    /**
     * Handle the ajax request.
     *
     * @param Widget $widget The widget.
     *
     * @return void
     *
     * @throws ResponseException Throw the ajax response.
     */
    private function handleAjaxRequest(Widget $widget): void
    {
        $request = $this->requestStack->getMainRequest();

        if (
            null === $request
            || ('dropZoneAjax' !== $request->request->get('action'))
            || ($widget->id !== $request->request->get('id'))
        ) {
            return;
        }

        if ($request->request->has('removeFile')) {
            $response = new JsonResponse($this->doRemoveFile($widget));

            throw new ResponseException($response);
        }

        $response = new JsonResponse($this->doUploadLoadFile($widget));

        throw new ResponseException($response);
    }

    /**
     * Handle the ajax request for the uploaded file.
     *
     * @param Widget $widget The widget.
     *
     * @return array
     */
    private function doUploadLoadFile(Widget $widget): array
    {
        /**
         * @psalm-suppress UndefinedMagicPropertyAssignment
         * @psalm-suppress UndefinedMagicPropertyFetch
         */
        $widget->extensions = $widget->extensions ?: Config::get('uploadTypes');

        $this->parseGlobalUploadFiles($widget);

        $uploadTypes = Config::get('uploadTypes');
        Config::set('uploadTypes', $widget->extensions);

        /** @psalm-suppress UndefinedMagicPropertyFetch */
        new Folder($widget->tempFolder);

        $upload = new FileUpload();
        $upload->setName($widget->name);
        /** @psalm-suppress UndefinedMagicPropertyFetch */
        $uploads = $upload->uploadTo($widget->tempFolder);

        Config::set('uploadTypes', $uploadTypes);

        $status = 409;

        if (\count($uploads) > 0) {
            $status = 200;
        }

        $message = Message::generate();
        Message::reset();

        return ['message' => $message, 'status' => $status];
    }

    /**
     * Handle the ajax request for remove file.
     *
     * @param Widget $widget The widget.
     *
     * @return array
     */
    private function doRemoveFile(Widget $widget): array
    {
        $notfound = ['message' => 'The file is not found in the temporary folder.', 'status' => 409];

        /** @psalm-suppress UndefinedMagicPropertyFetch */
        $tempFolder = $this->projectDir . DIRECTORY_SEPARATOR . $widget->tempFolder;
        try {
            $foundFiles = $this->finder->files()->in($tempFolder);
        } catch (\InvalidArgumentException $exception) {
            return  $notfound;
        }

        if (!$foundFiles->count()) {
            return $notfound;
        }

        $searchFilename = $this->requestStack->getMainRequest()?->request?->get('removeFile');
        $foundFile      = null;
        /** @var SplFileInfo $file */
        foreach ($foundFiles as $file) {
            // If the finder has one more of directories, filter the search file here.
            if ($tempFolder !== $file->getPath()) {
                continue;
            }
            if ($searchFilename !== $file->getFilename()) {
                continue;
            }

            $foundFile = $file;
        }

        if (null === $foundFile) {
            return $notfound;
        }

        $this->filesystem->remove($foundFile->getRealPath());

        return ['message' => 'The file is successful removed from the temporary upload folder', 'status' => 200];
    }

    /**
     * Parse global upload files.
     *
     * @param string $propertyName The property name.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    private function parseGlobalUploadFiles(Widget $widget): void
    {
        $_FILES[$widget->name] = \array_map(
            '1' !== $this->getUploadLimit($widget)
            ? static fn(string|int $value): array => [$value]
            : static fn(string|int $value): string|int => $value,
            $_FILES['file']
        );
    }

    /**
     * Include drop zone assets.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    private function includeDropZoneAssets()
    {
        $css        = 'assets/dropzone/css/dropzone.min.css|static';
        $javascript = 'assets/dropzone/js/dropzone.min.js|static';

        $GLOBALS['TL_CSS'][\md5($css)]               = $css;
        $GLOBALS['TL_JAVASCRIPT'][\md5($javascript)] = $javascript;
    }

    /**
     * Get the upload limit.
     *
     * @param Widget $widget The widget.
     *
     * @return string
     */
    private function getUploadLimit(Widget $widget): string
    {
        /** @psalm-suppress UndefinedMagicPropertyFetch */
        if (!$widget->multiple) {
            return '1';
        }

        /** @psalm-suppress UndefinedMagicPropertyFetch */
        return (string) $widget->uploadLimit ?: 'null';
    }

    /**
     * Get the allowed upload types.
     *
     * @param Widget $widget The widget.
     *
     * @return string
     */
    private function getAllowedUploadTypes(Widget $widget): string
    {
        /** @psalm-suppress UndefinedMagicPropertyFetch */
        $allowedExtensions = $widget->extensions ?: Config::get('uploadTypes');

        $extensions = [];
        foreach (\explode(',', $allowedExtensions) as $allowedExtension) {
            $extensions[] = '.' . $allowedExtension;
        }

        return \implode(',', $extensions);
    }

    /**
     * Find the uploaded files.
     *
     * @param Widget $widget The widget.
     *
     * @return array
     */
    private function findUploadedFiles(Widget $widget): array
    {
        $uploadedFiles = [];

        /** @psalm-suppress UndefinedMagicPropertyFetch */
        $tempFolder = $this->projectDir . DIRECTORY_SEPARATOR . $widget->tempFolder;
        try {
            $foundFiles = $this->finder->files()->in($tempFolder);
        } catch (\InvalidArgumentException $exception) {
            return  $uploadedFiles;
        }

        if (!$foundFiles->count()) {
            return $uploadedFiles;
        }

        $mimeTypeGuesser = new MimeTypes();
        /** @var SplFileInfo $file */
        foreach ($foundFiles as $file) {
            // If the finder has one more of directories, filter the search file here.
            if ($tempFolder !== $file->getPath()) {
                continue;
            }

            if (null === $type = $mimeTypeGuesser->guessMimeType($file->getRealPath())) {
                continue;
            }

            if (false === \stripos($type, 'image')) {
                $uploadedFiles[] = [
                    'mockFile' => [
                        'name' => $file->getFilename(),
                        'size' => $file->getSize()
                    ]
                ];

                continue;
            }

            $rootDir = System::getContainer()->getParameter('kernel.project_dir');

            $src             = System::getContainer()->get('contao.image.factory')
                ?->create($file->getRealPath(), [120, 120, ResizeConfiguration::MODE_CROP])
                ->getUrl($rootDir);
            $uploadedFiles[] = [
                'mockFile' => [
                    'name' => $file->getFilename(),
                    'size' => $file->getSize(),
                    'type' => $type
                ],
                'imageUrl' => $src,
            ];
        }

        return $uploadedFiles;
    }

    /**
     * Detected if is the right widget and is configured for dropzone upload.
     *
     * @param Widget $widget The widget.
     *
     * @return bool
     */
    private function isDropzoneUpload(Widget $widget): bool
    {
        /** @psalm-suppress UndefinedMagicPropertyFetch */
        return $this->isMetaModelDataDefinition($widget)
               && ($widget instanceof UploadOnSteroids)
               && $widget->dropzoneUpload
               && $widget->storeFile;
    }

    /**
     * Determine if is a Metamodel data definition.
     *
     * @param Widget $widget The widget.
     *
     * @return bool
     */
    private function isMetaModelDataDefinition(Widget $widget): bool
    {
        /** @var DcCompat $container */
        return (($container = $widget->dataContainer) instanceof DcCompat)
               && ($container->getEnvironment()->getDataDefinition() instanceof MetaModelDataDefinition);
    }
}
