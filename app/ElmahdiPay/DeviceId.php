<?php

declare(strict_types=1);

namespace ElmahdiPay;

class DeviceId
{
    public static function getOrCreate(string $storagePath): string
    {
        if (file_exists($storagePath)) {
            $id = trim((string) file_get_contents($storagePath));
            if ($id !== '') {
                return $id;
            }
        }

        $id  = self::uuidV4();
        $dir = dirname($storagePath);

        if ($dir !== '' && !is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($storagePath, $id, LOCK_EX);

        return $id;
    }

    private static function uuidV4(): string
    {
        $bytes    = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf('%s-%s-%s-%s-%s',
            substr($hex,  0, 8), substr($hex,  8, 4),
            substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }
}
