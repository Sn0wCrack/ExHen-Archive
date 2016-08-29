<?php

class Client_nHentai {
    
    const LOG_TAG = 'nClient';
	const BASE_URL = 'https://nhentai.net';

	private $ctr = 0;

	public function index($search = '', $page = 1) {
		$params = array('page' => $page);
        
		if($search) {
			$params = array_merge($params, array(
				'q' => $search
			));
		}

		$url = self::BASE_URL . "/search/" . http_build_query($params);
		return $this->exec($url);
	}

	public function gallery($id) {
        $url = sprintf('%s/g/%d/', self::BASE_URL, $id);
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 300);

		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.63 Safari/537.36');
		
		$ret = curl_exec($ch);
		curl_close($ch);
		
        /*
		if(strpos($ret, 'Your IP address has been temporarily banned for using automated mirroring/harvesting software and/or failing to heed the overload warning.') !== false) {
			printf("Banned. Waiting a minute before retrying.\n");
			sleep(60*60);
			return $this->exec($url);
		}

        if(strpos($ret, 'You are opening pages too fast') !== false) {
            printf("Warned. Waiting 10 seconds before retying.\n");
            sleep(60*10);
            return $this->exec($url);
        }
        */
        
		return $ret;
	}
	
	public function buttonPress($url) {
        if (strpos($this->exec($url), "dlcheck") !== false) {
            $this->ctr++;
            if($this->ctr > 4) {
                sleep(3);
                $this->ctr = 0;
            }
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            $post = array("dlcheck" => "true",
                          "dltype" => "org");
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_FAILONERROR, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.63 Safari/537.36');
            
            $ret = curl_exec($ch);
            curl_close($ch);
            
            return $ret;
        } else {
            Log::debug("ExClient", "dlcheck bypassed already");
        }
        return "";
	}
    
}

?>
