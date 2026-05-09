<?php

namespace common\components\Services\ProfesionalEfectorServicio;

use common\models\Condiciones_laborales;
use common\models\ProfesionalEfectorServicio as ProfesionalEfectorServicioRecord;
use common\models\ProfesionalEfectorServicioAgenda;
use common\models\ProfesionalEfectorServicioCondicionLaboral;
use common\models\Servicio;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Pre-carga y persistencia de agenda / condición laboral para UI JSON
 * ({@see \frontend\modules\api\v1\controllers\ProfesionalAgendaController::actionConfigurarAgenda}, recurso humano).
 */
final class ProfesionalEfectorServicioAgendaUiService
{
    /**
     * Valores para inyectar en campos del descriptor (query + merge en GET).
     *
     * @return array<string, mixed>
     */
    public static function buildFieldValuesForGet(int $idEfector, array $query): array
    {
        $idRrHh = isset($query['id_rr_hh']) ? (int) $query['id_rr_hh'] : 0;
        $idServicio = isset($query['id_servicio']) ? (int) $query['id_servicio'] : 0;
        $idPesIn = isset($query['id_profesional_efector_servicio']) ? (int) $query['id_profesional_efector_servicio'] : 0;

        $out = [
            'id_efector' => (string) $idEfector,
        ];

        $pesAnchor = null;
        if ($idPesIn > 0) {
            $pesAnchor = ProfesionalEfectorServicioRecord::findOne(['id' => $idPesIn, 'deleted_at' => null]);
            if ($pesAnchor === null || (int) $pesAnchor->id_efector !== $idEfector) {
                throw new BadRequestHttpException('id_profesional_efector_servicio inválido para este efector.');
            }
            $out['id_profesional_efector_servicio'] = (string) $idPesIn;
            $idRrHhFromPersona = ProfesionalEfectorServicioRecord::resolveIdRrhhForPersona((int) $pesAnchor->id_persona);
            if ($idRrHhFromPersona > 0) {
                $idRrHh = $idRrHhFromPersona;
            }
            if ($idServicio <= 0) {
                $idServicio = (int) $pesAnchor->id_servicio;
            }
            if ((int) $pesAnchor->id_servicio !== $idServicio) {
                throw new BadRequestHttpException('id_servicio no coincide con la asignación profesional seleccionada.');
            }
        }

        if ($idRrHh > 0) {
            self::assertRecursoHumanoPerteneceAEfector($idRrHh, $idEfector);
        } elseif ($idPesIn <= 0) {
            return $out;
        }

        if ($idServicio <= 0) {
            return $out;
        }

        $servicioPre = Servicio::findOne($idServicio);
        if ($servicioPre !== null && strtoupper(trim((string) $servicioPre->acepta_turnos)) !== 'SI') {
            throw new BadRequestHttpException('Este servicio no admite agenda de turnos; no corresponde abrir la configuración de agenda.');
        }

        if ($idRrHh > 0) {
            $idPersona = ProfesionalEfectorServicioRecord::resolveIdPersonaFromIdRrhh($idRrHh);
            if ($idPersona === null || $idPersona <= 0) {
                return $out;
            }
            $pesServicio = ProfesionalEfectorServicioRecord::findOneActivoPorPersonaEfectorServicio(
                $idPersona,
                $idEfector,
                $idServicio
            );
            if ($pesServicio === null) {
                return $out;
            }
        }

        $out['id_servicio'] = (string) $idServicio;

        if ($pesAnchor !== null) {
            $pes = $pesAnchor;
        } elseif ($idRrHh > 0) {
            $idPersona = ProfesionalEfectorServicioRecord::resolveIdPersonaFromIdRrhh($idRrHh);
            if ($idPersona === null || $idPersona <= 0) {
                return $out;
            }
            $pes = ProfesionalEfectorServicioRecord::findOneActivoPorPersonaEfectorServicio(
                $idPersona,
                $idEfector,
                $idServicio
            );
            if ($pes === null) {
                $pes = new ProfesionalEfectorServicioRecord();
                $pes->id_persona = $idPersona;
                $pes->id_efector = $idEfector;
                $pes->id_servicio = $idServicio;
                if (!$pes->save()) {
                    return $out;
                }
            }
        } else {
            return $out;
        }

        $out['id_profesional_efector_servicio'] = (string) $pes->id;

        $agenda = ProfesionalEfectorServicioAgenda::findActivaPorProfesionalEfectorServicio((int) $pes->id);

        if ($agenda !== null) {
            $out['cupo_pacientes'] = (string) $agenda->cupo_pacientes;
            $out['duracion_slot_minutos'] = $agenda->duracion_slot_minutos !== null ? (string) $agenda->duracion_slot_minutos : '';
            $out['formas_atencion'] = (string) $agenda->formas_atencion;
            foreach (['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'] as $col) {
                $out[$col] = (string) ($agenda->$col ?? '');
            }
        }

        if ($pes !== null) {
            $laboral = ProfesionalEfectorServicioCondicionLaboral::findUltimaActivaPorPes((int) $pes->id);
            if ($laboral !== null) {
                $out['id_condicion_laboral'] = (string) $laboral->id_condicion_laboral;
                $out['fecha_inicio'] = (string) ($laboral->fecha_inicio ?? '');
                $out['fecha_fin'] = (string) ($laboral->fecha_fin ?? '');
            }
        }

        return $out;
    }

