<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Provide data access to web services. Analygous to Kohana's Database class.
 *
 * @author ozten
 */
class Web_Service {
    // config params for use with CURL
    protected $config;
    /**
     * Creates an instance of this class and allows overriding default config
     * @see config/webserviceclient.php for supported options
     */
    public function __construct($config=array())
    {
	$defaults = Kohana::config('webserviceclient.defaults');
	$this->config = array_merge($defaults, $config);

	$required_config_params = array('connection_timeout', 'timeout');
	foreach($required_config_params as $param) {
	    if (! array_key_exists($param, $this->config)) {
		trigger_error("Bad Config for a Web_Service instance, missing required parameter [$param]", E_USER_ERROR);
	    }
	}
    }

    /**
     * Makes a GET request for the resource and parses the response based
     * on the expected type. By default caching is disabled
     * @param string - the url for the web service including any paramters
     * @param string - the expected response type - xml, json, etc
     * @param integer - the lifetime (in seconds) of a cache item or 'default' 
     *                  to use app wide cache default, or lastly NULL to disable caching
     * @return object - the response or FALSE if there was an error
     */
    public function get($url, $response_type='json', $cache_lifetime=NULL)
    {
	if (is_null($cache_lifetime)) {
	    $data = $this->_get($url, $response_type);
	} else {
	    $cache = new Cache();
	    $cache_key = 'webservice_' . md5($url . $response_type);
	    $data = $cache->get($cache_key);
	    if ($data) {
		return $data;
	    } else {
		$data = $this->_get($url, $response_type);
		if ($data) {
		    if ($cache_lifetime == 'default') {
			$cache->set($cache_key, $data);
		    } else {
			$cache->set($cache_key, $data, NULL, $cache_lifetime);
		    }
		}
		return $data;
	    }
	}
    }

    /**
     * Makes a GET request for the resource and parses the response based
     * on the expected type
     * @param string - the url for the web service including any paramters
     * @param string - the expected response type - xml, json, etc
     * @return object - the response or FALSE if there was an error
     */
    private function _get($url, $response_type)
    {
	$curl = $this->_initCurl($url);
	$before = microtime(TRUE);
	$curl_response = curl_exec($curl);
	$after = microtime(TRUE);
	$t = $after - $before;
	if ($t > 3) {
	    Kohana::log('alert', "Web_Service " . $t . " seconds to access $url");
	}
	$headers  = curl_getinfo($curl);
	$http_status_code = $headers['http_code'];
	$code = curl_errno($curl);
	$message = curl_error($curl);
	curl_close($curl);
	Kohana::log('info', "$http_status_code $code $message");
	if ($http_status_code == 200) {
	    if ($response_type == 'json') {
		$data = json_decode($curl_response);
	    } else {
		$data = $curl_response;
	    }	    
	    return $data;
	} else {
	    // See http://curl.haxx.se/libcurl/c/libcurl-errors.html
	    Kohana::log('error', "Web_Service $code $message while retrieving $url which was HTTP status $http_status_code");
	    return FALSE;
	}
    }

    /**
     * Prepares CURL for web serivce calls
     * @param string - the url for the web service including any paramters
     * @return object - handle to the curl instance
     */
    private function _initCurl($url)
    {
	$curl = curl_init($url);
	curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->config['connection_timeout']);
	curl_setopt($curl, CURLOPT_TIMEOUT, $this->config['timeout']);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_ENCODING , "x-gzip");
	if (array_key_exists('basic_auth', $this->config) &&
	    is_array($this->config['basic_auth'])) {
	        $user = $this->config['basic_auth']['username'];
	        $pass = $this->config['basic_auth']['password'];
		curl_setopt($curl, CURLOPT_USERPWD, $user . ":" . $pass);
		Kohana::log('info', "Using $user $pass for basic auth");
	}
	return $curl;
    }

}
?>