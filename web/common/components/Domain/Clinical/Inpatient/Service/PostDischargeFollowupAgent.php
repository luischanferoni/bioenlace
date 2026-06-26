<?php

namespace common\components\Domain\Clinical\Inpatient\Service;

use common\components\Platform\Agent\AgentRunRecorder;
use common\models\SegNivelInternacion;
use Yii;

/**
 * Agente B02 v1: programa seguimiento post-alta y reutiliza rama B01 en respuestas.
 */
final class PostDischargeFollowupAgent
{
    public const AGENT_ID = PostDischargeFollowupSchedulerService::AGENT_ID;

    public const TRIGGER_TYPE = 'internacion_alta';

    private PostDischargeFollowupSchedulerService $scheduler;

    public function __construct(?PostDischargeFollowupSchedulerService $scheduler = null)
    {
        $this->scheduler = $scheduler ?? new PostDischargeFollowupSchedulerService();
    }

    public function isEnabled(): bool
    {
        return (bool) (Yii::$app->params['autonomous_agent_post_discharge_followup_enabled'] ?? true);
    }

    public function onDischarge(SegNivelInternacion $internacion): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $facts = $this->scheduler->buildFacts($internacion);
        $programId = $this->scheduler->resolveProgramId($facts);
        $anchorAt = $this->resolveAnchorAt($internacion);

        $result = $this->scheduler->scheduleForInternacion($internacion, $programId, $anchorAt);
        if (($result['scheduled'] ?? 0) < 1) {
            return;
        }

        AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_TYPE,
            'touchpoints_scheduled',
            (int) $internacion->id,
            isset($result['encounter_id']) ? (int) $result['encounter_id'] : null,
            (int) $internacion->id_persona,
            'program_' . $programId,
            $facts,
            $result
        );
    }

    private function resolveAnchorAt(SegNivelInternacion $internacion): string
    {
        $fecha = trim((string) ($internacion->fecha_fin ?? ''));
        $hora = trim((string) ($internacion->hora_fin ?? '00:00'));
        if ($fecha === '') {
            return date('Y-m-d H:i:s');
        }
        if (strlen($hora) === 5) {
            $hora .= ':00';
        }

        $ts = strtotime($fecha . ' ' . $hora);

        return $ts !== false ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');
    }
}
