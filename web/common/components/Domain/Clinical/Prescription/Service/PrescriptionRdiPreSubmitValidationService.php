<?php

namespace common\components\Domain\Clinical\Prescription\Service;

use common\components\Domain\Clinical\Prescription\Enum\PrescriptionLegalStatus;
use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\models\Clinical\ElectronicPrescription;
use common\models\Clinical\ElectronicPrescriptionItem;
use common\models\ProfesionalEfectorServicio;

/**
 * Validaciones declarativas antes de emitir receta hacia RDI (agente E03).
 */
final class PrescriptionRdiPreSubmitValidationService
{
    public const AGENT_ID = 'prescription-rdi-pre-submit';

    /**
     * @return list<string> Errores bloqueantes (vacío = OK).
     */
    public function validate(ElectronicPrescription $rx): array
    {
        $config = AutonomousAgentMetadata::loadAgent(self::AGENT_ID);
        if ($config === null) {
            return [];
        }

        $checks = is_array($config['checks'] ?? null) ? $config['checks'] : [];
        $errors = [];

        if (!empty($checks['require_prescriber_pes']) && (int) ($rx->id_profesional_efector_servicio ?? 0) <= 0) {
            $errors[] = 'Falta el profesional prescriptor (PES) en la receta.';
        } elseif (!empty($checks['require_prescriber_pes'])) {
            $pes = ProfesionalEfectorServicio::findOne([
                'id' => (int) $rx->id_profesional_efector_servicio,
                'deleted_at' => null,
            ]);
            if ($pes === null) {
                $errors[] = 'El profesional prescriptor no es válido.';
            }
        }

        if (!empty($checks['require_diagnosis_code'])) {
            $code = trim((string) ($rx->diagnosis_code ?? ''));
            if ($code === '') {
                $errors[] = 'Falta el diagnóstico codificado (requerido para receta digital).';
            }
        }

        $minDxLen = (int) ($checks['min_diagnosis_display_length'] ?? 0);
        if ($minDxLen > 0) {
            $display = trim((string) ($rx->diagnosis_display ?? ''));
            if (mb_strlen($display) < $minDxLen) {
                $errors[] = 'El texto del diagnóstico es demasiado corto.';
            }
        }

        $items = ElectronicPrescriptionItem::find()
            ->andWhere(['electronic_prescription_id' => (int) $rx->id, 'deleted_at' => null])
            ->orderBy(['line_number' => SORT_ASC])
            ->all();

        if ($items === []) {
            $errors[] = 'La receta no tiene medicamentos.';

            return $errors;
        }

        foreach ($items as $item) {
            $line = (int) $item->line_number;
            if (!empty($checks['require_medication_code'])) {
                $medCode = trim((string) ($item->medication_code ?? ''));
                if ($medCode === '') {
                    $errors[] = "Línea {$line}: falta código de medicamento (nomenclador).";
                }
            }
            if (!empty($checks['require_dosage_text'])) {
                $dosage = trim((string) ($item->dosage_text ?? ''));
                if ($dosage === '') {
                    $errors[] = "Línea {$line}: falta posología.";
                }
            }
        }

        $dupHours = (int) ($checks['block_duplicate_medication_hours'] ?? 0);
        if ($dupHours > 0) {
            $errors = array_merge($errors, $this->duplicateMedicationErrors($rx, $items, $dupHours));
        }

        return $errors;
    }

    /**
     * @param ElectronicPrescriptionItem[] $items
     * @return list<string>
     */
    private function duplicateMedicationErrors(
        ElectronicPrescription $rx,
        array $items,
        int $hours
    ): array {
        $errors = [];
        $since = date('Y-m-d H:i:s', time() - $hours * 3600);
        $codes = [];
        foreach ($items as $item) {
            $code = trim((string) ($item->medication_code ?? ''));
            if ($code !== '') {
                $codes[$code] = (int) $item->line_number;
            }
        }
        if ($codes === []) {
            return [];
        }

        $recent = ElectronicPrescription::find()
            ->alias('rx')
            ->innerJoin(['it' => ElectronicPrescriptionItem::tableName()], 'it.electronic_prescription_id = rx.id')
            ->andWhere([
                'rx.subject_persona_id' => (int) $rx->subject_persona_id,
                'rx.status' => PrescriptionLegalStatus::ISSUED,
                'rx.deleted_at' => null,
                'it.deleted_at' => null,
            ])
            ->andWhere(['>=', 'rx.issued_at', $since])
            ->andWhere(['!=', 'rx.id', (int) $rx->id])
            ->andWhere(['it.medication_code' => array_keys($codes)])
            ->select(['it.medication_code', 'it.medication_display'])
            ->asArray()
            ->all();

        foreach ($recent as $row) {
            $code = (string) ($row['medication_code'] ?? '');
            if ($code === '' || !isset($codes[$code])) {
                continue;
            }
            $display = (string) ($row['medication_display'] ?? $code);
            $errors[] = "Línea {$codes[$code]}: ya emitiste «{$display}» en las últimas {$hours} h.";
        }

        return $errors;
    }
}
