# Oracle Cloud Infrastructure API requests sign with PHP

<p align="center">
  <a href="https://packagist.org/packages/hitrov/oci-api-php-request-sign"><img src="https://img.shields.io/packagist/v/hitrov/oci-api-php-request-sign" alt="Latest Stable Version"></a>
  <a href="https://github.com/hitrov/oci-api-php-request-sign/actions"><img src="https://github.com/hitrov/oci-api-php-request-sign/workflows/Tests/badge.svg" alt="Test"></a>
</p>

- [Installation](#installation)
- [Preparing credentials](#preparing-credentials)
- [Basic usage](#basic-usage)
- [Alternatives for providing credentials](#alternatives-for-providing-credentials)
  - [Constructor arguments](#constructor-arguments)
  - [Separate credentials class](#separate-credentials-class)
- [Manual generation steps](#manual-generation-steps)
- [Inspiration](#inspiration)

If you prefer article style, here's a link to [Medium](https://hitrov.medium.com/creating-mini-php-sdk-to-sign-oracle-cloud-infrastructure-api-requests-d91a224c7008?sk=5b4405c1124bfeac30a370630fd94126)

## Installation
```bash
composer require hitrov/oci-api-php-request-sign
```
Import classes autoloader
```php
require 'vendor/autoload.php';
use Hitrov\OCI\Signer;
```

## Preparing credentials
`Signer` expects a list of environment variables available:
```bash
OCI_TENANCY_ID=ocid1.tenancy.oc1..aaaaaaaaba3pv6wkcr4jqae5f15p2b2m2yt2j6rx32uzr4h25vqstifsfdsq
OCI_USER_ID=ocid1.user.oc1..aaaaaaaat5nvwcna5j6aqzjcaty5eqbb6qt2jvpkanghtgdaqedqw3rynjq
OCI_KEY_FINGERPRINT=20:3b:97:13:55:1c:5b:0d:d3:37:d8:50:4e:c5:3a:34
OCI_PRIVATE_KEY_FILENAME=/path/to/privatekey.pem
```
There are few more ways to expose/pass them, please refer to [this](#alternatives-for-providing-credentials) section.

## Basic usage
Here's the example of PHP script on how to [CreatePreauthenticatedRequest](https://docs.oracle.com/en-us/iaas/api/#/en/objectstorage/20160918/PreauthenticatedRequest/CreatePreauthenticatedRequest) for Object Storage Service API.
```php
$signer = new Signer();

$curl = curl_init();

$url = 'https://objectstorage.eu-frankfurt-1.oraclecloud.com/n/{namespaceName}/b/{bucketName}/p/';
$method = 'POST';
$body = '{"accessType": "ObjectRead", "name": "read-access-to-image.png", "objectName": "path/to/image.png", "timeExpires": "2021-03-01T00:00:00-00:00"}';

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
    // not needed for GET or HEAD requests
    $curlOptions[CURLOPT_POSTFIELDS] = $body;
}

curl_setopt_array($curl, $curlOptions);

$response = curl_exec($curl);
echo $response;
curl_close($curl);
```
```
array(6) {
  [0]=>
  string(35) "date: Mon, 08 Feb 2021 20:49:22 GMT"
  [1]=>
  string(50) "host: objectstorage.eu-frankfurt-1.oraclecloud.com"
  [2]=>
  string(18) "content-length: 76"
  [3]=>
  string(30) "content-type: application/json"
  [4]=>
  string(62) "x-content-sha256: X48E9qOokqqrvdts8nOJRJN3OWDUoyWxBf7kbu9DBPE="
  [5]=>
  string(538) "Authorization: Signature version=\"1\",keyId=\"ocid1.tenancy.oc1..aaaaaaaaba3pv6wkcr4jqae5f15p2b2m2yt2j6rx32uzr4h25vqstifsfdsq/ocid1.user.oc1..aaaaaaaat5nvwcna5j6aqzjcaty5eqbb6qt2jvpkanghtgdaqedqw3rynjq/20:3b:97:13:55:1c:5b:0d:d3:37:d8:50:4e:c5:3a:34\",algorithm=\"rsa-sha256\",headers=\"date (request-target) host content-length content-type x-content-sha256\",signature=\"LXWXDA8VmXXc1NRbMmXtW61IS97DfIOMAnlj+Gm+oBPNc2svXYdhcXNJ+oFPoi9qJHLnoUiHqotTzuVPXSG5iyXzFntvkAn3lFIAja52iwwwcJflEIXj/b39eG2dCsOTmmUJguut0FsLhCRSX0eylTSLgxTFGoQi7K/m18nafso=\""
}
```
```json
{
  "accessUri": "/p/AlIlOEsMok7oE7YkN30KJUDjDKQjk493BKbuM-ANUNGdBBAHzHT_5lFlzYC9CQiA/n/{namespaceName}/b/{bucketName}/o/path/to/image.png",
  "id": "oHJQWGxpD+2PhDqtoewvLCf8/lYNlaIpbZHYx+mBryAad/q0LnFy37Me/quKhxEi:path/to/image.png",
  "name": "read-access-to-image.png",
  "accessType": "ObjectRead",
  "objectName": "path/to/image.png",
  "timeCreated": "2021-02-09T11:52:45.053Z",
  "timeExpires": "2021-03-01T00:00:00Z"
}
```
That's it!

## Alternatives for providing credentials
### Constructor arguments
```php
$signer = new Signer(
    $ociTenancyId,
    $ociUserId,
    $ociKeyFingerPrint,
    $privateKeyFilename
);
```
### Separate credentials class
Implement `Hitrov\OCI\KeyProvider\KeyProviderInterface` methods
- `public function getPrivateKey(): string;` // must return a string (contents of privatekey.pem)
- `public function getKeyId(): string;` // must return a string like `"{OCI_TENANCY_ID}/{OCI_USER_ID}/{OCI_KEY_FINGERPRINT}"`

Force `Signer` to use it instead of constructor arguments and environment variables:
```php
$keyProvider = new MockKeyProvider() // implements KeyProviderInterface;
$signer = new Signer();
$signer->setKeyProvider($keyProvider);
```
There's such an example covered in Unit tests `tests\Hitrov\Test\MockKeyProvider.php`

## Manual generation steps
There are more public methods exposed for all the stuff generated behind the scenes if you need it or just curious how it works:
```php
$signingHeadersNames = $signer->getSigningHeadersNames('POST');
var_dump($signingHeadersNames);
```
```
array(6) {
  [0]=>
  string(4) "date"
  [1]=>
  string(16) "(request-target)"
  [2]=>
  string(4) "host"
  [3]=>
  string(14) "content-length"
  [4]=>
  string(12) "content-type"
  [5]=>
  string(16) "x-content-sha256"
}
```
```php
// the value of `x-content-sha256` HTTP header
$bodyHashBase64 = $signer->getBodyHashBase64($body);
// X48E9qOokqqrvdts8nOJRJN3OWDUoyWxBf7kbu9DBPE=
```
```php
$signingString = $signer->getSigningString($url, $method, $body, 'application/json');
```
```
date: Mon, 08 Feb 2021 20:51:33 GMT
(request-target): post /n/{namespaceName}/b/{bucketName}/p/
host: objectstorage.eu-frankfurt-1.oraclecloud.com
content-length: 76
content-type: application/json
x-content-sha256: X48E9qOokqqrvdts8nOJRJN3OWDUoyWxBf7kbu9DBPE=
```
```php
// part of `authorization` HTTP header value
$signature = $signer->calculateSignature($signingString, $privateKeyString);
// LXWXDA8VmXXc1NRbMmXtW61IS97DfIOMAnlj+Gm+oBPNc2svXYdhcXNJ+oFPoi9qJHLnoUiHqotTzuVPXSG5iyXzFntvkAn3lFIAja52iwwwcJflEIXj/b39eG2dCsOTmmUJguut0FsLhCRSX0eylTSLgxTFGoQi7K/m18nafso=
```
```php
// part of `authorization` HTTP header value
$keyId = $signer->getKeyId(); // "{OCI_TENANCY_ID}/{OCI_USER_ID}/{OCI_KEY_FINGERPRINT}"
// ocid1.tenancy.oc1..aaaaaaaaba3pv6wkcr4jqae5f15p2b2m2yt2j6rx32uzr4h25vqstifsfdsq/ocid1.user.oc1..aaaaaaaat5nvwcna5j6aqzjcaty5eqbb6qt2jvpkanghtgdaqedqw3rynjq/20:3b:97:13:55:1c:5b:0d:d3:37:d8:50:4e:c5:3a:34
```
Authorization header is being generated this way (version is always `1` for this signing procedure):

`Authorization: Signature version=\"1\",keyId=\"{KEY_ID}\",algorithm=\"rsa-sha256\",headers=\"{SIGNING_HEADERS_NAMES_STRING}\",signature=\"{SIGNATURE}\"`
```php
$signingHeadersNamesString = implode(' ', $signingHeadersNames);
$authorizationHeader = $signer->getAuthorizationHeader($keyId, $signingHeadersNamesString, $signature);
// Authorization: Signature version=\"1\",keyId=\"ocid1.tenancy.oc1..aaaaaaaaba3pv6wkcr4jqae5f15p2b2m2yt2j6rx32uzr4h25vqstifsfdsq/ocid1.user.oc1..aaaaaaaat5nvwcna5j6aqzjcaty5eqbb6qt2jvpkanghtgdaqedqw3rynjq/20:3b:97:13:55:1c:5b:0d:d3:37:d8:50:4e:c5:3a:34\",algorithm=\"rsa-sha256\",headers=\"date (request-target) host content-length content-type x-content-sha256\",signature=\"LXWXDA8VmXXc1NRbMmXtW61IS97DfIOMAnlj+Gm+oBPNc2svXYdhcXNJ+oFPoi9qJHLnoUiHqotTzuVPXSG5iyXzFntvkAn3lFIAja52iwwwcJflEIXj/b39eG2dCsOTmmUJguut0FsLhCRSX0eylTSLgxTFGoQi7K/m18nafso=\"
```

## Inspiration
- [https://www.ateam-oracle.com/oracle-cloud-infrastructure-oci-rest-call-walkthrough-with-curl](https://www.ateam-oracle.com/oracle-cloud-infrastructure-oci-rest-call-walkthrough-with-curl)
- [Official GoLang SDK http_signer](https://github.com/oracle/oci-go-sdk/blob/master/common/http_signer.go)
- [Official GoLang SDK http_signer_test](https://github.com/oracle/oci-go-sdk/blob/master/common/http_signer_test.go)
