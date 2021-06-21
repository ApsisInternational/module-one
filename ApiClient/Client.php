<?php

namespace Apsis\One\ApiClient;

use Throwable;

class Client extends Rest
{
    const MAX_PRE_FILE_IMPORT_API_LIMIT = 3;

    /**
     * @var array
     */
    private $cacheContainer = [];

    /**
     * @var int
     */
    private $importCountInProcessingStatus;

    /**
     * Client constructor.
     */
    public function __construct()
    {
        $this->importCountInProcessingStatus = 0;
    }

    /**
     * @return int
     */
    public function getImportCountInProcessingStatus()
    {
        return $this->importCountInProcessingStatus;
    }

    /**
     * @param int $num
     *
     * @return $this
     */
    public function setImportCountInProcessingStatus(int $num)
    {
        $this->importCountInProcessingStatus = $num;
        return $this;
    }

    /**
     * @param bool $add
     *
     * @return int
     */
    public function countImportCountInProcessingStatus(bool $add = true)
    {
        if ($add) {
            $this->importCountInProcessingStatus += 1;
        } elseif ($this->importCountInProcessingStatus > 0) {
            $this->importCountInProcessingStatus -= 1;
        }

        return $this->importCountInProcessingStatus;
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    private function getFromCacheContainer(string $key)
    {
        if (strlen($key) && isset($this->cacheContainer[$key])) {
            if ((bool) getenv('APSIS_DEVELOPER')) {
                $this->helper->debug('API response from cache container.', ['URL' => $key]);
            }

            return $this->cacheContainer[$key];
        }
        return null;
    }

    /**
     * @param string $method
     * @param array $methodParams
     *
     * @return string|false
     */
    private function buildKeyForCacheContainer(string $method, array $methodParams)
    {
        return filter_var(
            implode(".", array_filter(array_merge([$method], $methodParams))),
            FILTER_SANITIZE_STRING
        );
    }

    /**
     * @param string $fromMethod
     * @param string $key
     *
     * @return mixed
     */
    private function executeRequestAndReturnResponse(string $fromMethod, string $key = '')
    {
        $response = $this->processResponse($this->execute(), $fromMethod);
        return strlen($key) ? $this->cacheContainer[$key] = $response : $response;
    }

    /**
     * SECURITY: Get access token
     *
     * Use client ID and client secret obtained when creating an API key in your APSIS One account to request an
     * OAuth 2.0 access token. Provide that token as Authorization: Bearer <access token> header when making calls to
     * other endpoints of this API.
     *
     *
     * @return mixed
     */
    public function getAccessToken()
    {
        $this->setUrl($this->hostName . '/oauth/token')
            ->setVerb(Rest::VERB_POST)
            ->buildBodyForGetAccessTokenCall();
        return $this->executeRequestAndReturnResponse(__METHOD__);
    }

    /**
     * DEFINITIONS: Get keyspaces
     *
     * Get all registered keyspaces.
     *
     * @return mixed
     */
    public function getKeySpaces()
    {
        $key = $this->buildKeyForCacheContainer(__FUNCTION__, []);
        if ($fromCache = $this->getFromCacheContainer($key)) {
            return $fromCache;
        }

        $this->setUrl($this->hostName . '/audience/keyspaces')
            ->setVerb(Rest::VERB_GET);
        return $this->executeRequestAndReturnResponse( __METHOD__, $key);
    }

    /**
     * DEFINITIONS: Get sections
     *
     * Get all sections on the APSIS One account.
     *
     * @return mixed
     */
    public function getSections()
    {
        $key = $this->buildKeyForCacheContainer(__FUNCTION__, []);
        if ($fromCache = $this->getFromCacheContainer($key)) {
            return $fromCache;
        }

        $this->setUrl($this->hostName . '/audience/sections')
            ->setVerb(Rest::VERB_GET);
        return $this->executeRequestAndReturnResponse(__METHOD__, $key);
    }

    /**
     * DEFINITIONS: Get attributes
     *
     * Gets all attributes within a specific section. Includes default and custom attributes. When any ecommerce
     * integration is connected to the specified section then also ecommerce attributes are returned.
     *
     * @param string $sectionDiscriminator
     *
     * @return mixed
     */
    public function getAttributes(string $sectionDiscriminator)
    {
        $key = $this->buildKeyForCacheContainer(__FUNCTION__, func_get_args());
        if ($fromCache = $this->getFromCacheContainer($key)) {
            return $fromCache;
        }

        $this->setUrl($this->hostName . '/audience/sections/' . $sectionDiscriminator . '/attributes')
            ->setVerb(Rest::VERB_GET);
        return $this->executeRequestAndReturnResponse(__METHOD__, $key);
    }

    /**
     * DEFINITIONS: Get consent lists
     *
     * Get all Consent lists within a specific section.
     *
     * @param string $sectionDiscriminator
     *
     * @return mixed
     */
    public function getConsentLists(string $sectionDiscriminator)
    {
        $key = $this->buildKeyForCacheContainer(__FUNCTION__, func_get_args());
        if ($fromCache = $this->getFromCacheContainer($key)) {
            return $fromCache;
        }

        $this->setUrl($this->hostName . '/audience/sections/' . $sectionDiscriminator . '/consent-lists')
            ->setVerb(Rest::VERB_GET);
        return $this->executeRequestAndReturnResponse(__METHOD__, $key);
    }

    /**
     * DEFINITIONS: Get topics
     *
     * Get all topics on a consent list
     *
     * @param string $sectionDiscriminator
     * @param string $consentListDiscriminator
     *
     * @return mixed
     */
    public function getTopics(string $sectionDiscriminator, string $consentListDiscriminator)
    {
        $key = $this->buildKeyForCacheContainer(__FUNCTION__, func_get_args());
        if ($fromCache = $this->getFromCacheContainer($key)) {
            return $fromCache;
        }

        $url = $this->hostName . '/audience/sections/' . $sectionDiscriminator . '/consent-lists/' .
            $consentListDiscriminator . '/topics';

        $this->setUrl($url)
            ->setVerb(Rest::VERB_GET);
        return $this->executeRequestAndReturnResponse(__METHOD__, $key);
    }

    /**
     * DEFINITIONS: Get events
     *
     * Get all events defined within a specific section.
     *
     * @param string $sectionDiscriminator
     *
     * @return mixed
     */
    public function getEvents(string $sectionDiscriminator)
    {
        $key = $this->buildKeyForCacheContainer(__FUNCTION__, func_get_args());
        if ($fromCache = $this->getFromCacheContainer($key)) {
            return $fromCache;
        }

        $this->setUrl($this->hostName . '/audience/sections/' . $sectionDiscriminator . '/events')
            ->setVerb(Rest::VERB_GET);
        return $this->executeRequestAndReturnResponse(__METHOD__, $key);
    }

    /**
     * PROFILES: Set attributes for a profile
     *
     * Updates profile attribute values using their version IDs as keys. Permits changes to default and custom
     * attributes. When any ecommerce integration is connected to the specified section then also ecommerce attributes
     * can be modified.
     * Content must follow JSON Merge Patch specs.
     * The maximum data payload size for requests to this endpoint is 100KB.
     *
     * @param string $keySpaceDiscriminator
     * @param string $profileKey
     * @param string $sectionDiscriminator
     * @param array $attributes
     *
     * @return mixed
     */
    public function addAttributesToProfile(
        string $keySpaceDiscriminator,
        string $profileKey,
        string $sectionDiscriminator,
        array $attributes
    ) {
        $url = $this->hostName . '/audience/keyspaces/' . $keySpaceDiscriminator . '/profiles/' . $profileKey .
            '/sections/' . $sectionDiscriminator . '/attributes';
        $this->setUrl($url)
            ->setVerb(Rest::VERB_PATCH)
            ->buildBody($attributes);
        return $this->executeRequestAndReturnResponse(__METHOD__);
    }

    /**
     *  PROFILES: Clear attribute value for a profile
     *
     * @param string $keySpaceDiscriminator
     * @param string $profileKey
     * @param string $sectionDiscriminator
     * @param string $versionId
     *
     * @return mixed
     */
    public function clearProfileAttribute(
        string $keySpaceDiscriminator,
        string $profileKey,
        string $sectionDiscriminator,
        string $versionId
    ) {
        $url = $this->hostName . '/audience/keyspaces/' . $keySpaceDiscriminator . '/profiles/' . $profileKey .
            '/sections/' . $sectionDiscriminator . '/attributes/' . $versionId;
        $this->setUrl($url)
            ->setVerb(Rest::VERB_DELETE);
        return $this->executeRequestAndReturnResponse(__METHOD__);
    }

    /**
     * PROFILES: Add events to a profile
     *
     * The maximum data payload size for requests to this endpoint is 100KB
     *
     * @param string $keySpaceDiscriminator
     * @param string $profileKey
     * @param string $sectionDiscriminator
     * @param array $events
     *
     * @return mixed
     */
    public function addEventsToProfile(
        string $keySpaceDiscriminator,
        string $profileKey,
        string $sectionDiscriminator,
        array $events
    ) {
        $url = $this->hostName . '/audience/keyspaces/' . $keySpaceDiscriminator . '/profiles/' . $profileKey .
            '/sections/' . $sectionDiscriminator . '/events';
        $this->setUrl($url)
            ->setVerb(Rest::VERB_POST)
            ->buildBody(['items' => $events]);
        return $this->executeRequestAndReturnResponse(__METHOD__);
    }

    /**
     * PROFILES: Subscribe profile to topic
     *
     * @param string $keySpaceDiscriminator
     * @param string $profileKey
     * @param string $sectionDiscriminator
     * @param string $consentListDiscriminator
     * @param string $topicDiscriminator
     *
     * @return mixed
     */
    public function subscribeProfileToTopic(
        string $keySpaceDiscriminator,
        string $profileKey,
        string $sectionDiscriminator,
        string $consentListDiscriminator,
        string $topicDiscriminator
    ) {
        $url = $this->hostName . '/audience/keyspaces/' . $keySpaceDiscriminator . '/profiles/' . $profileKey .
            '/sections/' . $sectionDiscriminator . '/subscriptions';
        $this->setUrl($url)
            ->setVerb(Rest::VERB_POST)
            ->buildBody(
                [
                    'consent_list_discriminator' => $consentListDiscriminator,
                    'topic_discriminator' => $topicDiscriminator
                ]
            );
        return $this->executeRequestAndReturnResponse(__METHOD__);
    }

    /**
     * PROFILES: Merge two profiles
     *
     * Merges two profiles designated in the body using keyspace discriminator and profile key. As a result of the
     * merge, both profile keys in both keyspaces will point to the same physical profile. Merging profiles using
     * profile keys from different keyspaces is supported. Merge is both associative and commutative so you can do
     * (a + b) + c if you need to merge more than two profiles.
     * If any of the merged profiles does not exist then it is created along the way. Also, if one of the merged
     * profiles is locked then the other profile will be locked as well if the merge succeeds.
     *
     * @param array $keySpacesToMerge
     *
     * @return mixed
     */
    public function mergeProfile(array $keySpacesToMerge)
    {
        $this->setUrl($this->hostName . '/audience/profiles/merges')
            ->setVerb(Rest::VERB_PUT)
            ->buildBody(['profiles' => $keySpacesToMerge]);
        return $this->executeRequestAndReturnResponse(__METHOD__);
    }

    /**
     * Delete a profile
     *
     * Profile will be permanently deleted along with its consents and events.
     * This operation is permanent and irreversible.
     *
     * @param string $keySpaceDiscriminator
     * @param string $profileKey
     *
     * @return mixed
     */
    public function deleteProfile(string $keySpaceDiscriminator, string $profileKey)
    {
        $url = $this->hostName . '/audience/keyspaces/' . $keySpaceDiscriminator . '/profiles/' . $profileKey;
        $this->setUrl($url)
            ->setVerb(Rest::VERB_DELETE);
        return $this->executeRequestAndReturnResponse(__METHOD__);
    }

    /**
     * CONSENTS: Create consent
     *
     * @param string $channelDiscriminator
     * @param string $address
     * @param string $sectionDiscriminator
     * @param string $consentListDiscriminator
     * @param string $topicDiscriminator
     * @param string $type
     *
     * @return mixed
     */
    public function createConsent(
        string $channelDiscriminator,
        string $address,
        string $sectionDiscriminator,
        string $consentListDiscriminator,
        string $topicDiscriminator,
        string $type
    ) {
        $url = $this->hostName . '/audience/channels/' . $channelDiscriminator . '/addresses/' . $address . '/consents';
        $body = [
            'section_discriminator' => $sectionDiscriminator,
            'type' => $type
        ];
        if (strlen($consentListDiscriminator)) {
            $body['consent_list_discriminator'] = $consentListDiscriminator;
        }
        if (strlen($topicDiscriminator)) {
            $body['topic_discriminator'] = $topicDiscriminator;
        }
        $this->setUrl($url)
            ->setVerb(Rest::VERB_POST)
            ->buildBody($body);
        return $this->executeRequestAndReturnResponse(__METHOD__);
    }

    /**
     * CONSENTS: Get opt-in consents
     *
     * Returns all opt-in consented topics for a given address.
     * Verifies whether an opt-in consent (topic-level opt-in with no opt-out on higher level) exists for given address.
     * If no opt-in consent exists for a given address then an empty list is returned.
     *
     * @param string $channelDiscriminator
     * @param string $address
     * @param string $sectionDiscriminator
     * @param string $consentListDiscriminator
     *
     * @return mixed
     */
    public function getOptInConsents(
        string $channelDiscriminator,
        string $address,
        string $sectionDiscriminator,
        string $consentListDiscriminator
    ) {
        $url = $this->hostName . '/audience/channels/' . $channelDiscriminator . '/addresses/' . $address
            . '/consents/sections/' . $sectionDiscriminator . '/consent-lists/' . $consentListDiscriminator
            . '/evaluations';
        $this->setUrl($url)
            ->setVerb(Rest::VERB_GET);
        return $this->executeRequestAndReturnResponse(__METHOD__);
    }

    /**
     * EXPORTS & IMPORTS: Import profiles - Initialize
     *
     * Initialize importing profiles into APSIS One.
     *
     * @param string $sectionDiscriminator
     * @param array $data
     *
     * @return mixed
     */
    public function initializeProfileImport(string $sectionDiscriminator, array $data)
    {
        $this->setUrl($this->hostName . '/audience/sections/' . $sectionDiscriminator . '/imports')
            ->setVerb(Rest::VERB_POST)
            ->buildBody($data);
        return $this->executeRequestAndReturnResponse(__METHOD__);
    }

    /**
     * EXPORTS & IMPORTS: Import profiles - Upload file
     *
     * Upload CSV file after initializeProfileImport
     *
     * @param string $url
     * @param array $fields
     * @param string $fileNameWithPath
     *
     * @return mixed
     */
    public function uploadFileForProfileImport(string $url, array $fields, string $fileNameWithPath)
    {
        $ch = curl_init();
        try {
            if (function_exists('curl_file_create')) {
                $fields['file'] = curl_file_create($fileNameWithPath, 'text/csv');
            } else {
                $fields['file'] = '@' . $fileNameWithPath;
            }
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_ENCODING, "");
            curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, Rest::VERB_POST);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: multipart/form-data']);

            $this->responseBody = $this->helper->unserialize(curl_exec($ch));
            $this->curlError = curl_error($ch);
            if (empty($this->curlError)) {
                $this->responseInfo = curl_getinfo($ch);
            }
            curl_close($ch);
        } catch (Throwable $e) {
            curl_close($ch);
            $this->curlError = $e->getMessage();
            $this->helper->logError(__METHOD__, $e);
        }
        return $this->processResponse($this->responseBody, __METHOD__);
    }

    /**
     * EXPORTS & IMPORTS: Get import status
     *
     * Gets a status of a profile import that has been previously requested
     *
     * @param string $sectionDiscriminator
     * @param string $importId
     *
     * @return mixed
     */
    public function getImportStatus(string $sectionDiscriminator, string $importId)
    {
        $this->setUrl($this->hostName . '/audience/sections/' . $sectionDiscriminator . '/imports/' . $importId)
            ->setVerb(Rest::VERB_GET);
        return $this->executeRequestAndReturnResponse(__METHOD__);
    }
}
