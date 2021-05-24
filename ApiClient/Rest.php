<?php

namespace Apsis\One\ApiClient;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Exception;
use stdClass;

/**
 * Rest class to make cURL requests.
 */
abstract class Rest
{
    const HTTP_CODE_CONFLICT = 409;
    const HTTP_ERROR_CODE_TO_RETRY = [500, 501, 503, 408, 429];
    const HTTP_CODES_DISABLE_MODULE = [400, 401, 403];
    const HTTP_CODES_FORCE_GENERATE_TOKEN = [401, 403];

    /**
     * http verbs
     */
    const VERB_GET = 'GET';
    const VERB_POST = 'POST';
    const VERB_PUT = 'PUT';
    const VERB_DELETE = 'DELETE';
    const VERB_PATCH = 'PATCH';

    /**
     * @var string
     */
    protected $hostName;

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
     * @var string
     */
    private $clientId;

    /**
     * @var string
     */
    private $clientSecret;

    /**
     * @var null|stdClass
     */
    protected $responseBody;

    /**
     * @var array
     */
    protected $responseInfo;

    /**
     * @var ApsisCoreHelper
     */
    protected $helper;

    /**
     * @var string
     */
    protected $curlError;

    /**
     * @var bool
     */
    protected $logResponse = false;

    /**
     * @return null|stdClass
     */
    protected function execute()
    {
        $this->responseBody = null;
        $this->responseInfo = [];
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
                    $this->curlError = __METHOD__ . ' : Current verb (' . $this->verb . ') is an invalid REST verb.';
                    curl_close($ch);
            }
        } catch (Exception $e) {
            curl_close($ch);
            $this->curlError = $e->getMessage();
            $this->helper->logError(__METHOD__, $e);
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
        $this->curlError = curl_error($ch);
        if (empty($this->curlError)) {
            $this->responseInfo = curl_getinfo($ch);
        }
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
     * @return $this
     */
    protected function buildBodyForGetAccessTokenCall()
    {
        return $this->buildBody([
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret
        ]);
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
     * @param string $token
     *
     * @return $this
     */
    public function setToken(string $token)
    {
        $this->token = $token;
        return $this;
    }

    /**
     * @param string $clientId
     * @param string $clientSecret
     *
     * @return $this
     */
    public function setClientCredentials(string $clientId, string $clientSecret)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        return $this;
    }

    /**
     * @param ApsisCoreHelper $helper
     * @param bool $logResponse
     *
     * @return $this
     */
    public function setHelper(ApsisCoreHelper $helper, bool $logResponse = false)
    {
        $this->helper = $helper;
        $this->logResponse = $logResponse;
        return $this;
    }

    /**
     * @param string $hostName
     *
     * @return $this
     */
    public function setHostName(string $hostName)
    {
        $this->hostName = $hostName;
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
     * @param mixed $response
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

        if ($this->logResponse && ! empty($this->responseInfo)) {
            $info = [
                'Request time in seconds' => $this->responseInfo['total_time'],
                'Endpoint URL' => $this->responseInfo['url'],
                'Http code' => $this->responseInfo['http_code']
            ];
            $this->helper->debug('CURL Transfer', $info);
        }

        if (isset($response->status) && isset($response->detail)) {
            if (strpos($method, '::getAccessToken') !== false) {
                // Return as it is
                return $response;
            } elseif (in_array($response->status, self::HTTP_CODES_FORCE_GENERATE_TOKEN)) {
                // Client factory will automatically generate new one. If not then will disable automatically.
                return false;
            } elseif ($response->status === self::HTTP_CODE_CONFLICT) {
                //For Profile merge request
                return self::HTTP_CODE_CONFLICT;
            }

            //Log error
            $this->helper->debug($method, (array) $response);

            //All other error response handling
            return (in_array($response->status, self::HTTP_ERROR_CODE_TO_RETRY)) ? false : (string) $response->detail;
        }

        return $response;
    }
}
