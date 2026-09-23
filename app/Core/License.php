<?php
namespace Core;

use ElmahdiPay\ElmahdiPay;
use ElmahdiPay\DeviceId;
use ElmahdiPay\LicenseVerifier;
use ElmahdiPay\Exceptions\ElmahdiPayException;

class License
{
    const APP_SLUG = 'cashirak-pos';

    private static ?array $cache = null;

    public static function requireActive(): void
    {
        if (self::isActive()) return;

        $page     = basename((string)($_SERVER['PHP_SELF'] ?? ''));
        $excluded = ['license.php', 'install.php', 'logout.php'];

        if (!in_array($page, $excluded)) {
            header('Location: /license.php');
            exit;
        }
    }

    public static function isActive(): bool
    {
        $data = self::loadCache();
        if (!$data || ($data['status'] ?? '') !== 'active') return false;

        // 1. RSA-verify the signed token
        $token = (string)($data['license_key'] ?? '');
        $check = self::verifyToken($token);
        if (!$check['valid']) return false;

        // 2. App slug binding
        if (($check['payload']['app_slug'] ?? '') !== self::APP_SLUG) return false;

        // 3. Expiry (null = lifetime)
        $expiresAt = $check['payload']['expires_at'] ?? null;
        if ($expiresAt !== null && strtotime((string)$expiresAt) < time()) return false;

        // 4. Device binding
        if (($data['device_id'] ?? '') !== self::deviceId()) return false;

        // 5. Clock rollback check — block if system time went backwards
        $now      = time();
        $lastSeen = (int)($data['last_seen_time'] ?? 0);
        if ($lastSeen > 0 && $now < $lastSeen) return false;

        // 6. Persist current time as the new floor
        $data['last_seen_time'] = $now;
        self::saveCache($data);

        return true;
    }

    public static function activate(string $licenseKey): array
    {
        $licenseKey = trim($licenseKey);
        if ($licenseKey === '') {
            return ['result' => 'invalid', 'message' => 'مفتاح الترخيص فارغ'];
        }

        // Verify signature locally before touching the network
        $check = self::verifyToken($licenseKey);
        if (!$check['valid']) {
            return ['result' => 'invalid', 'message' => 'مفتاح الترخيص غير صالح — تحقق من النسخ'];
        }
        if (($check['payload']['app_slug'] ?? '') !== self::APP_SLUG) {
            return ['result' => 'invalid', 'message' => 'مفتاح الترخيص لتطبيق مختلف'];
        }

        try {
            $pay    = new ElmahdiPay(self::APP_SLUG);
            $device = self::deviceId();
            $result = $pay->activateLicense($licenseKey, $device, 'Cashirak POS');
        } catch (ElmahdiPayException $e) {
            return ['result' => 'error', 'message' => $e->getMessage()];
        }

        if (in_array($result['result'], ['activated', 'already_active'])) {
            self::saveCache([
                'license_key'    => $licenseKey,
                'status'         => 'active',
                'device_id'      => self::deviceId(),
                'activated_at'   => time(),
                'last_seen_time' => time(),
            ]);
        }

        return $result;
    }

    public static function getPaymentMethods(): array
    {
        try {
            $pay = new ElmahdiPay(self::APP_SLUG);
            return $pay->getPaymentMethods();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function createPurchaseOrder(array $data): array
    {
        $pay = new ElmahdiPay(self::APP_SLUG);
        return $pay->createOrder($data);
    }

    public static function checkPurchaseStatus(string $reference): array
    {
        $pay = new ElmahdiPay(self::APP_SLUG);
        return $pay->checkOrderStatus($reference);
    }

    public static function deviceId(): string
    {
        return DeviceId::getOrCreate(STORAGE_PATH . '/.device_id');
    }

    public static function getInfo(): array
    {
        return self::loadCache() ?? ['status' => 'none'];
    }

    // ── Private ──────────────────────────────────────────────────────────────────

    private static function verifyToken(string $token): array
    {
        $invalid = ['valid' => false, 'payload' => null];
        if ($token === '') return $invalid;

        $pem = self::loadPublicKey();
        if ($pem === '') return $invalid;

        try {
            $verifier = new LicenseVerifier($pem);
            return $verifier->verify($token);
        } catch (\Throwable) {
            return $invalid;
        }
    }

    private static function loadPublicKey(): string
    {
        $path = ROOT_PATH . '/certs/license_public.pem';
        if (!file_exists($path)) return '';
        return (string)file_get_contents($path);
    }

    private static function loadCache(): ?array
    {
        if (self::$cache !== null) return self::$cache;
        $path = STORAGE_PATH . '/.license_data';
        if (!file_exists($path)) return null;
        $data = @json_decode(@file_get_contents($path), true);
        if (!is_array($data)) { self::$cache = null; return null; }
        self::$cache = $data;
        return $data;
    }

    private static function saveCache(array $data): void
    {
        self::$cache = $data;
        file_put_contents(
            STORAGE_PATH . '/.license_data',
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }
}
