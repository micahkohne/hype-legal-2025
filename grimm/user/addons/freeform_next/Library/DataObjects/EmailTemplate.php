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

namespace Solspace\Addons\FreeformNext\Library\DataObjects;

use Solspace\Addons\FreeformNext\Library\Exceptions\DataObjects\EmailTemplateException;
use Solspace\Addons\FreeformNext\Library\Helpers\StringHelper;

class EmailTemplate
{
    const METADATA_PATTERN = "/{!--\s*__KEY__:\s*(.*)\s*--}/";

    private readonly string $name;

    private readonly string $fileName;

    private readonly string $handle;

    private readonly ?string $description;

    private readonly string|bool $templateData;

    private readonly ?string $fromEmail;

    private readonly ?string $fromName;

    private readonly ?string $replyToEmail;

    private readonly bool $includeAttachments;

    private readonly ?string $subject;

    private readonly ?string $body;

    /**
     * EmailTemplate constructor.
     *
     * @param string $filePath
     */
    public function __construct($filePath)
    {
        $this->templateData = file_get_contents($filePath);

        $this->handle   = pathinfo($filePath, PATHINFO_FILENAME);
        $this->fileName = pathinfo($filePath, PATHINFO_BASENAME);

        $name = $this->getMetadata('templateName', false);
        if (!$name) {
            $name = StringHelper::camelize(StringHelper::humanize($this->handle));
        }

        $this->name = $name;

        $this->description  = $this->getMetadata('description', false);
        $this->fromEmail    = $this->getMetadata('fromEmail', true);
        $this->fromName     = $this->getMetadata('fromName', true);
        $this->replyToEmail = $this->getMetadata('replyToEmail', false);
        $this->subject      = $this->getMetadata('subject', true);
        $this->body         = preg_replace('/{!--.*--}\n?/', '', $this->templateData);

        $includeAttachments = $this->getMetadata('includeAttachments', false);
        $includeAttachments = $includeAttachments &&
            in_array(strtolower($includeAttachments), ['true', 'yes', 'y', '1'], true);

        $this->includeAttachments = $includeAttachments;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getHandle(): string|array
    {
        return $this->handle;
    }

    /**
     * @return string
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return string
     */
    public function getFromEmail(): ?string
    {
        return $this->fromEmail;
    }

    /**
     * @return string
     */
    public function getFromName(): ?string
    {
        return $this->fromName;
    }

    /**
     * @return string
     */
    public function getReplyToEmail(): ?string
    {
        return $this->replyToEmail;
    }

    /**
     * @return bool
     */
    public function isIncludeAttachments(): bool
    {
        return $this->includeAttachments;
    }

    /**
     * @return string
     */
    public function getSubject(): ?string
    {
        return $this->subject;
    }

    /**
     * @return string
     */
    public function getBody(): string|array|null
    {
        return $this->body;
    }

    /**
     * @param string $key
     * @param bool   $required
     *
     * @return null|string
     * @throws EmailTemplateException
     */
    private function getMetadata(string $key, bool $required = false): ?string
    {
        $value   = null;
        $pattern = str_replace('__KEY__', $key, self::METADATA_PATTERN);

        if (preg_match($pattern, $this->templateData, $matches)) {
            [$_, $value] = $matches;
            $value = trim($value);
        } else if ($required) {
            throw new EmailTemplateException(
                sprintf('Email template "%s" does not contain "%s"', $this->fileName, $key)
            );
        }

        return $value;
    }
}
