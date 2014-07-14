<?php
class Curl
{
    private $settings = [];
    private $auth = [];

    private $connect;
    private $html;

    public function __construct(array $settings)
    {
        if (empty($settings['base'])) {
            throw new \Exception('"base" option is empty');
        }

        if (empty($settings['cookie'])) {
            throw new \Exception('"cookie" option is empty');
        }

        if (empty($settings['debug'])) {
            $settings['debug'] = false;
        }

        $this->settings = $settings;

        return $this->connect();
    }

    public function connect()
    {
        if (is_resource($this->connect)) {
            $this->close();
        }

        $this->connect = curl_init();

        $header = [
            'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:30.0) Gecko/20100101 Firefox/30.0',
            'Connection: keep-alive',
            'Cache-Control: max-age=0'
        ];

        curl_setopt($this->connect, CURLOPT_HEADER, false);
        curl_setopt($this->connect, CURLOPT_HTTPHEADER, $header);
        curl_setopt($this->connect, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->connect, CURLOPT_AUTOREFERER, true);
        curl_setopt($this->connect, CURLOPT_COOKIEJAR, $this->settings['cookie']);
        curl_setopt($this->connect, CURLOPT_COOKIEFILE, $this->settings['cookie']);
        curl_setopt($this->connect, CURLOPT_COOKIESESSION, false);
        curl_setopt($this->connect, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->connect, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->connect, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($this->connect, CURLOPT_MAXREDIRS, 5);
        curl_setopt($this->connect, CURLOPT_REFERER, $this->settings['base']);

        return $this;
    }

    public function close()
    {
        curl_close($this->connect);

        return $this;
    }

    public function setOption($option, $value)
    {
        curl_setopt($this->connect, $option, $value);

        return $this;
    }

    public function setAuth(array $auth)
    {
        $this->auth = http_build_query($auth);

        return $this;
    }

    public function get($url, array $data = [])
    {
        if (!is_resource($this->connect)) {
            $this->connect();
        }

        $url = $this->settings['base'].$url;

        if ($data) {
            $url .= strstr($url, '?') ? '&' : '?';
            $url .= http_build_query($data);
        }

        if ($this->auth) {
            $url .= (strstr($url, '?') ? '&' : '?').$this->auth;
        }

        if ($this->settings['debug']) {
            d($url);
        }

        curl_setopt($this->connect, CURLOPT_URL, $url);

        $json = json_decode($this->response = curl_exec($this->connect));
        $code = curl_getinfo($this->connect, CURLINFO_HTTP_CODE);

        if (strpos($code, '20') !== 0) {
            if (is_object($json)) {
                throw new \Exception(sprintf('Query can not be executed: %s (Code: %s)', $this->response->message, $code));
            } else {
                throw new \Exception($this->response);
            }
        }

        return $json;
    }

    public function post($url, array $data = [])
    {
        if (!is_resource($this->connect)) {
            $this->connect();
        }

        curl_setopt($this->connect, CURLOPT_POST, true);
        curl_setopt($this->connect, CURLOPT_POSTFIELDS, $data);

        $response = $this->get($url, []);

        curl_setopt($this->connect, CURLOPT_POST, false);

        return $response;
    }
}
