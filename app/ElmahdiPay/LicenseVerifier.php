<?php

declare(strict_types=1);

namespace ElmahdiPay;

class LicenseVerifier
{
    /** @var \OpenSSLAsymmetricKey|resource */
    private mixed $publicKey;

    public function __construct(string $publicKeyPem)
    {
        $key = openssl_pkey_get_public($publicKeyPem);
        if ($key === false) {
            throw new \InvalidArgumentException(
                'Invalid public key PEM: ' . (openssl_error_string() ?: 'unknown error')
            );
        }
        $this->publicKey = $key;
    }

    public function verify(string $licenseKey): array
    {
        $invalid = ['valid' => false, 'payload' => null];

        $parts = explode('.', $licenseKey, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return $invalid;
        }

        $payloadJson = $this->base64UrlDecode($parts[0]);
        $signature   = $this->base64UrlDecode($parts[1]);

        if ($payloadJson === '' || $signature === '') {
            return $invalid;
        }

        $result = openssl_verify($payloadJson, $signature, $this->publicKey, OPENSSL_ALGO_SHA256);
        if ($result !== 1) {
            return $invalid;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return $invalid;
        }

        return ['valid' => true, 'payload' => $payload];
    }

    private function base64UrlDecode(string $s): string
    {
        $pad = strlen($s) % 4;
        if ($pad !== 0) {
            $s .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode(strtr($s, '-_', '+/'), true);
        return $decoded === false ? '' : $decoded;
    }
}
