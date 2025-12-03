<?php

namespace frontend\controllers;

use frontend\controllers\DefaultController;

use Yii;

use yii\helpers\ArrayHelper;

use common\models\Consulta;
use common\models\OdontoConsultaPersona;
use common\models\OdontoNomenclador;
use common\models\ConsultaOdontologiaPracticas;
use common\models\ConsultaOdontologiaEstados;
use common\models\ConsultaOdontologiaDiagnosticos;

use common\models\snomed\SnomedHallazgos;
use common\models\snomed\SnomedProcedimientos;
/**
 * ConsultaOdontologiaController implements the CRUD actions for ConsultaOdontologiaPracticas, ConsultaOdontologiaEstados and ConsultaOdontologiaDiagnosticos model.
 */
class ConsultaOdontologiaController extends DefaultController
{

    public function createCore($modelConsulta)
    {
        list($modelosOdontoConsultaPracticas,
            $modelosOdontoConsultaEstados,
            $modelosOdontoConsultaDiagnosticos,
            $dataNomencladorConsulta,
            $dataNomencladorPiezayCara,
            $dataNomencladorTto,
            $odontogramaPaciente,
        ) = $this->odontogramaPrepararDatos($modelConsulta->id_persona);

        $errores = [];

        if (Yii::$app->request->post()) {
            $idsEnPostEstados = [];
            $idsEnPostDiagnosticos = [];
            $idsEnPostPracticas = [];
            
            $idsEstadosBDPrevioPost = [];
            foreach ($modelosOdontoConsultaEstados as $modeloOdontoConsultaEstado) {
                if ($modeloOdontoConsultaEstado["id_consulta"] == $modelConsulta->id_consulta) {
                    $idsEstadosBDPrevioPost[] = $modeloOdontoConsultaEstado["id_consultas_odontologia_estados"];
                }
            }

            $idsDiagnosticosBDPrevioPost = [];
            foreach ($modelosOdontoConsultaDiagnosticos as $modeloOdontoConsultaDiagnostico) {
                if ($modeloOdontoConsultaDiagnostico["id_consulta"] == $modelConsulta->id_consulta) {
                    $idsDiagnosticosBDPrevioPost[] = $modeloOdontoConsultaDiagnostico["id"];
                }
            }

            $idsPracticasBDPrevioPost = [];
            foreach ($modelosOdontoConsultaPracticas as $modeloOdontoConsultaPractica) {
                if ($modeloOdontoConsultaPractica["id_consulta"] == $modelConsulta->id_consulta) {
                    $idsPracticasBDPrevioPost[] = $modeloOdontoConsultaPractica["id_consultas_odontologia_practicas"];
                }
            }

            $transaction = \Yii::$app->db->beginTransaction();
            
            try {
                
                $modelConsulta->save();

                // IMPORTANTE: considerar que por el post llegan todos los estados/diagnosticos/practicas
                // del paciente, realizados en esta y otras consultas

                if (isset(Yii::$app->request->post()['estados']) && Yii::$app->request->post()['estados'] !== "") {
                    
                    list($guardado, $piezaError, $msgsError, $idsEnPostEstados) = $this->guardarEstados($modelConsulta->id_consulta, $modelosOdontoConsultaEstados);
                    if (!$guardado) {
                        throw new \Exception("Error en la pieza N° (" . $piezaError . "): " . $msgsError);
                    }
                }

                if (isset(Yii::$app->request->post()['diagnosticos']) && Yii::$app->request->post()['diagnosticos'] !== "") {
                    
                    list($guardado, $piezaError, $msgsError, $idsEnPostDiagnosticos) = $this->guardarDiagnosticos($modelConsulta->id_consulta, $modelosOdontoConsultaDiagnosticos);
                    if (!$guardado) {   
                        throw new \Exception("Error en la pieza N° (" . $piezaError . "): " . $msgsError);
                    }
                }

                if (isset(Yii::$app->request->post()['practicas']) && Yii::$app->request->post()['practicas'] !== "") {

                    list($guardado, $piezaError, $msgsError, $idsEnPostPracticas) = $this->guardarPracticas($modelConsulta->id_consulta, $modelosOdontoConsultaPracticas);
                    if (!$guardado) {
                        throw new \Exception("Error en la pieza N° (" . $piezaError . "): " . $msgsError);
                    }
                }

                if (isset(Yii::$app->request->post()['practicas_boca']) && Yii::$app->request->post()['practicas_boca'] != "") {

                    $nuevoConsultaOdontoPracticas = new ConsultaOdontologiaPracticas();
                    $nuevoConsultaOdontoPracticas->id_consulta = $modelConsulta->id_consulta;
                    $nuevoConsultaOdontoPracticas->codigo = Yii::$app->request->post()['practicas_boca'];
                    $nuevoConsultaOdontoPracticas->tipo = 'BOCA';
                    $nuevoConsultaOdontoPracticas->tiempo =  'PRESENTE';
                    if (!$nuevoConsultaOdontoPracticas->save()) {
                        throw new \Exception("Error en la pieza N° (" . $piezaError . "): " . $nuevoConsultaOdontoPracticas->getFirstError());
                    }
                }
                
                // eliminar los que estaban en la BD y no vienen en el post
                $idsAEliminar = array_diff($idsEstadosBDPrevioPost, $idsEnPostEstados);
                // hard delete, hardDeleteGrupo verifica que $idsAEliminar no sea vacio
                ConsultaOdontologiaEstados::hardDeleteGrupo($modelConsulta->id_consulta, $idsAEliminar);

                // eliminar los que estaban en la BD y no vienen en el post
                $idsAEliminar = array_diff($idsDiagnosticosBDPrevioPost, $idsEnPostDiagnosticos);
                // hard delete, hardDeleteGrupo verifica que $idsAEliminar no sea vacio
                ConsultaOdontologiaDiagnosticos::hardDeleteGrupo($modelConsulta->id_consulta, $idsAEliminar);
                
                // eliminar los que estaban en la BD y no vienen en el post
                $idsAEliminar = array_diff($idsPracticasBDPrevioPost, $idsEnPostPracticas);
                // hard delete, hardDeleteGrupo verifica que $idsAEliminar no sea vacio
                ConsultaOdontologiaPracticas::hardDeleteGrupo($modelConsulta->id_consulta, $idsAEliminar);

            } catch (\Exception $th) {
                if ($th->getMessage() != "") {
                    //var_dump($th->getMessage());die;
                    Yii::error($th->getMessage());
                    if (strpos($th->getMessage(), "Error en la pieza") === false) {
                        $errores[] = "Ocurrió un error, no se pudieron guardar los cambios";
                    } else {
                        $errores[] = $th->getMessage();
                    }
                }

                $transaction->rollBack();

                return $this->renderAjax('../consultas/v2/_form_odontologia', [
                    'modelosOdontoConsultaEstados' => $modelosOdontoConsultaEstados,
                    'modelosOdontoConsultaPracticas' => $modelosOdontoConsultaPracticas,
                    'modelosOdontoConsultaDiagnosticos' => $modelosOdontoConsultaDiagnosticos,
                    'errores' => $errores,
                    //'dataNomencladorConsulta' => (empty($dataNomencladorConsulta)) ?  [new OdontoConsultaPersona] : $dataNomencladorConsulta,
                    //'dataNomencladorTto' => (empty($dataNomencladorTto)) ?  '' : $dataNomencladorTto,
                    //'dataNomencladorPiezayCara' => (empty($dataNomencladorPiezayCara)) ?  '' : $dataNomencladorPiezayCara,
                    //'odontogramaPaciente' => (empty($odontogramaPaciente)) ? '' : $odontogramaPaciente,
                    //'odontograma_paciente_caras_pieza_dental' => (empty($odontograma_paciente_caras_pieza_dental)) ? '' : $odontograma_paciente_caras_pieza_dental,
                    'idConsulta' => $modelConsulta->id_consulta,
                    'modelConsulta' => $modelConsulta
                ]);
            }

            $transaction->commit();

            return [
                'success' => true,
                'msg' => 'La consulta fue creada correctamente',
                'url_siguiente' => $modelConsulta->urlSiguiente
            ];
        }

        return $this->renderAjax('../consultas/v2/_form_odontologia', [
            'modelosOdontoConsultaEstados' => $modelosOdontoConsultaEstados,
            'modelosOdontoConsultaPracticas' => $modelosOdontoConsultaPracticas,
            'modelosOdontoConsultaDiagnosticos' => $modelosOdontoConsultaDiagnosticos,
            //'dataNomencladorConsulta' => (empty($dataNomencladorConsulta)) ?  [new OdontoConsultaPersona] : $dataNomencladorConsulta,
            //'dataNomencladorTto' => (empty($dataNomencladorTto)) ?  '' : $dataNomencladorTto,
            //'dataNomencladorPiezayCara' => (empty($dataNomencladorPiezayCara)) ?  '' : $dataNomencladorPiezayCara,
            //'odontogramaPaciente' => (empty($odontogramaPaciente)) ? '' : $odontogramaPaciente,
            //'odontograma_paciente_caras_pieza_dental' => (empty($odontograma_paciente_caras_pieza_dental)) ? '' : $odontograma_paciente_caras_pieza_dental,
            //'indice' => (isset($indice)) ? $indice : '',
            'errores' => $errores,
            'idConsulta' => $modelConsulta->id_consulta,
            'modelConsulta' => $modelConsulta
        ]);
    }

