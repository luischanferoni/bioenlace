<?php

namespace common\components\Clinical\Prescription\Support;

use common\models\Clinical\ElectronicPrescription;

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
}
