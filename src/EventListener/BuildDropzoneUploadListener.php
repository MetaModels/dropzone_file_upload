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

namespace MetaModels\DropzoneFileUploadBundle\EventListener;

use ContaoCommunityAlliance\DcGeneral\Contao\RequestScopeDeterminatorAwareTrait;
use MetaModels\AttributeFileBundle\Attribute\File;
use MetaModels\DcGeneral\Events\MetaModel\BuildAttributeEvent;
use MetaModels\ViewCombination\ViewCombination;

/**
 * This event build the dropzone for upload draggable file field, in the frontend editing scope.
 */
final class BuildDropzoneUploadListener
{
    use RequestScopeDeterminatorAwareTrait;

    /**
     * The view combinations.
     *
     * @var ViewCombination
     */
    private $viewCombination;

    /**
     * The property information from the input screen.
     *
     * @var array
     */
    private $information;

    /**
     * The constructor.
     *
     * @param ViewCombination $viewCombination The view combination.
     */
    public function __construct(ViewCombination $viewCombination)
    {
        $this->viewCombination = $viewCombination;
    }

    /**
     * Build the attribute for single upload field, in the frontend editing scope.
     *
     * @param BuildAttributeEvent $event The event.
     *
     * @return void
     */
    public function __invoke(BuildAttributeEvent $event): void
    {
        if (!$this->wantToHandle($event)) {
            return;
        }

        $this->addWidgetModeInformationToProperty($event);
    }

    /**
     * Add widget mode information to the property extra information.
     *
     * @param BuildAttributeEvent $event The event.
     *
     * @return void
     */
    private function addWidgetModeInformationToProperty(BuildAttributeEvent $event): void
    {
        $property =
            $event->getContainer()->getPropertiesDefinition()->getProperty($event->getAttribute()->getColName());

        $extra = [
            'dropzoneUpload'      => true,
            'dropzoneLabel'       => $this->information['fe_widget_file_dropzone_label'] ?: null,
            'dropzoneDescription' => $this->information['fe_widget_file_dropzone_description'] ?: null,
            'uploadLimit'         => $this->information['fe_widget_file_dropzone_limit'] ?: null,
        ];

        $property->setExtra(\array_merge($property->getExtra(), $extra));
    }

    /**
     * Detect if in the right scope and the attribute is configured as single upload field.
     *
     * @param BuildAttributeEvent $event The event.
     *
     * @return bool
     */
    private function wantToHandle(BuildAttributeEvent $event): bool
    {
        return $this->scopeDeterminator->currentScopeIsFrontend()
               && !$this->scopeDeterminator->currentScopeIsUnknown()
               && $this->isDropzoneUploadField($event);
    }

    /**
     * Detect if is configured as dropzone upload field.
     *
     * @param BuildAttributeEvent $event The event.
     *
     * @return bool
     */
    private function isDropzoneUploadField(BuildAttributeEvent $event): bool
    {
        $properties = [];
        if (!(($attribute = $event->getAttribute()) instanceof File)
            || !($inputScreen = $this->viewCombination->getScreen($event->getContainer()->getName()))
            || !(isset($inputScreen['properties']) && ($properties = $inputScreen['properties']))
        ) {
            return false;
        }

        $supportedModes = [
            'fe_single_upload',
            'fe_single_upload_preview',
            'fe_multiple_upload',
            'fe_multiple_upload_preview'
        ];
        $information    = $properties[\array_flip(\array_column($properties, 'attr_id'))[$attribute->get('id')]];
        if (!isset($information['file_widgetMode'])
            || !\in_array($information['file_widgetMode'], $supportedModes, true)
        ) {
            return false;
        }

        $this->information = $information;

        return (bool) $this->information['fe_widget_file_dropzone'];
    }
}
