<?php

namespace common\components\Services\ProfesionalEfectorServicio;

use common\models\ProfesionalEfectorServicio as ProfesionalEfectorServicioModel;
use common\models\RrhhEfector;
use common\models\RrhhServicio;
use common\models\Servicio;
use common\models\ServiciosEfector;
use Yii;

/**
 * Alta idempotente: persona + efector + servicio → {@see RrhhEfector}, {@see RrhhServicio}, {@see ProfesionalEfectorServicioModel}.
 *
 * Sin HttpException: errores de negocio como \InvalidArgumentException.
 */
final class ProfesionalEfectorServicioAltaService
{
    /**
     * @return array{id_rr_hh: int, id_servicio: int, servicio_acepta_turnos: string}
     */
    public static function ensurePersonaServicioEnEfector(int $idPersona, int $idEfector, int $idServicio): array
    {
        if ($idPersona <= 0 || $idEfector <= 0 || $idServicio <= 0) {
            throw new \InvalidArgumentException('Datos inválidos para la asignación (persona, efector o servicio).');
        }

        $habilitado = ServiciosEfector::findActive()
            ->where(['id_efector' => $idEfector, 'id_servicio' => $idServicio])
            ->one();
        if ($habilitado === null) {
            throw new \InvalidArgumentException('El servicio no está habilitado en este efector.');
        }

        /** @var Servicio|null $servicio */
        $servicio = Servicio::findOne($idServicio);
        if ($servicio === null) {
            throw new \InvalidArgumentException('Servicio inexistente.');
        }
        $acepta = strtoupper(trim((string) $servicio->acepta_turnos));

        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();
        try {
            /** @var RrhhEfector|null $re */
            $re = RrhhEfector::findActive()
                ->where(['id_persona' => $idPersona, 'id_efector' => $idEfector])
                ->one();
            if ($re === null) {
                $re = new RrhhEfector();
                $re->id_persona = $idPersona;
                $re->id_efector = $idEfector;
                if (!$re->save()) {
                    throw new \RuntimeException('No se pudo crear el vínculo RRHH–efector: ' . json_encode($re->getErrors()));
                }
            }

            /** @var RrhhServicio|null $rs */
            $rs = RrhhServicio::find()
                ->where([
                    'id_rr_hh' => (int) $re->id_rr_hh,
                    'id_servicio' => $idServicio,
                    'deleted_at' => null,
                ])
                ->one();
            if ($rs === null) {
                $rs = new RrhhServicio();
                $rs->id_rr_hh = (int) $re->id_rr_hh;
                $rs->id_servicio = $idServicio;
                if ($rs->hasAttribute('horario') && ($rs->horario === null || $rs->horario === '')) {
                    $rs->horario = '';
                }
                if (!$rs->save()) {
                    throw new \RuntimeException('No se pudo asignar el servicio al RRHH: ' . json_encode($rs->getErrors()));
                }
            }

            /** @var ProfesionalEfectorServicioModel|null $pes */
            $pes = ProfesionalEfectorServicioModel::findActive()
                ->where([
                    'id_persona' => $idPersona,
                    'id_efector' => $idEfector,
                    'id_servicio' => $idServicio,
                ])
                ->one();
            if ($pes === null) {
                $pes = new ProfesionalEfectorServicioModel();
                $pes->id_persona = $idPersona;
                $pes->id_efector = $idEfector;
                $pes->id_servicio = $idServicio;
            }
            $pes->legacy_rrhh_servicio_id = (int) $rs->id;
            if (!$pes->save()) {
                throw new \RuntimeException('No se pudo registrar la asignación profesional–efector–servicio: ' . json_encode($pes->getErrors()));
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return [
            'id_rr_hh' => (int) $re->id_rr_hh,
            'id_servicio' => $idServicio,
            'servicio_acepta_turnos' => $acepta,
        ];
    }
}