    /**
     * Valores para precargar UI de condición laboral (solo RRHH).
     *
     * @return array<string, mixed>
     */
    public static function buildCondicionLaboralValuesForGet(int $idEfector, array $query): array
    {
        $idRrHh = isset($query['id_rr_hh']) ? (int) $query['id_rr_hh'] : 0;
        $idPesIn = isset($query['id_profesional_efector_servicio']) ? (int) $query['id_profesional_efector_servicio'] : 0;
        $out = [
            'id_efector' => (string) $idEfector,
        ];
        if ($idPesIn > 0) {
            $pes = ProfesionalEfectorServicioRecord::findOne(['id' => $idPesIn, 'deleted_at' => null]);
            if ($pes !== null && (int) $pes->id_efector === $idEfector) {
                $out['id_profesional_efector_servicio'] = (string) $idPesIn;
                $idRrFromP = ProfesionalEfectorServicioRecord::resolveIdRrhhForPersona((int) $pes->id_persona);
                if ($idRrFromP > 0) {
                    $idRrHh = $idRrFromP;
                }
            }
        }
        if ($idRrHh <= 0 && !isset($out['id_profesional_efector_servicio'])) {
            return $out;
        }
        if ($idRrHh > 0) {
            self::assertRecursoHumanoPerteneceAEfector($idRrHh, $idEfector);
        }
        if (!isset($out['id_profesional_efector_servicio']) && $idRrHh > 0) {
            $idPersonaLegacy = ProfesionalEfectorServicioRecord::resolveIdPersonaFromIdRrhh($idRrHh);
            if ($idPersonaLegacy !== null && $idPersonaLegacy > 0) {
                $pesAny = ProfesionalEfectorServicioRecord::find()
                    ->where([
                        'id_persona' => $idPersonaLegacy,
                        'id_efector' => $idEfector,
                        'deleted_at' => null,
                    ])
                    ->orderBy(['id' => SORT_ASC])
                    ->one();
                if ($pesAny !== null) {
                    $out['id_profesional_efector_servicio'] = (string) $pesAny->id;
                }
            }
        }

        $idPesLaboral = isset($out['id_profesional_efector_servicio']) ? (int) $out['id_profesional_efector_servicio'] : 0;
        if ($idPesLaboral > 0) {
            $laboral = ProfesionalEfectorServicioCondicionLaboral::findUltimaActivaPorPes($idPesLaboral);
            if ($laboral !== null) {
                $out['id_condicion_laboral'] = (string) $laboral->id_condicion_laboral;
                $out['fecha_inicio'] = (string) ($laboral->fecha_inicio ?? '');
                $out['fecha_fin'] = (string) ($laboral->fecha_fin ?? '');
            }
        }

        return $out;
    }

