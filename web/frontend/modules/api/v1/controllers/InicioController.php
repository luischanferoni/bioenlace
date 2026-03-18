<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\models\Consulta;
use common\models\Turno;
use common\models\Guardia;
use common\models\Persona;
use common\models\InfraestructuraPiso;

/**
 * API para datos de la pantalla de inicio (site/inicio-dia).
 * Según el encounter class del usuario: AMB → turnos, IMP → internados, EMER → guardias.
 * GET /api/v1/inicio/datos?fecha=YYYY-MM-DD
 */
class InicioController extends BaseController
{
    public static $frontendControllerClass = null;

    public function actionDatos()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $fecha = Yii::$app->request->get('fecha', date('Y-m-d'));
        $encounterClass = Yii::$app->user->getEncounterClass();
        $idEfector = Yii::$app->user->getIdEfector();
        $idRrhh = Yii::$app->user->getIdRecursoHumano();

        if (!$encounterClass || !$idEfector) {
            return $this->success([
                'encounter_class' => $encounterClass ?: null,
                'kind' => null,
                'data' => [],
                'fecha' => $fecha,
                'error' => 'Falta configuración de encounter o efector.',
            ]);
        }

        switch ($encounterClass) {
            case Consulta::ENCOUNTER_CLASS_AMB:
                $data = $this->formatearTurnosParaInicio($fecha, $idRrhh);
                return $this->success(['encounter_class' => $encounterClass, 'kind' => 'turnos', 'data' => $data, 'fecha' => $fecha]);
            case Consulta::ENCOUNTER_CLASS_IMP:
                $data = InfraestructuraPiso::getInternadosPorEfector($idEfector);
                return $this->success(['encounter_class' => $encounterClass, 'kind' => 'internados', 'data' => $data, 'fecha' => $fecha]);
            case Consulta::ENCOUNTER_CLASS_EMER:
                $data = $this->formatearGuardiasParaInicio($idEfector);
                return $this->success(['encounter_class' => $encounterClass, 'kind' => 'guardias', 'data' => $data, 'fecha' => $fecha]);
            default:
                return $this->success(['encounter_class' => $encounterClass, 'kind' => null, 'data' => [], 'fecha' => $fecha]);
        }
    }

    private function formatearTurnosParaInicio($fecha, $idRrhh)
    {
        if (!$idRrhh) {
            return [];
        }
        $turnos = Turno::getTurnosPorRrhhPorFecha($fecha, $idRrhh);
        $out = [];
        foreach ($turnos as $turno) {
            $paciente = $turno->persona;
            $servicio = $turno->servicio ? $turno->servicio->nombre : ($turno->rrhhServicioAsignado ? $turno->rrhhServicioAsignado->servicio->nombre : 'Sin servicio');
            $out[] = [
                'id' => $turno->id_turnos,
                'id_persona' => $turno->id_persona,
                'paciente' => [
                    'id' => $paciente ? $paciente->id_persona : null,
                    'nombre_completo' => $paciente ? $paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D) : 'Sin paciente',
                    'documento' => $paciente ? $paciente->documento : null,
                ],
                'fecha' => $turno->fecha,
                'hora' => $turno->hora,
                'servicio' => $servicio,
                'estado' => $turno->estado,
                'estado_label' => Turno::ESTADOS[$turno->estado] ?? 'Sin estado',
                'observaciones' => $turno->hasAttribute('observaciones') ? $turno->observaciones : null,
            ];
        }
        return $out;
    }

    private function formatearGuardiasParaInicio($idEfector)
    {
        $guardias = Guardia::pacientesPendientesPorEfector($idEfector);
        $out = [];
        foreach ($guardias as $guardia) {
            $paciente = $guardia->paciente;
            $out[] = [
                'id' => $guardia->id,
                'id_persona' => $guardia->id_persona,
                'nombre_completo' => $paciente ? $paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N) : 'Sin nombre',
                'documento' => $paciente ? $paciente->documento : null,
                'tipo_documento' => $paciente && $paciente->tipoDocumento ? $paciente->tipoDocumento->nombre : null,
                'fecha' => $guardia->fecha,
                'hora' => $guardia->hora,
                'estado' => $guardia->estado,
            ];
        }
        return $out;
    }
}
