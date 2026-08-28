<?php

namespace common\components\Platform\Assistant\Context;

use common\components\Domain\Scheduling\Service\TurnoPacienteListadoService;
use common\models\Scheduling\Turno;
use Yii;

final class AssistantContextAnchorResolver
{
    /**
     * @param list<array{span: string, category: string, synonyms: list<string>}> $extractions
     */
    public static function resolve(int $userId, array $extractions): AssistantContextAnchorBag
    {
        $bag = new AssistantContextAnchorBag();
        $bag->subjectPersonaId = self::sessionPersonaId();
        if ($bag->subjectPersonaId <= 0) {
            return $bag;
        }

        foreach ($extractions as $ex) {
            $cat = trim((string) ($ex['category'] ?? ''));
            if ($cat === 'efector') {
                // Mención textual; resolución de id en fase posterior si hace falta.
                continue;
            }
        }

        $appointment = self::resolveReferenceAppointment($bag->subjectPersonaId);
        if ($appointment !== null) {
            $bag->appointmentId = (int) $appointment->id_turnos;
            if ((int) ($appointment->id_efector ?? 0) > 0) {
                $bag->siteId = (int) $appointment->id_efector;
            }
            if ((int) ($appointment->id_servicio_asignado ?? 0) > 0) {
                $bag->serviceId = (int) $appointment->id_servicio_asignado;
            }
            if ((int) ($appointment->id_profesional_efector_servicio ?? 0) > 0) {
                $bag->pesId = (int) $appointment->id_profesional_efector_servicio;
            }
            $bag->withResolvedFrom('appointment', 'proximo_pendiente');
            if ($bag->siteId > 0) {
                $bag->withResolvedFrom('site_id', 'appointment.id_efector');
            }
        }

        return $bag;
    }

    private static function sessionPersonaId(): int
    {
        if (!Yii::$app->has('user', true)) {
            return 0;
        }
        $user = Yii::$app->user;
        if ($user === null || !method_exists($user, 'getIdPersona')) {
            return 0;
        }

        return (int) $user->getIdPersona();
    }

    private static function resolveReferenceAppointment(int $idPersona): ?Turno
    {
        if ($idPersona <= 0) {
            return null;
        }
        try {
            $list = (new TurnoPacienteListadoService())->list([
                'subject_persona_id' => $idPersona,
                'alcance' => 'pendientes',
                'limit' => 1,
            ]);
            $rows = is_array($list['turnos'] ?? null) ? $list['turnos'] : [];
            if ($rows === []) {
                return null;
            }
            $id = (int) ($rows[0]['id'] ?? 0);
            if ($id <= 0) {
                return null;
            }

            return Turno::findOne($id);
        } catch (\Throwable $e) {
            Yii::warning('AssistantContextAnchorResolver: ' . $e->getMessage(), 'asistente');

            return null;
        }
    }
}
