<?php

require_once 'Request.php';
require_once 'Response.php';


class Minio
{
    const CODE_SUCCESS = 200;
    const CODE_DEL_SUCCESS = 204;

    private $endpoint = 'http://xxx.com';
    private $accessKey = 'minioadmin';
    private $secretKey = 'minioadmin';
    private $multiCurl;
    private $curlOpts = [
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_LOW_SPEED_LIMIT => 1,
        CURLOPT_LOW_SPEED_TIME => 30
    ];
    private static $instance;

    public function __construct()
    {
        $this->multiCurl = curl_multi_init();
    }

    public function __destruct()
    {
        curl_multi_close($this->multiCurl);
    }

    /**
     * 单例模式 获取实例
     * @return Minio
     */
    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 获取bucket列表
     * @return array
     */
    public function listBuckets()
    {
        $res = $this->requestBucket('GET', '');
        if ($res && $res['code'] == self::CODE_SUCCESS) {
            $res['data'] = ['bucket' => array_column($res['data']['Buckets']['Bucket'], 'Name')];
            return $res;
        } else {
            return $res;
        }
    }

    /**
     * 获取bucket目录文件信息
     * @param $bucket
     * @return Response
     */
    public function getBucket(string $bucket)
    {
        return $this->requestBucket('GET', $bucket);
    }

    /**
     * 创建bucket目录
     * @param $bucket
     * @return bool true创建成功，false失败
     */
    public function createBucket(string $bucket)
    {
        $res = $this->requestBucket('PUT', $bucket);
        if ($res['code'] == self::CODE_SUCCESS) {
            return true;
        }

        return false;
    }

    /**
     * 删除bucket目录
     * @param $bucket
     * @return bool true删除成功，false失败
     */
    public function deleteBucket(string $bucket)
    {
        $res = $this->requestBucket('DELETE', $bucket);
        if ($res['code'] == self::CODE_DEL_SUCCESS) {
            return true;
        }

        return false;
    }

    /**
     * 上传文件
     * @param string $file 本地需要上传的全路径文件
     * @param string $uri 保存路径名称
     * @return Response
     */
    public function putObject(string $file, string $uri)
    {
        //判断bucket是否存在，不存在则创建
        $uri = ltrim($uri, DIRECTORY_SEPARATOR);
        $uriArr = explode(DIRECTORY_SEPARATOR, $uri);
        $bucket = $uriArr[0];

        $listBuckets = $this->listBuckets();
        $bucketArr = $listBuckets['data']['bucket'];

        if (!in_array($bucket, $bucketArr)) {
            $this->createBucket($bucket);
        }

        $request = (new Request('PUT', $this->endpoint, $uri))
            ->setFileContents(fopen($file, 'r'))
            ->setMultiCurl($this->multiCurl)
            ->setCurlOpts($this->curlOpts)
            ->sign($this->accessKey, $this->secretKey);

        $res = $this->objectToArray($request->getResponse());

        return $res;
    }

    /**
     * 获取文件链接
     * @param string $uri 保存路径名称
     * @return string
     */
    public function getObjectUrl(string $uri)
    {
        return $this->endpoint . $uri;
    }


    /**
     * 获取文件类型，header中体现
     * @param string $uri 保存路径名称
     * @return Response code=200为文件存在
     */
    public function getObjectInfo(string $uri)
    {
        $uri = ltrim($uri, DIRECTORY_SEPARATOR);

        $request = (new Request('HEAD', $this->endpoint, $uri))
            ->setMultiCurl($this->multiCurl)
            ->setCurlOpts($this->curlOpts)
            ->sign($this->accessKey, $this->secretKey);

        return $this->objectToArray($request->getResponse());
    }

    /**
     * 获取文件 ，data返回二进制数据流
     * @param string $uri 保存路径名称
     * @param null $resource
     * @param array $headers
     * @return Response
     */
    public function getObject(string $uri)
    {
        $uri = ltrim($uri, DIRECTORY_SEPARATOR);

        $request = (new Request('GET', $this->endpoint, $uri))
            ->setMultiCurl($this->multiCurl)
            ->setCurlOpts($this->curlOpts)
            ->sign($this->accessKey, $this->secretKey);

        return $this->objectToArray($request->getResponse());
    }

    /**
     * 删除文件
     * @param string $uri 保存路径名称
     * @return bool true删除成功，false删除失败
     */
    public function deleteObject(string $uri)
    {
        $uri = ltrim($uri, DIRECTORY_SEPARATOR);

        $request = (new Request('DELETE', $this->endpoint, $uri))
            ->setMultiCurl($this->multiCurl)
            ->setCurlOpts($this->curlOpts)
            ->sign($this->accessKey, $this->secretKey);

        $res = $this->objectToArray($request->getResponse());
        if ($res['code'] == self::CODE_DEL_SUCCESS) {
            return true;
        }

        return false;
    }
    
    /**
     * 拷贝文件
     * @param $fromObject 源文件
     * @param $toObject 目标文件
     * @return array|mixed {"code":200}
     */
    public function copyObject($fromObject, $toObject)
    {
        $fromObject = ltrim($fromObject, DIRECTORY_SEPARATOR);
        $toObject = ltrim($toObject, DIRECTORY_SEPARATOR);

        //判断目标bucket是否存在，不存在则创建
        $toArr = explode(DIRECTORY_SEPARATOR, $toObject);
        $toBucket = $toArr[0];
        $listBuckets = $this->listBuckets();
        $bucketArr = $listBuckets['data']['bucket'];
        if (!in_array($toBucket, $bucketArr)) {
            $this->createBucket($toBucket);
        }

        $request = (new Request('PUT', $this->endpoint, $toObject))
            ->setHeaders(['x-amz-copy-source' => $fromObject])
            ->setMultiCurl($this->multiCurl)
            ->setCurlOpts($this->curlOpts)
            ->sign($this->accessKey, $this->secretKey);

        $res = $this->objectToArray($request->getResponse());

        return $res;
    }

    /**
     * bucket目录请求
     * @param string $method
     * @param string $bucket
     * @param array $headers
     * @return mixed
     */
    protected function requestBucket(string $method = 'GET', string $bucket = '')
    {
        $request = (new Request($method, $this->endpoint, $bucket))
            ->setMultiCurl($this->multiCurl)
            ->setCurlOpts($this->curlOpts)
            ->sign($this->accessKey, $this->secretKey);

        return $this->objectToArray($request->getResponse());
    }

    /**
     * 对象转数组
     * @param $object
     * @return mixed
     */
    private function objectToArray($object)
    {
        $arr = is_object($object) ? get_object_vars($object) : $object;
        $returnArr = [];
        foreach ($arr as $key => $val) {
            $val = (is_array($val)) || is_object($val) ? $this->objectToArray($val) : $val;
            $returnArr[$key] = $val;
        }
        return $returnArr;
    }

}
