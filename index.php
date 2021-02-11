<?php

require 'vendor/autoload.php';

use Hitrov\OCI\Signer;
use Hitrov\Test\MockKeyFileProvider;
use Hitrov\Test\MockKeyProvider;

$filename = implode(DIRECTORY_SEPARATOR, ['tests', 'resources', 'privatekey.pem']);
$signer = new Signer(
    MockKeyProvider::OCI_TENANCY_ID,
    MockKeyProvider::OCI_USER_ID,
    MockKeyProvider::OCI_KEY_FINGERPRINT,
    $filename
);

// Alternatively:
//$signer = new Signer();
//$keyProvider = new MockKeyFileProvider(); // or
//$keyProvider = new MockKeyProvider();
//$signer->setKeyProvider($keyProvider);

$namespaceName = 'frepgpx8ftrx';
$bucketName = 'test20210130';
$url = "https://objectstorage.eu-frankfurt-1.oraclecloud.com/n/$namespaceName/b/$bucketName/p/";
$method = 'POST';
$body = '{
  "accessType": "ObjectRead", 
  "name": "read-access-to-image.png", 
  "objectName": "path/to/image.png", 
  "timeExpires": "2021-03-01T00:00:00-00:00"
}';

$headers = $signer->getHeaders($url, $method, $body, 'application/json');
var_dump($headers);

$bodyHashBase64 = $signer->getBodyHashBase64($body);
var_dump($bodyHashBase64);

$signingHeadersNames = $signer->getSigningHeadersNames('POST');
var_dump($signingHeadersNames);

$signingString = $signer->getSigningString($url, $method, $body, 'application/json');
var_dump($signingString);

$signature = $signer->calculateSignature($signingString, MockKeyProvider::OCI_PRIVATE_KEY);
var_dump($signature);

$keyId = $signer->getKeyId();
var_dump($keyId);

$authorizationHeader = $signer->getAuthorizationHeader($keyId, implode(' ', $signingHeadersNames), $signature);
var_dump($authorizationHeader);

// real request example.
// 1. provide credentials to Signer
$signer = new Signer;
// 2. adjust path to file (objectName) in body JSON above
// 3. comment `die;` below
die;

$curl = curl_init();

$headers = $signer->getHeaders($url, $method, $body, 'application/json');
var_dump($headers);

$curlOptions = [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 5,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => $headers,
];

if ($body) {
    $curlOptions[CURLOPT_POSTFIELDS] = $body;
}

curl_setopt_array($curl, $curlOptions);

$response = curl_exec($curl);
echo $response;
curl_close($curl);
