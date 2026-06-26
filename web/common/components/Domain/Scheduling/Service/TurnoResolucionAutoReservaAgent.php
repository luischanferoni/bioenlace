<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Platform\Agent\AgentRunRecorder;
use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\models\Scheduling\Turno;
use common\models\TurnoResolucion;
use Yii;

/**
 * Agente A01 D2 v1: auto-reserva en resolución con preferencias del paciente.
 */
final class TurnoResolucionAutoReservaAgent
{
    public const AGENT_ID = TurnoResolucionAutoReservaService::AGENT_ID;

    public const TRIGGER_TYPE = 'turno_en_resolucion';

    private TurnoResolucionAutoReservaService $autoReserva;

    private PersonaAgendaPreferenciasService $preferencias;

    public function __construct(
        ?TurnoResolucionAutoReservaService $autoReserva = null,
        ?PersonaAgendaPreferenciasService $preferencias = null
    ) {
        $this->autoReserva = $autoReserva ?? new TurnoResolucionAutoReservaService();
        $this->preferencias = $preferencias ?? new PersonaAgendaPreferenciasService();
    }

    /**
     * Intenta reubicar automáticamente. Devuelve payload de turno reubicado o null si no aplica.
     *
     * @return array<string, mixed>|null
     */
    public function tryAutoReserva(Turno $turno): ?array
    {
        if ($turno->estado !== Turno::ESTADO_EN_RESOLUCION) {
            return null;
        }

        $res = TurnoResolucion::findPendientePorTurno((int) $turno->id_turnos);
        if ($res === null) {
            return null;
        }

        if (!$this->autoReserva->isEnabledForEfector((int) $turno->id_efector)) {
            return null;
        }

        $prefs = $this->preferencias->getForPersona((int) $turno->id_persona);
        if (!($prefs['auto_reserva_resolucion'] ?? false)) {
            return null;
        }

        $config = AutonomousAgentMetadata::loadAgent(self::AGENT_ID);
        if ($config === null) {
            return null;
        }

        $winner = $this->autoReserva->pickCandidate($turno, $res, $prefs, $config);
        if ($winner === null) {
            AgentRunRecorder::record(
                self::AGENT_ID,
                self::TRIGGER_TYPE,
                'no_unambiguous_candidate',
                (int) $res->id,
                null,
                (int) $turno->id_persona,
                null,
                ['id_turno' => (int) $turno->id_turnos],
                ['prefs' => $prefs]
            );

            return null;
        }

        try {
            $result = $this->applyWinner($turno, (int) $turno->id_persona, $winner);
        } catch (\Throwable $e) {
            Yii::warning('Auto-reserva resolución: ' . $e->getMessage(), 'turno-resolucion-auto-reserva');
            AgentRunRecorder::record(
                self::AGENT_ID,
                self::TRIGGER_TYPE,
                'apply_failed',
                (int) $res->id,
                null,
                (int) $turno->id_persona,
                null,
                ['id_turno' => (int) $turno->id_turnos, 'error' => $e->getMessage()],
                $winner
            );

            return null;
        }

        AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_TYPE,
            'auto_rebooked',
            (int) $res->id,
            null,
            (int) $turno->id_persona,
            null,
            ['id_turno' => (int) $turno->id_turnos],
            array_merge($winner, ['result' => $result])
        );

        return $result;
    }

    /**
     * @param array<string, mixed> $result
     * @return array{title: string, body: string}
     */
    public function buildAutoRebookedPush(array $result, ?array $config = null): array
    {
        $config = $config ?? AutonomousAgentMetadata::loadAgent(self::AGENT_ID) ?? [];
        $msgs = is_array($config['patient_messages'] ?? null) ? $config['patient_messages'] : [];
        $tpl = is_array($msgs['auto_rebooked'] ?? null) ? $msgs['auto_rebooked'] : [];

        $fecha = (string) ($result['fecha'] ?? '');
        $hora = substr((string) ($result['hora'] ?? ''), 0, 5);
        $replace = [
            '{{fecha}}' => $fecha,
            '{{hora}}' => $hora,
        ];

        return [
            'title' => str_replace(array_keys($replace), array_values($replace), (string) ($tpl['title'] ?? 'Te reubicamos el turno')),
            'body' => str_replace(array_keys($replace), array_values($replace), (string) ($tpl['body'] ?? 'Reservamos tu turno para el {{fecha}} a las {{hora}}.')),
        ];
    }

    /**
     * @param array<string, mixed> $winner
     * @return array<string, mixed>
     */
    private function applyWinner(Turno $turno, int $idPersona, array $winner): array
    {
        if (($winner['kind'] ?? '') === 'neighbor' && !empty($winner['eleccion'])) {
            return TurnoResolucionService::resolverEleccionVecina(
                (int) $turno->id_turnos,
                $idPersona,
                (string) $winner['eleccion']
            );
        }

        return TurnoResolucionService::reubicarComoPaciente(
            (int) $turno->id_turnos,
            $idPersona,
            [
                'fecha' => (string) ($winner['fecha'] ?? ''),
                'hora' => (string) ($winner['hora'] ?? ''),
                'id_profesional_efector_servicio' => (int) ($winner['id_profesional_efector_servicio'] ?? 0),
                'id_efector' => (int) ($winner['id_efector'] ?? 0),
            ]
        );
    }
}
