<?php

namespace common\components\Organization\Service\ProfesionalEfectorServicio;

use common\models\Condiciones_laborales;
use common\components\Organization\Service\ProfesionalEfectorServicio\AgendaIntervaloMinutos;
use common\models\ProfesionalEfectorServicio as ProfesionalEfectorServicioRecord;
use common\models\ProfesionalEfectorServicioAgenda;
use common\models\ProfesionalEfectorServicioCondicionLaboral;
use common\models\Servicio;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Pre-carga y persistencia de agenda / condición laboral para UI JSON
 * ({@see \frontend\modules\api\v1\controllers\ProfesionalAgendaController::actionConfigurarAgenda}).
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
        $idServicio = isset($query['id_servicio']) ? (int) $query['id_servicio'] : 0;
        $idPesIn = isset($query['id_profesional_efector_servicio']) ? (int) $query['id_profesional_efector_servicio'] : 0;
        $idStaff = ProfesionalEfectorServicioRecord::staffContextIdFromRequestParams($query);
        if ($idPesIn > 0) {
            $idStaff = $idPesIn;
        }

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
            if ($idServicio <= 0) {
                $idServicio = (int) $pesAnchor->id_servicio;
            }
            if ((int) $pesAnchor->id_servicio !== $idServicio) {
                throw new BadRequestHttpException('id_servicio no coincide con la asignación profesional seleccionada.');
            }
        }

        if ($idStaff > 0) {
            self::assertStaffContextPerteneceAEfector($idStaff, $idEfector);
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

        if ($idStaff > 0) {
            $idPersona = ProfesionalEfectorServicioRecord::resolveIdPersonaFromStaffContextId($idStaff);
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
        } elseif ($idStaff > 0) {
            $idPersona = ProfesionalEfectorServicioRecord::resolveIdPersonaFromStaffContextId($idStaff);
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
            $out['intervalo_minutos'] = (string) ($agenda->intervalo_minutos ?? AgendaIntervaloMinutos::DEFAULT);
            $out['formas_atencion'] = (string) $agenda->formas_atencion;
            $out['acepta_consultas_online'] = $agenda->acepta_consultas_online ? '1' : '0';
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

        $out['vigente_desde'] = date('Y-m-d', strtotime('+1 day'));

        return $out;
    }

    /**
     * Preview de impacto al cambiar intervalo / horarios (sin persistir).
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function previewAgendaConfig(int $idEfector, array $post): array
    {
        $idPes = self::resolvePesIdForAgendaSubmit($idEfector, $post);

        return ProfesionalEfectorServicioAgendaVersionService::previewImpacto($idPes, $idEfector, $post);
    }

    /**
     * Valores para precargar UI de condición laboral (solo RRHH).
     *
     * @return array<string, mixed>
     */
    public static function buildCondicionLaboralValuesForGet(int $idEfector, array $query): array
    {
        $idPesIn = isset($query['id_profesional_efector_servicio']) ? (int) $query['id_profesional_efector_servicio'] : 0;
        $idStaff = ProfesionalEfectorServicioRecord::staffContextIdFromRequestParams($query);
        if ($idPesIn > 0) {
            $idStaff = $idPesIn;
        }
        $out = [
            'id_efector' => (string) $idEfector,
        ];
        if ($idPesIn > 0) {
            $pes = ProfesionalEfectorServicioRecord::findOne(['id' => $idPesIn, 'deleted_at' => null]);
            if ($pes !== null && (int) $pes->id_efector === $idEfector) {
                $out['id_profesional_efector_servicio'] = (string) $idPesIn;
            }
        }
        if ($idStaff <= 0 && !isset($out['id_profesional_efector_servicio'])) {
            return $out;
        }
        if ($idStaff > 0) {
            self::assertStaffContextPerteneceAEfector($idStaff, $idEfector);
        }
        if (!isset($out['id_profesional_efector_servicio']) && $idStaff > 0) {
            $idPersonaLegacy = ProfesionalEfectorServicioRecord::resolveIdPersonaFromStaffContextId($idStaff);
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
     * @return array{message: string, agenda_ui_completed: string}
     */
    public static function submitAgendaConfig(int $idEfector, array $post): array
    {
        $idServicio = (int) ($post['id_servicio'] ?? 0);
        $idPesPost = (int) ($post['id_profesional_efector_servicio'] ?? 0);
        if ($idPesPost > 0) {
            $pesFromPost = ProfesionalEfectorServicioRecord::findOne(['id' => $idPesPost, 'deleted_at' => null]);
            if ($pesFromPost !== null && $idServicio <= 0) {
                $idServicio = (int) $pesFromPost->id_servicio;
            }
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

        $idPes = self::resolvePesIdForAgendaSubmit($idEfector, $post);

        return ProfesionalEfectorServicioAgendaVersionService::publicarVersion($idPes, $idEfector, $post);
    }

    /**
     * @param array<string, mixed> $post
     */
    private static function resolvePesIdForAgendaSubmit(int $idEfector, array $post): int
    {
        $idStaff = ProfesionalEfectorServicioRecord::staffContextIdFromRequestParams($post);
        $idServicio = (int) ($post['id_servicio'] ?? 0);
        $idPesPost = (int) ($post['id_profesional_efector_servicio'] ?? 0);
        if ($idPesPost > 0) {
            $idStaff = $idPesPost;
        }

        if ($idPesPost > 0) {
            $pesFromPost = ProfesionalEfectorServicioRecord::findOne(['id' => $idPesPost, 'deleted_at' => null]);
            if ($pesFromPost === null || (int) $pesFromPost->id_efector !== $idEfector) {
                throw new BadRequestHttpException('id_profesional_efector_servicio inválido para este efector.');
            }

            return (int) $pesFromPost->id;
        }

        if ($idServicio <= 0 || $idStaff <= 0) {
            throw new BadRequestHttpException('Indique id_servicio e id_profesional_efector_servicio.');
        }

        self::assertStaffContextPerteneceAEfector($idStaff, $idEfector);
        $idPersona = ProfesionalEfectorServicioRecord::resolveIdPersonaFromStaffContextId($idStaff);
        if ($idPersona === null || $idPersona <= 0) {
            throw new BadRequestHttpException('El recurso humano no pertenece al efector en sesión.');
        }

        $pes = ProfesionalEfectorServicioRecord::findOneActivoPorPersonaEfectorServicio(
            $idPersona,
            $idEfector,
            $idServicio
        );
        if ($pes === null) {
            throw new BadRequestHttpException('No se pudo resolver la asignación profesional.');
        }

        return (int) $pes->id;
    }

    /**
     * Crear/editar condición laboral (upsert) de un RRHH (sin tocar agenda por servicio).
     *
     * @param array<string, mixed> $post
     * @return array{message: string, condicion_laboral_ui_completed: string}
     */
    public static function submitCondicionLaboral(int $idEfector, array $post, bool $requireOwnPes = false): array
    {
        $idStaff = ProfesionalEfectorServicioRecord::staffContextIdFromRequestParams($post);
        $idPes = (int) ($post['id_profesional_efector_servicio'] ?? 0);
        if ($idPes > 0) {
            $idStaff = $idPes;
        }

        if ($idPes > 0) {
            $pesOk = ProfesionalEfectorServicioRecord::findOne(['id' => $idPes, 'deleted_at' => null]);
            if ($pesOk === null || (int) $pesOk->id_efector !== $idEfector) {
                throw new BadRequestHttpException('id_profesional_efector_servicio inválido para este efector.');
            }
        } elseif ($idStaff > 0) {
            self::assertStaffContextPerteneceAEfector($idStaff, $idEfector);
            $idPersonaLegacy = ProfesionalEfectorServicioRecord::resolveIdPersonaFromStaffContextId($idStaff);
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
            throw new BadRequestHttpException('Indique id_profesional_efector_servicio con PES en este efector.');
        }

        if ($requireOwnPes) {
            $idPersona = (int) Yii::$app->user->getIdPersona();
            $pesOwn = ProfesionalEfectorServicioRecord::findOne(['id' => $idPes, 'deleted_at' => null]);
            if ($pesOwn === null || (int) $pesOwn->id_persona !== $idPersona) {
                throw new ForbiddenHttpException('Solo podés registrar licencias sobre tus asignaciones.');
            }
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

        return [
            'message' => 'Condición laboral guardada.',
            'condicion_laboral_ui_completed' => '1',
        ];
    }

    private static function assertStaffContextPerteneceAEfector(int $idStaffContext, int $idEfector): void
    {
        if ($idEfector <= 0) {
            throw new BadRequestHttpException('No hay efector en sesión.');
        }

        if (!ProfesionalEfectorServicioRecord::staffContextTienePesEnEfector($idStaffContext, $idEfector)) {
            throw new ForbiddenHttpException('El profesional no pertenece al efector actual.');
        }
    }
}
