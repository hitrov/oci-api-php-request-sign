<?php
declare(strict_types=1);

namespace Hitrov\Test;

use Hitrov\OCI\Exception\PrivateKeyFileNotFoundException;
use Hitrov\OCI\Exception\SignerValidateException;
use Hitrov\OCI\Signer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

class SignerTest extends TestCase
{
    const OCI_COMPARTMENT_ID = 'ocid1.compartment.oc1..aaaaaaaam3we6vgnherjq5q2idnccdflvjsnog7mlr6rtdb25gilchfeyjxa';

    const TEST_URL = 'https://iaas.us-phoenix-1.oraclecloud.com/20160918/instances' .
        '?availabilityDomain=Pjwf%3A%20PHX-AD-1&' .
        'compartmentId=ocid1.compartment.oc1..aaaaaaaam3we6vgnherjq5q2idnccdflvjsnog7mlr6rtdb25gilchfeyjxa' .
        '&displayName=TeamXInstances&volumeId=ocid1.volume.oc1.phx.abyhqljrgvttnlx73nmrwfaux7kcvzfs3s66izvxf2h4lgvyndsdsnoiwr5q';

    const TEST_URL2 = 'https://iaas.us-phoenix-1.oraclecloud.com/20160918/volumeAttachments';

    const TEST_BODY = <<<EOT
{
    "compartmentId": "ocid1.compartment.oc1..aaaaaaaam3we6vgnherjq5q2idnccdflvjsnog7mlr6rtdb25gilchfeyjxa",
    "instanceId": "ocid1.instance.oc1.phx.abuw4ljrlsfiqw6vzzxb43vyypt4pkodawglp3wqxjqofakrwvou52gb6s5a",
    "volumeId": "ocid1.volume.oc1.phx.abyhqljrgvttnlx73nmrwfaux7kcvzfs3s66izvxf2h4lgvyndsdsnoiwr5q"
}
EOT;

    const DATE_STRING = 'Thu, 05 Jan 2014 21:31:40 GMT';

    const EXPECTED_SIGNING_STRING = 'date: ' . self::DATE_STRING . "\n" .
    '(request-target): get /20160918/instances?availabilityDomain=Pjwf%3A%20PH' .
    'X-AD-1&compartmentId=ocid1.compartment.oc1..aaaaaaaam3we6vgnherjq5q2i' .
    'dnccdflvjsnog7mlr6rtdb25gilchfeyjxa&displayName=TeamXInstances&' .
    'volumeId=ocid1.volume.oc1.phx.abyhqljrgvttnlx73nmrwfaux7kcvzfs3s66izvxf2h4lgvyndsdsnoiwr5q' . "\n" .
    'host: iaas.us-phoenix-1.oraclecloud.com';

    const EXPECTED_SIGNING_STRING2 = <<<EOT
date: Thu, 05 Jan 2014 21:31:40 GMT
(request-target): post /20160918/volumeAttachments
host: iaas.us-phoenix-1.oraclecloud.com
content-length: 316
content-type: application/json
x-content-sha256: V9Z20UJTvkvpJ50flBzKE32+6m2zJjweHpDMX/U4Uy0=
EOT;

    const EXPECTED_SIGNATURE = 'GBas7grhyrhSKHP6AVIj/h5/Vp8bd/peM79H9Wv8kjoaCivujVXlpbKLjMPe' .
    'DUhxkFIWtTtLBj3sUzaFj34XE6YZAHc9r2DmE4pMwOAy/kiITcZxa1oHPOeRheC0jP2dqbTll' .
    '8fmTZVwKZOKHYPtrLJIJQHJjNvxFWeHQjMaR7M=';

    const EXPECTED_SIGNATURE2 = 'Mje8vIDPlwIHmD/cTDwRxE7HaAvBg16JnVcsuqaNRim23fFPgQfLoOOxae6WqKb1uPjYEl0qIdazWaBy/Ml8DRhqlocMwoSXv0fbukP8J5N80LCmzT/FFBvIvTB91XuXI3hYfP9Zt1l7S6ieVadHUfqBedWH0itrtPJBgKmrWso=';

    const GENERIC_HEADERS = 'date (request-target) host';
    const BODY_HEADERS = 'date (request-target) host content-length content-type x-content-sha256';

