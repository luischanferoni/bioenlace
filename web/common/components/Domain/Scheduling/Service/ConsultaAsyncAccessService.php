<?php

namespace common\components\Domain\Scheduling\Service;

use common\models\Clinical\Encounter;
use common\models\ProfesionalEfectorServicio;
use Yii;

/**
 * Acceso staff a encounters async (reparto por servicio en efector de sesión).
 */
final class ConsultaAsyncAccessService
{
    public static function staffCanAccessAsyncEncounter(Encounter $encounter): bool
    {
        if ($encounter->parent_type !== Encounter::PARENT_SOLICITUD_ASYNC) {
            return false;
        }

        $serviceId = (int) ($encounter->service_id ?? 0);
        if ($serviceId <= 0) {
            return false;
        }

        $scope = new ConsultaAsyncStaffScopeService();
        $servicios = $scope->idServiciosAtendiblesEnEfector();

        return in_array($serviceId, $servicios, true);
    }

    public static function staffPuedeTomar(Encounter $encounter): bool
    {
        if (!self::staffCanAccessAsyncEncounter($encounter)) {
            return false;
        }

        $idPesAsignado = (int) ($encounter->id_profesional_efector_servicio ?? 0);
        if ($idPesAsignado > 0) {
            return self::pesPerteneceAlUsuarioActual($idPesAsignado);
        }

        $serviceId = (int) ($encounter->service_id ?? 0);

        return (new ConsultaAsyncStaffScopeService())->idPesSesionParaServicio($serviceId) > 0;
    }

    public static function pesPerteneceAlUsuarioActual(int $idPes): bool
    {
        if ($idPes <= 0) {
            return false;
        }
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            return false;
        }
        $pes = ProfesionalEfectorServicio::findOne(['id' => $idPes, 'deleted_at' => null]);

        return $pes !== null && (int) $pes->id_persona === $idPersona;
    }
}
