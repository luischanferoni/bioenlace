<?php

namespace frontend\controllers;

use frontend\controllers\DefaultController;

use Yii;
use yii\helpers\ArrayHelper;
use yii\base\Exception;

use frontend\components\SisseHtmlHelpers;

use common\models\PersonasAntecedenteFamiliar;
use common\models\snomed\SnomedSituacion;

class ConsultaAntecedentesFamiliaresController extends DefaultController
{
    use \frontend\controllers\traits\ConsultaTrait;

    public function createCore($modelConsulta)
    {
        $idsBDPrevioPost = [];
        $mapCodigoId = [];

        $modelosPersonasAntecedenteFamiliarConsultas = $modelConsulta->personasAntecedenteFamiliarConsultas;
        if (!$modelosPersonasAntecedenteFamiliarConsultas) {
            $modelosPersonasAntecedenteFamiliarConsultas = [new PersonasAntecedenteFamiliar()];
        } else {
            $idsBDPrevioPost = ArrayHelper::getColumn($modelosPersonasAntecedenteFamiliarConsultas, 'id');
            // necesito lo siguiente porque desde la vista (post) no recibo los id solo los codigos
            // y el hard delete lo hago por id
            $mapCodigoId = yii\helpers\ArrayHelper::map($modelosPersonasAntecedenteFamiliarConsultas, 'codigo', 'id');            
        }

        // paso un array de modelos a uno solo       
        $modelPersonasAntecedenteFamiliarConsultas = SisseHtmlHelpers::loadFromModelsAndCreateSelect2(PersonasAntecedenteFamiliar::classname(), $modelosPersonasAntecedenteFamiliarConsultas);
        // modelPersonasAntecedenteFamiliarConsultas puede ser un nuevo modelo, entonces select2_codigo es null
        $conceptsIdGuardados = $modelPersonasAntecedenteFamiliarConsultas->select2_codigo ? $modelPersonasAntecedenteFamiliarConsultas->select2_codigo : [];

        if (Yii::$app->request->post()) {

            $modelPersonasAntecedenteFamiliarConsultas->load(Yii::$app->request->post());

            // vuelvo a pasar de un select2 a muchos modelos
            $modelosPersonasAntecedenteFamiliarConsultas = SisseHtmlHelpers::loadFromSelect2AndCreateModels(PersonasAntecedenteFamiliar::classname());

            $terminos = explode(",", Yii::$app->request->post("terminos_situaciones_familiares"));

            $mensajeRespuesta = 'Se saltó el paso anterior sin agregar registros';
            
            //if(isset($modelosPersonasAntecedenteConsultas)) {
            
            $transaction = \Yii::$app->db->beginTransaction();

            try {
                $idsEnPost = [];
                
                $modelConsulta->save();
                
                foreach ($modelosPersonasAntecedenteFamiliarConsultas as $i => $modelAntecedenteFamiliar) {

                    SnomedSituacion::crearSiNoExiste($modelAntecedenteFamiliar->codigo, $terminos[$i]);

                    // Puede estar ya creado
                    if (in_array($modelAntecedenteFamiliar->codigo, $conceptsIdGuardados)) {
                        // voy registrando los ids para el hardDeleteAll
                        // los que estan en idsBDPrevioPost y no en idsEnPost se eliminan
                        $idsEnPost[] = $mapCodigoId[$modelAntecedenteFamiliar->codigo];                        
                        continue;
                    }

                    $modelAntecedenteFamiliar->origen_id_antecedente = 'snomed';
                    $modelAntecedenteFamiliar->tipo_antecedente = 'Familiar';
                    $modelAntecedenteFamiliar->id_consulta = $modelConsulta->id_consulta;
                    $modelAntecedenteFamiliar->id_persona = $modelConsulta->id_persona;
                    if (!$modelAntecedenteFamiliar->save()) {
                        throw new Exception();
                    }
                }
                
                // eliminar los que estaban en la BD y no vienen en el post
                $idsAEliminar = array_diff($idsBDPrevioPost, $idsEnPost);
                // hard delete, hardDeleteGrupo verifica que $idsAEliminar no sea vacio
                PersonasAntecedenteFamiliar::hardDeleteGrupo($modelConsulta->id_consulta, $idsAEliminar);

                $mensajeRespuesta = 'El/Los antecedentes personales fueron cargados exitosamente.'; 
                if (count($idsAEliminar) > 0) {
                    $mensajeRespuesta = 'El/Los antecedentes personales fueron actualizados exitosamente.'; 
                }

            } catch (\Exception $th) {
                if ($th->getMessage() != "") {
                    Yii::error($th->getMessage());
                }

                $transaction->rollBack();

                $dataAntecedentesFamilares = [];
                $conceptsIdABuscar = $modelPersonasAntecedenteFamiliarConsultas->select2_codigo ? $modelPersonasAntecedenteFamiliarConsultas->select2_codigo : [];
                if (count($conceptsIdABuscar) > 0) {
                    $snomedSituaciones = SnomedSituacion::find()->where(['in', 'conceptId', $conceptsIdABuscar])->asArray()->all();
                    $dataAntecedentesFamilares = yii\helpers\ArrayHelper::map($snomedSituaciones, 'conceptId', 'term');
                }

                return $this->renderAjax('../consultas/v2/_form_antecedentes_familiares', [
                    'modelConsulta' => $modelConsulta,
                    'modelPersonasAntecedenteFamiliarConsultas' => $modelPersonasAntecedenteFamiliarConsultas,
                    'dataAntecedentesFamilares' => $dataAntecedentesFamilares,
                    'antecedentesFamiliares' => PersonasAntecedenteFamiliar::find()->where(['id_persona' => $modelConsulta->id_persona, 'tipo_antecedente' => 'Familiar'])->all()
                ]);
            }

            $transaction->commit();
        //}
            return [
                'success' => true,
                'msg' => $mensajeRespuesta,
                'url_siguiente' => $modelConsulta->urlSiguiente
            ];
        }

        $dataAntecedentesFamilares = [];
        $conceptsIdABuscar = $modelPersonasAntecedenteFamiliarConsultas->select2_codigo ? $modelPersonasAntecedenteFamiliarConsultas->select2_codigo : [];
        if (count($conceptsIdABuscar) > 0) {
            $snomedSituaciones = SnomedSituacion::find()->where(['in', 'conceptId', $conceptsIdABuscar])->asArray()->all();
            $dataAntecedentesFamilares = yii\helpers\ArrayHelper::map($snomedSituaciones, 'conceptId', 'term');
        }

        return $this->renderAjax('../consultas/v2/_form_antecedentes_familiares', [
            'modelConsulta' => $modelConsulta,
            'modelPersonasAntecedenteFamiliarConsultas' => (empty($modelPersonasAntecedenteFamiliarConsultas)) ? [new PersonasAntecedenteFamiliar] : $modelPersonasAntecedenteFamiliarConsultas,
            'dataAntecedentesFamilares' => $dataAntecedentesFamilares,
            'antecedentesFamiliares' => PersonasAntecedenteFamiliar::find()->where(['id_persona' => $modelConsulta->id_persona, 'tipo_antecedente' => 'Familiar'])->all()
        ]);
    }
}
