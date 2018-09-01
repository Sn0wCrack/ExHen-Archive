<?php

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Cookie\CookieJar;

class ExClient
{
    const BASE_URL = 'https://exhentai.org';
    const USER_AGENT = 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.63 Safari/537.36';

    private $ctr = 0;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var Response
     */
    private $lastResponse;

    /**
     * @var CookieJar
     */
    private $cookieJar;

    /**
     * @var array|array
     */
    private $guzzleDefaults;

    /**
     * ExClient constructor.
     * @param array $options
     */
    public function __construct()
    {
        $this->guzzleDefaults = [
            'allow_redirects' => [
                'max'             => 300,
                'refer'           => true,
                'track_redirects' => true,
            ],
            'headers'         => [
                'User-Agent' => self::USER_AGENT,
            ]
        ];

        $this->client = new Client([
            'base_uri' => self::BASE_URL,
            'defaults' => $this->guzzleDefaults
        ]);

        $this->cookieJar = Config::buildCookieJar();
    }

    public function index($search = '', $page = 0, $extraParams = array())
    {
        $params = array('page' => $page);

        if (is_array($extraParams)) {
            $params = array_merge($params, $extraParams);
        }

        if ($search) {
            $params = array_merge($params, array( //todo - move to config
                'f_doujinshi' => 1,
                'f_manga' => 1,
                'f_artistcg' => 0,
                'f_gamecg' => 0,
                'f_non-h' => 0,
                'f_search' => $search
            ));
        }

        return $this->get('/', $params);
    }

    public function tagSearch($search = '', $page = 0)
    {
        return $this->get(sprinf("%s/tag/%s/%d", self::BASE_URL, $search, $page));
    }

    public function gallery($id, $hash, $thumbPage = 0)
    {
        return $this->get(sprintf('%s/g/%d/%s/?p=%d', self::BASE_URL, $id, $hash, $thumbPage));
    }

    public function buttonPress($url)
    {
        if (strpos($this->get($url), "dlcheck") !== false) {
            $this->ctr++;
            if ($this->ctr > 4) {
                sleep(3);
                $this->ctr = 0;
            }

            return $this->post($url, array_merge([
                'dlcheck' => true,
                'dltype'  => 'org'
            ],$this->guzzleDefaults));
        } else {
            Log::debug("ExClient", "dlcheck bypassed already");
        }
        return "";
    }
    
    public function invalidateForm($url)
    {
        $this->post($url, ['invalidate_session' => 1]);
        self::validateResponse($this->lastResponse);
    }
    
    public function getArchiveFileSize($url)
    {
        $this->lastResponse = $this->client->head($url);
        self::validateResponse($this->lastResponse);

        $result = "unknown";

        $content_length = $this->lastResponse->getHeader('Content-Length');
        $status = $this->lastResponse->getStatusCode();

        if ($status == 200 || ($status > 300 && $status <= 308)) {
            $result = $content_length;
        }
        
        return $result;
    }

    /**
     * @param $uri
     * @param array $formdata
     * @return bool|string
     * @throws BannedException
     * @throws BrowsingTooFastException
     * @throws HttpResponseException
     */
    public function post($uri, array $formdata)
    {
        $params = array_merge([
            'form_params'   => $formdata,
        ], $this->guzzleDefaults);

        $this->lastResponse = $this->client->post($uri, $params);

        self::validateResponse($this->lastResponse);
        return $this->lastResponse->getBody()->getContents();
    }

    /**
     * Perform a GET request
     *
     * @param $uri
     * @param array|null $parameters
     * @return bool|string
     * @throws BannedException
     * @throws BrowsingTooFastException
     * @throws HttpResponseException
     */
    public function get($uri, array $parameters = null)
    {
        $params = array_merge([
            'query'   => $parameters,
            'cookies' => $this->cookieJar,
        ], $this->guzzleDefaults);

        $this->lastResponse = $this->client->get($uri, $params);

        self::validateResponse($this->lastResponse);
        return $this->lastResponse->getBody()->getContents();
    }

    private static function validateResponse(Response $response)
    {
        $statusCode = $response->getStatusCode();
        $reponseContent = $response->getBody()->getContents();

        if (strpos($reponseContent, 'Your IP address has been temporarily banned for using automated mirroring/harvesting software and/or failing to heed the overload warning.') !== false) {
            throw new BannedException();
        }

        if (strpos($reponseContent, 'You are opening pages too fast') !== false) {
            throw new BrowsingTooFastException("Browsing to fast");
        }

        if (substr($statusCode,0,1) != 2) {
            throw new ExHentaiException("Got {$statusCode} response code with reason: {$response->getReasonPhrase()}");
        }

        // Rewind stream so it can be read again
        $response->getBody()->rewind();
    }

    /**
     * @return Response
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }
}
