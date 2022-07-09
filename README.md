# minio-php-sdk
php sdk for minio 


# author
gusijin


# brief introduction
PHP native code calls API interface to realize the application of Minio resource storage and release

PHP realizes the functions of uploading and deleting operation files, creating, deleting and querying buckets


# Getting Started
```php

// Instantiate an Minio client.
$minioClient = Minio::getInstance();

// Bucket

$res = $minioClient->listBuckets();

$res = $minioClient->getBucket('file');

$res = $minioClient->createBucket('my'.rand(1000,9999));

$res = $minioClient->putBucketPolicy('my3833');

$res = $minioClient->deleteBucket('1111');


// File

// Upload a publicly accessible file. The file size and type are determined by the SDK.
$res = $minioClient->putObject( '/www/php/www.gsj.com/minio/Dingtalk_20210913104610.jpg','my3833/20210913104610_0007.jpg' );

$res = $minioClient->getObjectInfo('/attachment/202110/1633571028_57616.jpg');

$res = $minioClient->getObjectUrl('/hst-bucket/20210913104610_0001.jpg');


$res = $minioClient->getObject( '/hst-bucket/20210913104610_0001.jpg');

$res = $minioClient->deleteObject('/hst-bucket/20210913104610_0001.jpg');

$res = $minioClient->copyObject('/attachment/202110/1633571028_57616.jpg','recycle/202110/1633571028_57616.jpg');

```