    public function testShouldHashBody(): void
    {
        $signer = new Signer(
            MockKeyProvider::OCI_TENANCY_ID, MockKeyProvider::OCI_USER_ID, MockKeyProvider::OCI_KEY_FINGERPRINT, null
        );

        $shouldHashBody = $this->getPrivateMethod(Signer::class, 'shouldHashBody');

        $actual = $shouldHashBody->invokeArgs($signer, ['GET']);
        $this->assertFalse($actual);

        $actual = $shouldHashBody->invokeArgs($signer, ['POST']);
        $this->assertTrue($actual);
    }

    public function testGetPrivateKey(): void
    {
        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'resources', 'privatekey.pem']);
        $getPrivateKeyMethod = $this->getPrivateMethod(Signer::class, 'getPrivateKey');

        $signer = new Signer(
            MockKeyProvider::OCI_TENANCY_ID, MockKeyProvider::OCI_USER_ID, MockKeyProvider::OCI_KEY_FINGERPRINT, $filename
        );

        $actual = $getPrivateKeyMethod->invokeArgs($signer, []);
        $this->assertEquals(MockKeyProvider::OCI_PRIVATE_KEY, $actual);
    }

    public function testGetBodyHashBase64(): void
    {
        $signer = new Signer(
            MockKeyProvider::OCI_TENANCY_ID, MockKeyProvider::OCI_USER_ID, MockKeyProvider::OCI_KEY_FINGERPRINT, null
        );

        $actual = $signer->getBodyHashBase64(self::TEST_BODY);
        $this->assertEquals('V9Z20UJTvkvpJ50flBzKE32+6m2zJjweHpDMX/U4Uy0=', $actual);
    }

    public function testGetHeadersToSign(): void
    {
        $signer = new Signer(
            MockKeyProvider::OCI_TENANCY_ID, MockKeyProvider::OCI_USER_ID, MockKeyProvider::OCI_KEY_FINGERPRINT, null
        );
        $headersToSign = $this->getPrivateMethod(Signer::class, 'getHeadersToSign');
        $actual = $headersToSign->invokeArgs(
            $signer,
            [
                self::TEST_URL,
                'GET',
                null,
                Signer::CONTENT_TYPE_APPLICATION_JSON,
                self::DATE_STRING,
            ]
        );
        $this->assertEquals(explode(' ', self::GENERIC_HEADERS), array_keys($actual));

        $signer = new Signer(
            MockKeyProvider::OCI_TENANCY_ID, MockKeyProvider::OCI_USER_ID, MockKeyProvider::OCI_KEY_FINGERPRINT, null
        );
        $actual = $headersToSign->invokeArgs(
            $signer,
            [
                self::TEST_URL2,
                'POST',
                self::TEST_BODY,
                Signer::CONTENT_TYPE_APPLICATION_JSON,
                self::DATE_STRING,
            ]
        );
        $this->assertEquals(explode(' ', self::BODY_HEADERS), array_keys($actual));
    }

    public function testGetSigningString(): void
    {
        $signer = new Signer(
            MockKeyProvider::OCI_TENANCY_ID, MockKeyProvider::OCI_USER_ID, MockKeyProvider::OCI_KEY_FINGERPRINT, null
        );
        $actual = $signer->getSigningString(
            self::TEST_URL, 'GET', null, Signer::CONTENT_TYPE_APPLICATION_JSON, self::DATE_STRING
        );
        $this->assertEquals(self::EXPECTED_SIGNING_STRING, $actual);

        $signer = new Signer(
            MockKeyProvider::OCI_TENANCY_ID, MockKeyProvider::OCI_USER_ID, MockKeyProvider::OCI_KEY_FINGERPRINT, null
        );
        $actual = $signer->getSigningString(
            self::TEST_URL2, 'POST', self::TEST_BODY, Signer::CONTENT_TYPE_APPLICATION_JSON, self::DATE_STRING
        );
        $this->assertEquals(self::EXPECTED_SIGNING_STRING2, $actual);
    }

    public function testCalculateSignature(): void
    {
        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'resources', 'privatekey.pem']);

        $signer = new Signer(
            MockKeyProvider::OCI_TENANCY_ID, MockKeyProvider::OCI_USER_ID, MockKeyProvider::OCI_KEY_FINGERPRINT, $filename
        );

        $actual = $signer->calculateSignature(self::EXPECTED_SIGNING_STRING, MockKeyProvider::OCI_PRIVATE_KEY);
        $this->assertEquals(self::EXPECTED_SIGNATURE, $actual);

        $signer = new Signer(
            MockKeyProvider::OCI_TENANCY_ID, MockKeyProvider::OCI_USER_ID, MockKeyProvider::OCI_KEY_FINGERPRINT, $filename
        );

        $actual = $signer->calculateSignature(self::EXPECTED_SIGNING_STRING2, MockKeyProvider::OCI_PRIVATE_KEY);
        $this->assertEquals(self::EXPECTED_SIGNATURE2, $actual);
    }

    public function testGetKeyId(): void
    {
        $signer = new Signer(
            MockKeyProvider::OCI_TENANCY_ID, MockKeyProvider::OCI_USER_ID, MockKeyProvider::OCI_KEY_FINGERPRINT, null
        );

        $expected = MockKeyProvider::OCI_TENANCY_ID . '/' . MockKeyProvider::OCI_USER_ID . '/' . MockKeyProvider::OCI_KEY_FINGERPRINT;
        $actual = $signer->getKeyId();
        $this->assertEquals($expected, $actual);
    }

    public function testGetAuthorizationHeader(): void
    {
        $signer = new Signer(
            MockKeyProvider::OCI_TENANCY_ID, MockKeyProvider::OCI_USER_ID, MockKeyProvider::OCI_KEY_FINGERPRINT, null
        );
        $keyId = $signer->getKeyId();

        $headersString = self::GENERIC_HEADERS;
        $signature = self::EXPECTED_SIGNATURE;
        $expected = "Authorization: Signature version=\"1\",keyId=\"$keyId\",algorithm=\"rsa-sha256\",headers=\"$headersString\",signature=\"$signature\"";
        $actual = $signer->getAuthorizationHeader($keyId, $headersString, $signature);
        $this->assertEquals($expected, $actual);

        $signer = new Signer(
            MockKeyProvider::OCI_TENANCY_ID, MockKeyProvider::OCI_USER_ID, MockKeyProvider::OCI_KEY_FINGERPRINT, null
        );
        $headersString = self::BODY_HEADERS;
        $signature = self::EXPECTED_SIGNATURE2;
        $expected = "Authorization: Signature version=\"1\",keyId=\"$keyId\",algorithm=\"rsa-sha256\",headers=\"$headersString\",signature=\"$signature\"";
        $actual = $signer->getAuthorizationHeader($keyId, $headersString, $signature);
        $this->assertEquals($expected, $actual);
    }

    public function testGetHeaders(): void
    {
        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'resources', 'privatekey.pem']);

        $signer = new Signer(
            MockKeyProvider::OCI_TENANCY_ID, MockKeyProvider::OCI_USER_ID, MockKeyProvider::OCI_KEY_FINGERPRINT, $filename
        );
        $keyId = $signer->getKeyId();

        $authorization = $this->getAuthorizationHeader($keyId, self::GENERIC_HEADERS, self::EXPECTED_SIGNATURE);
        $expected = [
            'date: ' . self::DATE_STRING,
            'host: iaas.us-phoenix-1.oraclecloud.com',
            $authorization
        ];
        $actual = $signer->getHeaders(
            self::TEST_URL, 'GET', null, Signer::CONTENT_TYPE_APPLICATION_JSON, self::DATE_STRING
        );
        $this->assertEquals($expected, $actual);

        $signer = new Signer(
            MockKeyProvider::OCI_TENANCY_ID, MockKeyProvider::OCI_USER_ID, MockKeyProvider::OCI_KEY_FINGERPRINT, $filename
        );
        $keyId = $signer->getKeyId();

        $authorization = $this->getAuthorizationHeader($keyId, self::BODY_HEADERS, self::EXPECTED_SIGNATURE2);
        $expected = [
            'date: ' . self::DATE_STRING,
            'host: iaas.us-phoenix-1.oraclecloud.com',
            'content-length: 316',
            'content-type: application/json',
            'x-content-sha256: V9Z20UJTvkvpJ50flBzKE32+6m2zJjweHpDMX/U4Uy0=',
            $authorization
        ];
        $actual = $signer->getHeaders(
            self::TEST_URL2, 'POST', self::TEST_BODY, Signer::CONTENT_TYPE_APPLICATION_JSON, self::DATE_STRING
        );
        $this->assertEquals($expected, $actual);
    }

    public function testInvalidUrl(): void
    {
        $signer = new Signer(
            MockKeyProvider::OCI_TENANCY_ID, MockKeyProvider::OCI_USER_ID, MockKeyProvider::OCI_KEY_FINGERPRINT, null

        );
        $this->expectException(SignerValidateException::class);
        $signer->getHeaders(
            'not url', 'GET', null, Signer::CONTENT_TYPE_APPLICATION_JSON, self::DATE_STRING
        );
    }

    public function testPrivateKeyLocationUrl(): void
    {
        $signer = new Signer(
            MockKeyProvider::OCI_TENANCY_ID,
            MockKeyProvider::OCI_USER_ID,
            MockKeyProvider::OCI_KEY_FINGERPRINT,
            getenv('OCI_PRIVATE_KEY_URL'),
        );

        $headers = $signer->getHeaders(
            self::TEST_URL, 'GET', null, Signer::CONTENT_TYPE_APPLICATION_JSON, self::DATE_STRING
        );
        $this->assertNotEmpty($headers);
    }

    public function testNoOciDataProvided(): void
    {
        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'resources', 'privatekey.pem']);
        $signer = new Signer(
            MockKeyProvider::OCI_TENANCY_ID, MockKeyProvider::OCI_USER_ID, null, $filename
        );
        // no fingerprint
        $this->expectException(SignerValidateException::class);
        $signer->getHeaders(
            self::TEST_URL, 'GET', null, Signer::CONTENT_TYPE_APPLICATION_JSON, self::DATE_STRING
        );
    }

    public function testNoPrivateKey(): void
    {
        $signer = new Signer(
            MockKeyProvider::OCI_TENANCY_ID, MockKeyProvider::OCI_USER_ID, MockKeyProvider::OCI_KEY_FINGERPRINT
            // no private key filename
        );
        $this->expectException(SignerValidateException::class);
        $signer->getHeaders(
            self::TEST_URL, 'GET', null, Signer::CONTENT_TYPE_APPLICATION_JSON, self::DATE_STRING
        );
    }

    public function testPrivateKeyNotFound(): void
    {
        $signer = new Signer(
            MockKeyProvider::OCI_TENANCY_ID, MockKeyProvider::OCI_USER_ID, MockKeyProvider::OCI_KEY_FINGERPRINT, 'no-such-file'
        );
        $this->expectException(PrivateKeyFileNotFoundException::class);
        $signer->getHeaders(
            self::TEST_URL, 'GET', null, Signer::CONTENT_TYPE_APPLICATION_JSON, self::DATE_STRING
        );
    }

    public function testSetKeyProvider(): void
    {
        $signer = new Signer(
            // no OCI_ values and private key passed
        );

        $keyProvider = new MockKeyProvider();
        $signer->setKeyProvider($keyProvider);
        $privateKey = $keyProvider->getPrivateKey();

        $actual = $signer->calculateSignature(self::EXPECTED_SIGNING_STRING, $privateKey);
        $this->assertEquals(self::EXPECTED_SIGNATURE, $actual);

        $actual = $signer->calculateSignature(self::EXPECTED_SIGNING_STRING2, $privateKey);
        $this->assertEquals(self::EXPECTED_SIGNATURE2, $actual);
    }

    public function testGetSigningHeadersNames(): void
    {
        $signer = new Signer();
        $actual = $signer->getSigningHeadersNames('GET');
        $this->assertEquals(explode(' ', self::GENERIC_HEADERS), $actual);

        $signer = new Signer();
        $actual = $signer->getSigningHeadersNames('POST');
        $this->assertEquals(explode(' ', self::BODY_HEADERS), $actual);
    }

    /**
     * @param string $keyId
     * @param string $headersString
     * @param string $signature
     * @return string
     */
    private function getAuthorizationHeader(string $keyId, string $headersString, string $signature): string
    {
        return "Authorization: Signature version=\"1\",keyId=\"$keyId\",algorithm=\"rsa-sha256\",headers=\"$headersString\",signature=\"$signature\"";
    }

    /**
     * @param string $className
     * @param string $methodName
     * @return ReflectionMethod
     * @throws ReflectionException
     */
    private function getPrivateMethod(string $className, string $methodName)
    {
        $class = new ReflectionClass($className);
        $method = $class->getMethod($methodName);
        $method->setAccessible(true);

        return $method;
    }
}
