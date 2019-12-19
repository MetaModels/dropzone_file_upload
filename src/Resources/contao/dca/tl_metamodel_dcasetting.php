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

// Load configuration for the frontend editing.
use Contao\System;
use MetaModels\ContaoFrontendEditingBundle\MetaModelsContaoFrontendEditingBundle;

if (\in_array(MetaModelsContaoFrontendEditingBundle::class, System::getContainer()->getParameter('kernel.bundles'), true)) {
    $paletteProperties = [
        'fe_widget_file_dropzone',
        'fe_widget_file_dropzone_removable',
        'fe_widget_file_dropzone_limit',
        'fe_widget_file_dropzone_label',
        'fe_widget_file_dropzone_hide_label',
        'fe_widget_file_dropzone_description'
    ];

    $GLOBALS['TL_DCA']['tl_metamodel_dcasetting']['metasubselectpalettes']['file_widgetMode']['fe_single_upload']['dropzone_settings']           =
        $paletteProperties;
    $GLOBALS['TL_DCA']['tl_metamodel_dcasetting']['metasubselectpalettes']['file_widgetMode']['fe_single_upload_preview']['dropzone_settings']   =
        $paletteProperties;
    $GLOBALS['TL_DCA']['tl_metamodel_dcasetting']['metasubselectpalettes']['file_widgetMode']['fe_multiple_upload']['dropzone_settings']         =
        $paletteProperties;
    $GLOBALS['TL_DCA']['tl_metamodel_dcasetting']['metasubselectpalettes']['file_widgetMode']['fe_multiple_upload_preview']['dropzone_settings'] =
        $paletteProperties;

    unset($paletteProperties);

    $GLOBALS['TL_DCA']['tl_metamodel_dcasetting']['fields']['fe_widget_file_dropzone'] = [
        'label'     => &$GLOBALS['TL_LANG']['tl_metamodel_dcasetting']['fe_widget_file_dropzone'],
        'exclude'   => true,
        'inputType' => 'checkbox',
        'eval'      => [
            'tl_class'       => 'w50 m12'
        ],
        'sql'       => "char(1) NOT NULL default ''",
    ];

    $GLOBALS['TL_DCA']['tl_metamodel_dcasetting']['fields']['fe_widget_file_dropzone_limit'] = [
        'label'     => &$GLOBALS['TL_LANG']['tl_metamodel_dcasetting']['fe_widget_file_dropzone_limit'],
        'exclude'   => true,
        'inputType' => 'text',
        'eval'      => [
            'maxlength'     => 6,
            'tl_class'      => 'w50 clr'
        ],
        'sql'       => "varchar(6) NOT NULL default ''"
    ];

    $GLOBALS['TL_DCA']['tl_metamodel_dcasetting']['fields']['fe_widget_file_dropzone_label'] = [
        'label'     => &$GLOBALS['TL_LANG']['tl_metamodel_dcasetting']['fe_widget_file_dropzone_label'],
        'exclude'   => true,
        'inputType' => 'text',
        'eval'      => [
            'maxlength'     => 255,
            'tl_class'      => 'w50 clr'
        ],
        'sql'       => "varchar(255) NOT NULL default ''"
    ];

    $GLOBALS['TL_DCA']['tl_metamodel_dcasetting']['fields']['fe_widget_file_dropzone_hide_label'] = [
        'label'     => &$GLOBALS['TL_LANG']['tl_metamodel_dcasetting']['fe_widget_file_dropzone_hide_label'],
        'exclude'   => true,
        'inputType' => 'checkbox',
        'eval'      => [
            'tl_class'       => 'w50 m12'
        ],
        'sql'       => "char(1) NOT NULL default ''",
    ];

    $GLOBALS['TL_DCA']['tl_metamodel_dcasetting']['fields']['fe_widget_file_dropzone_description'] = [
        'label'     => &$GLOBALS['TL_LANG']['tl_metamodel_dcasetting']['fe_widget_file_dropzone_description'],
        'exclude'   => true,
        'inputType' => 'text',
        'eval'      => [
            'maxlength'     => 255,
            'tl_class'      => 'w50'
        ],
        'sql'       => "varchar(255) NOT NULL default ''"
    ];

    $GLOBALS['TL_DCA']['tl_metamodel_dcasetting']['fields']['fe_widget_file_dropzone_removable'] = [
        'label'     => &$GLOBALS['TL_LANG']['tl_metamodel_dcasetting']['fe_widget_file_dropzone_removable'],
        'exclude'   => true,
        'inputType' => 'checkbox',
        'eval'      => [
            'tl_class'       => 'w50 m12'
        ],
        'sql'       => "char(1) NOT NULL default ''",
    ];
}
