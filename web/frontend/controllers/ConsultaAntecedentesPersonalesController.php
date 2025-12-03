<?php

namespace frontend\controllers;

use frontend\controllers\DefaultController;

use Yii;
use yii\helpers\ArrayHelper;
use yii\base\Exception;

use frontend\components\SisseHtmlHelpers;

use common\models\PersonasAntecedente;
use common\models\snomed\SnomedSituacion;

class ConsultaAntecedentesPersonalesController extends DefaultController
{
    //use \frontend\controllers\traits\ConsultaTrait;

    public function createCore($modelConsulta)
    {     
        $idsBDPrevioPost = [];
        $mapCodigoId = [];

        $modelosPersonasAntecedenteConsultas = $modelConsulta->personasAntecedenteConsultas;
        if (!$modelosPersonasAntecedenteConsultas) {
            $modelosPersonasAntecedenteConsultas = [new PersonasAntecedente()];
        } else {
            $idsBDPrevioPost = ArrayHelper::getColumn($modelosPersonasAntecedenteConsultas, 'id');
            // necesito lo siguiente porque desde la vista (post) no recibo los id solo los codigos
            // y el hard delete lo hago por id
            $mapCodigoId = yii\helpers\ArrayHelper::map($modelosPersonasAntecedenteConsultas, 'codigo', 'id');            
        }

        // paso un array de modelos (de la BD) a uno solo       
        $modelPersonasAntecedenteConsultas = SisseHtmlHelpers::loadFromModelsAndCreateSelect2(PersonasAntecedente::classname(), $modelosPersonasAntecedenteConsultas);
        // modelConsultaSintomas puede ser un nuevo modelo, entonces select2_codigo es null
        $conceptsIdGuardados = $modelPersonasAntecedenteConsultas->select2_codigo ? $modelPersonasAntecedenteConsultas->select2_codigo : [];

        if (Yii::$app->request->post()) {

            //var_dump($modelPersonasAntecedenteConsultas->select2_codigo);

            $modelPersonasAntecedenteConsultas->load(Yii::$app->request->post());

            // vuelvo a pasar de un select2 (del POST) a muchos modelos, pisando la variable modelosPersonasAntecedenteConsultas
            // que viene de la BD, el objeto es simple, solo se pueden crear o agregar registros no hay datos a actualizar
            $modelosPersonasAntecedenteConsultas = SisseHtmlHelpers::loadFromSelect2AndCreateModels(PersonasAntecedente::classname());

            //var_dump($modelPersonasAntecedenteConsultas->select2_codigo);die;
            $terminos = explode(",", Yii::$app->request->post("terminos_situaciones_personales"));
            $mensajeRespuesta = 'Se saltó el paso anterior sin agregar registros';
            //if(Yii::$app->request->post()["PersonasAntecedente"]['select2_codigo'] != '') {
            
                $transaction = \Yii::$app->db->beginTransaction();

                try {
                    $idsEnPost = [];

                    $modelConsulta->save();
                    
                    foreach ($modelosPersonasAntecedenteConsultas as $i => $modelAntecedentePersonal) {

                        SnomedSituacion::crearSiNoExiste($modelAntecedentePersonal->codigo, $terminos[$i]);

                        // Puede estar ya creado
                        if (in_array($modelAntecedentePersonal->codigo, $conceptsIdGuardados)) {
                            // voy registrando los ids para el hardDeleteAll
                            // los que estan en idsBDPrevioPost y no en idsEnPost se eliminan
                            $idsEnPost[] = $mapCodigoId[$modelAntecedentePersonal->codigo];                            
                            continue;
                        }

                        $modelAntecedentePersonal->origen_id_antecedente = 'snomed';
                        $modelAntecedentePersonal->tipo_antecedente = 'Personal';
                        $modelAntecedentePersonal->id_consulta = $modelConsulta->id_consulta;
                        $modelAntecedentePersonal->id_persona = $modelConsulta->id_persona;
                        if (!$modelAntecedentePersonal->save()) {
                            throw new Exception();
                        }
                    }

                    // eliminar los que estaban en la BD y no vienen en el post
                    $idsAEliminar = array_diff($idsBDPrevioPost, $idsEnPost);                  
                    // hard delete, hardDeleteGrupo verifica que $idsAEliminar no sea vacio
                    PersonasAntecedente::hardDeleteGrupo($modelConsulta->id_consulta, $idsAEliminar);

                    $mensajeRespuesta = 'El/Los antecedentes personales fueron cargados exitosamente.';                        
                    if (count($idsAEliminar) > 0) {
                        $mensajeRespuesta = 'El/Los antecedentes personales fueron actualizados exitosamente.'; 
                    }

                } catch (\Exception $th) {                
                    if ($th->getMessage() != "") {
                        Yii::error($th->getMessage());

                    }

                    $transaction->rollBack();

                    $dataAntecedentesPersonales = [];
                    $conceptsIdABuscar = $modelPersonasAntecedenteConsultas->select2_codigo ? $modelPersonasAntecedenteConsultas->select2_codigo : [];
                    if (count($conceptsIdABuscar) > 0) {
                        $snomedSituaciones = SnomedSituacion::find()->where(['in', 'conceptId', $conceptsIdABuscar])->asArray()->all();
                        $dataAntecedentesPersonales = yii\helpers\ArrayHelper::map($snomedSituaciones, 'conceptId', 'term');
                    }

                    return $this->renderAjax('../consultas/v2/_form_antecedentes_personales', [
                        'modelConsulta' => $modelConsulta,
                        'modelPersonasAntecedenteConsultas' => $modelPersonasAntecedenteConsultas,
                        'dataAntecedentesPersonales' => $dataAntecedentesPersonales,
                        'antecedentesPersonales' => PersonasAntecedente::find()->where(['id_persona' => $modelConsulta->id_persona, 'tipo_antecedente' => 'Personal'])->all()
                    ]);
                }

                $transaction->commit();

           /* }else{// se borran todos los antecedentes cargados en la consulta                
                if($conceptsIdGuardados[0] !== NULL) {
                    // hard delete
                    PersonasAntecedente::eliminarGrupo($modelConsulta->id_consulta, $conceptsIdGuardados);
                    $mensajeRespuesta = "Se eliminaron todos los registros de antecedentes que se encontraban registrados.";
                }                
            }*/

            return [
                'success' => true,
                'msg' => $mensajeRespuesta,
                'url_siguiente' => $modelConsulta->urlSiguiente
            ];
        }

        $dataAntecedentesPersonales = [];
        $conceptsIdABuscar = $modelPersonasAntecedenteConsultas->select2_codigo ? $modelPersonasAntecedenteConsultas->select2_codigo : [];
        if (count($conceptsIdABuscar) > 0) {
            $snomedSituaciones = SnomedSituacion::find()->where(['in', 'conceptId', $conceptsIdABuscar])->asArray()->all();
            $dataAntecedentesPersonales = yii\helpers\ArrayHelper::map($snomedSituaciones, 'conceptId', 'term');
        }

        return $this->renderAjax('../consultas/v2/_form_antecedentes_personales', [
            'modelConsulta' => $modelConsulta,
            'modelPersonasAntecedenteConsultas' => (empty($modelPersonasAntecedenteConsultas)) ? [new PersonasAntecedente] : $modelPersonasAntecedenteConsultas,
            'dataAntecedentesPersonales' => $dataAntecedentesPersonales,
            'antecedentesPersonales' => PersonasAntecedente::find()->where(['id_persona' => $modelConsulta->id_persona, 'tipo_antecedente' => 'Personal'])->all()
        ]);
    }

}
