<?php

/**
 * This file is part of MetaModels/dropzone_file_upload.
 *
 * (c) 2019 The MetaModels team.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    MetaModels/attribute_file
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @copyright  2019 The MetaModels team.
 * @license    https://github.com/MetaModels/dropzone_file_upload/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

declare(strict_types=1);

namespace MetaModels\DropzoneFileUploadBundle\Hook;

use Contao\Config;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\Environment;
use Contao\FileUpload;
use Contao\Folder;
use Contao\FrontendTemplate;
use Contao\Message;
use Contao\Widget;
use ContaoCommunityAlliance\DcGeneral\Contao\Compatibility\DcCompat;
use ContaoCommunityAlliance\DcGeneral\ContaoFrontend\Widgets\UploadOnSteroids;
use MetaModels\DcGeneral\DataDefinition\MetaModelDataDefinition;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * This initialize the dropzone upload field.
 */
final class InitialIzeDropzoneUpload
{
    /**
     * The translator.
     *
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * The request stack.
     *
     * @var RequestStack
     */
    private $requestStack;

    /**
     * The constructor.
     *
     * @param TranslatorInterface $translator   The translator.
     * @param RequestStack        $requestStack The request stack.
     */
    public function __construct(TranslatorInterface $translator, RequestStack $requestStack)
    {
        $this->translator   = $translator;
        $this->requestStack = $requestStack;
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

        $this->handleAjaxResponse($widget);

        $this->includeDropZoneAssets();

        $dropZone                       = new FrontendTemplate('mm_form_field_dropzone');
        $dropZone->url                  = '\'' . Environment::get('requestUri') . '\'';
        $dropZone->uploadDescription    = $widget->dropzoneDescription ?: '';
        $dropZone->controlInputField    = $widget->id;
        $dropZone->dropzonePreviews     = 'dropzone_previews_' . $widget->id;
        $dropZone->uploadFolder         = $widget->uploadFolder;
        $dropZone->id                   = $widget->id;
        $dropZone->maxFiles             = $this->getUploadLimit($widget);
        $dropZone->dropzoneLabel        = $widget->dropzoneLabel;
        $dropZone->dictMaxFilesExceeded =
            \sprintf(
                $this->translator->trans('ERR.maxFileUpload', [], 'contao_default'),
                $this->getUploadLimit($widget)
            );
        $dropZone->acceptedFiles        = $this->getAllowedUploadTypes($widget);

        return \substr($content, 0, \strrpos($content, '</div>')) . $dropZone->parse() . '</div>';
    }

    /**
     * Handle the ajax response.
     *
     * @param Widget $widget The widget.
     *
     * @throws ResponseException Throw the ajax response.
     */
    private function handleAjaxResponse(Widget $widget): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (('dropZoneAjax' !== $request->request->get('action'))
            || ($widget->id !== $request->request->get('id'))
        ) {
            return;
        }

        $response = new JsonResponse($this->prepareAjaxResponse($widget));
        //$response->headers->set('Content-Type', 'application/json');

        throw new ResponseException($response);
    }

    /**
     * Prepare the ajax response.
     *
     * @param Widget $widget The widget.
     *
     * @return array
     */
    private function prepareAjaxResponse(Widget $widget): array
    {
        $request = $this->requestStack->getCurrentRequest();

        $widget->extensions = $widget->extensions ?: Config::get('uploadTypes');
        //$widget->name       = $widget->multiple ? \substr($widget->name, 0, $widget->name - 2) : $widget->name;

        $this->parseGlobalUploadFiles($widget->name);

        $uploadTypes = Config::get('uploadTypes');
        Config::set('uploadTypes', $widget->extensions);

        $tmpFolder = 'system' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'dropzone';
        $tmpFolder .= DIRECTORY_SEPARATOR . $request->request->get('REQUEST_TOKEN');
        $tmpFolder .= DIRECTORY_SEPARATOR . $widget->id;

        new Folder($tmpFolder);

        $upload = new FileUpload();
        $upload->setName($widget->name);
        $uploads = $upload->uploadTo($tmpFolder);

        Config::set('uploadTypes', $uploadTypes);

        $status = 'error';

        if (\count($uploads) > 0) {
            $status = 'confirmed';
        }

        $message = Message::generate();
        Message::reset();

        return ['message' => $message, 'status' => $status];
    }

    /**
     * Parse global upload files.
     *
     * @param string $propertyName The property name.
     *
     * @return void
     */
    private function parseGlobalUploadFiles(string $propertyName): void
    {
        $files = array();

        foreach ($_FILES['file'] as $param => $value) {
            if (!is_array($param)) {
                $files[$param][] = $value;

                continue;
            }

            $files[$param] = $value;
        }

        $_FILES[$propertyName] = $files;
    }

    /**
     * Include drop zone assets.
     *
     * @return void
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
        if (!$widget->multiple) {
            return '1';
        }

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
        $allowedExtensions = $widget->extensions ?: Config::get('uploadTypes');

        $extensions = [];
        foreach (\explode(',', $allowedExtensions) as $allowedExtension) {
            $extensions[] = '.' . $allowedExtension;
        }

        return \implode(',', $extensions);
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
