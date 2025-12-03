<?php

namespace frontend\controllers\traits;

use Yii;

use yii\base\InvalidConfigException;
   
use common\models\Persona;
use common\models\Consulta;
use common\models\ConsultasConfiguracion;
use frontend\components\UserRequest;
use common\models\Turno;
use common\models\ConsultaDerivaciones;

trait ConsultaTrait {

    public function crearUrlParaAtencion($paciente)
    {
        // Priorizar parámetros enviados por POST a través de UserRequest cuando corresponda
        $servicioActual = UserRequest::requireUserParam('servicio_actual');
        $encounterClass = UserRequest::requireUserParam('encounterClass');
        Consulta::calcularUrlV2(Yii::$app->request->get('id_consulta'), $paciente, $servicioActual, $encounterClass);

        $idConfiguracion = 0;
        $paso = 0;
        
        if (Yii::$app->request->get('id_consulta') != "" && Yii::$app->request->get('id_consulta') != null) {
            $modelConsulta = Consulta::findOne(Yii::$app->request->get('id_consulta'));
            $idConfiguracion = $modelConsulta->id_configuracion;
            Yii::debug('id_configuracion: '.$idConfiguracion);
            $paso = $modelConsulta->paso_completado + 1;
            list($urlAnterior, $urlActual, $urlSiguiente) = ConsultasConfiguracion::getUrlPorIdConfiguracion($idConfiguracion, $paso);
            
            if ($urlSiguiente == null) {
                $urlSiguiente = 'fin';
            }

            return [
                $modelConsulta, 
                ['id_configuracion' => $idConfiguracion, 'paso' => $paso],
                ['url_anterior' =>  $urlAnterior, 'url_siguiente' => $urlSiguiente],
            ];
        }
        
        // no recibimos el id_consulta, a deducir de acuerdo al encounter y el servicio
        $idServicioRrhh = UserRequest::requireUserParam('servicio_actual');
        if ($idServicioRrhh == "" || $idServicioRrhh == null) {
            $idServicioRrhh = 0;
        }

        $encounterClass = UserRequest::requireUserParam('encounterClass');
        if ($encounterClass == "" || $encounterClass == null) {
            $encounterClass = '';
        }

        list($idConfiguracion, $urlAnterior, $urlActual, $urlSiguiente, $parametrosExtra) = Consulta::calcularUrl($paciente, $idServicioRrhh, $encounterClass);
        if ($urlSiguiente == null) {
            $urlSiguiente = 'fin';
        }
        
        return [
                new Consulta(),
                ['id_configuracion' => $idConfiguracion, 'paso' => $paso],
                ['url_anterior' =>  $urlAnterior, 'url_siguiente' => $urlSiguiente],
            ];
    }

    public function guardarConsulta($arrayConfiguracion, $modelConsulta, $paciente)
    {
        if ($modelConsulta->isNewRecord) {

            $modelConsulta->id_rr_hh = Yii::$app->user->getIdRecursoHumano();
            $modelConsulta->id_servicio = Yii::$app->user->getServicioActual();
            $modelConsulta->id_persona = $paciente->id_persona;
            $modelConsulta->id_efector = Yii::$app->user->getIdEfector();
        }

        $modelConsulta->id_configuracion = $arrayConfiguracion['id_configuracion'];
        $modelConsulta->paso_completado = $arrayConfiguracion['paso'];

        if (!$modelConsulta->save()) {
            throw new Exception();
        }

        return $modelConsulta;
    }

}