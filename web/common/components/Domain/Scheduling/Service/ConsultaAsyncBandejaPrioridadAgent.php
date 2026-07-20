<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Platform\Agent\AgentRunRecorder;
use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\components\Platform\Core\Service\Push\PushNotificationSender;
use common\components\Platform\Core\Service\Push\PushNotificationTypes;
use common\models\Clinical\Encounter;
use common\models\ProfesionalEfectorServicio;
use Yii;

/**
 * Agente H01 v1: prioriza bandeja async staff (SLA, triage, antigüedad).
 */
final class ConsultaAsyncBandejaPrioridadAgent
{
    public const AGENT_ID = ConsultaAsyncBandejaPrioridadService::AGENT_ID;

    public const TRIGGER_NUEVA_SOLICITUD = 'consulta_async_nueva_solicitud';

    public const TRIGGER_PACIENTE_MENSAJE = 'consulta_async_paciente_mensaje';

    public const TRIGGER_BANDEJA_REFRESH = 'consulta_async_bandeja_refresh';

    private ConsultaAsyncBandejaPrioridadService $prioridad;

    public function __construct(?ConsultaAsyncBandejaPrioridadService $prioridad = null)
    {
        $this->prioridad = $prioridad ?? new ConsultaAsyncBandejaPrioridadService();
    }

    public function isEnabled(): bool
    {
        return (bool) (Yii::$app->params['autonomous_agent_consulta_async_prioridad_enabled'] ?? true);
    }

