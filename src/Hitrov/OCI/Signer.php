<?php
declare(strict_types=1);

namespace Hitrov\OCI;

use Hitrov\OCI\Exception\PrivateKeyFileNotFoundException;
use Hitrov\OCI\Exception\SignerValidateException;
use Hitrov\OCI\Exception\SigningValidationFailedException;
use Hitrov\OCI\KeyProvider\KeyProviderInterface;

class Signer
{
    const OCI_TENANCY_ID = 'OCI_TENANCY_ID';
    const OCI_USER_ID = 'OCI_USER_ID';
    const OCI_KEY_FINGERPRINT = 'OCI_KEY_FINGERPRINT';
    const OCI_PRIVATE_KEY_FILENAME = 'OCI_PRIVATE_KEY_FILENAME';

    const SIGNING_HEADER_DATE = 'date';
    const SIGNING_HEADER_REQUEST_TARGET = '(request-target)';
    const SIGNING_HEADER_HOST = 'host';
    const SIGNING_HEADER_CONTENT_LENGTH = 'content-length';
    const SIGNING_HEADER_CONTENT_TYPE = 'content-type';
    const SIGNING_HEADER_X_CONTENT_SHA256 = 'x-content-sha256';

    const CONTENT_TYPE_APPLICATION_JSON = 'application/json';

    private const DATE_FORMAT_RFC7231 = 'D, d M Y H:i:s \G\M\T';

    private string $ociTenancyId;
    private string $ociUserId;
    private string $ociKeyFingerPrint;
    private string $ociPrivateKeyLocation;

    /**
     * @var array<string, string>
     */
    private array $headersToSign;

    private KeyProviderInterface $keyProvider;

    /**
     * Signer constructor.
     * @param string|null $ociTenancyId
     * @param string|null $ociUserId
     * @param string|null $keyFingerPrint
     * @param string|null $privateKeyLocation
     */
    public function __construct(?string $ociTenancyId = null, ?string $ociUserId = null, ?string $keyFingerPrint = null, ?string $privateKeyLocation = null)
    {
        $this->ociTenancyId = $ociTenancyId ?? getenv(self::OCI_TENANCY_ID) ?: '';
        $this->ociUserId = $ociUserId ?? getenv(self::OCI_USER_ID) ?: '';
        $this->ociKeyFingerPrint = $keyFingerPrint ?? getenv(self::OCI_KEY_FINGERPRINT) ?: '';
        $this->ociPrivateKeyLocation = $privateKeyLocation ?? getenv(self::OCI_PRIVATE_KEY_FILENAME) ?: '';
    }

    /**
     * @param string $url
     * @param string $method
     * @param string|null $body
     * @param string|null $contentType
     * @param string|null $dateString
     * @return string[]
     * @throws PrivateKeyFileNotFoundException
     * @throws SignerValidateException
     * @throws SigningValidationFailedException
     */
    public function getHeaders(string $url, string $method = 'GET', ?string $body = null, ?string $contentType = self::CONTENT_TYPE_APPLICATION_JSON, ?string $dateString = null): array
    {
        $this->validateParameters($url);

        $headersToSign = $this->getHeadersToSign($url, $method, $body, $contentType, $dateString);
        $signingString = $this->getSigningString($url, $method, $body, $contentType, $dateString);
        $privateKey = $this->getPrivateKey();
        $signature = $this->calculateSignature($signingString, $privateKey);

        $headers = [];
        foreach ($headersToSign as $headerName => $value) {
            if ($headerName === self::SIGNING_HEADER_REQUEST_TARGET) {
                continue;
            }
            $headers[] = "$headerName: $value";
        }
        $keyId = $this->getKeyId();
        $headers[] = $this->getAuthorizationHeader($keyId, implode(' ', array_keys($headersToSign)), $signature);

        return $headers;
    }

