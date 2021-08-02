<?php
declare(strict_types=1);

namespace Hitrov\Test;

use PHPUnit\Framework\TestCase;

class KeyProviderTest extends TestCase
{
    public function testGetPrivateKey(): void
    {
        $keyProvider = new MockKeyProvider();

        $privateKey = $keyProvider->getPrivateKey();
        $this->assertEquals(MockKeyProvider::OCI_PRIVATE_KEY, $privateKey);

        $keyFileProvider = new MockKeyFileProvider();

        $privateKey = $keyFileProvider->getPrivateKey();
        $this->assertEquals(MockKeyProvider::OCI_PRIVATE_KEY, $privateKey);
    }

    public function testGetKeyId(): void
    {
        $keyProvider = new MockKeyProvider();
        $expectedKeyId = 'ocid1.tenancy.oc1..aaaaaaaaba3pv6wkcr4jqae5f15p2b2m2yt2j6rx32uzr4h25vqstifsfdsq/ocid1.user.oc1..aaaaaaaat5nvwcna5j6aqzjcaty5eqbb6qt2jvpkanghtgdaqedqw3rynjq/20:3b:97:13:55:1c:5b:0d:d3:37:d8:50:4e:c5:3a:34';

        $keyId = $keyProvider->getKeyId();
        $this->assertEquals($expectedKeyId, $keyId);

        $keyFileProvider = new MockKeyFileProvider();

        $keyId = $keyFileProvider->getKeyId();
        $this->assertEquals($expectedKeyId, $keyId);
    }
}
