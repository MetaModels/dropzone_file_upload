<?php

/**
 * This file is part of MetaModels/attribute_file.
 *
 * (c) 2012-2019 The MetaModels team.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    MetaModels/attribute_file
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @copyright  2012-2019 The MetaModels team.
 * @license    https://github.com/MetaModels/attribute_file/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

declare(strict_types=1);

namespace MetaModels\DropzoneFileUploadBundle\DependencyInjection;

use MetaModels\ContaoFrontendEditingBundle\MetaModelsContaoFrontendEditingBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages the bundle configuration
 */
class MetaModelsDropzoneFileUploadExtension extends Extension
{
    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        // Load configuration for the frontend editing.
        if (\in_array(MetaModelsContaoFrontendEditingBundle::class, $container->getParameter('kernel.bundles'), true)) {
            $loader->load('dc-general/table/tl_metamodel_dcasetting.yml');
            $loader->load('frontend_editing/services.yml');
            $loader->load('frontend_editing/event_listener.yml');
            $loader->load('frontend_editing/contao_hook.yml');
        }
    }
}
