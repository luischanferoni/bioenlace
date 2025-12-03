<?php

namespace frontend\components\apis;


use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\helpers\Json;
use yii\httpclient\Client;
use webvimark\modules\UserManagement\models\User;
use common\models\Efector;
use common\models\Persona;

class Forms extends Component
{
    /**
     * {@inheritdoc}
     */
    protected function defaultName()
    {
        return 'forms';
    }

    /**
     * {@inheritdoc}
     */
    protected function defaultTitle()
    {
        return 'forms';
    }

    /**
     * {@inheritdoc}
     */
    public function applyAccessTokenToRequest($request, $accessToken)
    {
        $request->getHeaders()->set('Authorization', 'Bearer ' . $accessToken->getToken());
    }

    function caller($metodo, $token, $verb = "GET")
    {
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ];
        //$ch = curl_init(YII_ENV_PROD ? self::URL : self::URL_TEST.$metodo);

        $ch = curl_init($this->apiBaseUrl . $metodo);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $verb);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $resp = curl_exec($ch);

        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpcode !== 200) {
            Yii::error("Error de forms: httpcode: " . $httpcode);
        }

        $respuesta = json_decode($resp, true);

        return $respuesta;
    }

    function getTodosForms()
    {
        $client = new Client();
        $request = file_get_contents('../assets/jsonForms/formsList.json');

        // traemos todos los forms disponibles
        $decodedText = urlencode($request);
        $response = $client->createRequest()
            ->setMethod('GET')
            ->setUrl(Yii::$app->params['hostFormsAPI'] . '/forms?filter=' . $decodedText)
            ->setFormat(Client::FORMAT_JSON)
            ->send();
        $dataForms = [];
        if (!$response->isOk) {
            return [];
        }

        return $response->data;
    }

    function getTodosFormsPorUser()
    {
        $client = new Client();
        $request = file_get_contents('../assets/jsonForms/formsList.json');

        // traemos todos los forms disponibles
        $decodedText = urlencode($request);
        $response = $client->createRequest()
            ->setMethod('GET')
            ->setUrl(Yii::$app->params['hostFormsAPI'] . '/forms?filter=' . $decodedText)
            ->setFormat(Client::FORMAT_JSON)
            ->send();
        $dataForms = [];
        if (!$response->isOk) {
            return [];
        }
        //var_dump($response->data);
        //echo "<br><br>";
        foreach ($response->data as $form) {
            //Obtener reglas
            $reglas = $client->createRequest()
                ->setMethod('GET')
                ->setUrl(Yii::$app->params['hostFormsAPI'] . '/forms/' . $form['id'] . '/reglas')
                ->setFormat(Client::FORMAT_JSON)
                ->send();
            // Verificar Reglas por rol solamente ...
            // las reglas por session de usuario se verifican en render
            if ($reglas->isOk) {
                //var_dump($reglas->data);
                //echo "<br><br>";
                $noCumpleRegla = false;
                foreach ($reglas->data as $regla) {
                    if ($regla['condicion'] == '5') {
                        if (!User::hasRole([$regla['valor']])) {
                            $noCumpleRegla = true;
                        }
                    }
                }
            }
            if ($noCumpleRegla) {
                continue;
            }

            $dataForms[] = $form;
        }
        /*echo "forms<br>";
        var_dump($dataForms);
        echo "<br><br>";*/
        return $dataForms;
    }

    function getDetalleFormPorUser($userId)
    {
        $client = new Client();

        $forms = $this->getTodosForms();

        $dataForms = [];

        // Para cada form traemos las instancias de cada uno, filtrado por userId
        foreach ($forms as $form) {
            // Si el formTipoId es de 1 o n x paciente
            /* if($form['formTipoId'] == 3 || $form['formTipoId'] == 4) {
                $personaSession = unserialize($session['persona']);
            }*/
            //Obtener reglas
            $reglas = $client->createRequest()
                ->setMethod('GET')
                ->setUrl(Yii::$app->params['hostFormsAPI'] . '/forms/' . $form['id'] . '/reglas')
                ->setFormat(Client::FORMAT_JSON)
                ->send();
            // Verificar Reglas por rol solamente ...
            // las reglas por session de usuario se verifican en render
            if ($reglas->isOk) {
                $noCumpleRegla = false;
                foreach ($reglas->data as $regla) {
                    if ($regla['condicion'] ==  '5') {
                        if (!User::hasRole([$regla['valor']])) {
                            $noCumpleRegla = true;
                        }
                    }
                }
            }
            if ($noCumpleRegla) {
                continue;
            }

            $requestCount = '{"formId":' . $form['id'] . ', "createdBy": ' . $userId . ', "deltedAt": null}';
            $decodedWhere = urlencode($requestCount);
            $cantidad = $client->createRequest()
                ->setMethod('GET')
                ->setUrl(Yii::$app->params['hostFormsAPI'] . '/instancias/count?where=' . $decodedWhere)
                ->setFormat(Client::FORMAT_JSON)
                ->send();

            if ($cantidad->isOk) {
                $form['cantidad'] = $cantidad->data['count'];
            } else {
                $form['cantidad'] = 0;
            }

            $dataForms[] = $form;
        }

        return $dataForms;
    }

    function getCantidadPorForm($formId)
    {
        $client = new Client();

        $requestCount = '{"formId":' . $formId . '}';
        $decodedWhere = urlencode($requestCount);
        $cantidad = $client->createRequest()
            ->setMethod('GET')
            ->setUrl(Yii::$app->params['hostFormsAPI'] . '/instancias/count?where=' . $decodedWhere)
            ->setFormat(Client::FORMAT_JSON)
            ->send();

        if ($cantidad->isOk) {
            $cantidad = $cantidad->data['count'];
        } else {
            $cantidad = 0;
        }

        return $cantidad;
    }

    public function getPreguntasPorForm($formId, $adminEfector)
    {
        $client = new Client();
        // Se busca el form y las preguntas
        $request = file_get_contents('../assets/jsonForms/formInstancias.json');
        $decodedText = urlencode($request);

        // el filtro por el efector
        //$filtro[] = ["pregunta_id" => 99, "condicion" => "=", "valor" => $idEfector];

        $response = $client->createRequest()
            ->setMethod('GET')
            ->setUrl(Yii::$app->params['hostFormsAPI'] . '/forms/' . $formId . '?filter=' . $decodedText)
            //->setUrl(Yii::$app->params['hostFormsAPI'] . '/instanciasConFiltroPreguntaValor?filter=' . $decodedText.'&preguntasValores='.json_encode($filtro))            
            ->setHeaders(['Content-type' => 'application/json'])
            ->setData([])
            ->send();
        /*echo "<pre>";
        print_r($response);
        echo "</pre>";
        die;*/
        if (!$response->isOk) {
            return [];
        }

        $secciones = $response->data['secciones'];

        $preguntasArray = [];

        foreach ($secciones as $item) {
            if (array_key_exists('preguntas', $item)) {
                if ($formId == 26) {
                    $preguntasArray[] = ['clave' => 'fecha_creacion', 'id' => 0, 'tipo' => 'date', 'nombre' => 'Fecha Creacion'];
                    $preguntasArray[] = ['clave' => 'nombre', 'id' => 1, 'tipo' => 'string', 'nombre' => 'Nombre'];
                    $preguntasArray[] = ['clave' => 'sistolica', 'id' => 2, 'tipo' => 'string', 'nombre' => 'Sistolica'];
                    $preguntasArray[] = ['clave' => 'diastolica', 'id' => 3, 'tipo' => 'string', 'nombre' => 'Diastolica'];
                    $preguntasArray[] = ['clave' => 'riesgo', 'id' => 98, 'tipo' => 'select', 'nombre' => 'Riesgo', 'opciones' => ['' => 'Todos', 0 => 'Riesgo Bajo', 1 => 'Riesgo Moderado', 2 => 'Riesgo Muy Alto']];
                    if ($adminEfector) {
                        $preguntasArray[] = ['clave' => 'encuestador', 'id' => 4, 'tipo' => 'string', 'nombre' => 'Encuestador'];
                    }
                }
                //$preguntasArray[] = ['clave' => 'id', 'id' => 3, 'tipo' => 'string', 'nombre' => 'Id'];
                foreach ($item['preguntas'] as $pregunta) {

                    if ($formId == 26) {
                        if ($pregunta["titulo"] == 'id_efector') {
                            continue;
                            $preguntasArray[] = [
                                'clave' => 'id_efector',
                                'id' => $pregunta['id'],
                                'tipo' => 'text',
                                'nombre' => 'Efector'
                            ];
                            continue;
                        }

                        if ($pregunta["titulo"] == 'total') {
                            continue;
                        }

                        if ($pregunta["titulo"] == 'Sistolica') {
                            continue;
                        }
                        if ($pregunta["titulo"] == 'Diastolica') {
                            continue;
                        }
                    }

                    $clave = str_replace(' ', '_', strtolower($pregunta["titulo"]));
                    $clave = preg_replace('/[^A-Za-z0-9\_]/', '', $clave);
                    $clave = preg_replace('/-+/', '-', $clave);

                    switch ($pregunta['tipoPreguntaId']) {
                        case 1:
                        case 2:
                            $preguntasArray[] = [
                                'clave' => $clave,
                                'id' => $pregunta['id'],
                                'tipo' => 'text',
                                'nombre' => $pregunta['titulo']
                            ];
                            break;
                        case 3:

                            $preguntasArray[] = [
                                'clave' => $clave,
                                'id' => $pregunta['id'],
                                'tipo' => 'number',
                                'nombre' => $pregunta['titulo']
                            ];
                            break;
                        case 6:

                            $preguntasArray[] = [
                                'clave' => $clave,
                                'id' => $pregunta['id'],
                                'tipo' => 'date',
                                'nombre' => $pregunta['titulo']
                            ];
                            break;
                        case 11:
                        case 12:
                            if (array_key_exists('campos', $pregunta)) {
                                $campos = $pregunta['campos'];
                                $options = [];
                                $options[''] = "Todos";
                                foreach ($campos as $campo) {
                                    $options[$campo['id']] = $campo['nombre'];
                                }
                            }

                            $preguntasArray[] = [
                                'clave' => $clave,
                                'id' => $pregunta['id'],
                                'tipo' => 'select',
                                'nombre' => $pregunta['titulo'],
                                'opciones' => $options
                            ];
                            break;
                    }
                }
            }
        }

        return $preguntasArray;
    }

    public function getCantidadInstanciasPorEfector($formId, $formTipoId, $idEfector, $filtroModel)
    {
        $client = new Client();

        // el filtro por el efector
        $filtroModel[] = ["pregunta_id" => 99, "condicion" => "=", "valor" => $idEfector];

        // buscar las instancias del form
        $requestFilter = str_replace(
            ',
      "createdBy": "#USERID"',
            "",
            str_replace(
                '"#LIMIT"',
                "1",
                str_replace(
                    '"#OFFSET"',
                    "0",
                    str_replace(
                        '"#ID"',
                        $formId,
                        file_get_contents('../assets/jsonForms/instanciasCompletas.json')
                    )
                )
            )
        );

        $decodedFilter = urlencode($requestFilter);
        $cantidad = $client->createRequest()
            ->setMethod('GET')
            ->setUrl(Yii::$app->params['hostFormsAPI'] . '/instanciasConFiltroPreguntaValor/count?filter=' . $decodedFilter . '&preguntasValores=' . json_encode($filtroModel))
            ->setFormat(Client::FORMAT_JSON)
            ->send();

        if ($cantidad->isOk) {
            $cantidad = $cantidad->data['count'];
        } else {
            $cantidad = 0;
        }

        return $cantidad;
    }

    public function getCantidadInstanciasPorFormPorUserEfector($formId, $formTipoId, $userId, $idEfector, $filtroModel)
    {
        $client = new Client();

        // buscar las instancias del form
        $requestFilter = str_replace(
            '"#USERID"',
            $userId,
            str_replace(
                '"#LIMIT"',
                "1",
                str_replace(
                    '"#OFFSET"',
                    "0",
                    str_replace(
                        '"#ID"',
                        $formId,
                        file_get_contents('../assets/jsonForms/instanciasCompletas.json')
                    )
                )
            )
        );

        $decodedFilter = urlencode($requestFilter);
        $cantidad = $client->createRequest()
            ->setMethod('GET')
            ->setUrl(Yii::$app->params['hostFormsAPI'] . '/instanciasConFiltroPreguntaValor/count?filter=' . $decodedFilter . '&preguntasValores=' . json_encode($filtroModel))
            ->setFormat(Client::FORMAT_JSON)
            ->send();

        if ($cantidad->isOk) {
            $cantidad = $cantidad->data['count'];
        } else {
            $cantidad = 0;
        }

        return $cantidad;
    }

    public function getInstanciasPorEfector($formId, $formTipoId, $idEfector, $filtroModel, $offset)
    {
        $client = new Client();

        // el filtro por el efector
        $filtroModel[] = ["pregunta_id" => 99, "condicion" => "=", "valor" => $idEfector];

        // buscar las instancias del form
        $requestFilter = str_replace(
            ',
      "createdBy": "#USERID"',
            "",
            str_replace(
                '"#LIMIT"',
                "20",
                str_replace(
                    '"#OFFSET"',
                    $offset,
                    str_replace(
                        '"#ID"',
                        $formId,
                        file_get_contents('../assets/jsonForms/instanciasCompletas.json')
                    )
                )
            )
        );

        $decodedFilter = urlencode($requestFilter);
        $resinstancias = $client->createRequest()
            ->setMethod('GET')
            ->setUrl(Yii::$app->params['hostFormsAPI'] . '/instanciasConFiltroPreguntaValor?filter=' . $decodedFilter . '&preguntasValores=' . json_encode($filtroModel))
            ->setFormat(Client::FORMAT_JSON)
            ->send();


        if ($resinstancias->isOk) {
            return $this->procesarInstanciasFindRisk($resinstancias, $formTipoId, true);
        }

        return ['instancias' => []];
    }

    public function getInstanciasPorFormPorUserEfector($formId, $formTipoId, $userId, $idEfector, $filtroModel, $offset)
    {
        $client = new Client();

        // el filtro por el efector
        $filtroModel[] = ["pregunta_id" => 99, "condicion" => "=", "valor" => $idEfector];

        // buscar las instancias del form
        $requestFilter = str_replace(
            '"#USERID"',
            $userId,
            str_replace(
                '"#LIMIT"',
                "20",
                str_replace(
                    '"#OFFSET"',
                    $offset,
                    str_replace(
                        '"#ID"',
                        $formId,
                        file_get_contents('../assets/jsonForms/instanciasCompletas.json')
                    )
                )
            )
        );

        $decodedFilter = urlencode($requestFilter);
        $resinstancias = $client->createRequest()
            ->setMethod('GET')
            ->setUrl(Yii::$app->params['hostFormsAPI'] . '/instanciasConFiltroPreguntaValor?filter=' . $decodedFilter . '&preguntasValores=' . json_encode($filtroModel))
            ->setFormat(Client::FORMAT_JSON)
            ->send();


        if ($resinstancias->isOk) {
            return $this->procesarInstanciasFindRisk($resinstancias, $formTipoId);
        }

        return ['instancias' => []];
    }

    public function procesarInstanciasFindRisk($resinstancias, $formTipoId, $adminEfector = false)
    {
        $instancias = $resinstancias->data;
        /*echo "<pre>";
        print_r($instancias);
        echo "</pre>";
        die;*/
        // TODO: buscar las personas para armar la tabla si el tipo de form es 3 o 4
        $instanciasResult = [];

        foreach ($instancias as $key => $instancia) {
            // excluimos las instancias sin valores
            if (!isset($instancia['valores'])) {
                continue;
            }

            if ($instancia["createdFor"]) {
                $paciente = Persona::findOne($instancia["createdFor"]);
                #$instanciasResult[$key]["nombre"] = ($paciente) ? $paciente->getNombreCompleto('apellido_nombre') : 'No definido';
                $instanciasResult[$key]["nombre"] = '<a href="' . \yii\helpers\Url::to(['paciente/historia/' . $paciente->id_persona]) . '" target="_blank">' . $paciente->apellido . ', ' . $paciente->nombre . ' ' . $paciente->otro_nombre . '</a>';
            } else {
                $instanciasResult[$key]["nombre"] = 'No definido';
            }

            if ($adminEfector) {
                $rrhh = Persona::findOne(['id_user' => $instancia["createdBy"]]);

                if ($rrhh) {
                    $instanciasResult[$key]['encuestador'] = $rrhh->apellido . ', ' . $rrhh->nombre . ' ' . $rrhh->otro_nombre;
                } else {
                    $instanciasResult[$key]['encuestador'] = "--";
                }
            }

            $instanciasResult[$key]['fecha_creacion'] = Yii::$app->formatter->asDate($instancia["createdAt"], 'dd-MM-Y');

            if ($formTipoId == 3 || $formTipoId == 4) {

                foreach ($instancia['valores'] as $keyValor => $value) {
                    $clave = str_replace(' ', '_', strtolower($value["pregunta"]["titulo"]));
                    $clave = preg_replace('/[^A-Za-z0-9\_]/', '', $clave);
                    $clave = preg_replace('/-+/', '-', $clave);

                    if ($clave == 'id_efector') {
                        continue;
                        /* if($value['valor'] == $idEfector) {
                            $instanciasResult[$key][$clave] = $nombreEfector;
                        }*/
                    }

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

                    if ($value["preguntaId"] == 98) {
                        // las respuestas correspondientes a la valoracion del riesgo
                        if (intval($value['valor']) < 7) {
                            $instanciasResult[$key]['riesgo'] = 'Riesgo Bajo';
                        } elseif (intval($value['valor']) < 13) {
                            $instanciasResult[$key]['riesgo'] = 'Riesgo Moderado';
                        } else {
                            $instanciasResult[$key]['riesgo'] = 'Riesgo Muy Alto';
                        }
                    }

                    // filtros
                    /*if (in_array($value["pregunta"]["id"], array_keys($filtroModel))) {
                        if ($value['valor'] != $filtroModel[$value["pregunta"]["id"]]) {
                            unset($instanciasResult[$key]);
                            continue 2;
                        }
                    }*/
                }
            }
        }

        /*echo "<pre>";
        print_r($instanciasResult);
        echo "</pre>";
        die;*/
        return [
            'instancias' => $instanciasResult,
        ];
    }


    public function getInstanciasPorEfectorParaExport($formId, $formTipoId, $idEfector, $filtroModel, $limit)
    {
        $client = new Client();

        // el filtro por el efector
        $filtroModel[] = ["pregunta_id" => 99, "condicion" => "=", "valor" => $idEfector];

        // buscar las instancias del form
        $requestFilter = str_replace(
            ',
      "createdBy": "#USERID"',
            "",
            str_replace(
                '"#LIMIT"',
                $limit,
                str_replace(
                    '"#OFFSET"',
                    0,
                    str_replace(
                        '"#ID"',
                        $formId,
                        file_get_contents('../assets/jsonForms/instanciasCompletas.json')
                    )
                )
            )
        );

        $decodedFilter = urlencode($requestFilter);
        $resinstancias = $client->createRequest()
            ->setMethod('GET')
            ->setUrl(Yii::$app->params['hostFormsAPI'] . '/instanciasConFiltroPreguntaValor?filter=' . $decodedFilter . '&preguntasValores=' . json_encode($filtroModel))
            ->setFormat(Client::FORMAT_JSON)
            ->send();


        if ($resinstancias->isOk) {
            return $this->procesarInstanciasFindRisk($resinstancias, $formTipoId, true);
        }

        return ['instancias' => []];
    }

    public function getInstanciasPorFormPorUserEfectorParaExport($formId, $formTipoId, $userId, $idEfector, $filtroModel, $limit)
    {
        $client = new Client();

        // el filtro por el efector
        $filtroModel[] = ["pregunta_id" => 99, "condicion" => "=", "valor" => $idEfector];

        // buscar las instancias del form
        $requestFilter = str_replace(
            '"#USERID"',
            $userId,
            str_replace(
                '"#LIMIT"',
                $limit,
                str_replace(
                    '"#OFFSET"',
                    0,
                    str_replace(
                        '"#ID"',
                        $formId,
                        file_get_contents('../assets/jsonForms/instanciasCompletas.json')
                    )
                )
            )
        );

        $decodedFilter = urlencode($requestFilter);
        $resinstancias = $client->createRequest()
            ->setMethod('GET')
            ->setUrl(Yii::$app->params['hostFormsAPI'] . '/instanciasConFiltroPreguntaValor?filter=' . $decodedFilter . '&preguntasValores=' . json_encode($filtroModel))
            ->setFormat(Client::FORMAT_JSON)
            ->send();


        if ($resinstancias->isOk) {
            return $this->procesarInstanciasFindRisk($resinstancias, $formTipoId);
        }

        return ['instancias' => []];
    }


    public function getTodasInstanciasPorUser($userId)
    {
        $forms = $this->getTodosFormsPorUser();

        foreach ($forms as $form) {
            $this->getInstanciasPorFormPorUser($form['id'], $userId);
        }
    }
}