    public function actionDetalle()
    {
        $modelConsulta = Consulta::findOne(Yii::$app->request->get('id_consulta'));

        $estados = $modelConsulta->odontologiaEstados;

        $practicas = $modelConsulta->odontologiaPracticas;

        $diagnosticos = $modelConsulta->odontologiaDiagnosticos;        

        return $this->renderAjax('../consultas/v2/_detalle_ondontologia', 
                                [
                                    'modelConsulta' => $modelConsulta,
                                    'estados' => $estados, 
                                    'practicas' => $practicas, 
                                    'diagnosticos' => $diagnosticos
                                ]);
    }

    protected function odontogramaPrepararDatos($id_persona)
    {
        // ODONTOGRAMA -- preparar datos //

        //$modelOdontoConsultaPerson = new OdontoConsultaPersona();
        //$modelOdontoConsultaPersona = $modelOdontoConsultaPerson->getOdontoConsultaPorPersona($paciente->id_persona);
        $modelOdontoConsultaPracticas = ConsultaOdontologiaPracticas::getPorPaciente($id_persona, 'FUTURA');
        $modelOdontoConsultaEstados = ConsultaOdontologiaEstados::getPorPaciente($id_persona);
        $modelOdontoConsultaDiagnosticos = ConsultaOdontologiaDiagnosticos::getPorPaciente($id_persona);

        $modelOdontoNomencladorGral = new OdontoNomenclador();
        $modelOdontoNomenclador = $modelOdontoNomencladorGral->getOdontoNomenclador('CONSULTA');

        $modelosOdontoNomencladorPiezayCara = $modelOdontoNomencladorGral->getPiezayCara();
        //$modelOdontoNomencladorPorPieza = $modelOdontoNomencladorGral->getOdontoNomenclador('pieza');
        $modelOdontoNomencladorCompleta = $modelOdontoNomencladorGral->getOdontoNomenclador('completa');

        $modelOdontoNomencladorCaras = $modelOdontoNomencladorGral->getOdontoNomencladorCara();

        if (isset($modelOdontoNomenclador)) foreach ($modelOdontoNomenclador as $key => $value) {
            $dataNomencladorConsulta[$value["codigo_faco"]] = $value["codigo_faco"] . ' - ' . $value["detalle_nomenclador"];
        }

        if (isset($modelOdontoNomencladorCompleta)) foreach ($modelOdontoNomencladorCompleta as $key => $value) {
            $dataNomencladorTto[$value["codigo_faco"]] = $value["codigo_faco"] . ' - ' . $value["detalle_nomenclador"];
        }

        $dataNomencladorPiezayCara = [];
        foreach ($modelosOdontoNomencladorPiezayCara as $key => $value) {
            $tipo_atencion = preg_replace('/\s+/', '_', $value["tipo_atencion"]);

            $dataNomencladorPiezayCara[$tipo_atencion . "-" . $value["codigo_faco"]] = $value["codigo_faco"] . ' - ' . $value["detalle_nomenclador"];
        }

        /*if (isset ($modelOdontoNomencladorPorPieza)) foreach($modelOdontoNomencladorPorPieza as $key => $value) {
            $dataNomencladorPorPieza[$value["codigo_faco"]] = $value["codigo_faco"].' - '.$value["detalle_nomenclador"];
        }
        if (isset ($modelOdontoNomencladorCaras)) foreach($modelOdontoNomencladorCaras as $key => $value) {
            $dataNomencladorCaras[$value["codigo_faco"]] = $value["codigo_faco"].' - '.$value["detalle_nomenclador"];
        }*/

        /* Extraigo de $model_odonto_consulta_persona los datos para cada pieza dental */
        $caras = array();
        $indice = array();
        $odontogramaPaciente = [];

        // $odontograma_paciente_caras_pieza_dental = $caras;

        return [
            $modelOdontoConsultaPracticas,
            $modelOdontoConsultaEstados,
            $modelOdontoConsultaDiagnosticos,
            $dataNomencladorConsulta, 
            //$dataNomencladorPorPieza, 
            $dataNomencladorPiezayCara,
            $dataNomencladorTto,
            //$dataNomencladorCaras, 
            $odontogramaPaciente,
            // $odontograma_paciente_caras_pieza_dental,
        ];
        // ODONTOGRAMA -- fin preparar datos //  
    }

