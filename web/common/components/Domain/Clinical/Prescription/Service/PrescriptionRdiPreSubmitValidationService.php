<?php

namespace common\components\Domain\Clinical\Prescription\Service;

use common\components\Domain\Clinical\Prescription\Enum\PrescriptionLegalStatus;
use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\models\Clinical\ElectronicPrescription;
use common\models\Clinical\ElectronicPrescriptionItem;
use common\models\ProfesionalEfectorServicio;
use yii\base\Model;

/**
 * Validación pre-emisión RDI (agente E03).
 *
 * Integridad hard: escenarios Yii {@see ElectronicPrescription::SCENARIO_RDI_ISSUE}
 * / {@see ElectronicPrescriptionItem::SCENARIO_RDI_ISSUE} + chequeos de dominio.
 * YAML solo knobs de política (ventana anti-duplicado, largo mínimo de display).
 */
final class PrescriptionRdiPreSubmitValidationService
{
    public const AGENT_ID = 'prescription-rdi-pre-submit';

    public const DEFAULT_BLOCK_DUPLICATE_MEDICATION_HOURS = 24;

    public const DEFAULT_MIN_DIAGNOSIS_DISPLAY_LENGTH = 3;

    /**
     * @return list<string> Errores bloqueantes (vacío = OK).
     */
    public function validate(ElectronicPrescription $rx): array
    {
        $policy = $this->loadPolicyKnobs();
        $errors = [];

        $previousScenario = $rx->scenario;
        $rx->scenario = ElectronicPrescription::SCENARIO_RDI_ISSUE;
        if (!$rx->validate()) {
            $errors = array_merge($errors, $this->flattenModelErrors($rx));
        }
        $rx->scenario = $previousScenario;

        if ((int) ($rx->id ?? 0) > 0 && (int) ($rx->id_profesional_efector_servicio ?? 0) > 0) {
            $pes = ProfesionalEfectorServicio::findOne([
                'id' => (int) $rx->id_profesional_efector_servicio,
                'deleted_at' => null,
            ]);
            if ($pes === null) {
                $errors[] = 'El profesional prescriptor no es válido.';
            }
        }

        $minDxLen = $policy['min_diagnosis_display_length'];
        if ($minDxLen > 0) {
            $display = trim((string) ($rx->diagnosis_display ?? ''));
            if (mb_strlen($display) < $minDxLen) {
                $errors[] = 'El texto del diagnóstico es demasiado corto.';
            }
        }

        $items = $this->resolveItems($rx);
        if ($items === []) {
            $errors[] = 'La receta no tiene medicamentos.';

            return $this->uniqueErrors($errors);
        }

        foreach ($items as $item) {
            $line = (int) $item->line_number;
            $itemScenario = $item->scenario;
            $item->scenario = ElectronicPrescriptionItem::SCENARIO_RDI_ISSUE;
            if (!$item->validate()) {
                foreach ($this->flattenModelErrors($item) as $msg) {
                    $errors[] = "Línea {$line}: {$msg}";
                }
            }
            $item->scenario = $itemScenario;
        }

        $dupHours = $policy['block_duplicate_medication_hours'];
        if ($dupHours > 0 && (int) ($rx->id ?? 0) > 0) {
            $errors = array_merge($errors, $this->duplicateMedicationErrors($rx, $items, $dupHours));
        }

        return $this->uniqueErrors($errors);
    }

    /**
     * Knobs operativos desde YAML; si falta el archivo o la clave, usan defaults de dominio.
     * No gobiernan PES / códigos / posología (eso es integridad hard).
     *
     * @return array{block_duplicate_medication_hours: int, min_diagnosis_display_length: int}
     */
    public function loadPolicyKnobs(): array
    {
        $config = AutonomousAgentMetadata::loadAgent(self::AGENT_ID);
        $section = [];
        if (is_array($config)) {
            if (is_array($config['policy'] ?? null)) {
                $section = $config['policy'];
            } elseif (is_array($config['checks'] ?? null)) {
                // Compat: YAML v1 usaba `checks` también para gates hard.
                $section = $config['checks'];
            }
        }

        return [
            'block_duplicate_medication_hours' => array_key_exists('block_duplicate_medication_hours', $section)
                ? max(0, (int) $section['block_duplicate_medication_hours'])
                : self::DEFAULT_BLOCK_DUPLICATE_MEDICATION_HOURS,
            'min_diagnosis_display_length' => array_key_exists('min_diagnosis_display_length', $section)
                ? max(0, (int) $section['min_diagnosis_display_length'])
                : self::DEFAULT_MIN_DIAGNOSIS_DISPLAY_LENGTH,
        ];
    }

    /**
     * @return ElectronicPrescriptionItem[]
     */
    private function resolveItems(ElectronicPrescription $rx): array
    {
        if ($rx->isRelationPopulated('items')) {
            $related = $rx->items;
            if (!is_array($related)) {
                return [];
            }

            return array_values(array_filter(
                $related,
                static fn ($item) => $item instanceof ElectronicPrescriptionItem
            ));
        }

        if ((int) ($rx->id ?? 0) <= 0) {
            return [];
        }

        return ElectronicPrescriptionItem::find()
            ->andWhere(['electronic_prescription_id' => (int) $rx->id, 'deleted_at' => null])
            ->orderBy(['line_number' => SORT_ASC])
            ->all();
    }

    /**
     * @return list<string>
     */
    private function flattenModelErrors(Model $model): array
    {
        $out = [];
        foreach ($model->getErrors() as $messages) {
            foreach ((array) $messages as $message) {
                $msg = trim((string) $message);
                if ($msg !== '') {
                    $out[] = $msg;
                }
            }
        }

        return $out;
    }

    /**
     * @param list<string> $errors
     * @return list<string>
     */
    private function uniqueErrors(array $errors): array
    {
        return array_values(array_unique($errors));
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