    /**
     * @param string $signingString
     * @param string $privateKey
     * @return string
     * @throws SigningValidationFailedException
     */
    public function calculateSignature(string $signingString, string $privateKey): string
    {
        // fetch private key from file and ready it
        $privateKeyId = openssl_pkey_get_private($privateKey);

        // compute signature
        $result = openssl_sign($signingString, $binarySignature, $privateKeyId, OPENSSL_ALGO_SHA256); // sha256WithRSAEncryption
        if (!$result) {
            throw new SigningValidationFailedException('Cannot generate signature.');
        }

        $signatureBase64 = base64_encode($binarySignature);

        // verify
        $details = openssl_pkey_get_details($privateKeyId);
        $public_key_pem = $details['key'];
        $r = openssl_verify($signingString, $binarySignature, $public_key_pem, "sha256WithRSAEncryption");
        if ($r !== 1) {
            throw new SigningValidationFailedException('Cannot verify signature.');
        }

        return $signatureBase64;
    }

    /**
     * @param string $url
     * @param string $method
     * @param string|null $body
     * @param string|null $contentType
     * @param string|null $dateString
     * @return string
     */
    public function getSigningString(
        string $url,
        string $method = 'GET',
        ?string $body = null,
        ?string $contentType = self::CONTENT_TYPE_APPLICATION_JSON,
        ?string $dateString = null
    ): string
    {
        $headersToSign = $this->getHeadersToSign($url, $method, $body, $contentType, $dateString);
        $signingHeaders = [];
        foreach ($headersToSign as $header => $value) {
            $signingHeaders[] = "$header: $value";
        }

        return implode("\n", $signingHeaders);
    }

    /**
     * @return string
     */
    public function getKeyId(): string
    {
        if (isset($this->keyProvider)) {
            return $this->keyProvider->getKeyId();
        }

        return implode('/', [
            $this->ociTenancyId,
            $this->ociUserId,
            $this->ociKeyFingerPrint,
        ]);
    }

    /**
     * @param string|null $body
     * @return string
     */
    public function getBodyHashBase64(?string $body): string
    {
        $bodyHashBinary = hash('sha256', $body, true);

        return base64_encode($bodyHashBinary);
    }

    /**
     * @param string $method
     * @return string[]
     */
    public function getSigningHeadersNames(string $method): array
    {
        $signingHeaders = $this->getGenericHeadersNames();
        if ($this->shouldHashBody($method)) {
            $bodyHeaders = $this->getBodyHeadersNames();
            $signingHeaders = array_merge($signingHeaders, $bodyHeaders);
        }

        return $signingHeaders;
    }

    /**
     * @param KeyProviderInterface $keyProvider
     */
    public function setKeyProvider(KeyProviderInterface $keyProvider): void
    {
        $this->keyProvider = $keyProvider;
    }

    /**
     * @param string $keyId
     * @param string $signedHeaders
     * @param string $signatureBase64
     * @return string
     */
    public function getAuthorizationHeader(string $keyId, string $signedHeaders, string $signatureBase64): string
    {
        return "Authorization: Signature version=\"1\",keyId=\"$keyId\",algorithm=\"rsa-sha256\",headers=\"$signedHeaders\",signature=\"$signatureBase64\"";
    }

    protected function getCurrentDate(): string {
        return gmdate(self::DATE_FORMAT_RFC7231); // Thu, 05 Jan 2014 21:31:40 GMT
    }

    /**
     * @return string
     * @throws PrivateKeyFileNotFoundException
     */
    private function getPrivateKey(): ?string
    {
        if (isset($this->keyProvider)) {
            return $this->keyProvider->getPrivateKey();
        }

        if ($this->ociPrivateKeyLocation) {
            if (filter_var($this->ociPrivateKeyLocation, FILTER_VALIDATE_URL)) {
                return file_get_contents($this->ociPrivateKeyLocation);
            }

            if (!file_exists($this->ociPrivateKeyLocation)) {
                throw new PrivateKeyFileNotFoundException("Private key file does not exist: $this->ociPrivateKeyLocation");
            }

            return file_get_contents($this->ociPrivateKeyLocation);
        }

        return null;
    }

