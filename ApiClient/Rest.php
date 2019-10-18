<?php

namespace Apsis\One\ApiClient;

use Apsis\One\Helper\Core as ApsisCoreHelper;
use Exception;
use stdClass;

/**
 * Rest class to make cURL requests.
 */
class Rest
{
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
    private $responseBody;

    /**
     * @var string|array
     */
    private $responseInfo;

    /**
     * @var ApsisCoreHelper
     */
    protected $helper;

    /**
     * @var string
     */
    protected $curlError;

    /**
     * @return null|stdClass
     */
    protected function execute()
    {
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
                    $this->helper->log('Current verb (' . $this->verb . ') is an invalid REST verb.');
                    curl_close($ch);
            }
        } catch (Exception $e) {
            curl_close($ch);
            $this->helper->logMessage(__METHOD__, $e->getMessage());
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
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        $this->doExecute($ch);
    }

    /**
     * Execute post request.
     *
     * @param mixed $ch
     */
    private function executePost($ch)
    {
        if (! is_string($this->requestBody)) {
            $this->buildPostBody();
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->requestBody);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");

        $this->doExecute($ch);
    }

    /**
     * Execute patch request.
     *
     * @param mixed $ch
     */
    private function executePatch($ch)
    {
        //@toDo patch
        $this->doExecute($ch);
    }

    /**
     * Execute put request.
     *
     * @param mixed $ch
     */
    private function executePut($ch)
    {
        //@toDo put
        $this->doExecute($ch);
    }

    /**
     * Execute delete request.
     *
     * @param mixed $ch
     */
    private function executeDelete($ch)
    {
        //@toDo delete
        $this->doExecute($ch);
    }

    /**
     * Execute request.
     *
     * @param mixed $ch
     */
    private function doExecute(&$ch)
    {
        $this->setCurlOpts($ch);
        $this->responseBody = json_decode(curl_exec($ch));
        $this->responseInfo = curl_getinfo($ch);
        $err = curl_error($ch);

        if ($err) {
            $this->helper->log('CURL ERROR ' . $err);
            $this->curlError = $err;
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
    protected function buildPostBody($data = null)
    {
        $this->requestBody = json_encode($data);
        return $this;
    }

    /**
     * Curl options.
     *
     * @param mixed $ch
     */
    private function setCurlOpts(&$ch)
    {
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, "");
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        /** @todo remove verifyhost options */
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json'
        ];
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
     * @param ApsisCoreHelper $apsisCoreHelper
     *
     * @return $this
     */
    public function setHelper(ApsisCoreHelper $apsisCoreHelper)
    {
        $this->helper = $apsisCoreHelper;
        return $this;
    }
}
