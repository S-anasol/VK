<?php

/**
 * The PHP class for vk.com API and to support OAuth.
 * @author Sanasol <mail@sanasol.ws>
 * @author Vlad Pronsky <vladkens@yandex.ru>
 * @license https://raw.github.com/vladkens/VK/master/LICENSE MIT
 */

namespace VK;

class VK
{
    /**
     * VK application id.
     * @var string
     */
    private $app_id;

    /**
     * VK application secret key.
     * @var string
     */
    private $api_secret;

    /**
     * API version. If null uses latest version.
     * @var int
     */
    private $api_version;

    /**
     * VK access token.
     * @var string
     */
    private $access_token;

    /**
     * Authorization status.
     * @var bool
     */
    private $auth = false;

    /**
     * HTTP Proxy.
     * Format: 127.0.0.1:8080
     * @var string
     */
    private $proxy = false;

    /**
     * API Response timeout in seconds
     * @var integer
     */
    private $timeout = 5;

    /**
     * Antigate API key
     * @var integer
     */
    private $antigate_key = false;

    /**
     * Temporary captcha image save path
     * @var string
     */
    private $captcha_path = '/tmp';

    /**
     * Instance curl.
     * @var Resource
     */
    private $ch;

    const AUTHORIZE_URL = 'https://oauth.vk.com/authorize';
    const ACCESS_TOKEN_URL = 'https://oauth.vk.com/access_token';

    /**
     * Constructor.
     * @param   string $app_id
     * @param   string $api_secret
     * @param   string $access_token
     * @throws  VKException
     */
    public function __construct($app_id, $api_secret, $access_token = null)
    {
        $this->app_id = $app_id;
        $this->api_secret = $api_secret;
        $this->setAccessToken($access_token);

        $this->ch = curl_init();
    }

    /**
     * Destructor.
     */
    public function __destruct()
    {
        curl_close($this->ch);
    }

    /**
     * Set special API version.
     * @param   int $version
     * @return  void
     */
    public function setApiVersion($version)
    {
        $this->api_version = $version;
    }

    /**
     * Set HTTP proxy.
     * @param   string $proxy
     * @return  void
     */
    public function setProxy($proxy)
    {
        $this->proxy = $proxy;
    }

    /**
     * Set API Response timeout.
     * @param   int $seconds
     * @return  void
     */
    public function setTimeout($seconds)
    {
        $this->timeout = $seconds;
    }

    /**
     * Set Antigate API key
     * @param   int $seconds
     * @return  void
     */
    public function setAntigate($api_Key)
    {
        $this->antigate_key = $api_Key;
    }

    /**
     * SetCaptcha image save path
     * @param   int $seconds
     * @return  void
     */
    public function setCaptchaSavePath($path)
    {
        $this->captcha_path = $path;
    }

    /**
     * Set Access Token.
     * @param   string $access_token
     * @throws  VKException
     * @return  void
     */
    public function setAccessToken($access_token)
    {
        $this->access_token = $access_token;
    }

    /**
     * Returns base API url.
     * @param   string $method
     * @param   string $response_format
     * @return  string
     */
    public function getApiUrl($method, $response_format = 'json')
    {
        return 'https://api.vk.com/method/' . $method . '.' . $response_format;
    }

    /**
     * Returns authorization link with passed parameters.
     * @param   string $api_settings
     * @param   string $callback_url
     * @param   bool $test_mode
     * @return  string
     */
    public function getAuthorizeUrl($api_settings = '',
                                    $callback_url = 'https://api.vk.com/blank.html', $test_mode = false)
    {
        $parameters = array(
            'client_id' => $this->app_id,
            'scope' => $api_settings,
            'redirect_uri' => $callback_url,
            'response_type' => 'code'
        );

        if ($test_mode) {
            $parameters['test_mode'] = 1;
        }

        return $this->createUrl(self::AUTHORIZE_URL, $parameters);
    }

    /**
     * Returns access token by code received on authorization link.
     * @param   string $code
     * @param   string $callback_url
     * @throws  VKException
     * @return  array
     */
    public function getAccessToken($code, $callback_url = 'https://api.vk.com/blank.html')
    {
        if (!is_null($this->access_token) && $this->auth) {
            throw new VKException('Already authorized.');
        }

        $parameters = array(
            'client_id' => $this->app_id,
            'client_secret' => $this->api_secret,
            'code' => $code,
            'redirect_uri' => $callback_url
        );

        $rs = json_decode($this->request(
            $this->createUrl(self::ACCESS_TOKEN_URL, $parameters)), true);

        if (isset($rs['error'])) {
            throw new VKException($rs['error'] .
                (!isset($rs['error_description']) ?: ': ' . $rs['error_description']));
        } else {
            $this->auth = true;
            $this->access_token = $rs['access_token'];
            return $rs;
        }
    }