    /**
     * Persistencia: agenda del servicio seleccionado (sin condición laboral).
     *
     * @param array<string, mixed> $post
     * @return array{message: string}
     */
    public static function submitAgendaConfig(int $idEfector, array $post): array
    {
        $idRrHh = (int) ($post['id_rr_hh'] ?? 0);
        $idServicio = (int) ($post['id_servicio'] ?? 0);
        $idPesPost = (int) ($post['id_profesional_efector_servicio'] ?? 0);

        $pesFromPost = null;
        if ($idPesPost > 0) {
            $pesFromPost = ProfesionalEfectorServicioRecord::findOne(['id' => $idPesPost, 'deleted_at' => null]);
            if ($pesFromPost === null || (int) $pesFromPost->id_efector !== $idEfector) {
                throw new BadRequestHttpException('id_profesional_efector_servicio inválido para este efector.');
            }
            if ($idRrHh <= 0) {
                $idRrHh = ProfesionalEfectorServicioRecord::resolveIdRrhhForPersona((int) $pesFromPost->id_persona);
            }
            if ($idServicio <= 0) {
                $idServicio = (int) $pesFromPost->id_servicio;
            }
            if ((int) $pesFromPost->id_servicio !== $idServicio) {
                throw new BadRequestHttpException('id_servicio no coincide con la asignación profesional.');
            }
        }

        if ($idServicio <= 0 || ($idRrHh <= 0 && $idPesPost <= 0)) {
            throw new BadRequestHttpException('Indique id_servicio e id_profesional_efector_servicio.');
        }

        if ($idRrHh > 0) {
            self::assertRecursoHumanoPerteneceAEfector($idRrHh, $idEfector);
        }

        if ($idServicio === 62) {
            throw new BadRequestHttpException('Servicio no editable en este flujo.');
        }

        $servicio = Servicio::findOne($idServicio);
        if ($servicio === null) {
            throw new NotFoundHttpException('Servicio inexistente.');
        }

        if (strtoupper(trim((string) $servicio->acepta_turnos)) !== 'SI') {
            throw new BadRequestHttpException('Este servicio no admite agenda de turnos; no se puede guardar configuración de agenda.');
        }

        if ($idRrHh > 0) {
            $idPersona = ProfesionalEfectorServicioRecord::resolveIdPersonaFromIdRrhh($idRrHh);
            if ($idPersona === null || $idPersona <= 0) {
                throw new BadRequestHttpException('El recurso humano no pertenece al efector en sesión.');
            }
            if (!ProfesionalEfectorServicioRecord::rrhhTieneAsignacionPesEnEfector($idRrHh, $idEfector)) {
                throw new BadRequestHttpException('El recurso humano no pertenece al efector en sesión.');
            }

            $pes = ProfesionalEfectorServicioRecord::findOneActivoPorPersonaEfectorServicio(
                $idPersona,
                $idEfector,
                $idServicio
            );
            if ($pes === null) {
                $pes = new ProfesionalEfectorServicioRecord();
                $pes->id_persona = $idPersona;
                $pes->id_efector = (int) $idEfector;
                $pes->id_servicio = (int) $idServicio;
                if (!$pes->save()) {
                    throw new \RuntimeException('No se pudo crear la asignación profesional-efector-servicio.');
                }
            }
        } else {
            $pes = $pesFromPost;
            if ($pes === null) {
                throw new BadRequestHttpException('No se pudo resolver la asignación profesional.');
            }
        }

        $agendaNew = ProfesionalEfectorServicioAgenda::findActivaPorProfesionalEfectorServicio((int) $pes->id);
        if ($agendaNew === null) {
            $agendaNew = new ProfesionalEfectorServicioAgenda();
            $agendaNew->id_profesional_efector_servicio = (int) $pes->id;
            $agendaNew->id_efector = (int) $idEfector;
        }
        $agendaNew->load($post, '');
        $agendaNew->id_profesional_efector_servicio = (int) $pes->id;
        if (empty($agendaNew->id_efector)) {
            $agendaNew->id_efector = (int) $idEfector;
        }

        if (!$agendaNew->validate()) {
            throw new BadRequestHttpException(implode(' ', $agendaNew->getFirstErrors()));
        }
        if (!$agendaNew->save(false)) {
            throw new \RuntimeException('No se pudo guardar la agenda.');
        }

        return ['message' => 'Agenda guardada.'];
    }

