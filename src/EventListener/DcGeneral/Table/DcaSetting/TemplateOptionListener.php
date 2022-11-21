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

namespace MetaModels\DropzoneFileUploadBundle\EventListener\DcGeneral\Table\DcaSetting;

use ContaoCommunityAlliance\DcGeneral\Contao\RequestScopeDeterminator;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\Event\GetPropertyOptionsEvent;
use Doctrine\DBAL\Connection;
use MetaModels\BackendIntegration\TemplateList;
use MetaModels\CoreBundle\EventListener\DcGeneral\Table\DcaSetting\AbstractListener;
use MetaModels\IFactory;

/**
 * This handles the providing of available templates.
 */
final class TemplateOptionListener extends AbstractListener
{
    /**
     * The template list provider.
     *
     * @var TemplateList
     */
    private $templateList;

    /**
     * Create a new instance.
     *
     * @param RequestScopeDeterminator $scopeDeterminator The scope determinator.
     * @param IFactory                 $factory           The MetaModel factory.
     * @param Connection               $connection        The database connection.
     * @param TemplateList             $templateList      The template list provider.
     */
    public function __construct(
        RequestScopeDeterminator $scopeDeterminator,
        IFactory $factory,
        Connection $connection,
        TemplateList $templateList
    ) {
        parent::__construct($scopeDeterminator, $factory, $connection);
        $this->templateList = $templateList;
    }

    /**
     * Retrieve the options for the property.
     *
     * @param GetPropertyOptionsEvent $event The event.
     *
     * @return void
     */
    public function __invoke(GetPropertyOptionsEvent $event): void
    {
        if (!$this->wantToHandle($event) || ($event->getPropertyName() !== 'fe_widget_file_dropzone_template')) {
            return;
        }

        $event->setOptions($this->templateList->getTemplatesForBase('mm_form_field_dropzone'));
    }
}