    /**
     * Return user authorization status.
     * @return  bool
     */
    public function isAuth()
    {
        return !is_null($this->access_token);
    }

    /**
     * Check for validity access token.
     * @param   string $access_token
     * @return  bool
     */
    public function checkAccessToken($access_token = null)
    {
        $token = is_null($access_token) ? $this->access_token : $access_token;
        if (is_null($token)) {
            return false;
        }

        $rs = $this->api('getUserSettings', array('access_token' => $token));
        return isset($rs['response']);
    }

    /**
     * Execute API method with parameters and return result.
     * @param   string $method
     * @param   array $parameters
     * @param   string $format
     * @param   string $requestMethod
     * @return  mixed
     */
    public function api($method, $parameters = array(), $format = 'array', $requestMethod = 'get', $try = 0)
    {
        if ($try >= 5) {
            throw new VKException("Number of attempts reached the limit");
        }

        $parameters['timestamp'] = time();
        $parameters['api_id'] = $this->app_id;
        $parameters['random'] = rand(0, 10000);

        if (!array_key_exists('access_token', $parameters) && !is_null($this->access_token)) {
            $parameters['access_token'] = $this->access_token;
        }

        if (!array_key_exists('v', $parameters) && !is_null($this->api_version)) {
            $parameters['v'] = $this->api_version;
        }

        ksort($parameters);

        $sig = '';
        foreach ($parameters as $key => $value) {
            $sig .= $key . '=' . $value;
        }
        $sig .= $this->api_secret;

        $parameters['sig'] = md5($sig);

        if ($method == 'execute' || $requestMethod == 'post') {
            $rs = $this->request(
                $this->getApiUrl($method, $format == 'array' ? 'json' : $format), "POST", $parameters);
        } else {
            $rs = $this->request($this->createUrl(
                $this->getApiUrl($method, $format == 'array' ? 'json' : $format), $parameters));
        }

        $response = json_decode($rs, true);

        if (!empty($response["error"])) {
            if ($response["error"]["error_code"] == 14) {
                if (!empty($this->antigate_key)) {
                    $tmpfname = tempnam($this->captcha_path, 'cap');
                    file_put_contents($tmpfname, file_get_contents($response["error"]["captcha_img"]));
                    $text = $this->recognize($tmpfname, $this->antigate_key, false, "antigate.com");
                    unlink($tmpfname);
                    if (!empty($text)) {
                        unset($parameters['sig']);
                        $parameters['captcha_sid'] = $response["error"]["captcha_sid"];
                        $parameters['captcha_key'] = $text;

                        return $this->api($method, $parameters, $format, $try++);
                    } else {
                        throw new VKException("Captcha recognition error");
                    }
                } else {
                    throw new VKException("Captcha recognition required");
                }
            }

            /**
             * Too Many requests error, waiting half second and trying again
             */
            if ($response["error"]["error_code"] == 6) {
                usleep(500000);
                unset($parameters['sig']);
                return $this->api($method, $parameters, $format, $try++);
            }

            /**
             * Access Token expired or invalid
             */
            if ($response["error"]["error_code"] == 5) {
                throw new VKException("User authorization failed. Access Token expired or invalid.");
            }
        }

        if (curl_getinfo($this->ch, CURLINFO_HTTP_CODE) != 200) {
            throw new VKException("Response error HTTP Code: ".curl_getinfo($this->ch, CURLINFO_HTTP_CODE));
        }

        if ($rs == false) {
            throw new VKException('Curl error: '.curl_error($this->ch));
        } else {
            return $format == 'array' ? json_decode($rs, true) : $rs;
        }
    }

    /**
     * Concatenate keys and values to url format and return url.
     * @param   string $url
     * @param   array $parameters
     * @return  string
     */
    private function createUrl($url, $parameters)
    {
        $url .= '?' . http_build_query($parameters);
        return $url;
    }

