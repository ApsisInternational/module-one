<?php

namespace Apsis\One\ApiClient;

class Client extends Rest
{
    /**
     * @var array
     */
    private array $cacheContainer = [];

    /**
     * @param string $key
     *
     * @return mixed
     */
    private function getFromCacheContainer(string $key): mixed
    {
        if (strlen($key) && isset($this->cacheContainer[$key])) {
            if ((bool) getenv('APSIS_DEVELOPER')) {
                $this->helper->debug('API response from cache container.', ['API_METHOD.PARAMS' => $key]);
            }

            return $this->cacheContainer[$key];
        }
        return null;
    }

    /**
     * @param string $method
     * @param array $methodParams
     *
     * @return string
     */
    private function buildKeyForCacheContainer(string $method, array $methodParams): string
    {
        return (string) preg_replace(
            '/[^a-z0-9_\-]/',
            '',
            implode(".", array_filter(array_merge([$method], $methodParams)))
        );
    }

    /**
     * @param string $fromMethod
     * @param string $key
     *
     * @return mixed
     */
    private function executeRequestAndReturnResponse(string $fromMethod, string $key = ''): mixed
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
    public function getAccessToken(): mixed
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
    public function getKeySpaces(): mixed
    {
        $key = $this->buildKeyForCacheContainer(__FUNCTION__, []);
        if ($fromCache = $this->getFromCacheContainer($key)) {
            return $fromCache;
        }

        $this->setUrl($this->hostName . '/audience/keyspaces')
            ->setVerb(Rest::VERB_GET);
        return $this->executeRequestAndReturnResponse(__METHOD__, $key);
    }

    /**
     * DEFINITIONS: Get sections
     *
     * Get all sections on the APSIS One account.
     *
     * @return mixed
     */
    public function getSections(): mixed
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
    public function getAttributes(string $sectionDiscriminator): mixed
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
     * DEFINITIONS: Get topics
     *
     * Get all topics on a consent list
     *
     * @param string $sectionDiscriminator
     *
     * @return mixed
     */
    public function getTopics(string $sectionDiscriminator): mixed
    {
        $key = $this->buildKeyForCacheContainer(__FUNCTION__, func_get_args());
        if ($fromCache = $this->getFromCacheContainer($key)) {
            return $fromCache;
        }

        $url = $this->hostName . '/v2/audience/sections/' . $sectionDiscriminator . '/topics';

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
    public function getEvents(string $sectionDiscriminator): mixed
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
    ): mixed {
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
    ): mixed {
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
    ): mixed {
        $url = $this->hostName . '/audience/keyspaces/' . $keySpaceDiscriminator . '/profiles/' . $profileKey .
            '/sections/' . $sectionDiscriminator . '/events';
        $this->setUrl($url)
            ->setVerb(Rest::VERB_POST)
            ->buildBody(['items' => $events]);
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
    public function mergeProfile(array $keySpacesToMerge): mixed
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
    public function deleteProfile(string $keySpaceDiscriminator, string $profileKey): mixed
    {
        $url = $this->hostName . '/audience/keyspaces/' . $keySpaceDiscriminator . '/profiles/' . $profileKey;
        $this->setUrl($url)
            ->setVerb(Rest::VERB_DELETE);
        return $this->executeRequestAndReturnResponse(__METHOD__);
    }

    /**
     * CONSENTS: Create consent
     *
     * @param string $keyspaceDiscriminator
     * @param string $profileKey
     * @param string $sectionDiscriminator
     * @param string $topicDiscriminator
     * @param string $channelDiscriminator
     * @param string $type
     *
     * @return mixed
     */
    public function createConsent(
        string $keyspaceDiscriminator,
        string $profileKey,
        string $sectionDiscriminator,
        string $topicDiscriminator,
        string $channelDiscriminator,
        string $type
    ): mixed {
        $url = $this->hostName . '/v2/audience/keyspaces/' . $keyspaceDiscriminator . '/profiles/' . $profileKey .
            '/sections/' . $sectionDiscriminator . '/consents';
        $body = [
            'topic_discriminator' => $topicDiscriminator,
            'channel_discriminator' => $channelDiscriminator,
            'type' => $type,
            'reason' => 'User provided consent.',
            'source' => 'Magento connector'
        ];
        $this->setUrl($url)
            ->setVerb(Rest::VERB_POST)
            ->buildBody($body);
        return $this->executeRequestAndReturnResponse(__METHOD__);
    }

    /**
     * CONSENTS: Get consents
     *
     * Get all or selected types of consents for different topics over various channels currently existing for a
     * specific profile.
     * Profile is identified by keyspace discriminator and profile key. Topic is identified by section discriminator and
     * topic discriminator.
     *
     * @param string $keyspaceDiscriminator
     * @param string $profileKey
     * @param string $sectionDiscriminator
     *
     * @return mixed
     */
    public function getConsents(
        string $keyspaceDiscriminator,
        string $profileKey,
        string $sectionDiscriminator
    ): mixed {
        $url = $this->hostName . '/v2/audience/keyspaces/' . $keyspaceDiscriminator . '/profiles/' . $profileKey .
            '/sections/' . $sectionDiscriminator . '/consents?consent_types=opt-in&consent_types=confirmed-opt-in';
        $this->setUrl($url)
            ->setVerb(Rest::VERB_GET);
        return $this->executeRequestAndReturnResponse(__METHOD__);
    }
}
