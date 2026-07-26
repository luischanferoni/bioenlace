<?php

namespace common\components\Domain\Clinical\Service;

use common\components\Domain\Clinical\Enum\ConditionClinicalStatus;
use common\models\Clinical\Condition;
use common\models\DiagnosticoConsulta;

/**
 * Ciclo de vida de Condition (diagnósticos / problemas activos).
 */
final class ConditionLifecycleService
{
    public function resolve(Condition $condition, ?string $note = null): Condition
    {
        return $this->transition($condition, ConditionClinicalStatus::RESOLVED, $note);
    }

    public function inactivate(Condition $condition, ?string $note = null): Condition
    {
        return $this->transition($condition, ConditionClinicalStatus::INACTIVE, $note);
    }

    public function remit(Condition $condition, ?string $note = null): Condition
    {
        return $this->transition($condition, ConditionClinicalStatus::REMISSION, $note);
    }

    public function reactivate(Condition $condition, ?string $note = null): Condition
    {
        return $this->transition($condition, ConditionClinicalStatus::ACTIVE, $note);
    }

    public function transition(Condition $condition, string $toStatus, ?string $note = null): Condition
    {
        $toStatus = strtoupper(trim($toStatus));
        if (!ConditionClinicalStatus::isValid($toStatus)) {
            throw new \InvalidArgumentException("Estado clínico no válido: {$toStatus}");
        }
        $from = strtoupper(trim((string) ($condition->clinical_status ?? '')));
        if ($from === '') {
            $from = ConditionClinicalStatus::UNKNOWN;
        }
        if (!ConditionClinicalStatus::canTransition($from, $toStatus)) {
            throw new \InvalidArgumentException(
                "No se puede pasar la condición #{$condition->id} de «{$from}» a «{$toStatus}»."
            );
        }
        if ($from === $toStatus) {
            return $condition;
        }

        $condition->clinical_status = $toStatus;
        $attrs = ['clinical_status', 'updated_at', 'updated_by'];
        $note = $note !== null ? trim($note) : '';
        if ($note !== '') {
            $prefix = '[' . date('Y-m-d H:i') . ' estado→' . $toStatus . '] ';
            $existing = trim((string) ($condition->note ?? ''));
            $condition->note = $existing === '' ? $prefix . $note : $existing . "\n" . $prefix . $note;
            $attrs[] = 'note';
        }
        if (!$condition->save(false, $attrs)) {
            throw new \RuntimeException(
                'No se pudo actualizar la condición: ' . json_encode($condition->getErrors())
            );
        }

        return $condition;
    }

    /**
     * Aplica resoluciones del profesional: mapa condition_id → clinical_status
     * o lista [{id, clinical_status, note?}].
     *
     * @param array<string|int, mixed>|list<array<string, mixed>> $resolutions
     * @return list<Condition>
     */
    public function applyResolutions(array $resolutions, int $subjectPersonaId): array
    {
        $normalized = $this->normalizeResolutions($resolutions);
        $updated = [];
        foreach ($normalized as $row) {
            $id = (int) ($row['id'] ?? 0);
            $status = strtoupper(trim((string) ($row['clinical_status'] ?? '')));
            if ($id <= 0 || $status === '') {
                continue;
            }
            $condition = Condition::findOne($id);
            if ($condition === null || $condition->deleted_at !== null) {
                throw new \InvalidArgumentException("Condición #{$id} no encontrada.");
            }
            if ((int) $condition->subject_persona_id !== $subjectPersonaId) {
                throw new \InvalidArgumentException(
                    "La condición #{$id} no pertenece al paciente de esta atención."
                );
            }
            $note = isset($row['note']) ? (string) $row['note'] : null;
            $updated[] = $this->transition($condition, $status, $note);
        }

        return $updated;
    }

    /**
     * @param array<string|int, mixed>|list<array<string, mixed>> $resolutions
     * @return list<array{id: int, clinical_status: string, note?: string}>
     */
    private function normalizeResolutions(array $resolutions): array
    {
        if ($resolutions === []) {
            return [];
        }
        $out = [];
        $isList = array_keys($resolutions) === range(0, count($resolutions) - 1);
        if ($isList) {
            foreach ($resolutions as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $id = (int) ($row['id'] ?? $row['condition_id'] ?? 0);
                $status = (string) ($row['clinical_status'] ?? $row['status'] ?? '');
                if ($id <= 0 || $status === '') {
                    continue;
                }
                $item = ['id' => $id, 'clinical_status' => $status];
                if (isset($row['note'])) {
                    $item['note'] = (string) $row['note'];
                }
                $out[] = $item;
            }

            return $out;
        }

        foreach ($resolutions as $idKey => $value) {
            $id = (int) $idKey;
            if ($id <= 0) {
                continue;
            }
            if (is_array($value)) {
                $status = (string) ($value['clinical_status'] ?? $value['status'] ?? $value['value'] ?? '');
                $item = ['id' => $id, 'clinical_status' => $status];
                if (isset($value['note'])) {
                    $item['note'] = (string) $value['note'];
                }
                $out[] = $item;
                continue;
            }
            $out[] = ['id' => $id, 'clinical_status' => (string) $value];
        }

        return $out;
    }

    public function statusLabel(string $status): string
    {
        $map = DiagnosticoConsulta::ESTADOS_CLINICOS;

        return $map[$status] ?? $status;
    }
}
