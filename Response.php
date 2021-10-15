<?php

class Response
{
    public $code; //200为成功
    public $error;
    public $headers;
    public $data;

    public function __construct()
    {
        $this->code = null;
        $this->error = null;
        $this->headers = [];
        $this->data = null;
    }

    public function saveToResource($resource)
    {
        $this->data = $resource;
    }

    /**
     * 返回内容回调
     * @param $ch
     * @param $data
     * @return false|int
     */
    public function __curlWriteFunction($ch, $data)
    {
        if (is_resource($this->data)) {
            return fwrite($this->data, $data);
        } else {
            $this->data .= $data;
            return strlen($data);
        }
    }

    /**
     * 头部信息回调
     * @param $ch
     * @param $data
     * @return int
     */
    public function __curlHeaderFunction($ch, $data)
    {
        $header = explode(':', $data);

        if (count($header) == 2) {
            list($key, $value) = $header;
            $this->headers[$key] = trim($value);
        }

        return strlen($data);
    }

    /**
     * 返回内容处理
     * @param $ch
     */
    public function finalize($ch)
    {
        if (is_resource($this->data)) {
            rewind($this->data);
        }

        if (curl_errno($ch) || curl_error($ch)) {
            $this->error = [
                'code' => curl_errno($ch),
                'message' => curl_error($ch),
            ];
        } else {
            $this->code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

            if ($content_type == 'application/xml') {
                $obj = simplexml_load_string($this->data, "SimpleXMLElement", LIBXML_NOCDATA);
                $this->data = json_decode(json_encode($obj), true);
            }
        }
    }
}