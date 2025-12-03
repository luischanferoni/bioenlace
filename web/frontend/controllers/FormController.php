<?php

namespace frontend\controllers;

use Yii;
use yii\base\DynamicModel;
use yii\httpclient\Client;
use yii\web\Controller;
use yii\filters\VerbFilter;

use webvimark\modules\UserManagement\models\User;
use common\models\Persona;

class FormController extends Controller
{
    public function getHostFormsAPI()
    {
        return Yii::$app->params['hostFormsAPI'];
    }

    public function behaviors()
    {
         //control de acceso mediante la extensión
         return [
            'ghost-access' => [
                'class' => 'frontend\components\SisseGhostAccessControl',
                'except' => ['search']
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    public function actionForms()
    {

        $client = new Client();
        $request = file_get_contents('../assets/jsonForms/formsList.json');
        // TODO: poner filtro en funcion del rol, si es efector , si es medico , si es paciente etc
        // Para efector 1 y 2 para paciente 3 y 4 where

        $decodedText = urlencode($request);
        $response = $client->createRequest()
            ->setMethod('GET')
            ->setUrl($this->getHostFormsAPI() . '/forms?filter=' . $decodedText)
            ->setFormat(Client::FORMAT_JSON)
            ->send();
        $dataForms = array();
        if ($response->isOk) {
            $session = Yii::$app->getSession();
            // Se busca la cantidad de cada form
            $userId = Yii::$app->request->get('id_user') ? Yii::$app->request->get('id_user') : Yii::$app->user->id;

            foreach ($response->data as $form) {
                // Si el formTipoId es de 1 o n x paciente
                if($form['formTipoId'] == 3 || $form['formTipoId'] == 4) {
                    $personaSession = unserialize($session['persona']);
                }
                //Obtener reglas
                $reglas = $client->createRequest()
                        ->setMethod('GET')
                        ->setUrl($this->getHostFormsAPI() . '/forms/'.$form['id'].'/reglas')
                        ->setFormat(Client::FORMAT_JSON)
                        ->send();
                // Verificar Reglas por rol solamente ...
                // las reglas por session de usuario se verifican en render
                if ($reglas->isOk) {
                    $noCumpleRegla = false;
                    foreach ($reglas->data as $regla) {
                        if($regla['condicion'] ==  '5') {
                            if(!User::hasRole([$regla['valor']])) {
                                $noCumpleRegla = true;
                            }
                        }
                    }
                }
                if($noCumpleRegla) {
                    continue;
                }

                $requestCount = '{"formId":' . $form['id'] . ', "createdBy": ' . $userId . '}';
                $decodedWhere = urlencode($requestCount);
                $cantidad = $client->createRequest()
                     ->setMethod('GET')
                     ->setUrl($this->getHostFormsAPI() . '/instancias/count?where=' . $decodedWhere)
                     ->setFormat(Client::FORMAT_JSON)
                     ->send();

                if ($cantidad->isOk) {
                    $form['cantidad'] = $cantidad->data['count'];
                } else {
                    $form['cantidad'] = 0;
                }
                $dataForms[] = $form;
            }
        }

        return $this->render('list', [
            'data' => $dataForms,
        ]);

    }

    /**
     * Updates an existing Tipo_dia model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $id
     * @return mixed
     */
    public function actionRender($id)
    {
        $userId = Yii::$app->request->get('id_user') ? Yii::$app->request->get('id_user') : Yii::$app->user->id;
        $session = Yii::$app->getSession();
        $personaSession = unserialize($session['persona']);
        $personaId = $personaSession ? $personaSession->id_persona : null;

        $client = new Client();
        $mensajeSuccess = "";

        if (Yii::$app->request->post()) {

            $nuevainstancia = array(
                "formId" => intval($id),
                "tipoInstanciaId" => 1,
                "createdAt" => date('Y-m-d H:i:s'),
                "createdBy" => $userId,
            );
            if ($personaId) {
                $nuevainstancia["createdFor"] = $personaId;
            }

            $instancia = $client->createRequest()
                ->setMethod('POST')
                ->setUrl($this->getHostFormsAPI() . '/instancias')
                ->setFormat(Client::FORMAT_JSON)
                ->setData($nuevainstancia)
                ->send();
            if ($instancia->isOk) {
                $instanciaId = $instancia->data['id'];
                // INSERT DE valores
                $post = Yii::$app->request->post()['DynamicModel'];
                $valores = array();
                foreach ($post as $key => $value) {
                    if (is_array($value)) {
                        $value = implode('##', $value);
                    }

                    $valores[] = array(
                        "valor" => $value,
                        "preguntaId" => $key,
                        "instanciaId" => $instanciaId,
                    );
                }
                //var_dump($valores);
                $response = $client->createRequest()
                    ->setMethod('POST')
                    ->setUrl($this->getHostFormsAPI() . '/allvalores')
                    ->setFormat(Client::FORMAT_JSON)
                    ->setData($valores)
                    ->send();

                if ($response->isOk) {
                    // mOSTRAR EL MENSAJE DE EXITO DEL FORM
                    $mensajeSuccess = "Los datos se guardaron correctamente";
                } else {
                    echo "Ocurrió un error la instancia no pudo ser creada";
                    die();
                }
            } else {
                echo "Ocurrió un error la instancia no pudo ser creada";
                die();
            }

        } //end post

        $request = file_get_contents('../assets/jsonForms/formRender.json');
        $decodedText = urlencode($request);

        $response = $client->createRequest()
            ->setMethod('GET')
            ->setUrl($this->getHostFormsAPI() . '/forms/' . $id . '?filter=' . $decodedText)
            ->setHeaders(['Content-type' => 'application/json'])
            ->setData([])
            ->send();
        if (!$response->isOk) {
            \Yii::$app->getSession()->setFlash('error', '<b>No se encontró el formulario.</b>');
            return $this->redirect(['forms']);
        }


        $formTipoId = $response->data['formTipoId'];

        //Para los formularios por paciente se controla que se haya seleccionado el mismo
        if ($formTipoId == 3 || $formTipoId == 4) {
            if (!$personaSession) {
                \Yii::$app->getSession()->setFlash('error', '<b>Debe seleccionar un paciente previamente.</b>');
                return $this->redirect(['forms']);
            }
            //Obtener reglas
            $reglas = $client->createRequest()
                       ->setMethod('GET')
                       ->setUrl($this->getHostFormsAPI() . '/forms/'.$response->data['id'].'/reglas')
                       ->setFormat(Client::FORMAT_JSON)
                       ->send();
            // Verificar Reglas por los datos de la persona seleccionada
            if ($reglas->isOk) {
                $noCumpleRegla = false;
                foreach ($reglas->data as $regla) {
                    switch ($regla['condicion']) {
                        case '1':
                            // Session
                            $reglaSession =  (isset($personaSession[$regla['campo']])) ? $personaSession[$regla['campo']] : '';
                            if($reglaSession != $regla['valor']) {
                                \Yii::$app->getSession()->setFlash('error', '<b>La persona seleccionada no cumple con las condiciones necesarias.</b>');
                                return $this->redirect(['forms']);
                            }
                            break;
                        case '2':
                            // Session
                            $reglaSession =  (isset($personaSession[$regla['campo']])) ? $personaSession[$regla['campo']] : '';
                            if($reglaSession != $regla['valor']) {
                                \Yii::$app->getSession()->setFlash('error', '<b>La persona seleccionada no cumple con las condiciones necesarias.</b>');
                                return $this->redirect(['forms']);
                            }
                            break;
                        case '3':
                            // Session
                            $reglaSession =  (isset($personaSession[$regla['campo']])) ? $personaSession[$regla['campo']] : '';
                            if($reglaSession != $regla['valor']) {
                                \Yii::$app->getSession()->setFlash('error', '<b>La persona seleccionada no cumple con las condiciones necesarias.</b>');
                                return $this->redirect(['forms']);
                            }
                            break;
                        case '4':
                            // Session
                            break;

                        default:
                            # code...
                            break;
                    }
                }
            }


        }

        $secciones = $response->data['secciones'];
        $formTitulo = $response->data['nombre'];
        $formDescripcion = $response->data['descripcion'];
        $formLogo = $response->data['logo'];
        $preguntas = [];
        $texto = [];
        $textoLargo = [];
        $numero = [];
        $fecha = [];
        $arrayPreguntas = [];
        foreach ($secciones as $item) {
            if (array_key_exists('preguntas', $item)) {
                $preguntasArray[] = $item['preguntas'];
                $preguntas = $item['preguntas'];
                foreach ($preguntas as $pregunta) {
                    $arrayPreguntas[] = $pregunta['id'];
                    switch ($pregunta['tipoPreguntaId']) {
                        case 1:
                            $texto[] = $pregunta['id'];
                            break;
                        case 2:
                            $textoLargo[] = $pregunta['id'];
                            break;
                        case 3:
                            $numero[] = $pregunta['id'];
                            break;
                        case 6:
                            $fecha[] = $pregunta['id'];
                            break;
                    }
                }
            }
        }
        $inputs = compact($arrayPreguntas);

        $model = new DynamicModel($arrayPreguntas);
        if (!empty($texto)) {
            $model->addRule($texto, 'string', ['max' => 255]);
        }

        if (!empty($textoLargo)) {
            $model->addRule($textoLargo, 'string', ['max' => 16777215]);
        }

        if (!empty($numero)) {
            $model->addRule($numero, 'integer');
        }

        if (!empty($fecha)) {
            $model->addRule($fecha, 'date');
        }

        return $this->render('form', array(
            'model' => $model,
            'secciones' => $secciones,
            'formTipoId' => $formTipoId,
            'formTitulo' => $formTitulo,
            'formDescripcion' => $formDescripcion,
            'formLogo' => $formLogo,
            "mensajeSuccess" => $mensajeSuccess,
            "personaSession" => $personaSession
        ));
    }

    /**
     * @param string $id
     * @return mixed
     */
    public function actionInstancias($id)
    {
        $userId = Yii::$app->request->get('id_user') ? Yii::$app->request->get('id_user') : Yii::$app->user->id;
        $client = new Client();
        $mensajeSuccess = "";
        $instanciasResult = [];

        // Se busca el form y las preguntas
        $request = file_get_contents('../assets/jsonForms/formInstancias.json');
        $decodedText = urlencode($request);

        $response = $client->createRequest()
            ->setMethod('GET')
            ->setUrl($this->getHostFormsAPI() . '/forms/' . $id . '?filter=' . $decodedText)
            ->setHeaders(['Content-type' => 'application/json'])
            ->setData([])
            ->send();
        if ($response->isOk) {
            ;
        }

        $formTipoId = $response->data['formTipoId'];
        $secciones = $response->data['secciones'];
        $formTitulo = $response->data['nombre'];
        $formDescripcion = $response->data['descripcion'];
        $formLogo = $response->data['logo'];
        $preguntas = [];
        $texto = [];
        $textoLargo = [];
        $numero = [];
        $fecha = [];
        $arrayPreguntas = [];
        foreach ($secciones as $item) {
            if (array_key_exists('preguntas', $item)) {
                $preguntasArray[] = $item['preguntas'];
                $preguntas = $item['preguntas'];
                foreach ($preguntas as $pregunta) {
                    $arrayPreguntas[] = $pregunta['id'];
                    switch ($pregunta['tipoPreguntaId']) {
                        case 1:
                            $texto[] = $pregunta['id'];
                            break;
                        case 2:
                            $textoLargo[] = $pregunta['id'];
                            break;
                        case 3:
                            $numero[] = $pregunta['id'];
                            break;
                        case 6:
                            $fecha[] = $pregunta['id'];
                            break;
                    }
                }
            }
        }

        // buscar las instancias del form
        $requestFilter = str_replace(
            '"#USERID"',
            $userId,
            str_replace(
                '"#ID"',
                $id,
                file_get_contents('../assets/jsonForms/instancias.json')
            )
        );

        $decodedFilter = urlencode($requestFilter);
        $resinstancias = $client->createRequest()
            ->setMethod('GET')
            ->setUrl($this->getHostFormsAPI() . '/instancias?filter=' . $decodedFilter)
            ->setFormat(Client::FORMAT_JSON)
            ->send();
        $instancias = [];
        if ($resinstancias->isOk) {
            $instancias = $resinstancias->data;
            // TODO: buscar las personas para armar la tabla si el tipo de form es 3 o 4
            $instanciasResult = [];
            if (count($instancias) > 0) {

                foreach ($instancias as $key => $instancia) {
                    $instanciasResult[$key]['id'] = $instancia["id"];
                    $instanciasResult[$key]['fecha_creacion'] = Yii::$app->formatter->asDate($instancia["createdAt"], 'dd-MM-Y H:i:s');
                    if ($formTipoId == 3 || $formTipoId == 4) {
                        if ($instancia["createdFor"]) {
                            $paciente = Persona::findOne($instancia["createdFor"]);
                            $instanciasResult[$key]["documento"] = ($paciente)?$paciente->documento: 'No definido';
                            $instanciasResult[$key]["nombre"] = ($paciente)? $paciente->getNombreCompleto('apellido_nombre'): 'No definido';
                        } else {
                            $instanciasResult[$key]["documento"] = 'No definido';
                            $instanciasResult[$key]["nombre"] = 'No definido';
                        }
                    }
                }
            }
        }

        return $this->render('instancias', array(
            'secciones' => $secciones,
            'instancias' => $instanciasResult,
            'formTitulo' => $formTitulo,
            'formDescripcion' => $formDescripcion,
            'formLogo' => $formLogo,
            "mensajeSuccess" => $mensajeSuccess,
        ));
    }

    /**
     * @param string $id
     * @return mixed
     */
    public function actionVerinstancia($id)
    {
        $client = new Client();
        // buscar las instancia
        $requestFilter = str_replace('"#ID"', $id, file_get_contents('../assets/jsonForms/formView.json'));

        $decodedFilter =  urlencode($requestFilter);

        $resinstancias = $client->createRequest()
            ->setMethod('GET')
            ->setUrl($this->getHostFormsAPI() . '/instancias/?filter=' . $decodedFilter)
            ->setFormat(Client::FORMAT_JSON)
            ->send();
        $instancias = [];

        $instanciasResult = [];
        if ($resinstancias->isOk) {
            $instancias = $resinstancias->data;
            // TODO: buscar las personas para armar la tabla si el tipo de form es 3 o 4

            if (count($instancias) > 0) {
                $instanciasResult = [];

                $instanciasResult['id'] = $instancias[0]["id"];
                $instanciasResult['fecha_creacion'] = Yii::$app->formatter->asDate($instancias[0]["createdAt"], 'dd-MM-Y H:i:s');
                //if($formTipoId == 3 || $formTipoId == 4){
                if ($instancias[0]["createdFor"]) {
                    $paciente = Persona::findOne($instancias[0]["createdFor"]);
                    $instanciasResult["documento"] = $paciente->documento;
                    $instanciasResult["nombre"] = $paciente->getNombreCompleto('apellido_nombre');
                } else {
                    $instanciasResult["documento"] = 'No definido';
                    $instanciasResult["nombre"] = 'No definido';
                }
                //}
                if (isset($instancias[0]['valores'])) {
                    foreach ($instancias[0]['valores'] as $key => $value) {
                        $clave = str_replace(' ', '_', strtolower($value["pregunta"]["titulo"]));
                        $clave = preg_replace('/[^A-Za-z0-9\_]/', '', $clave);
                        $clave = preg_replace('/-+/', '-', $clave);
                        $instanciasResult[$clave] = '' . $value['valor'];

                        if (isset($value["pregunta"]["campos"])) { // TODO poner control en funcion del tipo de pregunta
                            // Si el tipo de pregunta es checkbox o select multiple
                            if ($value["pregunta"]["tipoPreguntaId"] == 4 || $value["pregunta"]["tipoPreguntaId"] == 12) {
                                $valoresSeleccionados = explode('##', $value['valor']);
                                $instanciasResult[$clave] = "";
                                foreach ($value["pregunta"]["campos"] as $i => $campo) {
                                    if (in_array($campo['id'], $valoresSeleccionados)) {
                                        $instanciasResult[$clave] .= ' ' . $campo['nombre'];
                                    }
                                }
                            } else {
                                foreach ($value["pregunta"]["campos"] as $i => $campo) {
                                    if ($campo['id'] == $value['valor']) {
                                        $instanciasResult[$clave] = '' . $campo['nombre'];
                                        break;
                                    }
                                }
                            }

                        }
                    }
                }
            }
        }

        if(Yii::$app->request->isAjax) {
            return $this->renderAjax('verinstancia', array(
                'datosForm' => $instanciasResult,

            ));
        } else {
            return $this->render('verinstancia', array(
                'datosForm' => $instanciasResult,

            ));
        }


    }
}
