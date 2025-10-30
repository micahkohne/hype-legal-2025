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

namespace Solspace\Addons\FreeformNext\Integrations\MailingLists;

use Override;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use Solspace\Addons\FreeformNext\Library\Exceptions\Integrations\IntegrationException;
use Solspace\Addons\FreeformNext\Library\Integrations\DataObjects\FieldObject;
use Solspace\Addons\FreeformNext\Library\Integrations\MailingLists\DataObjects\ListObject;
use Solspace\Addons\FreeformNext\Library\Integrations\MailingLists\MailingListOAuthConnector;
use Solspace\Addons\FreeformNext\Library\Integrations\TokenRefreshInterface;

class ConstantContactV3 extends MailingListOAuthConnector implements TokenRefreshInterface
{
    const TITLE = "Constant Contact (v3)";
    const LOG_CATEGORY = "ConstantContact_v3";

    /**
     * @return string
     */
    #[Override]
    public function getServiceProvider(): string
    {
        return "Constant Contact";
    }

    /**
     * @return bool
     * @throws GuzzleException
     */
    public function checkConnection()
    {
        try {
            $client = new Client();

            $endpoint = $this->getEndpoint("/contact_lists");

            $response = $client->get($endpoint, [
				"headers" => [
					"Authorization" => "Bearer " . $this->getAccessToken(),
                    "Content-Type" => "application/json"
				]
			]);
        } catch (BadResponseException $e) {
            $responseBody = $e->getResponse()->getBody();

            $errorMessage = implode(", ", [$responseBody, $e->getMessage()]);

            $this->getLogger()->error($errorMessage, self::LOG_CATEGORY);

            return false;
        }

        $status = $response->getStatusCode();
        if ($status !== 200) {
            $errorMessage = "Could not connect to ConstantContact API";

            $this->getLogger()->error($errorMessage, self::LOG_CATEGORY);

            return false;
        }

        $json = json_decode($response->getBody());

        return isset($json->lists);
    }

    /**
     * @return void
     */
    #[Override]
    public function initiateAuthentication(): never
    {
        $data = [
            "response_type" => "code",
            "client_id"     => $this->getClientId(),
            "redirect_uri"  => $this->getReturnUri(),
            "scope"         => "offline_access contact_data account_read account_update campaign_data",
            "state"         => $this->getId()
        ];

        header("Location: " . $this->getAuthorizeUrl() . "?" . http_build_query($data));
        die();
    }

    /**
     * @return bool
     * @throws IntegrationException|GuzzleException
     */
    public function refreshToken(): bool
    {
        return (bool) $this->fetchAccessToken();
    }

    /**
     * @param ListObject $mailingList
     * @param array $emails
     * @param array $mappedValues
     * @return bool
     * @throws IntegrationException|GuzzleException
     */
    public function pushEmails(ListObject $mailingList, array $emails, array $mappedValues): bool
    {
        try {
            $data = [];

            foreach ($mappedValues as $key => $value) {
                if (preg_match("/^street_address_(.*)/", $key, $matches)) {
                    if (empty($cdta["street_address"])) {
                        $data["street_address"] = [];
                    }

                    $data["street_address"][$matches[1]] = $value;
                } elseif (preg_match("/^custom_(.*)/", $key, $matches)) {
                    if (empty($data["custom_fields"])) {
                        $data["custom_fields"] = [];
                    }

                    $data["custom_fields"][] = [
                        "custom_field_id" => $matches[1],
                        "value" => $value,
                    ];
                } else {
                    $data[$key] = $value;
                }
            }

            if (isset($data["street_address"]) && empty($data["street_address"]["kind"])) {
                $data["street_address"]["kind"] = "home";
            }

            $email = reset($emails);
            $email = strtolower((string) $email);

            $data = array_merge(
                [
                    "email_address" => $email,
                    "create_source" => "Contact",
                    "list_memberships" => [$mailingList->getId()]
                ],
                $data
            );

            $client = new Client();

            $endpoint = $this->getEndpoint("/contacts/sign_up_form");

			$response = $client->post($endpoint, [
				"headers" => [
					"Authorization" => "Bearer " . $this->getAccessToken(),
					"Content-Type" => "application/json"
				],
				"body" => json_encode($data)
			]);
        } catch (BadResponseException $e) {
            $responseBody = $e->getResponse()->getBody();

            $errorMessage = implode(", ", [$responseBody, $e->getMessage()]);

            $this->getLogger()->error($errorMessage, self::LOG_CATEGORY);

            throw new IntegrationException($this->getTranslator()->translate($errorMessage));
        }

        $status = $response->getStatusCode();
        if ($status !== 200) {
            $errorMessage = "Could not add contacts to ConstantContact list";

            $this->getLogger()->error($errorMessage, self::LOG_CATEGORY);

            throw new IntegrationException($this->getTranslator()->translate($errorMessage));
        }

        return true;
    }

