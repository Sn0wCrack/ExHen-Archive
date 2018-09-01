<?php

use Goutte\Client;
use GuzzleHttp\Client as GuzzleClient;
use Symfony\Component\BrowserKit\Response;
use GuzzleHttp\Cookie\CookieJar;

class ExClient
{
    const LOG_TAG = 'ExClient';
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
     * @var \GuzzleHttp\Middleware
     */
    private $history;

    /**
     * @var array
     */
    private $historyContainer = [];

    /**
     * ExClient constructor.
     * @param array $options
     */
    public function __construct()
    {
        $this->history = \GuzzleHttp\Middleware::history($this->historyContainer);
        $stack = \GuzzleHttp\HandlerStack::create();
        $stack->push($this->history);

        $this->guzzleDefaults = [
            'allow_redirects' => [
                'max'             => 5,
                'refer'           => true,
                'track_redirects' => true,
            ],
            'headers'         => [
                'User-Agent' => self::USER_AGENT,
            ]
        ];

        $guzzleClient = new GuzzleClient([
            'base_uri' => self::BASE_URL,
            'defaults' => $this->guzzleDefaults,
            'handler' => $stack
        ]);

        $this->client = new Client([], null, Config::buildCookieJar());
        $this->client->setClient($guzzleClient);
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

            $crawler = $this->client->request('GET', $url);

            try {
                $form = $crawler->selectButton("Download Original Archive")->form();

                $crawler = $this->client->submit($form);
            } catch (InvalidArgumentException $exception) {
                if(strpos($crawler->html(), 'Insufficient Funds') !== false) {
                    throw new InsufficientFundsException($crawler->html());
                }
            }

            return $crawler->html();
        } else {
            Log::debug("ExClient", "dlcheck bypassed already");
        }
        return "";
    }
    
    public function invalidateForm($url)
    {
        $this->post($url, ['invalidate_session' => 1]);
        self::validateResponse($this->client->getInternalResponse());
    }
    
    public function getArchiveFileSize($url)
    {
        $this->client->request('HEAD',$url);
        $this->lastResponse = $this->client->getInternalResponse();

        self::validateResponse($this->lastResponse);

        $result = "unknown";

        $content_length = $this->lastResponse->getHeader('Content-Length');
        $status = $this->lastResponse->getStatus();

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
     * @throws ExHentaiException
     */
    public function post($uri, array $formdata)
    {
        Log::debug(self::LOG_TAG, 'POST REQUEST %s', $uri);
        $this->lastResponse = $this->client->request('POST', $uri, $formdata);

        self::validateResponse($this->client->getInternalResponse());
        return $this->client->getInternalResponse()->getContent();
    }

    /**
     * @param $uri
     * @param array|null $parameters
     * @return bool|string
     * @throws BannedException
     * @throws BrowsingTooFastException
     * @throws ExHentaiException
     */
    public function get($uri, array $parameters = [])
    {
        Log::debug(self::LOG_TAG, 'GET REQUEST %s', $uri);
        $this->lastResponse = $this->client->request('GET', $uri, $parameters);

        self::validateResponse($this->client->getInternalResponse());
        return $this->client->getInternalResponse()->getContent();
    }

    private static function validateResponse(Response $response)
    {
        $statusCode = $response->getStatus();
        $reponseContent = $response->getContent();

        if (strpos($reponseContent, 'Your IP address has been temporarily banned for using automated mirroring/harvesting software and/or failing to heed the overload warning.') !== false) {
            throw new BannedException();
        }

        if (strpos($reponseContent, 'You are opening pages too fast') !== false) {
            throw new BrowsingTooFastException("Browsing to fast");
        }

        if (substr($statusCode,0,1) != 2) {
            throw new ExHentaiException("Got {$statusCode}");
        }
    }

    /**
     * @return Response
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }

    public function getRequestHistory()
    {
        return $this->historyContainer;
    }
}