    public function onNuevaSolicitud(Encounter $encounter): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_NUEVA_SOLICITUD,
            'solicitud_created',
            (int) $encounter->id,
            (int) $encounter->id,
            (int) $encounter->subject_persona_id,
            null,
            [
                'service_id' => (int) ($encounter->service_id ?? 0),
                'urgency_band' => $this->urgencyBandFromEncounter($encounter),
            ]
        );

        try {
            (new ConsultaAsyncPushNotifier())->notifyNuevaSolicitudStaff($encounter);
        } catch (\Throwable $e) {
            Yii::warning('Push async nueva solicitud: ' . $e->getMessage(), 'consulta-async-push');
        }
    }

    public function onPacienteMensaje(Encounter $encounter): void
    {
        if (!$this->isEnabled()) {
            return;
        }
        if ($encounter->parent_type !== Encounter::PARENT_SOLICITUD_ASYNC) {
            return;
        }

        AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_PACIENTE_MENSAJE,
            'paciente_mensaje',
            (int) $encounter->id,
            (int) $encounter->id,
            (int) $encounter->subject_persona_id,
            null,
            [
                'status' => (string) $encounter->status,
                'paciente_sin_respuesta_staff' => $this->prioridad->pacienteTieneMensajeSinRespuestaStaff((int) $encounter->id),
            ]
        );

        if ($this->prioridad->pacienteTieneMensajeSinRespuestaStaff((int) $encounter->id)) {
            try {
                (new ConsultaAsyncPushNotifier())->notifyMensajePacienteStaff($encounter);
            } catch (\Throwable $e) {
                Yii::warning('Push async mensaje paciente: ' . $e->getMessage(), 'consulta-async-push');
            }
        }
    }

    /**
     * @param array<string, mixed> $bandeja
     * @param array<int, Encounter> $encounterById
     * @return array<string, mixed>
     */
    public function applyToStaffBandeja(array $bandeja, array $encounterById): array
    {
        if (!$this->isEnabled()) {
            return $bandeja;
        }

        $config = AutonomousAgentMetadata::loadAgent(self::AGENT_ID);
        if ($config === null) {
            return $bandeja;
        }

        $items = is_array($bandeja['items'] ?? null) ? $bandeja['items'] : [];
        if ($items === []) {
            return $bandeja;
        }

        foreach ($items as $i => $item) {
            if (!is_array($item)) {
                continue;
            }
            $encounterId = (int) ($item['encounter_id'] ?? 0);
            $encounter = $encounterById[$encounterId] ?? null;
            $items[$i]['prioridad'] = $this->prioridad->computePrioridad($item, $encounter, $config);
        }

        $items = $this->prioridad->sortItems($items);
        $this->processSlaEscalations($items, $encounterById, $config);
        $this->recordBandejaRanking($items);

        $bandeja['items'] = $items;
        $bandeja['prioridad_agent'] = [
            'agent_id' => self::AGENT_ID,
            'enabled' => true,
        ];

        return $bandeja;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param array<int, Encounter> $encounterById
     * @param array<string, mixed> $config
     */
    private function processSlaEscalations(array $items, array $encounterById, array $config): void
    {
        $escCfg = is_array($config['sla_escalation'] ?? null) ? $config['sla_escalation'] : [];
        if (empty($escCfg['enabled'])) {
            return;
        }

        $bands = is_array($escCfg['urgency_bands'] ?? null) ? $escCfg['urgency_bands'] : ['A', 'B'];
        $bands = array_map(static fn ($b) => strtoupper((string) $b), $bands);
        $pushTpl = is_array($escCfg['staff_push'] ?? null) ? $escCfg['staff_push'] : [];

        $idEfector = (int) Yii::$app->user->getIdEfector();

        foreach ($items as $item) {
            $sla = is_array($item['sla'] ?? null) ? $item['sla'] : [];
            if (empty($sla['incumplido']) || !empty($sla['respondido'])) {
                continue;
            }

            $band = strtoupper(trim((string) ($item['urgency_band'] ?? '')));
            if ($band === '' || !in_array($band, $bands, true)) {
                continue;
            }

            $encounterId = (int) ($item['encounter_id'] ?? 0);
            $encounter = $encounterById[$encounterId] ?? null;
            if ($encounter === null || $this->prioridad->wasSlaEscalated($encounter)) {
                continue;
            }

            $this->escalateSlaToStaff($item, $encounter, $pushTpl, $idEfector);
        }
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $pushTpl
     */
    private function escalateSlaToStaff(array $item, Encounter $encounter, array $pushTpl, int $idEfector): void
    {
        $serviceId = (int) ($item['servicio_id'] ?? $encounter->service_id ?? 0);
        $staffPersonas = $this->staffPersonaIdsForServicio($serviceId, $idEfector);
        if ($staffPersonas === []) {
            return;
        }

        $paciente = is_array($item['paciente'] ?? null) ? $item['paciente'] : [];
        $replace = [
            '{{paciente}}' => (string) ($paciente['nombre_completo'] ?? 'Paciente'),
            '{{servicio}}' => (string) ($item['servicio'] ?? 'Servicio'),
        ];
        $title = str_replace(array_keys($replace), array_values($replace), (string) ($pushTpl['title'] ?? 'SLA vencido'));
        $body = str_replace(array_keys($replace), array_values($replace), (string) ($pushTpl['body'] ?? ''));

        $push = new PushNotificationSender();
        foreach ($staffPersonas as $idPersona) {
            $push->sendToPersona(
                $idPersona,
                [
                    'type' => PushNotificationTypes::CONSULTA_ASYNC_SLA_ESCALATE_STAFF,
                    'encounter_id' => (string) $encounter->id,
                    'urgency_band' => (string) ($item['urgency_band'] ?? ''),
                ],
                $title,
                $body,
                true
            );
        }

        $note = $this->prioridad->parseEncounterNote($encounter);
        $this->prioridad->persistSlaEscalationFlag($encounter, $note);

        AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_BANDEJA_REFRESH,
            'sla_escalate_staff',
            (int) $encounter->id,
            (int) $encounter->id,
            (int) $encounter->subject_persona_id,
            'sla_escalation_' . strtoupper((string) ($item['urgency_band'] ?? '')),
            [
                'service_id' => $serviceId,
                'staff_notificados' => count($staffPersonas),
            ],
            ['rank' => (int) (($item['prioridad']['rank'] ?? 0))]
        );
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function recordBandejaRanking(array $items): void
    {
        if (count($items) < 2) {
            return;
        }

        $order = [];
        foreach ($items as $item) {
            $order[] = [
                'encounter_id' => (int) ($item['encounter_id'] ?? 0),
                'score' => (int) (($item['prioridad']['score'] ?? 0)),
                'rank' => (int) (($item['prioridad']['rank'] ?? 0)),
            ];
        }

        AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_BANDEJA_REFRESH,
            'bandeja_ranked',
            null,
            null,
            null,
            null,
            [
                'total' => count($order),
                'sla_incumplidos' => count(array_filter($items, static function (array $row): bool {
                    $sla = is_array($row['sla'] ?? null) ? $row['sla'] : [];

                    return !empty($sla['incumplido']);
                })),
            ],
            ['order' => $order]
        );
    }

    /**
     * @return list<int>
     */
    private function staffPersonaIdsForServicio(int $serviceId, int $idEfector): array
    {
        if ($serviceId <= 0) {
            return [];
        }

        $query = ProfesionalEfectorServicio::find()
            ->select('id_persona')
            ->where(['id_servicio' => $serviceId, 'deleted_at' => null]);

        if ($idEfector > 0) {
            $query->andWhere(['id_efector' => $idEfector]);
        }

        $ids = [];
        foreach ($query->column() as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $ids[$n] = $n;
            }
        }

        return array_values($ids);
    }

    private function urgencyBandFromEncounter(Encounter $encounter): ?string
    {
        $note = $this->prioridad->parseEncounterNote($encounter);

        return isset($note['urgency_band']) ? (string) $note['urgency_band'] : null;
    }
}
