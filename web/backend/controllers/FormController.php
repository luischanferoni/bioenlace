<?php

namespace backend\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\filters\VerbFilter;
use common\models\Efector;
use common\models\RrhhEfector;
use common\models\Persona;
use yii\httpclient\Client;
use yii\base\DynamicModel;
use common\models\Barrios;
//agregamos el modulo de la extension para el control de acceso
use webvimark\modules\UserManagement\UserManagementModule;
use webvimark\modules\UserManagement\models\User;

class FormController extends Controller
{
   public function getHostFormsAPI(){
      return Yii::$app->params['hostFormsAPI'];
   }
  

   public function behaviors()
   {
        //control de acceso mediante la extensión
       return [
           'ghost-access' => [
               'class' => 'webvimark\modules\UserManagement\components\GhostAccessControl',
           ],
           'verbs' => [
               'class' => VerbFilter::className(),
               'actions' => [
                   'delete' => ['post'],
               ],
           ],
       ];
   }

   /**
     * Creates a new form.
     * @return mixed
     */
    public function actionCreate()
    {
      $client = new Client();
      $mensajeError = "";
      $mensajeSuccess = "";

      $formulario = new DynamicModel(['nombre', 'descripcion', 'form_tipo_id', 'form_estado_id', 'logo']);
      $formulario->addRule(['nombre', 'descripcion', 'form_tipo_id','form_estado_id', 'logo'], 'safe');

      $seccion = new DynamicModel(['tituloseccion', 'orden', 'form_id', 'columnas', 'mostrartitulo']);
      $seccion->addRule(['tituloseccion', 'orden', 'form_id',  'columnas', 'mostrartitulo'], 'safe');
      $secciones = array();
      $secciones [] = $seccion;

      $pregunta = new DynamicModel(['titulo', 'seccion_id', 'tipo_pregunta_id', 'orden', 'valores']);
      $pregunta->addRule(['titulo', 'seccion_id', 'tipo_pregunta_id', 'orden', 'valores'], 'safe');
      $preguntas = array();
      $preguntas [] = $pregunta;

      $regla = new DynamicModel(['campo', 'condicion', 'valor', 'form_id']);
      $regla->addRule(['campo', 'condicion', 'valor', 'form_id'], 'safe');
     
      
      if (Yii::$app->request->post()) {
        //var_dump(Yii::$app->request->post());die();
         $postValues = Yii::$app->request->post()['DynamicModel'];
         $formValues = array();
         $formValues['nombre'] = $postValues['nombre'];
         $formValues['descripcion'] = $postValues['descripcion'];
         $formValues['formTipoId'] = intval($postValues['form_tipo_id']);
         $formValues['formEstadoId'] = intval($postValues['form_estado_id']);
         $formValues["creaatedAt"] = date('Y-m-d H:i:s');
         $formValues["createdBy"] =  1;
         $formValues["dominioId"] =  1;
         // Guardar el form y guardar el formId en en una variable
         $nuevoformulario = $client->createRequest()
            ->setMethod('POST')
            ->setUrl($this->getHostFormsAPI().'/forms')
            ->setFormat(Client::FORMAT_JSON)
            ->setData( $formValues)
            ->send();
         if ($nuevoformulario->isOk) {
               $formularioId = $nuevoformulario->data['id'];        
               $preguntasValues = array();
               $seccionesValues = array();
               $reglasValues = array();
               $seccionesIds = array();
               for ($i=0; $i < 20; $i++) { 
                  if(isset($postValues[$i])){                     
                     if(isset($postValues[$i]['tituloseccion'])){ 

                        $seccionesValues[$i]['titulo'] = $postValues[$i]['tituloseccion'];
                        $seccionesValues[$i]['orden'] = $postValues[$i]['orden'];
                        $seccionesValues[$i]['mostrartitulo'] = $postValues[$i]['mostrartitulo'];
                        $seccionesValues[$i]['columnas'] = intval($postValues[$i]['columnas']);
                        $seccionesValues[$i]['formId'] = $formularioId;                        
                     }
                     if(isset($postValues[$i]['tipo_pregunta_id'])){
                        $preguntasValues[$i]['titulo'] = $postValues[$i]['titulo'];
                        $preguntasValues[$i]['orden'] = 1; $postValues[$i]['orden'];
                        $preguntasValues[$i]['seccionId'] = $postValues[$i]['seccion_id'];
                        $preguntasValues[$i]['tipoPreguntaId'] = intval($postValues[$i]['tipo_pregunta_id']);
                        $preguntasValues[$i]['valores'] = $postValues[$i]['valores'];
                     }
                     if(isset($postValues[$i]['condicion'])){ 

                        $reglasValues[$i]['campo'] = $postValues[$i]['campo'];
                        $reglasValues[$i]['condicion'] = $postValues[$i]['condicion'];
                        $reglasValues[$i]['valor'] = $postValues[$i]['valor'];
                        $reglasValues[$i]['formId'] = $formularioId;                        
                     }
                  }else{
                     break;
                  }
               }

            foreach ($reglasValues as $key =>  $reglaValue) {
               $nuevaRegla = $client->createRequest()
                  ->setMethod('POST')
                  ->setUrl($this->getHostFormsAPI().'/reglas')
                  ->setFormat(Client::FORMAT_JSON)
                  ->setData( $reglaValue)
                  ->send();
               if (!$nuevaRegla->isOk) {                  
                  $mensajeError .= "La regla no pudo ser creada";
               }
            }
         // Guardar cada seccion y ponerle de indice el indice de la seccion y como valor el id q devuelve el post

         foreach ($seccionesValues as $key =>  $seccionValue) {
            $nuevaSeccion = $client->createRequest()
               ->setMethod('POST')
               ->setUrl($this->getHostFormsAPI().'/secciones')
               ->setFormat(Client::FORMAT_JSON)
               ->setData( $seccionValue)
               ->send();
            if ($nuevaSeccion->isOk) {
               $seccionesIds[$key] = $nuevaSeccion->data['id']; 
            }else{
               //var_dump($nuevaSeccion); die();
               $mensajeError .= "La seccion no pudo ser creada";
            }
         }

         //Reemplazar en cada pregunta la seccion_id usando lo de arriba
         foreach ($preguntasValues as $key =>  $preguntaValue) {
            $valores = $preguntaValue['valores'];
            unset($preguntaValue['valores']);
            $preguntaValue['seccionId'] = ($preguntaValue['seccionId'] == '')? 1 : $preguntaValue['seccionId']; 
            $nuevaSeccionId = $seccionesIds[$preguntaValue['seccionId']];
            $preguntaValue['seccionId'] = $nuevaSeccionId;
            $nuevaPregunta = $client->createRequest()
               ->setMethod('POST')
               ->setUrl($this->getHostFormsAPI().'/seccions/'.$nuevaSeccionId.'/preguntas')
               ->setFormat(Client::FORMAT_JSON)
               ->setData( $preguntaValue)
               ->send();
            if ($nuevaPregunta->isOk) {
               echo $preguntaId = $nuevaPregunta->data['id']; 
               // Guardo los valores 
               // TODO: hacer explode de posibles $valores y luego un foreach
               $valorValue = array();
               $valoresValues = explode(',', $valores);
               $i = 0;
               foreach ($valoresValues as $key => $value) {
                  $valorValue ['nombre']= $value;
                  $valorValue ['orden']= ''.$i;
                  $valorValue ['createdAt']= date('Y-m-d H:i:s');
                  $valorValue ['createdBy']=1;
                  $valorValue ['preguntaId']=$preguntaId;
                  $valorValue ['tipoCampoId']=1;
                  $valorValue ['formCampoLogicaId']= 0;                  
                  $nuevoCampo = $client->createRequest()
                  ->setMethod('POST')
                  ->setUrl($this->getHostFormsAPI().'/campos/')
                  ->setFormat(Client::FORMAT_JSON)
                  ->setData( $valorValue)
                  ->send();
                  if (!$nuevoCampo->isOk) {
                     //var_dump($nuevoCampo); die();
                     $mensajeError .= "El campo no pudo ser creado";
                  }

                  $i++;
               }
               // se deben poner los datos de la pregunta 

            }else{
               //var_dump($nuevaPregunta); die();
               $mensajeError .= "La pregunta no pudo ser creada";
            }
         }
         $mensajeSuccess = "El form se guardó con exito";
         // Guardar las preguntas.
      } else{
         //var_dump($nuevoformulario);die();
         $mensajeError .= 'El formulario no pudo guardarse exitosamente';
      } // fin de formulario guardado exitosamente
      }

      $requestTipoForm =  '{"fields": {"id": true,"nombre": true,"descripcion": false}}';
        

      $decodedText = urlencode($requestTipoForm);
      $responseTipoForm = $client->createRequest()
         ->setMethod('GET')
         ->setUrl($this->getHostFormsAPI().'/tipo-forms?filter='.$decodedText)
         ->setFormat(Client::FORMAT_JSON)
         ->send();
      $tipoForm = array();
      if ($responseTipoForm->isOk) {
         $tipoForm = $responseTipoForm->data;      
      }

      return $this->render('create', [
            'formulario' => $formulario,
            'secciones' => $secciones,
            'preguntas' => $preguntas,
            'seccion' => $seccion,
            'pregunta' => $pregunta,
            'regla' => $regla,
            'tipoForm' => $tipoForm,
            'mensajeSuccess' => $mensajeSuccess,
            'mensajeError' => $mensajeError
      ]);
     

    }
      public function actionForms()
    {
        $client = new Client();
        $request =  '{"fields":{"id":true, "nombre":true,"descripcion":true, "formTipoId": true, "formEstadoId": true}}';
        // TODO: poner filtro en funcion del rol, si es efector , si es medico , si es paciente etc
        // Para efector 1 y 2 para paciente 3 y 4 where  

        $decodedText = urlencode($request);
        $response = $client->createRequest()
            ->setMethod('GET')
            ->setUrl($this->getHostFormsAPI().'/forms?filter='.$decodedText)
            ->setFormat(Client::FORMAT_JSON)
            ->send();
        $dataForms = array();
        if ($response->isOk) {
           //var_dump($response->data);die();
           // Se busca la cantidad de cada form
           $userId = Yii::$app->request->get('id_user')?Yii::$app->request->get('id_user'):Yii::$app->user->id;
           
           foreach ($response->data as $form) {

               $requestCount  =  '{"formId":'.$form['id'].'}';
               $decodedWhere = urlencode($requestCount);
               $cantidad = $client->createRequest()
               ->setMethod('GET')
               ->setUrl($this->getHostFormsAPI().'/instancias/count?where='.$decodedWhere)
               ->setFormat(Client::FORMAT_JSON)
               ->send();               
               if ($cantidad->isOk) {
                  $form['cantidad'] = $cantidad->data['count'];
               }else{
                  $form['cantidad'] = 0;
               }
               $dataForms[]= $form;               
               //var_dump($dataForms);die();
           }
        }
        
        return $this->render('list', [
         'data' => $dataForms         
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
        //$model = $this->findModel($id);
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
                var_dump($valores);
                $response = $client->createRequest()
                    ->setMethod('POST')
                    ->setUrl($this->getHostFormsAPI() . '/allvalores')
                    ->setFormat(Client::FORMAT_JSON)
                    ->setData($valores)
                    ->send();
                //var_dump($response->data);

                if ($response->isOk) {
                    // mOSTRAR EL MENSAJE DE EXITO DEL FORM
                    $mensajeSuccess = "Los datos se guardaron correctamente";
                } else {
                    echo "Ocurrió un error la instancia no pudo ser creada";
                    var_dump($response);die();
                }
            } else {
                echo "Ocurrió un error la instancia no pudo ser creada";
                var_dump($instancia);die();
            }

        } //end post

       $response = $this->getFormData($id);


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
                                $reglaSession =  (isset($personaSession[$regla['campo']] ))? $personaSession[$regla['campo']] : '';
                                if($reglaSession != $regla['valor']){
                                    \Yii::$app->getSession()->setFlash('error', '<b>La persona seleccionada no cumple con las condiciones necesarias.</b>');
                                     return $this->redirect(['forms']);
                                }
                            break;
                            case '2':
                                // Session
                                $reglaSession =  (isset($personaSession[$regla['campo']] ))? $personaSession[$regla['campo']] : '';
                                if($reglaSession != $regla['valor']){
                                    \Yii::$app->getSession()->setFlash('error', '<b>La persona seleccionada no cumple con las condiciones necesarias.</b>');
                                     return $this->redirect(['forms']);
                                }
                            break;
                            case '3':
                                // Session
                                $reglaSession =  (isset($personaSession[$regla['campo']] ))? $personaSession[$regla['campo']] : '';
                                if($reglaSession != $regla['valor']){
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
        //var_dump($secciones);die();
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
            'formTipoId'=>$formTipoId,
            'formTitulo' => $formTitulo,
            'formDescripcion' => $formDescripcion,
            'formLogo' => $formLogo,
            "mensajeSuccess" => $mensajeSuccess,
            "personaSession"=> $personaSession
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
            'id'=> $id,
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
        
        $instanciasResult= [];
        $preguntasKeys =[];
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
                        $preguntasKeys [] = $clave;
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
        
        return $this->render('verinstancia', array(
            'datosForm' => $instanciasResult,
            'preguntasKeys'=> $preguntasKeys

        ));

    }


    /**
     * @param string $id
     * @return mixed
     */
    public function actionExportar($id)
    {
        $request =  '{"where":{"id": '.$id.'},"fields":{"id":true, "nombre":true,"descripcion":true, "formTipoId": true, "formEstadoId": true}}';
        // TODO: poner filtro en funcion del rol, si es efector , si es medico , si es paciente etc
        // Para efector 1 y 2 para paciente 3 y 4 where  
        $client = new Client();
        $decodedText = urlencode($request);
        $response = $client->createRequest()
            ->setMethod('GET')
            ->setUrl($this->getHostFormsAPI().'/forms?filter='.$decodedText)
            ->setFormat(Client::FORMAT_JSON)
            ->send();
        $dataForms = array();
        if (!$response->isOk) {
           
        }else{
            $formData  =  $response->data;

            //var_dump($formData);die();
            // buscar las instancia
            $requestFilter = str_replace('"#FORMID"', $id, file_get_contents('../assets/jsonForms/formViewAll.json'));
            
            $decodedFilter =  urlencode($requestFilter);

            $resinstancias = $client->createRequest()
                ->setMethod('GET')
                ->setUrl($this->getHostFormsAPI() . '/instancias/?filter=' . $decodedFilter)
                ->setFormat(Client::FORMAT_JSON)
                ->send();
            $instancias = [];
            
            $instanciasResult= [];
            $preguntasKeys= [];
            
            if ($resinstancias->isOk) {
                $instancias = $resinstancias->data;
                // TODO: buscar las personas para armar la tabla si el tipo de form es 3 o 4
                $instanciasResult = [];
                
                foreach ($instancias as $key => $instancia) {

                    // Se crean instancias sin valores,
                    // por temas de validacion no se cargan los valores
                    if (!isset($instancia['valores'])) {
                        continue;
                    }

                    //if($formTipoId == 3 || $formTipoId == 4){
                    if ($instancia["createdFor"]) {
                        $paciente = Persona::findOne($instancia["createdFor"]);
                        if(!isset($paciente)) {                                
                            continue;
                        }                            
                        $instanciasResult[$key]["documento"] = $paciente->documento;
                        $instanciasResult[$key]["nombre"] = $paciente->getNombreCompleto('apellido_nombre');
                    } else {
                        $instanciasResult[$key]["documento"] = 'No definido';
                        $instanciasResult[$key]["nombre"] = 'No definido';
                    }

                    $instanciasResult[$key]['id'] = $instancia["id"];
                    $instanciasResult[$key]['fecha_creacion'] = Yii::$app->formatter->asDate($instancia["createdAt"], 'dd-MM-Y H:i:s');
                    
                    if (isset($instancia['valores'])) {
                        
                        foreach ($instancia['valores'] as $value) {
                            $clave = str_replace(' ', '_', strtolower($value["pregunta"]["titulo"]));
                            $clave = preg_replace('/[^A-Za-z0-9\_]/', '', $clave);
                            $clave = preg_replace('/-+/', '-', $clave);
                            
                            if (!in_array($clave, $preguntasKeys)) $preguntasKeys [] = $clave; 
                            
                        
                            $instanciasResult[$key][$clave] = '' . $value['valor'];
                            
                            if (isset($value["pregunta"]["campos"])) { // TODO poner control en funcion del tipo de pregunta
                                // Si el tipo de pregunta es checkbox o select multiple
                                if ($value["pregunta"]["tipoPreguntaId"] == 4 || $value["pregunta"]["tipoPreguntaId"] == 12) {
                                    $valoresSeleccionados = explode('##', $value['valor']);
                                    $instanciasResult[$key][$clave] = "";
                                    foreach ($value["pregunta"]["campos"] as $i => $campo) {
                                        if (in_array($campo['id'], $valoresSeleccionados)) {
                                            $instanciasResult[$key][$clave] .= ' ' . $campo['nombre'];
                                        }
                                    }
                                } else {                                        
                                    foreach ($value["pregunta"]["campos"] as $i => $campo) {
                                        if ($campo['id'] == $value['valor']) {
                                            $instanciasResult[$key][$clave] = '' . $campo['nombre'];
                                            break;
                                        }
                                    }
                                }

                            }
                        }
                    }    
                }
            }
            
            return $this->render('export', array(
                'formData'=> $formData ,
                'datosForm' => $instanciasResult,
                'preguntasKeys' =>  $preguntasKeys 
            ));
        }

    }

   /**
     * Creates a new form.
     * @return mixed
     */
    public function actionEdit($id)
    {
        $client = new Client();
        $mensajeError = "";
        $mensajeSuccess = "";

        $formulario = new DynamicModel(['idform','nombre', 'descripcion', 'form_tipo_id', 'form_estado_id', 'logo']);
        $formulario->addRule(['idform','nombre', 'descripcion', 'form_tipo_id','form_estado_id', 'logo'], 'safe');

        $seccion = new DynamicModel(['idseccion','tituloseccion', 'orden', 'form_id', 'columnas', 'mostrartitulo']);
        $seccion->addRule(['idseccion','tituloseccion', 'orden', 'form_id',  'columnas', 'mostrartitulo'], 'safe');
        $secciones = array();
        $secciones = [$seccion];

        $pregunta = new DynamicModel(['idpregunta','titulo', 'seccion_id', 'tipo_pregunta_id', 'orden', 'valores']);
        $pregunta->addRule(['idpregunta','titulo', 'seccion_id', 'tipo_pregunta_id', 'orden', 'valores'], 'safe');
        $preguntas = array();
        $preguntas= [$pregunta];

        $regla = new DynamicModel(['idregla','campo', 'condicion', 'valor', 'form_id']);
        $regla->addRule(['idregla','campo', 'condicion', 'valor', 'form_id'], 'safe');

        $formData = $this->getFormData($id);

        $dataForm = [
            'DynamicModel' => [
                'idform'=> $formData->data['id'],
                'nombre'=> $formData->data['nombre'], 
                'descripcion'=> $formData->data['descripcion'],
                'form_tipo_id'=> $formData->data['formTipoId'], 
                'form_estado_id'=> $formData->data['formEstadoId'], 
                'logo' => $formData->data['logo']
            ]
        ];    
        // load data
        $formulario->load($dataForm);
        //reglas 
        $dataRegla =[];
        $reglas = [];
        $arrayIdsReglas = array();
        if(isset($formData->data['reglas'])){
            foreach ($formData->data['reglas'] as $reglaitem) {
                $arrayIdsReglas[] = $reglaitem['id'];
                $dataRegla = [
                    'DynamicModel' => [
                        'idregla'=> $reglaitem['id'],
                        'campo'=> $reglaitem['campo'], 
                        'condicion'=> $reglaitem['condicion'],
                        'valor'=> $reglaitem['valor'], 
                        'form_id'=> $reglaitem['formId']                    
                    ]
                ];
                $regla = new DynamicModel(['idregla','campo', 'condicion', 'valor', 'form_id']);
                $regla->addRule(['idregla','campo', 'condicion', 'valor', 'form_id'], 'safe');

                $regla->load($dataRegla);  
                $reglas[]= $regla;       
            }
        }
        

        //secciones 
        $dataSeccion =[];
        $dataPregunta =[];
        $secciones = array();
        $preguntas = array();
        $arrayIdsSecciones = array();
        $arrayIdsPreguntas = array();
        $arrayIdsValores = array();
        
        foreach ($formData->data['secciones'] as $seccionitem) {
            $arrayIdsSecciones[] = $seccionitem['id'];
            $dataSeccion = [                
                'DynamicModel' => [
                    'idseccion'=> $seccionitem['id'],
                    'tituloseccion'=> $seccionitem['titulo'], 
                    'orden'=> $seccionitem['orden'],
                    'columnas'=> $seccionitem['columnas'], 
                    'mostrartitulo'=> $seccionitem['mostrartitulo'], 
                    'form_id'=> $seccionitem['formId']                    
                ]
            ];
            $seccion = new DynamicModel(['idseccion','tituloseccion', 'orden', 'form_id', 'columnas', 'mostrartitulo']);
            $seccion->addRule(['idseccion','tituloseccion', 'orden', 'form_id',  'columnas', 'mostrartitulo'], 'safe');
            $seccion->load($dataSeccion); 
            $secciones[] = $seccion;
            
            if(isset($seccionitem['preguntas'])){
                foreach ($seccionitem['preguntas'] as $preguntaitem) {
                    $valores =[];
                    if(isset($preguntaitem['campos'])){
                        foreach ($preguntaitem['campos'] as $campoItem) {
                            $valores[] = $campoItem['nombre'];
                            $arrayIdsValores[] = $campoItem['id'];
                        }
                    }
                
                    $arrayIdsPreguntas[] = $preguntaitem['id'];
                    $dataPregunta = [                
                        'DynamicModel' => [
                            'idpregunta'=> $preguntaitem['id'],
                            'titulo'=> $preguntaitem['titulo'], 
                            'seccion_id'=> $preguntaitem['seccionId'],
                            'valores' => implode(',',$valores),
                            'tipo_pregunta_id'=> $preguntaitem['tipoPreguntaId'], 
                            'orden'=> $preguntaitem['orden']                                      
                        ]
                    ];

                    $pregunta = new DynamicModel(['idpregunta','titulo', 'seccion_id', 'tipo_pregunta_id', 'orden', 'valores']);
                    $pregunta->addRule(['idpregunta','titulo', 'seccion_id', 'tipo_pregunta_id', 'orden', 'valores'], 'safe');
                                
                    $pregunta->load($dataPregunta);                          
                    $preguntas[] = $pregunta; 
                }
            }          
        }
        //var_dump($preguntas);
        
              
      if (Yii::$app->request->post()) {
         $postValues = Yii::$app->request->post()['DynamicModel'];
         $formValues = array();
         $formularioId = intval($postValues['idform']);
         $formValues['nombre'] = $postValues['nombre'];
         $formValues['descripcion'] = $postValues['descripcion'];
         $formValues['formTipoId'] = intval($postValues['form_tipo_id']);
         $formValues['formEstadoId'] = intval($postValues['form_estado_id']);
         $formValues["updatedAt"] = date('Y-m-d H:i:s');
         $formValues["updatedBy"] =  1;
         
         
         // Guardar el form y guardar el formId en en una variable
         $editarformulario = $client->createRequest()
            ->setMethod('PATCH')
            ->setUrl($this->getHostFormsAPI().'/forms/'.$formularioId)
            ->setFormat(Client::FORMAT_JSON)
            ->setData( $formValues)
            ->send();
         if ($editarformulario->isOk) {                      
               $preguntasValues = array();
               $seccionesValues = array();
               $reglasValues = array();
               $seccionesIds = array();
               // 1° se deben borrar todos los valores de preguntas 
               // 2° se deben borrar las preguntas que ya no están 
               // 3° se deben borrar las secciones que ya no estan 
               // 4° se deben agregar las secciones nuevas y actualizar las que estaban
               // 5° se deben agregar las preguntas nuevas con sus valores y actualizar las nuevas
               for ($i=0; $i < 40; $i++) { 
                  if(isset($postValues[$i])){                     
                     if(isset($postValues[$i]['tituloseccion'])){ 
                        $seccionesValues[$i]['id'] = intval($postValues[$i]['idseccion']);
                        $seccionesValues[$i]['titulo'] = $postValues[$i]['tituloseccion'];
                        $seccionesValues[$i]['mostrartitulo'] = $postValues[$i]['mostrartitulo'];
                        $seccionesValues[$i]['orden'] = $postValues[$i]['orden'];
                        $seccionesValues[$i]['columnas'] = intval($postValues[$i]['columnas']);
                        $seccionesValues[$i]['formId'] = $formularioId;                        
                     }
                     if(isset($postValues[$i]['tipo_pregunta_id'])){
                        $preguntasValues[$i]['id'] = intval($postValues[$i]['idpregunta']);
                        $preguntasValues[$i]['titulo'] = $postValues[$i]['titulo'];
                        $preguntasValues[$i]['orden'] = intval($postValues[$i]['orden']);
                        $preguntasValues[$i]['seccionId'] = intval($postValues[$i]['seccion_id']);
                        $preguntasValues[$i]['tipoPreguntaId'] = intval($postValues[$i]['tipo_pregunta_id']);
                        $preguntasValues[$i]['valores'] = $postValues[$i]['valores'];
                     }
                     if(isset($postValues[$i]['condicion'])){ 
                        $reglasValues[$i]['id'] = intval($postValues[$i]['idregla']);
                        $reglasValues[$i]['campo'] = $postValues[$i]['campo'];
                        $reglasValues[$i]['condicion'] = $postValues[$i]['condicion'];
                        $reglasValues[$i]['valor'] = $postValues[$i]['valor'];
                        $reglasValues[$i]['formId'] = $formularioId;                        
                     }
                  }else{
                     break;
                  }
               }

            foreach ($reglasValues as $key =>  $reglaValue) {
            
               if(in_array($reglaValue['id'], $arrayIdsReglas)){
                    $nuevaRegla = $client->createRequest()
                    ->setMethod('PATCH')
                    ->setUrl($this->getHostFormsAPI().'/reglas/'.$reglaValue['id'])
                    ->setFormat(Client::FORMAT_JSON)
                    ->setData( $reglaValue)
                    ->send();
                    if (!$nuevaRegla->isOk) {                  
                        $mensajeError .= "La regla no pudo ser actualizada";
                    }
                    $arrayIdsReglas = array_diff( $arrayIdsReglas, [$reglaValue['id']] );
               }else{
                    $nuevaRegla = $client->createRequest()
                    ->setMethod('POST')
                    ->setUrl($this->getHostFormsAPI().'/reglas')
                    ->setFormat(Client::FORMAT_JSON)
                    ->setData( $reglaValue)
                    ->send();
                    if (!$nuevaRegla->isOk) {                  
                        $mensajeError .= "La regla no pudo ser creada";
                    }
               }
               
            }
            # Se borran las reglas que quedaron en el array
            foreach ($arrayIdsReglas as $reglaparaborrar) {
                $borrarRegla = $client->createRequest()
                    ->setMethod('DELETE')
                    ->setUrl($this->getHostFormsAPI().'/reglas/'.$reglaparaborrar)
                    ->setFormat(Client::FORMAT_JSON)                    
                    ->send();
                    if (!$nuevaRegla->isOk) {                  
                        $mensajeError .= "La regla no pudo ser eliminada";
                    }
            }
         // Guardar cada seccion y ponerle de indice el indice de la seccion y como valor el id q devuelve el post

         foreach ($seccionesValues as $key =>  $seccionValue) {
            if(in_array($seccionValue['id'], $arrayIdsSecciones)){
                var_dump('ya existe');
                $seccionesIds[$key] = $seccionValue['id'];
                unset($seccionValue['id']);
                $editarSeccion = $client->createRequest()
                ->setMethod('PATCH')
                ->setUrl($this->getHostFormsAPI().'/secciones/'.$seccionesIds[$key])
                ->setFormat(Client::FORMAT_JSON)
                ->setData( $seccionValue)
                ->send();                
                if ($editarSeccion->isOk) {                    ;
                    $arrayIdsSecciones = array_diff( $arrayIdsSecciones, [$seccionesIds[$key]] );
                }else{
                //var_dump($nuevaSeccion); die();
                    $mensajeError .= "La seccion no pudo ser actualizada";
                }
            }else{
                var_dump('no existe');
                unset($seccionValue['id']);
                $nuevaSeccion = $client->createRequest()
                ->setMethod('POST')
                ->setUrl($this->getHostFormsAPI().'/secciones')
                ->setFormat(Client::FORMAT_JSON)
                ->setData( $seccionValue)
                ->send();
                var_dump($nuevaSeccion);
                if ($nuevaSeccion->isOk) {
                    echo 'Guarda';
                $seccionesIds[$key] = $nuevaSeccion->data['id']; 
                }else{
                //var_dump($nuevaSeccion); die();
                $mensajeError .= "La seccion no pudo ser creada";
                }
            }
         }

         // TODO borrar todos los values de preguntas
         foreach ($arrayIdsValores as $valorparaborrar) {
            $borrarValor = $client->createRequest()
                ->setMethod('DELETE')
                ->setUrl($this->getHostFormsAPI().'/valores/'.$valorparaborrar)
                ->setFormat(Client::FORMAT_JSON)                    
                ->send();
                if (!$borrarValor->isOk) {                  
                    $mensajeError .= "El valor no pudo ser eliminado";
                }
        }
         
         foreach ($preguntasValues as $key =>  $preguntaValue) {
            
            $valores = $preguntaValue['valores'];
            unset($preguntaValue['valores']);
            $preguntaValue['seccionId'] = ($preguntaValue['seccionId'] == '')? 1 : $preguntaValue['seccionId']; 
            $preguntaId = '';          
            
            if(in_array($preguntaValue['id'], $arrayIdsPreguntas)){
                $preguntaId = $preguntaValue['id'];
                unset($preguntaValue['id']);
                $nuevaPregunta = $client->createRequest()
                    ->setMethod('PATCH')
                    ->setUrl($this->getHostFormsAPI().'/preguntas/'.$preguntaId)
                    ->setFormat(Client::FORMAT_JSON)
                    ->setData( $preguntaValue)
                    ->send();
                    if ($nuevaPregunta->isOk) {                        
                        $arrayIdsPreguntas = array_diff( $arrayIdsPreguntas, [$preguntaId] );
                    }else{
                        var_dump($nuevaPregunta);
                    }
            }else{
                unset($preguntaValue['id']);
                $nuevaPregunta = $client->createRequest()
                    ->setMethod('POST')
                    ->setUrl($this->getHostFormsAPI().'/seccions/'.$nuevaSeccionId.'/preguntas')
                    ->setFormat(Client::FORMAT_JSON)
                    ->setData( $preguntaValue)
                    ->send();
                if ($nuevaPregunta->isOk) {
                    $preguntaId = $nuevaPregunta->data['id'];                    
                }
            }

            if ($preguntaId != '') {
              
               // Guardo los valores 
               // Se hace explode de posibles $valores y luego un foreach
               $valorValue = array();
               $valoresValues = explode(',', $valores);
               $i = 0;
               foreach ($valoresValues as $key => $value) {
                  $valorValue ['nombre']= $value;
                  $valorValue ['orden']= ''.$i;
                  $valorValue ['createdAt']= date('Y-m-d H:i:s');
                  $valorValue ['createdBy']=1;
                  $valorValue ['preguntaId']=$preguntaId;
                  $valorValue ['tipoCampoId']=1;
                  $valorValue ['formCampoLogicaId']= 0;                  
                  $nuevoCampo = $client->createRequest()
                  ->setMethod('POST')
                  ->setUrl($this->getHostFormsAPI().'/campos/')
                  ->setFormat(Client::FORMAT_JSON)
                  ->setData( $valorValue)
                  ->send();
                  if (!$nuevoCampo->isOk) {
                     //var_dump($nuevoCampo); die();
                     $mensajeError .= "El campo no pudo ser creado";
                  }

                  $i++;
               }
               // se deben poner los datos de la pregunta 

            }else{
               //var_dump($nuevaPregunta); die();
               $mensajeError .= "La pregunta no pudo ser creada";
            }
         }
         // se borran las preguntas que quedaron 
         foreach ($arrayIdsPreguntas as $preguntaparaborrar) {
            $borrarValor = $client->createRequest()
                ->setMethod('DELETE')
                ->setUrl($this->getHostFormsAPI().'/preguntas/'.$preguntaparaborrar)
                ->setFormat(Client::FORMAT_JSON)                    
                ->send();
                if (!$borrarValor->isOk) {                  
                    $mensajeError .= "La pregunta no pudo ser eliminada";
                }
        }
        // se borran las secciones que quedaron 
         foreach ($arrayIdsSecciones as $seccionparaborrar) {
            $borrarValor = $client->createRequest()
                ->setMethod('DELETE')
                ->setUrl($this->getHostFormsAPI().'/secciones/'.$seccionparaborrar)
                ->setFormat(Client::FORMAT_JSON)                    
                ->send();
                if (!$borrarValor->isOk) {                  
                    $mensajeError .= "La sección no pudo ser eliminada";
                }
        }
        /*$mensajeSuccess = "El form se actualizó con exito";
        \Yii::$app->getSession()->setFlash('success', '<b>'.$mensajeSuccess.'</b>');
        return $this->redirect(['forms']);  */

      } else{
         //var_dump($nuevoformulario);die();
         $mensajeError .= 'El formulario no pudo actualizarse exitosamente';
      } // fin de formulario guardado exitosamente
      }

      $requestTipoForm =  '{"fields": {"id": true,"nombre": true,"descripcion": false}}';
        

      $decodedText = urlencode($requestTipoForm);
      $responseTipoForm = $client->createRequest()
         ->setMethod('GET')
         ->setUrl($this->getHostFormsAPI().'/tipo-forms?filter='.$decodedText)
         ->setFormat(Client::FORMAT_JSON)
         ->send();
      $tipoForm = array();
      if ($responseTipoForm->isOk) {
         $tipoForm = $responseTipoForm->data;      
      }

      return $this->render('edit', [
            'formulario' => $formulario,
            'secciones' => $secciones,
            'preguntas' => $preguntas,
            'reglas' => $reglas,
            'seccion' => $seccion,
            //'pregunta' => $pregunta,
            'regla' => $regla,
            'tipoForm' => $tipoForm,
            'mensajeSuccess' => $mensajeSuccess,
            'mensajeError' => $mensajeError
      ]);
     

    }   
    
    private function getFormData($id){

        $client = new Client();
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
        //var_dump($response); die();
        return $response;
    }
}
