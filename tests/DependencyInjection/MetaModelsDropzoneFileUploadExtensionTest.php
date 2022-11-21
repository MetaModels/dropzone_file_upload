<?php

/**
 * This file is part of MetaModels/attribute_file.
 *
 * (c) 2019-2022 The MetaModels team.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    MetaModels/attribute_file
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @copyright  2019-2022 The MetaModels team.
 * @license    https://github.com/MetaModels/attribute_file/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

declare(strict_types=1);

namespace MetaModels\DropzoneFileUploadBundle\Test\DependencyInjection;

use MetaModels\AttributeAliasBundle\DependencyInjection\MetaModelsAttributeAliasExtension;
use MetaModels\ContaoFrontendEditingBundle\MetaModelsContaoFrontendEditingBundle;
use MetaModels\DropzoneFileUploadBundle\DependencyInjection\MetaModelsDropzoneFileUploadExtension;
use MetaModels\DropzoneFileUploadBundle\EventListener\BuildDropzoneUploadListener;
use MetaModels\DropzoneFileUploadBundle\EventListener\DcGeneral\Table\DcaSetting\TemplateOptionListener;
use MetaModels\DropzoneFileUploadBundle\EventListener\HandleDropzoneUpload;
use MetaModels\DropzoneFileUploadBundle\Hook\InitialIzeDropzoneUpload;
use MetaModels\DropzoneFileUploadBundle\MetaModelsDropzoneFileUploadBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use function self;

/**
 * The test case for test the extension.
 *
 * @covers \MetaModels\DropzoneFileUploadBundle\DependencyInjection\MetaModelsDropzoneFileUploadExtension
 */
final class MetaModelsDropzoneFileUploadExtensionTest extends TestCase
{
    /**
     * Test that extension can be instantiated.
     *
     * @return void
     */
    public function testInstantiation(): void
    {
        $extension = new MetaModelsDropzoneFileUploadExtension();

        self::assertInstanceOf(MetaModelsDropzoneFileUploadExtension::class, $extension);
        self::assertInstanceOf(ExtensionInterface::class, $extension);
    }

    /**
     * Test that the services for frontend editing are loaded.
     *
     * @return void
     */
    public function testFrontenEditingRegistersServices(): void
    {
        $container = $this->getMockBuilder(ContainerBuilder::class)->getMock();
        $container
            ->method('getParameter')
            ->with('kernel.bundles')
            ->willReturnCallback(
                static function ($value) {
                    self::assertSame('kernel.bundles', $value);

                    return [MetaModelsDropzoneFileUploadBundle::class, MetaModelsContaoFrontendEditingBundle::class];
                }
            );
        $container
            ->expects(self::exactly(6))
            ->method('setDefinition')
            ->withConsecutive(
                [
                    TemplateOptionListener::class,
                    self::callback(
                        static function ($value) {
                            /** @var Definition $value */
                            self::assertInstanceOf(Definition::class, $value);
                            self::assertCount(1, $value->getTag('kernel.event_listener'));

                            return true;
                        }
                    )
                ],
                [
                    'mm.dropzone_file_upload.finder',
                    self::callback(
                        static function ($value) {
                            /** @var Definition $value */
                            self::assertInstanceOf(Definition::class, $value);
                            self::assertSame(Finder::class, $value->getClass());

                            return true;
                        }
                    )
                ],
                [
                    'mm.dropzone_file_upload.filesystem',
                    self::callback(
                        static function ($value) {
                            /** @var Definition $value */
                            self::assertInstanceOf(Definition::class, $value);
                            self::assertSame(Filesystem::class, $value->getClass());

                            return true;
                        }
                    )
                ],
                [
                    BuildDropzoneUploadListener::class,
                    self::callback(
                        static function ($value) {
                            /** @var Definition $value */
                            self::assertInstanceOf(Definition::class, $value);
                            self::assertCount(1, $value->getTag('kernel.event_listener'));

                            return true;
                        }
                    )
                ],
                [
                    HandleDropzoneUpload::class,
                    self::callback(
                        static function ($value) {
                            /** @var Definition $value */
                            self::assertInstanceOf(Definition::class, $value);
                            self::assertCount(1, $value->getTag('kernel.event_listener'));

                            return true;
                        }
                    )
                ],
                [
                    InitialIzeDropzoneUpload::class,
                    self::callback(
                        function ($value) {
                            /** @var Definition $value */
                            self::assertInstanceOf(Definition::class, $value);
                            self::assertCount(1, $value->getTag('contao.hook'));

                            return true;
                        }
                    )
                ]
            );

        $extension = new MetaModelsDropzoneFileUploadExtension();
        $extension->load([], $container);
    }

    /**
     * The load the extension without frontend editing.
     *
     * @return void
     */
    public function testWithoutFrontendEditing(): void
    {
        $container = $this->getMockBuilder(ContainerBuilder::class)->getMock();
        $container
            ->method('getParameter')
            ->with('kernel.bundles')
            ->willReturnCallback(
                static function ($value) {
                    self::assertSame('kernel.bundles', $value);

                    return [MetaModelsDropzoneFileUploadBundle::class];
                }
            );

        $extension = new MetaModelsDropzoneFileUploadExtension();
        $extension->load([], $container);
    }
}
