<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\filters\auth\CompositeAuth;
use common\models\Consulta;
use common\models\Guardia;
use common\models\InfraestructuraPiso;
use common\models\Persona;
use common\models\RrhhEfector;
use common\models\ServiciosEfector;
use common\models\Turno;
use frontend\modules\api\v1\components\JsonHttpBearerAuth;
use frontend\modules\api\v1\components\SessionIdentityAuth;

/**
 * Listado de pacientes por modalidad (encounter): ambulatorio, internación, guardia.
 * GET /api/v1/pacientes/ambulatorio?fecha=…
 * GET /api/v1/pacientes/internacion
 * GET /api/v1/pacientes/guardia
 */
class PacientesController extends BaseController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        unset($behaviors['authenticator']);
        $behaviors['authenticator'] = [
            'class' => CompositeAuth::class,
            'authMethods' => [
                JsonHttpBearerAuth::class,
                SessionIdentityAuth::class,
            ],
            'except' => ['options'],
        ];

        return $behaviors;
    }

    public function actionOptions()
    {
        Yii::$app->response->statusCode = 204;
    }

    /**
     * Listado de pacientes según encounter del usuario (turnos / internados / guardia).
     * GET /api/v1/pacientes?fecha=YYYY-MM-DD
     */
    public function actionIndex()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $fecha = Yii::$app->request->get('fecha', date('Y-m-d'));
        $encounterClass = Yii::$app->user->getEncounterClass();

        if (!$encounterClass) {
            return [
                'success' => false,
                'message' => 'No hay encounter configurado en la sesión.',
                'encounter_class' => null,
                'kind' => null,
                'data' => [],
                'fecha' => $fecha,
            ];
        }

        switch ($encounterClass) {
            case Consulta::ENCOUNTER_CLASS_AMB:
                $conPrueba = Yii::$app->request->get('prueba') === '1';
                $payload = $this->turnosAmbulatorioMedico($fecha, null, $conPrueba);
                return [
                    'success' => true,
                    'message' => 'OK',
                    'encounter_class' => $encounterClass,
                    'kind' => 'turnos',
                    'data' => $payload['turnos'],
                    'fecha' => $payload['fecha'],
                    'total' => $payload['total'],
                ];
            case Consulta::ENCOUNTER_CLASS_IMP:
                $data = $this->internadosPorEfector();
                return [
                    'success' => true,
                    'message' => 'OK',
                    'encounter_class' => $encounterClass,
                    'kind' => 'internados',
                    'data' => $data,
                    'fecha' => $fecha,
                ];
            case Consulta::ENCOUNTER_CLASS_EMER:
                $data = $this->guardiasPendientesPorEfector();
                return [
                    'success' => true,
                    'message' => 'OK',
                    'encounter_class' => $encounterClass,
                    'kind' => 'guardias',
                    'data' => $data,
                    'fecha' => $fecha,
                ];
            default:
                return [
                    'success' => false,
                    'message' => 'Encounter no soportado.',
                    'encounter_class' => $encounterClass,
                    'kind' => null,
                    'data' => [],
                    'fecha' => $fecha,
                ];
        }
    }

    /**
     * Turnos pendientes del médico (ambulatorio). Sin turno de prueba salvo ?prueba=1
     */
    public function actionAmbulatorio()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $fecha = Yii::$app->request->get('fecha', date('Y-m-d'));
        $conPrueba = Yii::$app->request->get('prueba') === '1';
        $payload = $this->turnosAmbulatorioMedico($fecha, null, $conPrueba);
        return array_merge(['success' => true], $payload);
    }

    public function actionInternacion()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return ['success' => true, 'internados' => $this->internadosPorEfector()];
    }

    public function actionGuardia()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return ['success' => true, 'guardias' => $this->guardiasPendientesPorEfector()];
    }

    /**
     * Misma respuesta que GET /api/v1/turnos (incl. turno prueba si aplica).
     *
     * @return array{turnos: array, fecha: string, total: int}
     */
    public static function agendaAmbulatorioJson(string $fecha, int $rrhhId, bool $conTurnoPrueba): array
    {
        $c = new self('pacientes', Yii::$app->getModule('v1'));
        return $c->turnosAmbulatorioMedico($fecha, $rrhhId, $conTurnoPrueba);
    }

    /**
     * @return array{turnos: array, fecha: string, total: int}
     */
    private function turnosAmbulatorioMedico(string $fecha, ?int $rrhhId, bool $agregarTurnoPruebaSiHoy): array
    {
        if ($rrhhId === null) {
            $rrhhId = Yii::$app->user->getIdRecursoHumano();
        }
        if (!$rrhhId || !RrhhEfector::findOne($rrhhId)) {
            return ['turnos' => [], 'fecha' => $fecha, 'total' => 0];
        }

        $turnos = Turno::findActive()
            ->andWhere(['id_rr_hh' => (int) $rrhhId, 'fecha' => $fecha])
            ->andWhere(['estado' => Turno::ESTADO_PENDIENTE])
            ->andWhere(['is', 'atendido', null])
            ->orderBy('hora')
            ->all();

        $formattedTurnos = [];
        foreach ($turnos as $turno) {
            $paciente = $turno->persona;
            $servicio = $turno->servicio ? $turno->servicio->nombre :
                ($turno->rrhhServicioAsignado ? $turno->rrhhServicioAsignado->servicio->nombre : 'Sin servicio');
            $consulta = Consulta::findOne(['id_turnos' => $turno->id_turnos]);
            $formattedTurnos[] = [
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
                'id_servicio_asignado' => $turno->id_servicio_asignado,
                'estado' => $turno->estado,
                'estado_label' => Turno::ESTADOS[$turno->estado] ?? 'Sin estado',
                'tipo_atencion' => isset($turno->tipo_atencion) ? $turno->tipo_atencion : Turno::TIPO_ATENCION_PRESENCIAL,
                'id_consulta' => $consulta ? $consulta->id_consulta : null,
                'atendido' => $turno->atendido,
                'created_at' => $turno->created_at,
                'observaciones' => $turno->hasAttribute('observaciones') ? $turno->observaciones : null,
            ];
        }

        if ($agregarTurnoPruebaSiHoy && $fecha === date('Y-m-d')) {
            $pacientePrueba = Persona::findOne(920779);
            if ($pacientePrueba) {
                $servicioPrueba = null;
                $idServicioAsignado = null;
                $rrhhEfector = RrhhEfector::findOne($rrhhId);
                if ($rrhhEfector && $rrhhEfector->id_efector) {
                    $servicioEfector = ServiciosEfector::find()->where(['id_efector' => $rrhhEfector->id_efector])->one();
                    if ($servicioEfector) {
                        $servicioPrueba = $servicioEfector->servicio ? $servicioEfector->servicio->nombre : 'Consulta General';
                        $idServicioAsignado = $servicioEfector->id_servicio;
                    }
                }
                if (!$servicioPrueba) {
                    $servicioPrueba = 'Consulta General';
                }
                array_unshift($formattedTurnos, [
                    'id' => 999999,
                    'id_persona' => 920779,
                    'paciente' => [
                        'id' => $pacientePrueba->id_persona,
                        'nombre_completo' => $pacientePrueba->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D),
                        'documento' => $pacientePrueba->documento,
                    ],
                    'fecha' => $fecha,
                    'hora' => '10:00',
                    'servicio' => $servicioPrueba,
                    'id_servicio_asignado' => $idServicioAsignado,
                    'estado' => Turno::ESTADO_PENDIENTE,
                    'estado_label' => Turno::ESTADOS[Turno::ESTADO_PENDIENTE] ?? 'Pendiente',
                    'atendido' => null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'observaciones' => null,
                ]);
            }
        }

        return [
            'turnos' => $formattedTurnos,
            'fecha' => $fecha,
            'total' => count($formattedTurnos),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function internadosPorEfector(): array
    {
        $idEfector = Yii::$app->user->getIdEfector();
        if (!$idEfector) {
            return [];
        }
        return InfraestructuraPiso::getInternadosPorEfector($idEfector);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function guardiasPendientesPorEfector(): array
    {
        $idEfector = Yii::$app->user->getIdEfector();
        if (!$idEfector) {
            return [];
        }
        $out = [];
        foreach (Guardia::pacientesPendientesPorEfector($idEfector) as $guardia) {
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
