<?php

namespace common\components\Services\ProfesionalEfectorServicio;

use common\models\Agenda_rrhh;
use common\models\Condiciones_laborales;
use common\models\ProfesionalEfectorServicio as ProfesionalEfectorServicioRecord;
use common\models\ProfesionalEfectorServicioAgenda;
use common\models\RrhhEfector;
use common\models\RrhhLaboral;
use common\models\RrhhServicio;
use common\models\Servicio;
use Yii;
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

        $out = [
            'id_efector' => (string) $idEfector,
        ];

        if ($idRrHh <= 0) {
            return $out;
        }

        self::assertRecursoHumanoPerteneceAEfector($idRrHh, $idEfector);
        $out['id_rr_hh'] = (string) $idRrHh;

        if ($idServicio <= 0) {
            return $out;
        }

        $servicioPre = Servicio::findOne($idServicio);
        if ($servicioPre !== null && strtoupper(trim((string) $servicioPre->acepta_turnos)) !== 'SI') {
            throw new BadRequestHttpException('Este servicio no admite agenda de turnos; no corresponde abrir la configuración de agenda.');
        }

        /** @var \yii\db\ActiveQuery $rrhhServicioQ */
        $rrhhServicioQ = RrhhServicio::find();
        $rrhhServicio = $rrhhServicioQ
            ->where(['id_rr_hh' => $idRrHh, 'id_servicio' => $idServicio])
            ->andWhere(['deleted_at' => null])
            ->one();

        if ($rrhhServicio === null) {
            return $out;
        }

        $out['id_servicio'] = (string) $idServicio;

        /** @var \yii\db\ActiveQuery $agendaQ */
        $agenda = null;

        /** @var ProfesionalEfectorServicioRecord|null $pes */
        $pes = ProfesionalEfectorServicioRecord::findActive()
            ->where(['legacy_rrhh_servicio_id' => (int) $rrhhServicio->id, 'deleted_at' => null])
            ->one();
        if ($pes !== null) {
            /** @var ProfesionalEfectorServicioAgenda|null $agenda */
            $agenda = ProfesionalEfectorServicioAgenda::findActive()
                ->where(['id_profesional_efector_servicio' => (int) $pes->id, 'deleted_at' => null])
                ->one();
        }
        if ($agenda === null) {
            $agendaQ = Agenda_rrhh::find();
            $agenda = $agendaQ
                ->where(['id_rrhh_servicio_asignado' => $rrhhServicio->id])
                ->andWhere(['deleted_at' => null])
                ->one();
        }

        if ($agenda !== null) {
            $out['cupo_pacientes'] = (string) $agenda->cupo_pacientes;
            $out['duracion_slot_minutos'] = $agenda->duracion_slot_minutos !== null ? (string) $agenda->duracion_slot_minutos : '';
            $out['formas_atencion'] = (string) $agenda->formas_atencion;
            foreach (['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'] as $col) {
                $out[$col] = (string) ($agenda->$col ?? '');
            }
        }

        /** @var \yii\db\ActiveQuery $laboralQ */
        $laboralQ = RrhhLaboral::find();
        $laboral = $laboralQ
            ->where(['id_rr_hh' => $idRrHh, 'deleted_at' => null])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        if ($laboral !== null) {
            $out['id_condicion_laboral'] = (string) $laboral->id_condicion_laboral;
            $out['fecha_inicio'] = (string) ($laboral->fecha_inicio ?? '');
            $out['fecha_fin'] = (string) ($laboral->fecha_fin ?? '');
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
        $out = [
            'id_efector' => (string) $idEfector,
        ];
        if ($idRrHh <= 0) {
            return $out;
        }
        self::assertRecursoHumanoPerteneceAEfector($idRrHh, $idEfector);
        $out['id_rr_hh'] = (string) $idRrHh;

        /** @var \yii\db\ActiveQuery $laboralQ */
        $laboralQ = RrhhLaboral::find();
        $laboral = $laboralQ
            ->where(['id_rr_hh' => $idRrHh, 'deleted_at' => null])
            ->orderBy(['id' => SORT_DESC])
            ->one();
        if ($laboral !== null) {
            $out['id_condicion_laboral'] = (string) $laboral->id_condicion_laboral;
            $out['fecha_inicio'] = (string) ($laboral->fecha_inicio ?? '');
            $out['fecha_fin'] = (string) ($laboral->fecha_fin ?? '');
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

        if ($idRrHh <= 0 || $idServicio <= 0) {
            throw new BadRequestHttpException('id_rr_hh e id_servicio son obligatorios.');
        }

        self::assertRecursoHumanoPerteneceAEfector($idRrHh, $idEfector);

        if ($idServicio === 62) {
            throw new BadRequestHttpException('Servicio no editable en este flujo.');
        }

        $servicio = Servicio::findOne($idServicio);
        if ($servicio === null) {
            throw new NotFoundHttpException('Servicio inexistente.');
        }

        /** @var \yii\db\ActiveQuery $rrhhServicioQ */
        $rrhhServicioQ = RrhhServicio::find();
        $rrhhServicio = $rrhhServicioQ
            ->where(['id_rr_hh' => $idRrHh, 'id_servicio' => $idServicio])
            ->andWhere(['deleted_at' => null])
            ->one();

        if ($rrhhServicio === null) {
            throw new BadRequestHttpException('El RRHH no tiene asignado ese servicio.');
        }

        if (strtoupper(trim((string) $servicio->acepta_turnos)) !== 'SI') {
            throw new BadRequestHttpException('Este servicio no admite agenda de turnos; no se puede guardar configuración de agenda.');
        }

        /** @var \yii\db\ActiveQuery $agendaQ */
        $agendaLegacy = null;
        $agendaLegacyQ = Agenda_rrhh::find();
        $agendaLegacy = $agendaLegacyQ
            ->where(['id_rrhh_servicio_asignado' => $rrhhServicio->id])
            ->andWhere(['deleted_at' => null])
            ->one();

        $rrhhEfector = RrhhEfector::find()
            ->where(['id_rr_hh' => $idRrHh, 'id_efector' => $idEfector, 'deleted_at' => null])
            ->one();
        if ($rrhhEfector === null) {
            throw new BadRequestHttpException('El recurso humano no pertenece al efector en sesión.');
        }
        /** @var ProfesionalEfectorServicioRecord|null $pes */
        $pes = ProfesionalEfectorServicioRecord::find()
            ->where(['legacy_rrhh_servicio_id' => (int) $rrhhServicio->id, 'deleted_at' => null])
            ->one();
        if ($pes === null) {
            $pes = new ProfesionalEfectorServicioRecord();
            $pes->id_persona = (int) $rrhhEfector->id_persona;
            $pes->id_efector = (int) $idEfector;
            $pes->id_servicio = (int) $idServicio;
            $pes->legacy_rrhh_servicio_id = (int) $rrhhServicio->id;
            if (!$pes->save()) {
                throw new \RuntimeException('No se pudo crear la asignación profesional-efector-servicio.');
            }
        }

        /** @var ProfesionalEfectorServicioAgenda|null $agendaNew */
        $agendaNew = ProfesionalEfectorServicioAgenda::findActive()
            ->where(['id_profesional_efector_servicio' => (int) $pes->id, 'deleted_at' => null])
            ->one();

        if ($agendaLegacy === null) {
            $agendaLegacy = new Agenda_rrhh();
            $agendaLegacy->id_rrhh_servicio_asignado = $rrhhServicio->id;
            $agendaLegacy->id_efector = $idEfector;
        }
        $agendaLegacy->load($post, '');
        $agendaLegacy->id_rrhh_servicio_asignado = $rrhhServicio->id;
        if (empty($agendaLegacy->id_efector)) {
            $agendaLegacy->id_efector = $idEfector;
        }

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

        $tx = Yii::$app->db->beginTransaction();
        try {
            if (!$agendaLegacy->validate()) {
                throw new BadRequestHttpException(implode(' ', $agendaLegacy->getFirstErrors()));
            }
            if (!$agendaNew->validate()) {
                throw new BadRequestHttpException(implode(' ', $agendaNew->getFirstErrors()));
            }

            if (!$agendaLegacy->save(false)) {
                throw new \RuntimeException('No se pudo guardar la agenda.');
            }
            if (!$agendaNew->save(false)) {
                throw new \RuntimeException('No se pudo guardar la agenda (nuevo modelo).');
            }

            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
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
        if ($idRrHh <= 0) {
            throw new BadRequestHttpException('id_rr_hh es obligatorio.');
        }
        self::assertRecursoHumanoPerteneceAEfector($idRrHh, $idEfector);

        $idCondicion = isset($post['id_condicion_laboral']) ? (int) $post['id_condicion_laboral'] : 0;
        if ($idCondicion <= 0) {
            throw new BadRequestHttpException('id_condicion_laboral es obligatorio.');
        }
        if (!Condiciones_laborales::find()->where(['id_condicion_laboral' => $idCondicion])->exists()) {
            throw new BadRequestHttpException('Condición laboral inválida.');
        }

        /** @var \yii\db\ActiveQuery $laboralQ */
        $laboralQ = RrhhLaboral::find();
        $laboral = $laboralQ
            ->where([
                'id_rr_hh' => $idRrHh,
                'id_condicion_laboral' => $idCondicion,
                'deleted_at' => null,
            ])
            ->one();

        if ($laboral === null) {
            $laboral = new RrhhLaboral();
            $laboral->id_rr_hh = $idRrHh;
            $laboral->id_condicion_laboral = $idCondicion;
        }

        $laboral->fecha_inicio = (string) ($post['fecha_inicio'] ?? '');
        $laboral->fecha_fin = (string) ($post['fecha_fin'] ?? '');

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

        /** @var \yii\db\ActiveQuery $reQ */
        $reQ = RrhhEfector::find();
        $re = $reQ
            ->where([
                'id_rr_hh' => $idRrHh,
                'id_efector' => $idEfector,
                'deleted_at' => null,
            ])
            ->one();

        if ($re === null) {
            throw new ForbiddenHttpException('RRHH no pertenece al efector actual.');
        }
    }
}
