<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Platform\Agent\AutonomousAgentRuleEngine;
use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\models\Platform\AgentRun;
use Yii;

/**
 * Construye hechos y resuelve canal alternativo cuando no hay cupos tras triage (agente A05).
 */
final class ReservaTriagePostCupoRoutingService
{
    public const AGENT_ID = 'reserva-triage-post-cupo-routing';

    /**
     * @param array<string, mixed> $params Request params del flujo de reserva (triage + servicio).
     * @return array<string, mixed>
     */
    public function buildFacts(array $params): array
    {
        $draft = array_merge(
            ReservaTriageServicioSugeridoService::draftDesdeParamsTriage($params),
            $this->draftModalidadDesdeParams($params)
        );

        $triageCatalog = new ReservaTurnoTriageCatalogService();
        $compiled = $triageCatalog->compileSelections($draft);

        $modalidad = new ReservaModalidadAtencionService();
        $opciones = $modalidad->opcionesParaDraft($draft);
        $opcionCodes = array_column($opciones, 'code');

        $hub = (new ReservaTriageServicioSugeridoService())->resolverParaDraft($draft, true);
        $especialista = (new ReservaTriageServicioSugeridoService())->resolverParaDraft($draft, false);

        $esModoHub = ReservaTriageServicioSugeridoService::esModoTeleconsultaHub($params);
        $idServicioReq = (int) ($params['id_servicio'] ?? $params['id_servicio_asignado'] ?? 0);

        return [
            'urgency_band' => strtoupper(trim((string) ($compiled['urgency_band'] ?? ''))),
            'reserva_triage_code' => strtolower(trim((string) ($compiled['reserva_triage_code'] ?? ''))),
            'async_available' => in_array(ReservaModalidadAtencionCatalogService::CODE_ASYNC, $opcionCodes, true),
            'tele_hub_available' => $hub['id_servicios'] !== [] || $esModoHub,
            'primaria_available' => $this->tieneServiciosPrimaria($draft),
            'especialista_sin_cupo' => $idServicioReq > 0 && $especialista['id_servicios'] !== [],
            'lista_espera_habilitada' => (bool) (Yii::$app->params['autonomous_agent_waitlist_enabled'] ?? true),
            'modo_tele_hub' => $esModoHub,
            'slots_empty' => true,
        ];
    }

    /**
     * @param array<string, mixed> $facts
     * @return array<string, mixed>|null
     */
    public function resolveRecommendation(array $facts): ?array
    {
        $config = AutonomousAgentMetadata::loadAgent(self::AGENT_ID);
        if ($config === null) {
            return null;
        }

        $normalized = $this->normalizeFactsForRules($facts);
        $rules = AutonomousAgentMetadata::rulesForAgent(self::AGENT_ID);

        foreach ($rules as $rule) {
            if (AutonomousAgentRuleEngine::matchAll([$rule], $normalized) === []) {
                continue;
            }

            $action = (string) ($rule['action'] ?? '');
            if ($action === 'halt') {
                return [
                    'rule_id' => (string) ($rule['id'] ?? ''),
                    'action' => 'halt',
                    'channel' => null,
                    'mensaje' => (string) ($rule['mensaje'] ?? ''),
                    'deep_link' => null,
                    'commit_async' => false,
                ];
            }

            if ($action !== 'recommend') {
                continue;
            }

            $channel = (string) ($rule['channel'] ?? '');
            $deepLinks = is_array($config['deep_links'] ?? null) ? $config['deep_links'] : [];

            return [
                'rule_id' => (string) ($rule['id'] ?? ''),
                'action' => 'recommend',
                'channel' => $channel,
                'mensaje' => (string) ($rule['mensaje'] ?? ''),
                'deep_link' => isset($deepLinks[$channel]) ? (string) $deepLinks[$channel] : null,
                'commit_async' => !empty($rule['commit_async']),
            ];
        }

        return null;
    }

    public function shouldThrottlePush(int $idPersona, int $cooldownHours): bool
    {
        if ($idPersona <= 0 || $cooldownHours <= 0) {
            return false;
        }

        $since = date('Y-m-d H:i:s', time() - $cooldownHours * 3600);

        return AgentRun::find()
            ->where([
                'agent_id' => self::AGENT_ID,
                'subject_persona_id' => $idPersona,
            ])
            ->andWhere(['>=', 'created_at', $since])
            ->andWhere(['outcome' => 'recommend'])
            ->exists();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function draftModalidadDesdeParams(array $params): array
    {
        $draft = [];
        foreach (['tipo_atencion', 'id_servicio_asignado', 'care_plan_id'] as $key) {
            $v = trim((string) ($params[$key] ?? ''));
            if ($v !== '') {
                $draft[$key] = $v;
            }
        }

        return $draft;
    }

    /**
     * @param array<string, mixed> $draft
     */
    private function tieneServiciosPrimaria(array $draft): bool
    {
        $resolver = new ReservaTriageServicioRolResolver();
        $hubIds = $resolver->idsServiciosHubParaDraft($draft);

        return $hubIds !== [];
    }

    /**
     * @param array<string, mixed> $facts
     * @return array<string, mixed>
     */
    private function normalizeFactsForRules(array $facts): array
    {
        $out = [];
        foreach ($facts as $key => $value) {
            if (is_bool($value)) {
                $out[$key] = $value ? 'true' : 'false';
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