    protected function mostrarHistorial($modelOdontoConsultaPersona, $odontogramaPaciente)
    {
        $odonto_codigo = array(
            'NE' => 'No Erupcionada',
            'C' => 'Caries', 'P' => 'Perdido', 'O' => 'Obturado', 'COR' => 'Corona', 'PF' => 'Prótesis Fija', 'E' => 'Extraído', 'PR' => 'Prótesis Removible'
        );
        $historial = '';

        if (!empty($modelOdontoConsultaPersona))
            if ($modelOdontoConsultaPersona[0]->attributes["id_odonto_consulta"] != NULL) {
                //var_dump($odontograma_paciente);
                $historial = '<table class="table-responsive">
                                                <thead>
                                                    <tr>
                                                    <th scope="col">Pieza</th>
                                                    <th scope="col">Estado</th>
                                                    <th scope="col">Fecha</th>
                                                    <th scope="col">Proceso</th>
                                                    </tr>
                                                </thead>
                                                <tbody>';
                foreach ($odontogramaPaciente as $datoPieza => $datoDdetalle) {
                    $tpieza = $datoPieza;
                    $testado = $odonto_codigo[$datoDdetalle[0]];
                    $tfecha = $datoDdetalle[1];
                    $tidConsulta = $datoDdetalle[3];
                    if ($datoDdetalle[2] == "")
                        $tproceso = "<a class='pendiente' id='" . $tidConsulta . "' name='" . $datoPieza . "_" . $testado . "' href='#'>Pendiente<i class='bi bi-check-circle'></i></a></br>";
                    else  $tproceso = $datoDdetalle[2] . '<i class="bi bi-check-circle-fill"></i>';
                    $historial = $historial .  " <tr>
                                                <th scope='row'>" . $tpieza . "</th>
                                                <td>" . $testado . "</td>
                                                <td>" . $tfecha . "</td>
                                                <td>" . $tproceso . "</td>
                                            </tr>";
                }
                $historial = $historial . '</tbody>
                                                </table>';
            } else {
                $historial = "Sin datos";
            }

        return [$historial];
    }