    /**
     * @param string $method
     * @return bool
     */
    private function shouldHashBody(string $method): bool
    {
        return in_array(strtoupper($method), ['POST', 'PUT', 'PATCH']);
    }

    /**
     * @param string $url
     * @param string $method
     * @param string|null $body
     * @param string|null $contentType
     * @param string|null $dateString
     * @return array<string, string>
     */
    private function getHeadersToSign(string $url, string $method, ?string $body, ?string $contentType, ?string $dateString = null): array
    {
        $parsed = parse_url($url);

        $headersMap = [];
        $headersNames = $this->getSigningHeadersNames($method);
        foreach ($headersNames as $headerName) {
            switch ($headerName) {
                case self::SIGNING_HEADER_DATE:
                    if (!$dateString) {
                        $dateString = $this->getCurrentDate();
                    }
                    $headersMap[self::SIGNING_HEADER_DATE] = $dateString;
                    break;
                case self::SIGNING_HEADER_REQUEST_TARGET:
                    $uri = $parsed['path'] ?? '';
                    if (!empty($parsed['query'])) {
                        $uri .= '?' . $parsed['query'];
                    }
                    $headersMap[self::SIGNING_HEADER_REQUEST_TARGET] = strtolower($method) . " $uri";
                    break;
                case self::SIGNING_HEADER_HOST:
                    $headersMap[self::SIGNING_HEADER_HOST] = $parsed['host'] ?? '';
                    break;
                case self::SIGNING_HEADER_CONTENT_LENGTH:
                    $contentLength = $this->getContentLength($body);
                    $headersMap[self::SIGNING_HEADER_CONTENT_LENGTH] = $contentLength;
                    break;
                case self::SIGNING_HEADER_CONTENT_TYPE:
                    $headersMap[self::SIGNING_HEADER_CONTENT_TYPE] = $contentType;
                    break;
                case self::SIGNING_HEADER_X_CONTENT_SHA256:
                    $bodyHashBase64 = $this->getBodyHashBase64($body);
                    $headersMap[self::SIGNING_HEADER_X_CONTENT_SHA256] = $bodyHashBase64;
                    break;
                default:
                    break;
            }
        }

        $this->headersToSign = $headersMap;

        return $this->headersToSign;
    }

    /**
     * @return string[]
     */
    private function getGenericHeadersNames(): array
    {
        return [
            self::SIGNING_HEADER_DATE,
            self::SIGNING_HEADER_REQUEST_TARGET,
            self::SIGNING_HEADER_HOST,
        ];
    }

    /**
     * @return string[]
     */
    private function getBodyHeadersNames(): array
    {
        return [
            self::SIGNING_HEADER_CONTENT_LENGTH,
            self::SIGNING_HEADER_CONTENT_TYPE,
            self::SIGNING_HEADER_X_CONTENT_SHA256,
        ];
    }

    /**
     * @param string $url
     * @throws PrivateKeyFileNotFoundException
     * @throws SignerValidateException
     */
    private function validateParameters(string $url): void
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new SignerValidateException("URL is invalid: $url");
        }

        if (isset($this->keyProvider)) {
            return;
        }

        if (
            empty($this->ociUserId) ||
            empty($this->ociTenancyId) ||
            empty($this->ociKeyFingerPrint) ||
            empty($this->ociPrivateKeyLocation)
        ) {
            throw new SignerValidateException('OCI User ID, tenancy ID, key fingerprint and private key filename are required.');
        }

        if (!filter_var($this->ociPrivateKeyLocation, FILTER_VALIDATE_URL) && !file_exists($this->ociPrivateKeyLocation)) {
            throw new PrivateKeyFileNotFoundException("Private key file does not exist: $this->ociPrivateKeyLocation");
        }
    }

    /**
     * @param string|null $body
     * @return int
     */
    private function getContentLength(?string $body): int
    {
        return strlen($body);
    }
}
