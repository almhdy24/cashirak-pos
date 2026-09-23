<?php

declare(strict_types=1);

namespace ElmahdiPay;

use ElmahdiPay\Exceptions\ElmahdiPayException;
use ElmahdiPay\Exceptions\ElmahdiPayValidationException;

class ElmahdiPay
{
    private const BASE_URL        = 'https://api.almhdy24.com/index.php/api/v1';
    private const TIMEOUT         = 30;
    private const MAX_PROOF_BYTES = 5 * 1024 * 1024;

    // Bundled Mozilla CA certificate bundle — applied automatically on Windows
    // where PHP's cURL ships without a default CA path.
    private const BUNDLED_CA_CERT = __DIR__ . '/../../certs/cacert.pem';

    /**
     * @param string|null $caBundle  CA bundle path. Defaults to bundled cacert.pem.
     *                               Pass null to let cURL use its system default.
     */
    public function __construct(
        private readonly string  $appSlug,
        private readonly ?string $caBundle = self::BUNDLED_CA_CERT,
    ) {}

    public static function generateIdempotencyKey(): string
    {
        $bytes    = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex      = bin2hex($bytes);
        return sprintf('%s-%s-%s-%s-%s',
            substr($hex,  0, 8), substr($hex,  8, 4),
            substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }

    public function getPaymentMethods(): array
    {
        $resp = $this->request('GET', '/pay/payment-methods');
        return $resp['data'] ?? [];
    }

    public function getExchangeRate(): ?float
    {
        $resp = $this->request('GET', '/pay/settings');
        $rate = $resp['data']['usd_to_sdg_rate'] ?? null;
        return $rate !== null ? (float) $rate : null;
    }

    public function createOrder(array $data): array
    {
        $this->validateProofImage($data['proof_image_path'] ?? null);

        if (empty($data['idempotency_key'])) {
            $data['idempotency_key'] = self::generateIdempotencyKey();
        }

        $proofPath = (string) $data['proof_image_path'];

        $fields = [
            'app_slug'          => $this->appSlug,
            'buyer_name'        => (string) ($data['buyer_name']        ?? ''),
            'buyer_contact'     => (string) ($data['buyer_contact']     ?? ''),
            'amount'            => (string) ($data['amount']            ?? ''),
            'currency'          => (string) ($data['currency']          ?? ''),
            'payment_method_id' => (string) ($data['payment_method_id'] ?? ''),
            'idempotency_key'   => (string) $data['idempotency_key'],
            'proof_file'        => new \CURLFile(
                $proofPath,
                $this->imageMimeType($proofPath),
                basename($proofPath)
            ),
        ];

        if (!empty($data['proof_note'])) {
            $fields['proof_note'] = (string) $data['proof_note'];
        }

        $resp = $this->request('POST', '/pay/orders', $fields, multipart: true);

        return [
            'reference'  => (string) ($resp['data']['reference'] ?? ''),
            'status'     => (string) ($resp['data']['status']    ?? ''),
            'idempotent' => (bool)   ($resp['idempotent']        ?? false),
        ];
    }

    public function checkOrderStatus(string $reference): array
    {
        $resp = $this->request('GET', '/pay/orders/' . rawurlencode($reference));
        return $resp['data'] ?? [];
    }

    public function activateLicense(
        string  $licenseKey,
        string  $deviceId,
        ?string $deviceLabel = null
    ): array {
        $body = ['license_key' => $licenseKey, 'device_id' => $deviceId];
        if ($deviceLabel !== null) {
            $body['device_label'] = $deviceLabel;
        }

        try {
            $resp = $this->request('POST', '/pay/licenses/activate', $body);
        } catch (ElmahdiPayException $e) {
            return $this->mapActivationError($e);
        }

        $reactivated = !empty($resp['reactivated']);

        return [
            'result' => $reactivated ? 'already_active' : 'activated',
            'data'   => $resp['data'] ?? null,
        ];
    }

    private function validateProofImage(mixed $path): void
    {
        if ($path === null || (string) $path === '') {
            throw new ElmahdiPayValidationException(
                'proof_image_path مطلوب — يرجى تحديد مسار ملف الإثبات'
            );
        }
        $path = (string) $path;
        if (!file_exists($path) || !is_readable($path)) {
            throw new ElmahdiPayValidationException(
                'ملف الإثبات غير موجود أو لا يمكن قراءته: ' . $path
            );
        }
        $size = filesize($path);
        if ($size === false || $size > self::MAX_PROOF_BYTES) {
            throw new ElmahdiPayValidationException(
                'حجم ملف الإثبات يتجاوز الحد المسموح به (5 ميجابايت).'
            );
        }
        $info = @getimagesize($path);
        if ($info === false) {
            throw new ElmahdiPayValidationException(
                'الملف ليس صورة صالحة. الأنواع المقبولة: JPG, PNG, WebP, GIF'
            );
        }
    }

    private function imageMimeType(string $path): string
    {
        $info = @getimagesize($path);
        return is_array($info) && isset($info['mime']) ? $info['mime'] : 'application/octet-stream';
    }

    private function resolveCaBundle(): ?string
    {
        if ($this->caBundle === null) {
            return null;
        }
        $path = realpath($this->caBundle);
        return ($path !== false && is_readable($path)) ? $path : null;
    }

    private function mapActivationError(ElmahdiPayException $e): array
    {
        $status  = $e->getHttpStatus();
        $message = $e->getMessage();
        $body    = $e->getDecodedBody();

        if ($status === 403) {
            if (str_contains($message, 'revoked'))                              return ['result' => 'revoked',       'message' => $message];
            if (str_contains($message, 'expired'))                              return ['result' => 'expired',       'message' => $message];
            if (str_contains($message, 'limit') || str_contains($message, 'Device limit'))
                return ['result' => 'limit_reached', 'message' => $message,
                        'max_devices' => (int)($body['max_devices'] ?? 0),
                        'active'      => (int)($body['active']      ?? 0)];
        }
        if ($status === 422 || $status === 404) {
            return ['result' => 'invalid', 'message' => $message];
        }
        throw $e;
    }

    private function request(
        string $method,
        string $path,
        array  $body      = [],
        bool   $multipart = false
    ): array {
        $url = self::BASE_URL . $path;
        $ch  = curl_init($url);

        // curl_init() returns false when cURL is unavailable or the URL is
        // invalid. Fail fast here — before curl_setopt() touches $body —
        // to avoid PHP's cURL extension calling getName() on a CURLFile
        // stored in $body while $ch is still false.
        if ($ch === false) {
            throw new ElmahdiPayException('تعذّر تهيئة اتصال cURL', 0);
        }

        $headers = ['Accept: application/json'];

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($multipart) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            } else {
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
            }
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        $caBundle = $this->resolveCaBundle();
        if ($caBundle !== null) {
            $opts[CURLOPT_CAINFO] = $caBundle;
        }

        curl_setopt_array($ch, $opts);

        $raw     = curl_exec($ch);
        $curlErr = curl_error($ch);
        $code    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErr !== '') {
            throw new ElmahdiPayException('cURL error: ' . $curlErr, 0);
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new ElmahdiPayException(
                'الاستجابة من الخادم غير صالحة (HTTP ' . $code . ')',
                $code, (string) $raw
            );
        }

        if ($code < 200 || $code >= 300) {
            $message = $decoded['message'] ?? null;
            if (!is_string($message)) {
                $errors  = $decoded['errors'] ?? [];
                $message = is_array($errors) ? implode('; ', $errors) : ('HTTP ' . $code);
            }
            throw new ElmahdiPayException($message, $code, (string) $raw, $decoded);
        }

        return $decoded;
    }
}