    protected function guardarEstados($idConsulta, $modelosOdontoConsultaEstados)
    {
        $estados = json_decode(Yii::$app->request->post()['estados']);                    

        $idsEnPostEstados = [];
        foreach ($estados as $estado) {

            // recibimos todos los estados del paciente en todas las consultas
            // se pueden actualizar estados, pero no las realizadas por otros RRHH            
            if (isset($estado->id_consulta) && $idConsulta != $estado->id_consulta) {continue;}
            
            // control para que no carguen practicas a denticion temporal para las cuales
            // ya exista una practica a su contrapartida definitiva
            $errorEstadoEnDefinitva = false;
            if (isset(OdontoNomenclador::CONTRAPARTIDA_TEMPORAL_DEFINITIVA[$estado->pieza])) {
                foreach ($modelosOdontoConsultaEstados as $estadosExistentes) {
                    if (OdontoNomenclador::CONTRAPARTIDA_TEMPORAL_DEFINITIVA[$estado->pieza] == $estadosExistentes["pieza"]) {
                        $errorEstadoEnDefinitva = true;
                        break;
                    }
                }
            }

            if ($errorEstadoEnDefinitva) {return [false, $estado->pieza, "Esta pieza ya tiene datos cargados en su correspondiente definitiva", []];}
            
            $nuevoConsultaOdontoEstado = new ConsultaOdontologiaEstados();
            if (isset($estado->id_consultas_odontologia_estados)) {                
                // es un update
                $idsEnPostEstados[] = $estado->id_consultas_odontologia_estados;
                $nuevoConsultaOdontoEstado->id_consultas_odontologia_estados = $estado->id_consultas_odontologia_estados;
                $nuevoConsultaOdontoEstado->setIsNewRecord(false);
            }

            // el usuario puede haber necesitado cambiar las caras, lo unico que puede cambiar ahora
            $nuevoConsultaOdontoEstado->id_consulta = $idConsulta;
            $nuevoConsultaOdontoEstado->pieza = $estado->pieza;
            $nuevoConsultaOdontoEstado->caras = (is_array($estado->caras)) ? implode("-", $estado->caras) : $estado->caras;
            $nuevoConsultaOdontoEstado->codigo = strval($estado->codigo);
            $nuevoConsultaOdontoEstado->tipo = $estado->tipo;
            $nuevoConsultaOdontoEstado->condicion = ConsultaOdontologiaEstados::CONDICION_ACTIVO;
            
            if (!$nuevoConsultaOdontoEstado->save()) {                  
                return [false, $estado->pieza, $nuevoConsultaOdontoEstado->getErrors(), []];
            }
        }
        return [true, 0, "", $idsEnPostEstados];
    }

