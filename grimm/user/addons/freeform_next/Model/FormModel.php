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

namespace Solspace\Addons\FreeformNext\Model;

use Stringable;
use DateTime;
use ExpressionEngine\Service\Model\Model;
use Solspace\Addons\FreeformNext\Library\Composer\Attributes\FormAttributes;
use Solspace\Addons\FreeformNext\Library\Composer\Components\Form;
use Solspace\Addons\FreeformNext\Library\Composer\Composer;
use Solspace\Addons\FreeformNext\Library\Helpers\FreeformHelper;
use Solspace\Addons\FreeformNext\Library\Session\EERequest;
use Solspace\Addons\FreeformNext\Library\Translations\EETranslator;
use Solspace\Addons\FreeformNext\Repositories\StatusRepository;
use Solspace\Addons\FreeformNext\Services\CrmService;
use Solspace\Addons\FreeformNext\Services\FieldsService;
use Solspace\Addons\FreeformNext\Services\FilesService;
use Solspace\Addons\FreeformNext\Services\FormsService;
use Solspace\Addons\FreeformNext\Services\MailerService;
use Solspace\Addons\FreeformNext\Services\MailingListsService;
use Solspace\Addons\FreeformNext\Services\SettingsService;
use Solspace\Addons\FreeformNext\Services\StatusesService;
use Solspace\Addons\FreeformNext\Services\SubmissionsService;

/**
 * @property int    $id
 * @property int    $siteId
 * @property string $name
 * @property string $handle
 * @property int    $spamBlockCount
 * @property string $description
 * @property string $layoutJson
 * @property string $returnUrl
 * @property string $defaultStatus
 * @property int    $legacyId
 */
class FormModel extends Model implements Stringable
{
    const MODEL = 'freeform_next:FormModel';
    const TABLE = 'freeform_next_forms';

    protected static $_primary_key = 'id';
    protected static $_table_name  = self::TABLE;

    protected static $_events = ['beforeInsert', 'beforeUpdate', 'beforeSave', 'beforeDelete'];

    protected $id;
    protected $siteId;
    protected $name;
    protected $handle;
    protected $spamBlockCount;
    protected $description;
    protected $layoutJson;
    protected $returnUrl;
    protected $defaultStatus;
    protected $legacyId;
    protected $dateCreated;
    protected $dateUpdated;

    private ?Composer $composer = null;

    /**
     * Creates a Form object with default settings
     *
     * @return FormModel
     */
    public static function create()
    {
        $defaultStatusId = StatusRepository::getInstance()->getDefaultStatusId();

        /** @var FormModel $form */
        $form = ee('Model')->make(
            self::MODEL,
            [
                'siteId'        => ee()->config->item('site_id'),
                'defaultStatus' => $defaultStatusId,
            ]
        );

        return $form;
    }

    /**
     * Returns the name of this calendar if toString() is invoked
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * Sets names, handles, descriptions
     * And updates the layout JSON
     *
     * @param Composer $composer
     */
    public function setLayout(Composer $composer): void
    {
        $form = $composer->getForm();
        $this->set(
            [
                'name'          => $form->getName(),
                'handle'        => $form->getHandle(),
                'description'   => $form->getDescription(),
                'defaultStatus' => $form->getDefaultStatus(),
                'returnUrl'     => $form->getReturnUrl(),
                'layoutJson'    => $composer->getComposerStateJSON(),
            ]
        );
    }

    /**
     * Assembles the composer object and returns it
     *
     * @return Composer
     */
    public function getComposer(): Composer
    {
        if (null === $this->composer) {
            $composerState  = $this->layoutJson ? json_decode($this->layoutJson, true) : null;
            $formAttributes = $this->getFormAttributes();

            $this->composer = new Composer(
                new FormsService(),              // implements FormHandlerInterface
                new FieldsService(),             // implements FieldHandlerInterface
                new SubmissionsService(),        // implements SubmissionHandlerInterface
                new MailerService(),             // implements MailHandlerInterface
                new FilesService(),              // implements FileUploadHandlerInterface
                new MailingListsService(),       // implements MailingListHandlerInterface
                new CrmService(),                // implements CRMHandlerInterface
                new StatusesService(),           // implements StatusHandlerInterface
                new EETranslator(),              // implements TranslatorInterface
                $composerState,                  // ?array $composerState
                $formAttributes,                 // ?FormAttributes $formAttributes
                null          // ?ComposerState $customComposerState
            );
        }

        return $this->composer;
    }

    public function setHandle($handle): void {
        $this->handle = $handle;
        $composer = $this->getComposer();

        $formComponent = $composer->getForm();
        $this->set(
            [
                'name'          => $formComponent->getName(),
                'handle'        => $handle,
                'description'   => $formComponent->getDescription(),
                'defaultStatus' => $formComponent->getDefaultStatus(),
                'returnUrl'     => $formComponent->getReturnUrl(),
                'layoutJson'    => $composer->getComposerStateJSON(),
            ]
        );
    }

    /**
     * @return Form
     */
    public function getForm(): Form
    {
        return $this->getComposer()->getForm();
    }

    /**
     * @param int $id
     */
    public function setLegacyId($id): void
    {
        $this->set(['legacyId' => $id]);
    }

    /**
     * @return FormAttributes
     */
    private function getFormAttributes(): FormAttributes
    {
        $sessionImplementation = (new SettingsService())->getSessionStorageImplementation();

        $attributes = new FormAttributes($this->id, $sessionImplementation, new EERequest());
        $attributes
            ->setActionUrl(null)
            ->setCsrfEnabled(false)
            ->setCsrfToken(null)
            ->setCsrfTokenName(null);

        return $attributes;
    }

    /**
     * Event beforeInsert sets the $dateCreated and $dateUpdated properties
     */
    public function onBeforeInsert(): void
    {
        $this->set(
            [
                'dateCreated' => $this->getTimestampableDate(),
                'dateUpdated' => $this->getTimestampableDate(),
            ]
        );
    }

    /**
     * Event beforeUpdate sets the $dateUpdated property
     */
    public function onBeforeUpdate(): void
    {
        $this->set(['dateUpdated' => $this->getTimestampableDate()]);
    }

    /**
     * @return DateTime
     */
    private function getTimestampableDate(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Event beforeSave validates the form
     */
    public function onBeforeSave(): void
    {
        FreeformHelper::get('validate', $this);
    }

    /**
     * Event beforeSave validates the form
     */
    public function onBeforeDelete(): void
    {
        FreeformHelper::get('validate', $this);
    }
}
