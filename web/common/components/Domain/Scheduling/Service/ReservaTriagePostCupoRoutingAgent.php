<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Platform\Agent\AgentRunRecorder;
use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\components\Platform\Core\Service\Push\PushNotificationSender;
use common\components\Platform\Core\Service\Push\PushNotificationTypes;
use Yii;

/**
 * Agente A05 v1: recomienda canal alternativo cuando no hay cupos tras triage.
 */
final class ReservaTriagePostCupoRoutingAgent
{
    public const AGENT_ID = ReservaTriagePostCupoRoutingService::AGENT_ID;

    public const TRIGGER_TYPE = 'reserva_triage_sin_cupos';

    private ReservaTriagePostCupoRoutingService $routing;

    public function __construct(?ReservaTriagePostCupoRoutingService $routing = null)
    {
        $this->routing = $routing ?? new ReservaTriagePostCupoRoutingService();
    }

    public function isEnabled(): bool
    {
        return (bool) (Yii::$app->params['autonomous_agent_reserva_triage_post_cupo_enabled'] ?? true);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null Recomendación para incluir en respuesta API.
     */
    public function onSinCupos(array $params, int $idPersona): ?array
    {
        if (!$this->isEnabled() || $idPersona <= 0) {
            return null;
        }

        $facts = $this->routing->buildFacts($params);
        $recommendation = $this->routing->resolveRecommendation($facts);
        if ($recommendation === null) {
            return null;
        }

        $outcome = (string) ($recommendation['action'] ?? '');
        $ruleId = (string) ($recommendation['rule_id'] ?? '');

        AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_TYPE,
            $outcome,
            null,
            null,
            $idPersona,
            $ruleId !== '' ? $ruleId : null,
            $facts,
            $recommendation
        );

        if ($outcome === 'recommend') {
            $cooldown = (int) (Yii::$app->params['reservaTriagePostCupo']['push_cooldown_hours'] ?? 24);
            if (!$this->routing->shouldThrottlePush($idPersona, $cooldown)) {
                $this->pushRecomendacion($idPersona, $recommendation);
            }

            if (!empty($recommendation['commit_async'])) {
                $this->tryCommitAsync($idPersona, $params);
            }
        }

        return $recommendation;
    }

    /**
     * @param array<string, mixed> $recommendation
     */
    private function pushRecomendacion(int $idPersona, array $recommendation): void
    {
        $config = AutonomousAgentMetadata::loadAgent(self::AGENT_ID);
        $pushCfg = is_array($config['push'] ?? null) ? $config['push'] : [];
        $title = (string) ($pushCfg['title'] ?? 'Alternativas de atención');
        $template = (string) ($pushCfg['body_template'] ?? '{{mensaje}}');
        $mensaje = (string) ($recommendation['mensaje'] ?? '');
        $body = str_replace('{{mensaje}}', $mensaje, $template);

        (new PushNotificationSender())->sendToPersona(
            $idPersona,
            [
                'type' => PushNotificationTypes::RESERVA_TRIAGE_CANAL_ALTERNATIVO,
                'channel' => (string) ($recommendation['channel'] ?? ''),
                'rule_id' => (string) ($recommendation['rule_id'] ?? ''),
                'deep_link' => (string) ($recommendation['deep_link'] ?? ''),
            ],
            $title,
            $body,
            false
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    private function tryCommitAsync(int $idPersona, array $params): void
    {
        $mensaje = trim((string) ($params['async_mensaje'] ?? $params['motivo'] ?? ''));
        if (mb_strlen($mensaje) < 10) {
            $mensaje = 'Solicitud de consulta clínica por mensaje tras triage sin cupos disponibles.';
        }

        try {
            (new ConsultaAsyncSolicitudService())->solicitarComoPaciente($idPersona, array_merge($params, [
                'mensaje' => $mensaje,
            ]));
        } catch (\Throwable $e) {
            Yii::warning('A05 commit async: ' . $e->getMessage(), 'autonomous-agent');
        }
    }
}
