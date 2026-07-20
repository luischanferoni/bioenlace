<?php

namespace common\components\Domain\Scheduling\Service;

use common\models\Clinical\Encounter;

/**
 * Lee/escribe meta JSON en encounter.note para solicitudes async.
 */
final class ConsultaAsyncEncounterMetaService
{
    /**
     * @return array<string, mixed>
     */
    public function parse(?string $note): array
    {
        if ($note === null || trim($note) === '') {
            return [];
        }
        $decoded = json_decode($note, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function fromEncounter(Encounter $encounter): array
    {
        return $this->parse($encounter->note);
    }

    /**
     * @param array<string, mixed> $patch
     */
    public function mergeAndSave(Encounter $encounter, array $patch): void
    {
        $meta = $this->fromEncounter($encounter);
        foreach ($patch as $k => $v) {
            if ($v === null) {
                unset($meta[$k]);
            } else {
                $meta[$k] = $v;
            }
        }
        $encounter->note = json_encode($meta, JSON_UNESCAPED_UNICODE);
        $encounter->save(false, ['note', 'updated_at', 'updated_by']);
    }

    public function medicacionOperacion(array $meta): string
    {
        return trim((string) ($meta['medicacion_operacion'] ?? ''));
    }

    public function carePlanId(array $meta): int
    {
        return (int) ($meta['care_plan_id'] ?? 0);
    }

    public function isStructuredMedicacion(array $meta): bool
    {
        $op = $this->medicacionOperacion($meta);
        if ($op === '') {
            return false;
        }

        return in_array($op, (new ConsultaAsyncChatPolicyCatalogService())->structuredMedicacionOperaciones(), true);
    }
}