    protected function guardarDiagnosticos($idConsulta, $modelosOdontoConsultaDiagnosticos)
    {
        $diagnosticos = json_decode(Yii::$app->request->post()['diagnosticos']);                    

        $idsEnPostDiagnosticos = [];
        foreach ($diagnosticos as $diagnostico) {

            // no se permite actualizar diagnosticos, ni si quiera el creador.
            // el creador solo podra eliminarlo
            
            if (isset($diagnostico->id_consulta) && $idConsulta != $diagnostico->id_consulta) {continue;}
            // no se permite actualizar diagnosticos
            if (isset($diagnostico->id)) {
                $idsEnPostDiagnosticos[] = $diagnostico->id;
                continue; // sin updates por el momento, por eso el continue
            }

            // control para que no carguen diagnosticos a denticion temporal para las cuales
            // ya exista una diagnosticos a su contrapartida definitiva
            $errorDiagnosticoEnDefinitva = false;
            if (isset(OdontoNomenclador::CONTRAPARTIDA_TEMPORAL_DEFINITIVA[$diagnostico->pieza])) {
                foreach ($modelosOdontoConsultaDiagnosticos as $diagnosticoExistente) {
                    if (OdontoNomenclador::CONTRAPARTIDA_TEMPORAL_DEFINITIVA[$diagnostico->pieza] == $diagnosticoExistente["pieza"]) {
                        $errorDiagnosticoEnDefinitva = true;
                        break;
                    }
                }
            }

            if ($errorDiagnosticoEnDefinitva) {return [false, $diagnostico->pieza, "Esta pieza ya tiene datos cargados en su correspondiente definitiva", []];}

            $nuevoConsultaOdontoDiagnosticos = new ConsultaOdontologiaDiagnosticos();
            $nuevoConsultaOdontoDiagnosticos->id_consulta = $idConsulta;
            $nuevoConsultaOdontoDiagnosticos->pieza = $diagnostico->pieza;
            $nuevoConsultaOdontoDiagnosticos->caras = (is_array($diagnostico->caras)) ? implode("-", $diagnostico->caras) : $diagnostico->caras;
            $nuevoConsultaOdontoDiagnosticos->codigo = strval($diagnostico->codigo);
            $nuevoConsultaOdontoDiagnosticos->tipo = $diagnostico->tipo;                        
            
            SnomedHallazgos::crearSiNoExiste($diagnostico->codigo, $diagnostico->term);

            if (!$nuevoConsultaOdontoDiagnosticos->save()) {
                return [false, $diagnostico->pieza, $nuevoConsultaOdontoDiagnosticos->getErrors(), $idsEnPostDiagnosticos];
            }            
        }

        return [true, 0, "", $idsEnPostDiagnosticos];
    }