    /**
     * @return ListObject[]
     * @throws IntegrationException|GuzzleException
     */
    public function fetchLists(): array
    {
        try {
            $client = new Client();

            $endpoint = $this->getEndpoint("/contact_lists");

			$response = $client->get($endpoint, [
				"headers" => [
					"Authorization" => "Bearer " . $this->getAccessToken(),
                    "Content-Type" => "application/json"
				]
			]);
        } catch (BadResponseException $e) {
            $responseBody = $e->getResponse()->getBody();

            $errorMessage = implode(', ', [$responseBody, $e->getMessage()]);

            $this->getLogger()->error($errorMessage, self::LOG_CATEGORY);

            throw new IntegrationException($this->getTranslator()->translate($errorMessage));
        }

        $status = $response->getStatusCode();
        if ($status !== 200) {
            $errorMessage = "Could not fetch ConstantContact lists";

            $this->getLogger()->error($errorMessage, self::LOG_CATEGORY);

            throw new IntegrationException($this->getTranslator()->translate($errorMessage));
        }

        $json = json_decode($response->getBody());

        $lists = [];

        if (isset($json->lists)) {
            foreach ($json->lists as $list) {
                if (isset($list->list_id, $list->name)) {
                    $lists[] = new ListObject(
                        $this,
                        $list->list_id,
                        $list->name,
                        $this->fetchFields($list->list_id)
                    );
                }
            }
        }

        return $lists;
    }

    /**
     * @param string $listId
     * @return FieldObject[]
     * @throws IntegrationException|GuzzleException
     */
    public function fetchFields($listId): array
    {
        try {
            $client = new Client();

            $endpoint = $this->getEndpoint("/contact_custom_fields?limit=100");

            $response = $client->get($endpoint, [
                "headers" => [
                    "Authorization" => "Bearer " . $this->getAccessToken(),
                    "Content-Type" => "application/json"
                ]
            ]);
        } catch (BadResponseException $e) {
            $responseBody = $e->getResponse()->getBody();

            $errorMessage = implode(", ", [$responseBody, $e->getMessage()]);

            $this->getLogger()->error($errorMessage, self::LOG_CATEGORY);

            throw new IntegrationException($this->getTranslator()->translate($errorMessage));
        }

        $status = $response->getStatusCode();
        if ($status !== 200) {
            $errorMessage = "Could not fetch ConstantContact custom fields";

            $this->getLogger()->error($errorMessage, self::LOG_CATEGORY);

            throw new IntegrationException($this->getTranslator()->translate($errorMessage));
        }

        $json = json_decode($response->getBody());

        $fieldList = [];

        $fieldList[] = new FieldObject("first_name", "First Name", FieldObject::TYPE_STRING, false);
        $fieldList[] = new FieldObject("last_name", "Last Name", FieldObject::TYPE_STRING, false);
        $fieldList[] = new FieldObject("job_title", "Job Title", FieldObject::TYPE_STRING, false);
        $fieldList[] = new FieldObject("company_name", "Company Name", FieldObject::TYPE_STRING, false);
        $fieldList[] = new FieldObject("phone_number", "Phone Number", FieldObject::TYPE_STRING, false);
        $fieldList[] = new FieldObject("anniversary", "Anniversary", FieldObject::TYPE_STRING, false);
        $fieldList[] = new FieldObject("birthday_month", "Birthday Month", FieldObject::TYPE_NUMERIC, false);
        $fieldList[] = new FieldObject("birthday_day", "Birthday Day", FieldObject::TYPE_NUMERIC, false);
        $fieldList[] = new FieldObject("street_address_kind", "Address: Kind", FieldObject::TYPE_STRING, false);
        $fieldList[] = new FieldObject("street_address_street", "Address: Street", FieldObject::TYPE_STRING, false);
        $fieldList[] = new FieldObject("street_address_city", "Address: City", FieldObject::TYPE_STRING, false);
        $fieldList[] = new FieldObject("street_address_state", "Address: State", FieldObject::TYPE_STRING, false);
        $fieldList[] = new FieldObject("street_address_postal_code", "Address: Postal Code", FieldObject::TYPE_STRING, false);
        $fieldList[] = new FieldObject("street_address_country", "Address: Country", FieldObject::TYPE_STRING, false);

        if (empty($json->custom_fields)) {
            return $fieldList;
        }

        foreach ($json->custom_fields as $field) {
            $fieldList[] = new FieldObject(
                "custom_".$field->custom_field_id,
                $field->label,
                FieldObject::TYPE_STRING,
                false
            );
        }

        return $fieldList;
    }

    /**
     * @return string
     */
    public function getAuthorizeUrl(): string
    {
        return "https://authz.constantcontact.com/oauth2/default/v1/authorize";
    }

    /**
     * @return string
     */
    public function getAccessTokenUrl(): string
    {
        return "https://authz.constantcontact.com/oauth2/default/v1/token";
    }

    /**
     * @return string
     */
    public function getApiRootUrl(): string
    {
        return "https://api.cc.email/v3";
    }
}
