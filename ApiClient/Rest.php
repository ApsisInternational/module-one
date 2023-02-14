<?php

namespace Apsis\One\ApiClient;

use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use CurlHandle;
use Throwable;

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
    protected string $hostName;

    /**
     * @var string
     */
    private string $url;

    /**
     * @var string
     */
    private string $verb;

    /**
     * @var string|bool
     */
    private string|bool $requestBody;

    /**
     * @var string
     */
    private string $token;

    /**
     * @var string
     */
    private string $clientId;

    /**
     * @var string
     */
    private string $clientSecret;

    /**
     * @var mixed
     */
    protected mixed $responseBody;

    /**
     * @var array
     */
    protected array $responseInfo;

    /**
     * @var ApsisCoreHelper
     */
    protected ApsisCoreHelper $helper;

    /**
     * @var string
     */
    protected string $curlError;

    /**
     * @return mixed
     */
    protected function execute(): mixed
    {
        $this->responseBody = null;
        $this->responseInfo = [];
        $this->curlError = '';
        $ch = curl_init();

        if (! $ch instanceof CurlHandle) {
            return $this->responseBody;
        }

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
        } catch (Throwable $e) {
            curl_close($ch);
            $this->curlError = $e->getMessage();
            $this->helper->logError(__METHOD__, $e);
        }
        return $this->responseBody;
    }

    /**
     * Execute curl get request.
     *
     * @param CurlHandle $ch
     *
     * @return void
     */
    private function executeGet(CurlHandle $ch): void
    {
        $headers = [
            'Accept: application/json'
        ];
        $this->doExecute($ch, $headers);
    }

    /**
     * Execute post/put/patch
     *
     * @param CurlHandle $ch
     * @param array $headers
     *
     * @return void
     */
    private function executePostPutPatch(CurlHandle $ch, array $headers): void
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
     * @param CurlHandle $ch
     *
     * @return void
     */
    private function executePost(CurlHandle $ch): void
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
     * @param CurlHandle $ch
     *
     * @param void
     */
    private function executePatch(CurlHandle $ch): void
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
     * @param CurlHandle $ch
     *
     * @param void
     */
    private function executePut(CurlHandle $ch): void
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
     * @param CurlHandle $ch
     *
     * @return void
     */
    private function executeDelete(CurlHandle $ch): void
    {
        $headers = [
            'Accept: application/problem+json'
        ];
        $this->doExecute($ch, $headers);
    }

    /**
     * Execute request.
     *
     * @param CurlHandle $ch
     * @param array headers
     *
     * @return void
     */
    private function doExecute(CurlHandle $ch, array $headers): void
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
    protected function buildBody($data = null): static
    {
        $this->requestBody = $this->helper->serialize($data);
        return $this;
    }

    /**
     * @return $this
     */
    protected function buildBodyForGetAccessTokenCall(): static
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
     * @param CurlHandle $ch
     * @param array $headers
     *
     * @return void
     */
    private function setCurlOpts(CurlHandle $ch, array $headers): void
    {
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, "");
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->verb);
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
    public function setToken(string $token): static
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
    public function setClientCredentials(string $clientId, string $clientSecret): static
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        return $this;
    }

    /**
     * @param ApsisCoreHelper $helper
     *
     * @return $this
     */
    public function setHelper(ApsisCoreHelper $helper): static
    {
        $this->helper = $helper;
        return $this;
    }

    /**
     * @param string $hostName
     *
     * @return $this
     */
    public function setHostName(string $hostName): static
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
    public function setUrl(string $url): static
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
    public function setVerb(string $verb): static
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
    protected function processResponse(mixed $response, string $method): mixed
    {
        if (strlen($this->curlError)) {
            $this->helper->log(__METHOD__ . ': CURL ERROR: ' . $this->curlError);
            return false;
        }

        if ((bool) getenv('APSIS_DEVELOPER') && ! empty($this->responseInfo)) {
            $info = [
                'Method' => $method,
                'Request time in seconds' => $this->responseInfo['total_time'],
                'Endpoint URL' => $this->responseInfo['url'],
                'Http code' => $this->responseInfo['http_code']
            ];
            $this->helper->debug('CURL Transfer', $info);
        }

        if (isset($response->status) && isset($response->detail)) {
            if (str_contains($method, '::getAccessToken')) {
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