    /**
     * Executes request on link.
     * @param   string $url
     * @param   string $method
     * @param   array $postfields
     * @return  string
     */
    private function request($url, $method = 'GET', $postfields = array())
    {
        $options = [
            CURLOPT_USERAGENT => 'VK/1.1 (+https://github.com/S-anasol/VK)',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POST => ($method == 'POST'),
            CURLOPT_POSTFIELDS => $postfields,
            CURLOPT_URL => $url,
            CURLOPT_TIMEOUT => $this->timeout
        ];

        if (!empty($this->proxy)) {
            $options[CURLOPT_PROXY] = $this->proxy;
        }

        curl_setopt_array($this->ch, $options);

        return curl_exec($this->ch);
    }

    /*
      $filename - file path to captcha. MUST be local file. URLs not working
      $apikey   - account's API key
      $rtimeout - delay between captcha status checks
      $mtimeout - captcha recognition timeout

      $is_verbose - false(commenting OFF),  true(commenting ON)

      additional custom parameters for each captcha:
      $is_phrase - 0 OR 1 - captcha has 2 or more words
      $is_regsense - 0 OR 1 - captcha is case sensetive
      $is_numeric -  0 OR 1 - captcha has digits only
      $min_len    -  0 is no limit, an integer sets minimum text length
      $max_len    -  0 is no limit, an integer sets maximum text length
      $is_russian -  0 OR 1 - with flag = 1 captcha will be given to a Russian-speaking worker

      usage examples:
      $text=recognize("/path/to/file/captcha.jpg","YOUR_KEY_HERE",true, "antigate.com");

      $text=recognize("/path/to/file/captcha.jpg","YOUR_KEY_HERE",false, "antigate.com");

      $text=recognize("/path/to/file/captcha.jpg","YOUR_KEY_HERE",false, "antigate.com",1,0,0,5);

      */
      public function recognize(
                  $filename,
                  $apikey,
                  $is_verbose = true,
                  $domain="anti-captcha.com",
                  $rtimeout = 5,
                  $mtimeout = 120,
                  $is_phrase = 0,
                  $is_regsense = 0,
                  $is_numeric = 0,
                  $min_len = 0,
                  $max_len = 0,
                  $is_russian = 0
                  ) {
          if (!file_exists($filename)) {
              if ($is_verbose) {
                  echo "file $filename not found\n";
              }
              return false;
          }
          $file = new \CURLFile($filename);
          $postdata = array(
              'method'    => 'post',
              'key'       => $apikey,
              'file'      => $file,
              'phrase'    => $is_phrase,
              'regsense'    => $is_regsense,
              'numeric'    => $is_numeric,
              'min_len'    => $min_len,
              'max_len'    => $max_len,
                'is_russian'    => $is_russian
          );
          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL,             "http://$domain/in.php");
          curl_setopt($ch, CURLOPT_RETURNTRANSFER,     1);
          curl_setopt($ch, CURLOPT_TIMEOUT,             60);
          curl_setopt($ch, CURLOPT_POST,                 1);
          curl_setopt($ch, CURLOPT_POSTFIELDS,         $postdata);
          $result = curl_exec($ch);
          if (curl_errno($ch)) {
              if ($is_verbose) {
                  echo "CURL returned error: ".curl_error($ch)."\n";
              }
              return false;
          }
          curl_close($ch);
          if (strpos($result, "ERROR")!==false) {
              if ($is_verbose) {
                  echo "server returned error: $result\n";
              }
              return false;
          } else {
              $ex = explode("|", $result);
              $captcha_id = $ex[1];
              if ($is_verbose) {
                  echo "captcha sent, got captcha ID $captcha_id\n";
              }
              $waittime = 0;
              if ($is_verbose) {
                  echo "waiting for $rtimeout seconds\n";
              }
              sleep($rtimeout);
              while (true) {
                  $result = file_get_contents("http://$domain/res.php?key=".$apikey.'&action=get&id='.$captcha_id);
                  if (strpos($result, 'ERROR')!==false) {
                      if ($is_verbose) {
                          echo "server returned error: $result\n";
                      }
                      return false;
                  }
                  if ($result=="CAPCHA_NOT_READY") {
                      if ($is_verbose) {
                          echo "captcha is not ready yet\n";
                      }
                      $waittime += $rtimeout;
                      if ($waittime>$mtimeout) {
                          if ($is_verbose) {
                              echo "timelimit ($mtimeout) hit\n";
                          }
                          break;
                      }
                      if ($is_verbose) {
                          echo "waiting for $rtimeout seconds\n";
                      }
                      sleep($rtimeout);
                  } else {
                      $ex = explode('|', $result);
                      if (trim($ex[0])=='OK') {
                          return trim($ex[1]);
                      }
                  }
              }

              return false;
          }
      }
}
