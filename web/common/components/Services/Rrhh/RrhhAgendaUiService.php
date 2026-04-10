<?php

namespace common\components\Services\Rrhh;

use common\models\Agenda_rrhh;
use common\models\Condiciones_laborales;
use common\models\RrhhEfector;
use common\models\RrhhLaboral;
use common\models\RrhhServicio;
use common\models\Servicio;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Submit y pre-carga para el wizard UI JSON {@see \frontend\modules\api\v1\controllers\RrhhController::actionEditarAgenda}.
 */
final class RrhhAgendaUiService
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

        self::assertRrhhBelongsToEfector($idRrHh, $idEfector);
        $out['id_rr_hh'] = (string) $idRrHh;

        if ($idServicio <= 0) {
            return $out;
        }

        $rrhhServicio = RrhhServicio::find()
            ->where(['id_rr_hh' => $idRrHh, 'id_servicio' => $idServicio])
            ->andWhere(['deleted_at' => null])
            ->one();

        if ($rrhhServicio === null) {
            return $out;
        }

        $out['id_servicio'] = (string) $idServicio;

        $agenda = Agenda_rrhh::find()
            ->where(['id_rrhh_servicio_asignado' => $rrhhServicio->id])
            ->andWhere(['deleted_at' => null])
            ->one();

        if ($agenda !== null) {
            $out['cupo_pacientes'] = (string) $agenda->cupo_pacientes;
            $out['duracion_slot_minutos'] = $agenda->duracion_slot_minutos !== null ? (string) $agenda->duracion_slot_minutos : '';
            $out['formas_atencion'] = (string) $agenda->formas_atencion;
            foreach (['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'] as $col) {
                $out[$col] = (string) ($agenda->$col ?? '');
            }
        }

        $laboral = RrhhLaboral::find()
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
     * Persistencia atómica: agenda del servicio seleccionado + una fila de condición laboral.
     *
     * @param array<string, mixed> $post
     * @return array{message: string}
     */
    public static function submit(int $idEfector, array $post): array
    {
        $idRrHh = (int) ($post['id_rr_hh'] ?? 0);
        $idServicio = (int) ($post['id_servicio'] ?? 0);

        if ($idRrHh <= 0 || $idServicio <= 0) {
            throw new BadRequestHttpException('id_rr_hh e id_servicio son obligatorios.');
        }

        self::assertRrhhBelongsToEfector($idRrHh, $idEfector);

        if ($idServicio === 62) {
            throw new BadRequestHttpException('Servicio no editable en este flujo.');
        }

        $servicio = Servicio::findOne($idServicio);
        if ($servicio === null) {
            throw new NotFoundHttpException('Servicio inexistente.');
        }

        $rrhhServicio = RrhhServicio::find()
            ->where(['id_rr_hh' => $idRrHh, 'id_servicio' => $idServicio])
            ->andWhere(['deleted_at' => null])
            ->one();

        if ($rrhhServicio === null) {
            throw new BadRequestHttpException('El RRHH no tiene asignado ese servicio.');
        }

        $agenda = Agenda_rrhh::find()
            ->where(['id_rrhh_servicio_asignado' => $rrhhServicio->id])
            ->andWhere(['deleted_at' => null])
            ->one();

        if ($agenda === null) {
            $agenda = new Agenda_rrhh();
            $agenda->id_rrhh_servicio_asignado = $rrhhServicio->id;
            $agenda->id_efector = $idEfector;
        }

        $agenda->load($post, '');
        $agenda->id_rrhh_servicio_asignado = $rrhhServicio->id;
        if (empty($agenda->id_efector)) {
            $agenda->id_efector = $idEfector;
        }

        if ($servicio->acepta_turnos === 'NO') {
            $agenda->formas_atencion = Agenda_rrhh::FORMA_ATENCION_SIN_ATENCION;
        }

        $idCondicion = isset($post['id_condicion_laboral']) ? (int) $post['id_condicion_laboral'] : 0;
        if ($idCondicion <= 0) {
            throw new BadRequestHttpException('id_condicion_laboral es obligatorio.');
        }

        if (!Condiciones_laborales::find()->where(['id_condicion_laboral' => $idCondicion])->exists()) {
            throw new BadRequestHttpException('Condición laboral inválida.');
        }

        $laboral = RrhhLaboral::find()
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

        $tx = Yii::$app->db->beginTransaction();
        try {
            if (!$agenda->validate()) {
                throw new BadRequestHttpException(implode(' ', $agenda->getFirstErrors()));
            }

            if (!$agenda->save(false)) {
                throw new \RuntimeException('No se pudo guardar la agenda.');
            }

            if (!$laboral->validate()) {
                throw new BadRequestHttpException(implode(' ', $laboral->getFirstErrors()));
            }

            if (!$laboral->save(false)) {
                throw new \RuntimeException('No se pudo guardar la condición laboral.');
            }

            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        return ['message' => 'Cambios guardados.'];
    }

    private static function assertRrhhBelongsToEfector(int $idRrHh, int $idEfector): void
    {
        if ($idEfector <= 0) {
            throw new BadRequestHttpException('No hay efector en sesión.');
        }

        $re = RrhhEfector::find()
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
