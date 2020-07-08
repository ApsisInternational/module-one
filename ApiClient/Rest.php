<?php

namespace Apsis\One\ApiClient;

use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Exception;
use stdClass;

/**
 * Rest class to make cURL requests.
 */
class Rest
{
    const HTTP_CODE_CONFLICT = 409;

    /**
     * http verbs
     */
    const VERB_GET = 'GET';
    const VERB_POST = 'POST';
    const VERB_PUT = 'PUT';
    const VERB_DELETE = 'DELETE';
    const VERB_PATCH = 'PATCH';

    /**
     * @var array
     */
    private $errorCodesToRetry = [500, 501, 503, 408, 429];

    /**
     * @var string
     */
    private $url;

    /**
     * @var string
     */
    private $verb;

    /**
     * @var string
     */
    private $requestBody;

    /**
     * @var string
     */
    private $token;

    /**
     * @var null|stdClass
     */
    protected $responseBody;

    /**
     * @var string|array
     */
    protected $responseInfo;

    /**
     * @var ApsisLogHelper
     */
    protected $helper;

    /**
     * @var string
     */
    protected $curlError;

    /**
     * Rest constructor.
     *
     * @param ApsisLogHelper $helper
     */
    public function __construct(ApsisLogHelper $helper)
    {
        $this->helper = $helper;
    }

    /**
     * @return null|stdClass
     */
    protected function execute()
    {
        $this->responseBody = null;
        $this->responseInfo = null;
        $this->curlError = '';
        $ch = curl_init();
        try {
            switch (strtoupper($this->verb)) {
                case self::VERB_GET:
                    $this->executeGet($ch);
                    break;
                case self::VERB_POST:
                    $this->executePost($ch);
                    break;
                case self::VERB_PATCH:
                    $this->executePatch($ch);
                    break;
                case self::VERB_PUT:
                    $this->executePut($ch);
                    break;
                case self::VERB_DELETE:
                    $this->executeDelete($ch);
                    break;
                default:
                    $this->helper->debug(__METHOD__ . ' : Current verb (' . $this->verb . ') is an invalid REST verb.');
                    curl_close($ch);
            }
        } catch (Exception $e) {
            curl_close($ch);
            $this->helper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
        return $this->responseBody;
    }

    /**
     * Execute curl get request.
     *
     * @param mixed $ch
     */
    private function executeGet($ch)
    {
        $headers = [
            'Accept: application/json'
        ];
        $this->doExecute($ch, $headers);
    }

    /**
     * @param mixed $ch
     * @param array $headers
     */
    private function executePostPutPatch($ch, array $headers)
    {
        if (! is_string($this->requestBody)) {
            $this->buildBody();
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->requestBody);
        $this->doExecute($ch, $headers);
    }

    /**
     * Execute post request.
     *
     * @param mixed $ch
     */
    private function executePost($ch)
    {
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json'
        ];
        $this->executePostPutPatch($ch, $headers);
    }

    /**
     * Execute patch request.
     *
     * @param mixed $ch
     */
    private function executePatch($ch)
    {
        $headers = [
            'Accept: application/problem+json',
            'Content-Type: application/merge-patch+json'
        ];
        $this->executePostPutPatch($ch, $headers);
    }

    /**
     * Execute put request.
     *
     * @param mixed $ch
     */
    private function executePut($ch)
    {
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json'
        ];
        $this->executePostPutPatch($ch, $headers);
    }

    /**
     * Execute delete request.
     *
     * @param mixed $ch
     */
    private function executeDelete($ch)
    {
        $headers = [
            'Accept: application/problem+json'
        ];
        $this->doExecute($ch, $headers);
    }

    /**
     * Execute request.
     *
     * @param mixed $ch
     * @param array headers
     */
    private function doExecute(&$ch, array $headers)
    {
        $this->setCurlOpts($ch, $headers);
        $this->responseBody = $this->helper->unserialize(curl_exec($ch));
        $this->responseInfo = curl_getinfo($ch);
        $this->curlError = curl_error($ch);
        curl_close($ch);
    }

    /**
     * Post data.
     *
     * @param null $data
     *
     * @return $this
     */
    protected function buildBody($data = null)
    {
        $this->requestBody = $this->helper->serialize($data);
        return $this;
    }

    /**
     * Curl options.
     *
     * @param mixed $ch
     * @param array $headers
     */
    private function setCurlOpts(&$ch, array $headers)
    {
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, "");
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->verb);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        if (isset($this->token)) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    /**
     * Get response info.
     *
     * @return string|array
     */
    protected function getResponseInfo()
    {
        return $this->responseInfo;
    }

    /**
     * @param string $token
     *
     * @return $this
     */
    public function setToken($token)
    {
        $this->token = $token;
        return $this;
    }

    /**
     * Set url.
     *
     * @param string $url
     *
     * @return $this
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Set the verb.
     *
     * @param string $verb
     *
     * @return $this
     */
    public function setVerb($verb)
    {
        $this->verb = $verb;
        return $this;
    }

    /**
     * @param null|stdClass $response
     * @param string $method
     *
     * @return mixed
     */
    protected function processResponse($response, string $method)
    {
        if (strlen($this->curlError)) {
            $this->helper->log(__METHOD__ . ': CURL ERROR: ' . $this->curlError);
            return false;
        }

        if (isset($response->status) && (int) $response->status === self::HTTP_CODE_CONFLICT) {
            return self::HTTP_CODE_CONFLICT;
        }

        if (isset($response->status) && isset($response->detail)) {
            $this->helper->debug($method, (array) $response);
            return (in_array($response->status, $this->errorCodesToRetry)) ? false : (string) $response->detail;
        }

        return $response;
    }
}