    protected function guardarPracticas($idConsulta, $modelosOdontoConsultaPracticas)
    {
        $practicas = json_decode(Yii::$app->request->post()['practicas']);

        $idsEnPostPracticas = [];        
        foreach ($practicas as $practica) {

            // aqui tengo que verificar la posible actualizacion de una practica, el paso de FUTURA (planificada) a PRESENTE (realizada)
            // Se permite este cambio por medicos que no sean los creadores de la practica original
            if (isset($practica->id_consulta) && $idConsulta != $practica->id_consulta) {
                
                // el unico cambio que se permite por otros medicos es el de pasar a realizada
                // de lo contrario saltamos
                if ($practica->tiempo !== ConsultaOdontologiaPracticas::TIEMPO_PRESENTE) { continue; }

                // del post viene PRESENTE, verificamos que en BD haya estado en FUTURA                    
                
                $practicaPosibleAModificar = $this->buscarPracticaAModificar($practica, $modelosOdontoConsultaPracticas);
                
                // en el post por alguna razon no viene la practica que en teoria se envio a la vista
                // practicas de otras consultas por el momento no se pueden eliminar
                if ($practicaPosibleAModificar === false) { continue; }

                // si esta pasando la practica desde planificada a realizada (FUTURA a PRESENTE)
                if ($practicaPosibleAModificar["tiempo"] !== ConsultaOdontologiaPracticas::TIEMPO_FUTURA) { continue; }
                
                // creamos una nueva practica con root_id indicando la practica modificada
                $nuevoConsultaOdontoPracticas = $this->loadNuevaPractica($idConsulta, $practica);
                $nuevoConsultaOdontoPracticas->root_id = $practica->id_consultas_odontologia_practicas;
                if (!$nuevoConsultaOdontoPracticas->save()) {
                    return [false, $practica->pieza, $nuevoConsultaOdontoPracticas->getErrors(), []];
                }
                $creacionEstado = $this->dispararCreacionEstados($practica, $idConsulta, $modelosOdontoConsultaPracticas);

                if (!$creacionEstado) {
                    return [false, $practica->pieza, "Ocurrió un error al intentar cambiar el estado de la práctica", []];
                }                
                continue;
            }

            $nuevoConsultaOdontoPracticas = $this->loadNuevaPractica($idConsulta, $practica);
            if (isset($practica->id_consultas_odontologia_practicas)) {
                // es un update                
                $nuevoConsultaOdontoPracticas->id_consultas_odontologia_practicas = $practica->id_consultas_odontologia_practicas;
                $nuevoConsultaOdontoPracticas->setIsNewRecord(false);

                $idsEnPostPracticas[] = $practica->id_consultas_odontologia_practicas;

                // los cambios del tiempo de la practica disparan la creacion de estados. Si intenta volver atras
                // eliminamos los estados creados
                
                $vueltaAtrasEstado = $this->volverAtrasEstados($practica, $idConsulta, $modelosOdontoConsultaPracticas);

                if (!$vueltaAtrasEstado) {
                    return [false, $practica->pieza, "Ocurrió un error al intentar volver atras la práctica", []];
                }

            } else {
                // para el create controlamos que no carguen practicas a denticion temporal para las cuales
                // ya existe una practica a su contrapartida definitiva
                $errorPracticaEnDefinitva = false;
                if (isset(OdontoNomenclador::CONTRAPARTIDA_TEMPORAL_DEFINITIVA[$practica->pieza])) {
                    foreach ($modelosOdontoConsultaPracticas as $practicaExistentes) {
                        if (OdontoNomenclador::CONTRAPARTIDA_TEMPORAL_DEFINITIVA[$practica->pieza] == $practicaExistentes["pieza"]) {
                            $errorPracticaEnDefinitva = true;
                            break;
                        }
                    }
                }

                if ($errorPracticaEnDefinitva) {return [false, $practica->pieza, "Esta pieza ya tiene datos cargados en su correspondiente definitiva", []];}
            }

            // esto es porque existen codigos que pertenecen al nomenclador, 
            // previo a la incorporacion a snomed
            if (isset($practica->term)) {
                SnomedProcedimientos::crearSiNoExiste($practica->codigo, $practica->term);
            }

            $creacionEstado = $this->dispararCreacionEstados($practica, $idConsulta, $modelosOdontoConsultaPracticas);

            if (!$creacionEstado) {
                return [false, $practica->pieza, "Ocurrió un error al intentar cambiar el estado de la práctica", []];
            }

            if (!$nuevoConsultaOdontoPracticas->save()) {
                return [false, $practica->pieza, $nuevoConsultaOdontoPracticas->getErrors(), []];
            }
        }

        return [true, 0, "", $idsEnPostPracticas];

    }

