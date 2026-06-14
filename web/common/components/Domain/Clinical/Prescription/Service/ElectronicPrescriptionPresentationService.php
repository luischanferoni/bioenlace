<?php

namespace common\components\Domain\Clinical\Prescription\Service;

use common\components\Domain\Clinical\Prescription\Support\PrescriptionDocumentSupport;
use common\models\Clinical\ElectronicPrescription;
use common\models\Person\Persona;

final class ElectronicPrescriptionPresentationService
{
    public function formatDetailMessage(ElectronicPrescription $rx): string
    {
        $lines = [];
        $num = trim((string) ($rx->prescription_number ?? ''));
        $lines[] = $num !== '' ? 'Receta ' . $num : 'Receta electrónica';

        if ($rx->issued_at !== null && $rx->issued_at !== '') {
            $lines[] = '';
            $lines[] = 'Emitida: ' . (string) $rx->issued_at;
        }
        if ($rx->valid_until !== null && $rx->valid_until !== '') {
            $lines[] = 'Válida hasta: ' . (string) $rx->valid_until;
        }

        $diag = trim((string) ($rx->diagnosis_display ?? ''));
        if ($diag !== '') {
            $lines[] = '';
            $lines[] = 'Diagnóstico:';
            $lines[] = $diag;
        }

        $lines[] = '';
        $lines[] = 'Medicación:';
        $hasItems = false;
        foreach ($rx->items as $item) {
            $hasItems = true;
            $label = trim((string) ($item->medication_display ?? 'Medicamento'));
            $dose = trim((string) ($item->dosage_text ?? ''));
            $piece = '* ' . ($label !== '' ? $label : 'Ítem');
            if ($dose !== '') {
                $piece .= ' — ' . $dose;
            }
            $lines[] = $piece;
        }
        if (!$hasItems) {
            $lines[] = '* Sin ítems registrados.';
        }

        if ($rx->verification_token !== null && $rx->verification_token !== '') {
            $lines[] = '';
            $lines[] = 'Código de verificación:';
            $lines[] = (string) $rx->verification_token;
            $verifyUrl = PrescriptionDocumentSupport::buildVerificationUrl($rx);
            if ($verifyUrl !== null) {
                $lines[] = 'Verificación (URL):';
                $lines[] = $verifyUrl;
            }
        }

        $notes = trim((string) ($rx->notes ?? ''));
        if ($notes !== '') {
            $lines[] = '';
            $lines[] = 'Observaciones:';
            $lines[] = $notes;
        }

        if ($rx->status === 'cancelled') {
            $lines[] = '';
            $lines[] = 'Estado: ANULADA';
            $reason = trim((string) ($rx->cancellation_reason ?? ''));
            if ($reason !== '') {
                $lines[] = $reason;
            }
        }

        return implode("\n", $lines);
    }

    public function formatPatientLabel(?Persona $persona): string
    {
        if ($persona === null) {
            return 'Paciente';
        }
        $name = trim($persona->nombre . ' ' . $persona->apellido);
        if ($persona->documento !== null && $persona->documento !== '') {
            $name .= ' — DNI ' . $persona->documento;
        }

        return $name !== '' ? $name : 'Paciente';
    }
}
