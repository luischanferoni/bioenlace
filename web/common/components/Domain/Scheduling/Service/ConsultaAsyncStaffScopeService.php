<?php

namespace common\components\Domain\Scheduling\Service;

use common\models\ProfesionalEfectorServicio;
use common\models\ServiciosEfector;
use Yii;

/**
 * Servicios del efector en sesión que el staff puede atender (vía PES activos).
 */
final class ConsultaAsyncStaffScopeService
{
    /**
     * @return list<int>
     */
    public function idServiciosAtendiblesEnEfector(): array
    {
        $idEfector = (int) Yii::$app->user->getIdEfector();
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idEfector <= 0 || $idPersona <= 0) {
            return [];
        }

        $idsPes = ProfesionalEfectorServicio::find()
            ->select('id_servicio')
            ->where([
                'id_efector' => $idEfector,
                'id_persona' => $idPersona,
                'deleted_at' => null,
            ])
            ->column();

        $ids = [];
        foreach ($idsPes as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $ids[$n] = $n;
            }
        }

        if ($ids === []) {
            return [];
        }

        $enEfector = ServiciosEfector::find()
            ->select('id_servicio')
            ->where(['id_efector' => $idEfector, 'deleted_at' => null])
            ->andWhere(['id_servicio' => array_values($ids)])
            ->column();

        $out = [];
        foreach ($enEfector as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $out[] = $n;
            }
        }

        return $out;
    }

    /**
     * PES de sesión válido para un servicio en el efector actual.
     */
    public function idPesSesionParaServicio(int $serviceId): int
    {
        if ($serviceId <= 0) {
            return 0;
        }
        $idEfector = (int) Yii::$app->user->getIdEfector();
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idEfector <= 0 || $idPersona <= 0) {
            return 0;
        }

        $idPesSesionRaw = Yii::$app->user->getIdProfesionalEfectorServicio();
        $idPesSesion = $idPesSesionRaw !== null && $idPesSesionRaw !== '' ? (int) $idPesSesionRaw : 0;
        if ($idPesSesion > 0) {
            $pes = ProfesionalEfectorServicio::findOne(['id' => $idPesSesion, 'deleted_at' => null]);
            if (
                $pes !== null
                && (int) $pes->id_efector === $idEfector
                && (int) $pes->id_persona === $idPersona
                && (int) $pes->id_servicio === $serviceId
            ) {
                return $idPesSesion;
            }
        }

        $pes = ProfesionalEfectorServicio::find()
            ->where([
                'id_efector' => $idEfector,
                'id_persona' => $idPersona,
                'id_servicio' => $serviceId,
                'deleted_at' => null,
            ])
            ->orderBy(['id' => SORT_ASC])
            ->one();

        return $pes !== null ? (int) $pes->id : 0;
    }
}
