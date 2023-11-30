<?php

class Request
{
    private $action;
    private $endpoint;
    private $uri;
    private $headers;
    private $curl;
    private $response;

    private $multi_curl;
    private $secure;

    public function __construct($action, $endpoint, $uri)
    {
        $this->action = $action;
        $this->uri = $uri;
        $url = parse_url($endpoint);
        $this->endpoint = $url['host'];
        if (!empty($url['port'])) {
            $this->endpoint = $url['host'] . ':' . $url['port'];
        }
        $url['scheme'] === 'http' || $this->secure = true;
        $this->headers = [
            'Content-MD5' => '',
            'Content-Type' => '',
            'Date' => gmdate('D, d M Y H:i:s T'),
            'Host' => $this->endpoint
        ];

        $this->curl = curl_init();
        $this->response = new Response();

        $this->multi_curl = null;
    }

    /**
     * 设置文件内容
     * @param $file
     * @return $this
     */
    public function setFileContents($file)
    {
        if (is_resource($file)) {
            $hash_ctx = hash_init('md5');
            $length = hash_update_stream($hash_ctx, $file);
            $md5 = hash_final($hash_ctx, true);

            rewind($file);
            $this->headers['Content-Type'] = mime_content_type($file);

            curl_setopt($this->curl, CURLOPT_PUT, true);
            curl_setopt($this->curl, CURLOPT_INFILE, $file);
            curl_setopt($this->curl, CURLOPT_INFILESIZE, $length);
        } else {
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $file);
            $md5 = md5($file, true);
        }
        $this->headers['Content-MD5'] = base64_encode($md5);
        return $this;
    }

    /**
     * 生成签名
     * @param $access_key
     * @param $secret_key
     * @return $this
     */
    public function sign($access_key, $secret_key)
    {
        $canonical_amz_headers = $this->getCanonicalAmzHeaders();
        $string_to_sign = '';
        $string_to_sign .= "{$this->action}\n";
        $string_to_sign .= "{$this->headers['Content-MD5']}\n";
        $string_to_sign .= "{$this->headers['Content-Type']}\n";
        $string_to_sign .= "{$this->headers['Date']}\n";

        if (!empty($canonical_amz_headers)) {
            $string_to_sign .= implode($canonical_amz_headers, "\n") . "\n";
        }

        $string_to_sign .= "/{$this->uri}";
        $signature = base64_encode(
            hash_hmac('sha1', $string_to_sign, $secret_key, true)
        );

        $this->headers['Authorization'] = "AWS $access_key:$signature";
        return $this;
    }

    /**
     * multi_curl设置
     * @param $mh
     * @return $this
     */
    public function setMultiCurl($mh)
    {
        $this->multi_curl = $mh;
        return $this;
    }

    /**
     * opt设置
     * @param $curl_opts
     * @return $this
     */
    public function setCurlOpts($curl_opts)
    {
        curl_setopt_array($this->curl, $curl_opts);

        return $this;
    }

    /**
     * 获取返回内容
     * @return Response
     */
    public function getResponse()
    {
        $http_headers = array_map(
            function ($header, $value) {
                return "$header: $value";
            },
            array_keys($this->headers),
            array_values($this->headers)
        );
        $url = "{$this->endpoint}/{$this->uri}";
        if ($this->secure) {
            $url = 'https://' . $url;
        } else {
            $url = 'http://' . $url;
        }
        curl_setopt_array($this->curl, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $http_headers,
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_WRITEFUNCTION => [
                $this->response, '__curlWriteFunction'
            ],
            CURLOPT_HEADERFUNCTION => [
                $this->response, '__curlHeaderFunction'
            ]
        ]);

        switch ($this->action) {
            case 'DELETE':
                curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            case 'HEAD':
                curl_setopt($this->curl, CURLOPT_NOBODY, true);
                break;
            case 'POST':
                curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'POST');
                break;
            case 'PUT':
                curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'PUT');
                break;
        }


        if (isset($this->multi_curl)) {
            curl_multi_add_handle($this->multi_curl, $this->curl);

            $running = null;
            do {
                curl_multi_exec($this->multi_curl, $running);
                curl_multi_select($this->multi_curl);
            } while ($running > 0);

            curl_multi_remove_handle($this->multi_curl, $this->curl);
        } else {
            curl_exec($this->curl);
        }

        $this->response->finalize($this->curl);
        curl_close($this->curl);
        return $this->response;
    }

    /**
     * 头部处理
     * @return array
     */
    private function getCanonicalAmzHeaders()
    {
        $canonical_amz_headers = [];
        foreach ($this->headers as $header => $value) {
            $header = trim(strtolower($header));
            $value = trim($value);

            if (strpos($header, 'x-amz-') === 0) {
                $canonical_amz_headers[$header] = "$header:$value";
            }
        }
        ksort($canonical_amz_headers);
        return $canonical_amz_headers;
    }
}