    /**
     * Crear/editar condición laboral (upsert) de un RRHH (sin tocar agenda por servicio).
     *
     * @param array<string, mixed> $post
     * @return array{message: string}
     */
    public static function submitCondicionLaboral(int $idEfector, array $post): array
    {
        $idRrHh = (int) ($post['id_rr_hh'] ?? 0);
        $idPes = (int) ($post['id_profesional_efector_servicio'] ?? 0);

        if ($idPes > 0) {
            $pesOk = ProfesionalEfectorServicioRecord::findOne(['id' => $idPes, 'deleted_at' => null]);
            if ($pesOk === null || (int) $pesOk->id_efector !== $idEfector) {
                throw new BadRequestHttpException('id_profesional_efector_servicio inválido para este efector.');
            }
        } elseif ($idRrHh > 0) {
            self::assertRecursoHumanoPerteneceAEfector($idRrHh, $idEfector);
            $idPersonaLegacy = ProfesionalEfectorServicioRecord::resolveIdPersonaFromIdRrhh($idRrHh);
            if ($idPersonaLegacy !== null && $idPersonaLegacy > 0) {
                $pesAny = ProfesionalEfectorServicioRecord::find()
                    ->where([
                        'id_persona' => $idPersonaLegacy,
                        'id_efector' => $idEfector,
                        'deleted_at' => null,
                    ])
                    ->orderBy(['id' => SORT_ASC])
                    ->one();
                if ($pesAny !== null) {
                    $idPes = (int) $pesAny->id;
                }
            }
        }

        if ($idPes <= 0) {
            throw new BadRequestHttpException('Indique id_profesional_efector_servicio o id_rr_hh con PES en este efector.');
        }

        $idCondicion = isset($post['id_condicion_laboral']) ? (int) $post['id_condicion_laboral'] : 0;
        if ($idCondicion <= 0) {
            throw new BadRequestHttpException('id_condicion_laboral es obligatorio.');
        }
        if (!Condiciones_laborales::find()->where(['id_condicion_laboral' => $idCondicion])->exists()) {
            throw new BadRequestHttpException('Condición laboral inválida.');
        }

        $laboral = ProfesionalEfectorServicioCondicionLaboral::find()
            ->where([
                'id_profesional_efector_servicio' => $idPes,
                'id_condicion_laboral' => $idCondicion,
                'deleted_at' => null,
            ])
            ->one();

        if ($laboral === null) {
            $laboral = new ProfesionalEfectorServicioCondicionLaboral();
            $laboral->id_profesional_efector_servicio = $idPes;
            $laboral->id_condicion_laboral = $idCondicion;
        }

        $fi = trim((string) ($post['fecha_inicio'] ?? ''));
        $ff = trim((string) ($post['fecha_fin'] ?? ''));
        $laboral->fecha_inicio = $fi !== '' ? $fi : null;
        $laboral->fecha_fin = $ff !== '' ? $ff : null;

        if (!$laboral->validate()) {
            throw new BadRequestHttpException(implode(' ', $laboral->getFirstErrors()));
        }
        if (!$laboral->save(false)) {
            throw new \RuntimeException('No se pudo guardar la condición laboral.');
        }

        return ['message' => 'Condición laboral guardada.'];
    }

    private static function assertRecursoHumanoPerteneceAEfector(int $idRrHh, int $idEfector): void
    {
        if ($idEfector <= 0) {
            throw new BadRequestHttpException('No hay efector en sesión.');
        }

        if (!ProfesionalEfectorServicioRecord::rrhhTieneAsignacionPesEnEfector($idRrHh, $idEfector)) {
            throw new ForbiddenHttpException('RRHH no pertenece al efector actual.');
        }
    }
}
