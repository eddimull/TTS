<?php

namespace Tests\Concerns;

/**
 * Gives Passport an in-memory RSA key pair (via config, like the
 * PASSPORT_PRIVATE_KEY / PASSPORT_PUBLIC_KEY env vars in production), so
 * tests don't depend on `php artisan passport:keys` files in storage/.
 */
trait UsesPassportKeys
{
    private static ?array $passportKeyPair = null;

    protected function setUpPassportKeys(): void
    {
        if (self::$passportKeyPair === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $private);
            self::$passportKeyPair = [$private, openssl_pkey_get_details($key)['key']];
        }

        config([
            'passport.private_key' => self::$passportKeyPair[0],
            'passport.public_key' => self::$passportKeyPair[1],
        ]);
    }
}
