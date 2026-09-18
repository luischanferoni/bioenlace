<?php

namespace common\components\Domain\Clinical\Encounter\Application;

use common\components\Domain\Clinical\Encounter\Domain\ConditionClinicalStatus;
use common\components\Domain\Clinical\Encounter\Domain\Model\Condition as ConditionAggregate;
use common\models\Clinical\Condition;

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
        $from = strtoupper(trim((string) ($condition->clinical_status ?? '')));
        if ($from === '') {
            $from = ConditionClinicalStatus::UNKNOWN;
        }
        $domain = ConditionAggregate::reconstitute(
            isset($condition->id) ? (int) $condition->id : null,
            $from,
            $condition->note ?? null
        );
        $domain->transitionTo($toStatus, $note, new \DateTimeImmutable('now'));

        if ($from === $domain->clinicalStatus()) {
            return $condition;
        }

        $condition->clinical_status = $domain->clinicalStatus();
        $attrs = ['clinical_status', 'updated_at', 'updated_by'];
        if ($domain->note() !== ($condition->note ?? null)) {
            $condition->note = $domain->note();
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
        $doneIds = [];
        $presentation = new ConditionPresentationService();

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
            $doneIds[$id] = true;

            // El review dedupea por etiqueta: al cerrar el id visible hay que cerrar
            // gemelos ACTIVE (p. ej. misma gripe en ICD-10 y SNOMED).
            if (!ConditionClinicalStatus::isClosedLike($status)) {
                continue;
            }
            $dedupeKey = $presentation->dedupeKeyForCondition($condition);
            if ($dedupeKey === '') {
                continue;
            }
            foreach ((new PatientActiveConditionQuery())->listActive($subjectPersonaId) as $twin) {
                $twinId = (int) ($twin->id ?? 0);
                if ($twinId <= 0 || isset($doneIds[$twinId])) {
                    continue;
                }
                if ($presentation->dedupeKeyForCondition($twin) !== $dedupeKey) {
                    continue;
                }
                $updated[] = $this->transition($twin, $status, $note);
                $doneIds[$twinId] = true;
            }
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
        $map = ConditionClinicalStatus::LABELS;

        return $map[$status] ?? $status;
    }
}
