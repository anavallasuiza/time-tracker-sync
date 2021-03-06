<?php
namespace ANS\TimeTrackerSync;

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
            'Accept: application/json',
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

    private function exec($url, array $data = [])
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
            echo '<pre><code>'.$url.'</code></pre>';
        }

        curl_setopt($this->connect, CURLOPT_URL, $url);

        $json = json_decode($this->response = curl_exec($this->connect));
        $code = curl_getinfo($this->connect, CURLINFO_HTTP_CODE);

        if (strpos($code, '20') !== 0) {
            if (is_object($json)) {
                if (isset($json->message)) {
                    throw new \Exception(sprintf('Query can not be executed: %s (Code: %s)', $json->message, $code));
                } if (isset($json->error)) {
                    throw new \Exception(sprintf('Query can not be executed: %s (Line: %s)', $json->error->message, $json->error->line));
                }
            }

            throw new \Exception($this->response);
        }

        return $json;
    }

    public function get($url, array $data = [])
    {
        if (!is_resource($this->connect)) {
            $this->connect();
        }

        curl_setopt($this->connect, CURLOPT_CUSTOMREQUEST, 'GET');

        return $this->exec($url, $data);
    }

    public function post($url, array $data = [])
    {
        if (!is_resource($this->connect)) {
            $this->connect();
        }

        curl_setopt($this->connect, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($this->connect, CURLOPT_POST, true);
        curl_setopt($this->connect, CURLOPT_POSTFIELDS, $data);

        $response = $this->exec($url);

        curl_setopt($this->connect, CURLOPT_POST, false);

        return $response;
    }

    public function delete($url, array $data = [])
    {
        if (!is_resource($this->connect)) {
            $this->connect();
        }

        curl_setopt($this->connect, CURLOPT_CUSTOMREQUEST, 'DELETE');

        return $this->exec($url, $data);
    }
}
