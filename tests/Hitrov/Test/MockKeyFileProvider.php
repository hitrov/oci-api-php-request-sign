<?php
declare(strict_types=1);

namespace Hitrov\Test;

class MockKeyFileProvider extends MockKeyProvider
{
    /**
     * @return string
     */
    public function getPrivateKey(): string
    {
        $filename = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'resources', 'privatekey.pem']);
        return file_get_contents($filename);
    }

}
