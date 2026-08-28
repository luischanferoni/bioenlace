<?php

namespace common\components\Domain\Scheduling\Assistant\Context;

use common\components\Platform\Assistant\Context\AssistantContextAspectLoaderInterface;
use common\components\Platform\Assistant\Context\AssistantContextHISAreaAspect;
use common\components\Platform\Assistant\Context\AssistantContextLoadContext;
use common\components\Domain\Scheduling\Service\TurnoPacienteListadoService;
use common\models\Scheduling\Turno;

final class AppointmentCurrentAspectLoader implements AssistantContextAspectLoaderInterface
{
    public function aspectKey(): string
    {
        return AssistantContextHISAreaAspect::APPOINTMENT_CURRENT;
    }

    public function load(AssistantContextLoadContext $ctx): array
    {
        $idPersona = $ctx->anchors->subjectPersonaId;
        if ($idPersona <= 0) {
            return ['scope' => [], 'appointment' => null];
        }

        $turno = null;
        if ($ctx->anchors->appointmentId > 0) {
            $turno = Turno::findOne($ctx->anchors->appointmentId);
        }
        if ($turno === null) {
            $list = (new TurnoPacienteListadoService())->list([
                'subject_persona_id' => $idPersona,
                'alcance' => 'pendientes',
                'limit' => 1,
            ]);
            $rows = is_array($list['turnos'] ?? null) ? $list['turnos'] : [];
            if ($rows !== []) {
                $id = (int) ($rows[0]['id'] ?? 0);
                if ($id > 0) {
                    $turno = Turno::findOne($id);
                }
            }
        }

        if ($turno === null) {
            return [
                'scope' => ['subject_persona_id' => $idPersona],
                'appointment' => null,
            ];
        }

        return [
            'scope' => [
                'subject_persona_id' => $idPersona,
                'appointment_id' => (int) $turno->id_turnos,
                'site_id' => (int) ($turno->id_efector ?? 0),
            ],
            'appointment' => [
                'id' => (int) $turno->id_turnos,
                'scheduled_date' => (string) $turno->fecha,
                'scheduled_time' => self::shortTime((string) $turno->hora),
                'status' => (string) $turno->estado,
                'status_reason' => $turno->estado_motivo !== null ? (string) $turno->estado_motivo : null,
                'service' => $turno->getNombreServicioParaDisplay(),
                'site_id' => (int) ($turno->id_efector ?? 0),
                'professional' => $turno->getProfesionalPersonaParaDisplay()
                    ? $turno->getProfesionalPersonaParaDisplay()->getNombreCompleto()
                    : null,
            ],
        ];
    }

    private static function shortTime(string $hora): string
    {
        $t = trim($hora);
        if (preg_match('/^(\d{1,2}):(\d{2})/', $t, $m) === 1) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return strlen($t) >= 5 ? substr($t, 0, 5) : $t;
    }
}
