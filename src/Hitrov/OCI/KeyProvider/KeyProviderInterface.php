<?php
declare(strict_types=1);

namespace Hitrov\OCI\KeyProvider;

interface KeyProviderInterface
{
    /**
     * Returns the contents of privatekey.pem
     *
     * @return string
     */
    public function getPrivateKey(): string;

    /**
     * @return string
     */
    public function getKeyId(): string;
}
