<?php

namespace common\components\Domain\Clinical\Prescription\Support;

use common\models\Clinical\ElectronicPrescription;
use Yii;

final class PrescriptionDocumentSupport
{
    public const SIGNATURE_PROVIDER_INTERNAL = 'bioenlace-internal';

    public static function generateVerificationToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function computeDocumentHash(string $canonicalJson): string
    {
        return hash('sha256', $canonicalJson);
    }

    public static function applyIssuanceSecurityFields(ElectronicPrescription $rx, string $fhirBundleJson): void
    {
        $rx->verification_token = self::generateVerificationToken();
        $rx->document_hash = self::computeDocumentHash($fhirBundleJson);
        $rx->signature_provider = self::SIGNATURE_PROVIDER_INTERNAL;
        $rx->signed_at = date('Y-m-d H:i:s');
    }

    public static function buildVerificationPayload(ElectronicPrescription $rx): string
    {
        $parts = array_filter([
            (string) ($rx->prescription_number ?? ''),
            (string) ($rx->verification_token ?? ''),
            (string) ($rx->document_hash ?? ''),
        ]);

        return implode('|', $parts);
    }

    /**
     * URL pública para verificación (QR / farmacia). Requiere params[recetaDigitalRepository][verificationPublicBaseUrl].
     */
    public static function buildVerificationUrl(ElectronicPrescription $rx): ?string
    {
        $token = trim((string) ($rx->verification_token ?? ''));
        if ($token === '') {
            return null;
        }

        $base = self::resolveVerificationPublicBaseUrl();
        if ($base === null) {
            return null;
        }

        return $base . '/clinical/electronic-prescription/verificar-receta?token=' . rawurlencode($token);
    }

    public static function resolveVerificationPublicBaseUrl(): ?string
    {
        $config = Yii::$app->params['recetaDigitalRepository'] ?? [];
        $base = isset($config['verificationPublicBaseUrl']) ? trim((string) $config['verificationPublicBaseUrl']) : '';
        if ($base === '') {
            return null;
        }
        $base = rtrim($base, '/');
        if (!str_ends_with($base, '/api/v1')) {
            if (str_ends_with($base, '/api')) {
                $base .= '/v1';
            } elseif (!str_contains($base, '/api/v1')) {
                $base .= '/api/v1';
            }
        }

        return $base;
    }
}