    protected function volverAtrasEstados($practica, $idConsulta, $modelosOdontoConsultaPracticas)
    {
        // Si la practica es del codigo "173291009" (extraccion) y esta realizada, dispara la creacion de un estado
        // ausente
        if ($practica->tiempo == ConsultaOdontologiaPracticas::TIEMPO_FUTURA && $practica->codigo == "173291009") {
            // si la practica que viene por post esta en FUTURA, puede haber pasado de PRESENTE
            // busco el estado de la practica en BD
            $practicaPosibleAModificar = false;
            foreach ($modelosOdontoConsultaPracticas as $modelConsultaOdontoPractica) {

                if ($modelConsultaOdontoPractica["id_consultas_odontologia_practicas"] === $practica->id_consultas_odontologia_practicas) {
                    $practicaPosibleAModificar = $modelConsultaOdontoPractica;
                    break;
                }
            }

            if ($practicaPosibleAModificar == false) {return false;}
            
            if ($practicaPosibleAModificar["tiempo"] == ConsultaOdontologiaPracticas::TIEMPO_PRESENTE) {                
                // esta volviendo atras, de PRESENTE (realizada) a FUTURA (planificada)
                $guardado = $this->quitarEstado($idConsulta, "P");
                if (!$guardado) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function dispararCreacionEstados($practica, $idConsulta, $modelosOdontoConsultaPracticas)
    {
        // Si ademas de pasar la practica a realizada, se trata de una extraccion creamos el estado AUSENTE
        if ($practica->tiempo == ConsultaOdontologiaPracticas::TIEMPO_PRESENTE && $practica->codigo == "173291009") {

            if (isset($practica->id_consultas_odontologia_practicas)) {
                // al hacer update, vemos si la practica en BD tenia el tiempo TIEMPO_FUTURA
                $practicaPosibleAModificar = false;
                foreach ($modelosOdontoConsultaPracticas as $modelConsultaOdontoPractica) {

                    if ($modelConsultaOdontoPractica["id_consultas_odontologia_practicas"] === $practica->id_consultas_odontologia_practicas) {
                        $practicaPosibleAModificar = $modelConsultaOdontoPractica;
                        break;
                    }
                }

                if ($practicaPosibleAModificar["tiempo"] !== ConsultaOdontologiaPracticas::TIEMPO_FUTURA) { return true; /*no pasa nada, retornamos*/ }
            }

            $guardado = $this->agregarEstado($idConsulta, $practica, "P");
            if (!$guardado) {
                return false;
            }
        }

        return true;
    }

    // Hay practicas que al pasar de un estado a otro disparan la generacion de un estado
    protected function agregarEstado($id_consulta, $practica, $codigo)
    {
        $nuevoConsultaOdontoEstado = new ConsultaOdontologiaEstados();
        $nuevoConsultaOdontoEstado->id_consulta = $id_consulta;
        $nuevoConsultaOdontoEstado->pieza = $practica->pieza;
        $nuevoConsultaOdontoEstado->caras = "";
        $nuevoConsultaOdontoEstado->codigo = $codigo;
        $nuevoConsultaOdontoEstado->tipo = $practica->tipo;
        $nuevoConsultaOdontoEstado->condicion = ConsultaOdontologiaEstados::CONDICION_ACTIVO;

        if (!$nuevoConsultaOdontoEstado->save()) {
            return false;
        }

        return true;
    }

    // Para volver atras la generacion de un estado
    protected function quitarEstado($idConsulta, $codigo)
    {
        $estado = ConsultaOdontologiaEstados::find()
                    ->where(['id_consulta' => $idConsulta, 'codigo' => $codigo])
                    ->one();
        
        if (!$estado || !$estado->hardDelete()) {
            return false;
        }

        return true;
    }

    /**
     * busca en el array de modelos provenientes de la BD
     * por id_consultas_odontologia_practicas del post
     */
    protected function buscarPracticaAModificar($practica, $modelosOdontoConsultaPracticas)
    {
        $practicaPosibleAModificar = false;
        foreach ($modelosOdontoConsultaPracticas as $modelConsultaOdontoPractica) {

            if ($modelConsultaOdontoPractica["id_consultas_odontologia_practicas"] === $practica->id_consultas_odontologia_practicas) {
                $practicaPosibleAModificar = $modelConsultaOdontoPractica;
                break;
            }
        }

        return $practicaPosibleAModificar;
    }

    protected function loadNuevaPractica($idConsulta, $practica)
    {
        $nuevoConsultaOdontoPracticas = new ConsultaOdontologiaPracticas();
        $nuevoConsultaOdontoPracticas->id_consulta = $idConsulta;
        $nuevoConsultaOdontoPracticas->pieza = $practica->pieza;
        $nuevoConsultaOdontoPracticas->caras = (is_array($practica->caras)) ? implode("-", $practica->caras) : $practica->caras;
        $nuevoConsultaOdontoPracticas->codigo = strval($practica->codigo);
        $nuevoConsultaOdontoPracticas->diagnostico = strval($practica->diagnostico);
        $nuevoConsultaOdontoPracticas->tipo = $practica->tipo;
        $nuevoConsultaOdontoPracticas->tiempo = $practica->tiempo;

        return $nuevoConsultaOdontoPracticas;
    }
}
