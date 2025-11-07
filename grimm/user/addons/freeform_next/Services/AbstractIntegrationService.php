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

namespace Solspace\Addons\FreeformNext\Services;

use Psr\Http\Message\ResponseInterface;
use Solspace\Addons\FreeformNext\Library\Integrations\AbstractIntegration;
use Solspace\Addons\FreeformNext\Model\IntegrationModel;

abstract class AbstractIntegrationService
{
    public const EVENT_BEFORE_SAVE = 'beforeSave';
    public const EVENT_AFTER_SAVE = 'afterSave';
    public const EVENT_BEFORE_DELETE = 'beforeDelete';
    public const EVENT_AFTER_DELETE = 'afterDelete';
    public const EVENT_FETCH_TYPES = 'fetchTypes';
    public const EVENT_BEFORE_PUSH = 'beforePush';
    public const EVENT_AFTER_PUSH = 'afterPush';
    public const EVENT_AFTER_RESPONSE = 'afterResponse';



    /**
     * {@inheritDoc}
     */
    public function onAfterResponse(AbstractIntegration $integration, ResponseInterface $response): void
    {
    }

    /**
     * Perform necessary actions after the integration has been saved.
     */
    protected function afterSaveHandler(IntegrationModel $model)
    {
    }
}
