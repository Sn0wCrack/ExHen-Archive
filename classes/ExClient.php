<?php

class ExClient {

	const BASE_URL = 'http://exhentai.org/';

	private $ctr = 0;

	public function index($search = '', $page = 0, $extraParams = array()) {
		$params = array('page' => $page);

		if(is_array($extraParams)) {
			$params = array_merge($params, $extraParams);
		}

		if($search) {
			$params = array_merge($params, array( //todo - move to config
				'f_doujinshi' => 1,
				'f_manga' => 1,
				'f_artistcg' => 0,
				'f_gamecg' => 0,
				'f_non-h' => 0,
				'f_search' => $search
			));
		}

		$url = self::BASE_URL.'?'.http_build_query($params);
		return $this->exec($url);
	}

	public function gallery($id, $hash, $thumbPage = 0) {
		$url = sprintf('%s/g/%d/%s/?p=%d', self::BASE_URL, $id, $hash, $thumbPage);
		return $this->exec($url);
	}

	public function exec($url) {
		$this->ctr++;
		if($this->ctr > 4) {
			sleep(3);
			$this->ctr = 0;
		}

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);

        $cookie = Config::buildCookie();
		curl_setopt($ch, CURLOPT_COOKIE, $cookie);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.63 Safari/537.36');
		
		$ret = curl_exec($ch);
		curl_close($ch);
		
		if(strpos($ret, 'Your IP address has been temporarily banned for using automated mirroring/harvesting software and/or failing to heed the overload warning.') !== false) {
			printf("Banned.\n");
			sleep(60*60);
			return $this->exec($url);
		}

        if(strpos($ret, 'You are opening pages too fast') !== false) {
            printf("Warned.\n");
            sleep(60*10);
            return $this->exec($url);
        }

		return $ret;
	}
}

?>
