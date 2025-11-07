<?php
/**
 * Freeform for ExpressionEngine
 *
 * @package       Solspace:Freeform
 * @author        Solspace, Inc.
 * @copyright     Copyright (c) 2008-2025, Solspace, Inc.
 * @link          https://docs.solspace.com/expressionengine/freeform/v3/
 * @license       https://docs.solspace.com/license-agreement/
 */

namespace Solspace\Addons\FreeformNext\Library\Database;

use Solspace\Addons\FreeformNext\Library\Composer\Components\Layout;
use Solspace\Addons\FreeformNext\Library\Composer\Components\Properties\IntegrationProperties;
use Solspace\Addons\FreeformNext\Library\Exceptions\Integrations\CRMIntegrationNotFoundException;
use Solspace\Addons\FreeformNext\Library\Integrations\CRM\AbstractCRMIntegration;
use Solspace\Addons\FreeformNext\Library\Integrations\DataObjects\FieldObject;

interface CRMHandlerInterface extends IntegrationHandlerInterface
{
    /**
     * @return AbstractCRMIntegration[]
     */
    public function getAllIntegrations();

    /**
     * @param int $id
     *
     * @return AbstractCRMIntegration|null
     * @throws CRMIntegrationNotFoundException
     */
    public function getIntegrationById($id);

    /**
     * Updates the fields of a given CRM integration
     *
     * @param FieldObject[]          $fields
     * @return bool
     */
    public function updateFields(AbstractCRMIntegration $integration, array $fields);

    /**
     * Returns all FieldObjects of a particular CRM integration
     *
     *
     * @return FieldObject[]
     */
    public function getFields(AbstractCRMIntegration $integration);

    /**
     * Flag the given CRM integration so that it's updated the next time it's accessed
     */
    public function flagIntegrationForUpdating(AbstractCRMIntegration $integration);

    /**
     * Push the mapped object values to the CRM
     *
     *
     * @return bool
     */
    public function pushObject(IntegrationProperties $properties, Layout $layout);
}
