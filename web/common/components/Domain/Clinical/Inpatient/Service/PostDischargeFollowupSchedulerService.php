<?php

namespace common\components\Domain\Clinical\Inpatient\Service;

use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\models\Clinical\CareFollowupTouchpointQueue;
use common\models\Clinical\Encounter;
use common\models\SegNivelInternacion;
use common\models\SegNivelInternacionTipoIngreso;
use Yii;

/**
 * Programa touchpoints post-alta desde metadata del agente B02.
 */
final class PostDischargeFollowupSchedulerService
{
    public const AGENT_ID = 'post-discharge-followup';

    public const TOUCHPOINT_KEY_PREFIX = 'pd-';

    /**
     * @return array{program: string, scheduled: int, encounter_id: int|null}
     */
    public function scheduleForInternacion(SegNivelInternacion $internacion, string $programId, string $anchorAt): array
    {
        $config = AutonomousAgentMetadata::loadAgent(self::AGENT_ID);
        if ($config === null) {
            return ['program' => $programId, 'scheduled' => 0, 'encounter_id' => null];
        }

        $programs = is_array($config['programs'] ?? null) ? $config['programs'] : [];
        $program = is_array($programs[$programId] ?? null) ? $programs[$programId] : null;
        if ($program === null && isset($programs['default']) && is_array($programs['default'])) {
            $programId = 'default';
            $program = $programs['default'];
        }
        if ($program === null) {
            return ['program' => $programId, 'scheduled' => 0, 'encounter_id' => null];
        }

        $encounter = $this->resolveEncounter($internacion);
        if ($encounter === null) {
            return ['program' => $programId, 'scheduled' => 0, 'encounter_id' => null];
        }

        $touchpoints = is_array($program['touchpoints'] ?? null) ? $program['touchpoints'] : [];
        $anchorTs = strtotime($anchorAt) ?: time();
        $now = date('Y-m-d H:i:s');
        $scheduled = 0;

        foreach ($touchpoints as $index => $tp) {
            if (!is_array($tp)) {
                continue;
            }
            $key = self::TOUCHPOINT_KEY_PREFIX . $programId . '-' . (int) $index;
            if (CareFollowupTouchpointQueue::find()->where([
                'encounter_id' => (int) $encounter->id,
                'touchpoint_key' => $key,
            ])->exists()) {
                continue;
            }

            $delayDays = max(0, (int) ($tp['delay_days'] ?? 0));
            $row = new CareFollowupTouchpointQueue();
            $row->encounter_id = (int) $encounter->id;
            $row->subject_persona_id = (int) $internacion->id_persona;
            $row->touchpoint_key = $key;
            $row->run_at = date('Y-m-d H:i:s', $anchorTs + $delayDays * 86400);
            $row->estado = CareFollowupTouchpointQueue::ESTADO_PENDIENTE;
            $row->title = trim((string) ($tp['title'] ?? 'Seguimiento post-alta')) ?: 'Seguimiento post-alta';
            $row->purpose = trim((string) ($tp['purpose'] ?? 'recovery')) ?: 'recovery';
            $row->form_kind = trim((string) ($tp['form_kind'] ?? 'symptoms')) ?: 'symptoms';
            $row->education_refs = null;
            $row->followup_pack_id = null;
            $row->education_pack_id = null;
            $row->intentos = 0;
            $row->created_at = $now;
            $row->updated_at = $now;
            if ($row->save(false)) {
                $scheduled++;
            }
        }

        return [
            'program' => $programId,
            'scheduled' => $scheduled,
            'encounter_id' => (int) $encounter->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildFacts(SegNivelInternacion $internacion): array
    {
        $tipoCodigo = '';
        if ((int) ($internacion->id_tipo_ingreso ?? 0) > 0) {
            $tipo = SegNivelInternacionTipoIngreso::findOne((int) $internacion->id_tipo_ingreso);
            if ($tipo !== null) {
                $tipoCodigo = strtolower(trim((string) ($tipo->tipo_ingreso ?? '')));
                $tipoCodigo = preg_replace('/[^a-z0-9_]+/', '_', $tipoCodigo) ?? $tipoCodigo;
            }
        }

        return [
            'id_tipo_ingreso' => (int) ($internacion->id_tipo_ingreso ?? 0),
            'tipo_ingreso_codigo' => $tipoCodigo,
            'id_tipo_alta' => (int) ($internacion->id_tipo_alta ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $facts
     */
    public function resolveProgramId(array $facts): string
    {
        $rules = AutonomousAgentMetadata::rulesForAgent(self::AGENT_ID);
        foreach ($rules as $rule) {
            if (!isset($rule['program'])) {
                continue;
            }
            if (\common\components\Platform\Agent\AutonomousAgentRuleEngine::matchAll([$rule], $facts) === []) {
                continue;
            }

            return (string) $rule['program'];
        }

        return 'default';
    }

    private function resolveEncounter(SegNivelInternacion $internacion): ?Encounter
    {
        $encounter = Encounter::find()
            ->andWhere([
                'parent_type' => Encounter::PARENT_INTERNACION,
                'parent_id' => (int) $internacion->id,
                'encounter_class' => Encounter::ENCOUNTER_CLASS_IMP,
            ])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        return $encounter instanceof Encounter ? $encounter : null;
    }
}
