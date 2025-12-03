<?php

namespace frontend\modules\api\v1\controllers;

use Yii;

use yii\filters\Cors;

use common\models\Dialogo;
use common\models\Mensaje;
use common\models\Servicio;
use common\models\Turno;

class ChatController extends \yii\rest\Controller
{
    public $enableCsrfValidation = false;
    // Add more verbs here if needed
    protected $_verbs = ['POST','OPTIONS'];

    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // remove auth filter before cors if you are using it
        unset($behaviors['authenticator']);

        // add CORS filter
        $behaviors['corsFilter'] = [
            'class' => Cors::class,
            'cors' => [
                'Origin' => ['http://localhost:3000', 'http://localhost:52294', 'https://riesgo-dbt.msalsgo.gob.ar'], // restrict access to
                'Access-Control-Request-Method' => $this->_verbs,
                // Not sure if you are using authorization filter
                'Access-Control-Allow-Headers' => ['content-type','authorization'], 
                // Try '*' first, once it works, make it more restrictive
                'Access-Control-Request-Headers' => ['*'],
                'Access-Control-Allow-Credentials' => true,
                'Access-Control-Max-Age' => 3600,
                'Access-Control-Expose-Headers' => ['X-Pagination-Current-Page'],
            ],
        ];

        // comento la AUTENTICACION solo para prueba, sino da unauthorized

        /*
        // re-add authentication filter if you are using it.
        $behaviors['authenticator'] = [
            'class' => HttpBearerAuth::class,
        ];
        // avoid authentication on CORS-pre-flight requests (HTTP OPTIONS method) if using authentication filter.
        $behaviors['authenticator']['except'] = ['options', 'login'];
        */
       return $behaviors;
    }

  // I do not think you need this method, 
  // this should already be mapped in the rules
 /* protected function verbs()
  {
      return [
          'login' => ['POST'],
      ];
  }*/
    /**
     * Send the HTTP options available to this route
     */
    public function actionOptions()
    {
        if (Yii::$app->getRequest()->getMethod() !== 'OPTIONS') {
            Yii::$app->getResponse()->setStatusCode(405);
        }
        Yii::$app->getResponse()->getHeaders()->set('Allow', implode(', ', $this->_verbs));
    }

    public function actionIndex()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return ['success' => true, 'mensajes' => [], 'msj' => ''];
    }

    public function actionRecibir()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $body = Yii::$app->request->getBodyParams();
        $senderId = $body['senderId'] ?? null;
        $content = $body['content'] ?? null;
    
        if (!$senderId || !$content) {
            return ['success' => false, 'msj' => 'Faltan datos obligatorios: senderId y content.'];
        }
    
        // 1. Buscar o crear el diálogo
        $dialogo = Dialogo::findOne(['usuario_id' => $senderId, 'bot_id' => 'BOT']);
        if (!$dialogo) {
            $dialogo = new Dialogo([
                'usuario_id' => $senderId,
                'bot_id' => 'BOT',
            ]);
            $dialogo->save();
        }

        // 2. Cargar estado parcial previo
        $estadoActual = json_decode($dialogo->estado_json, true) ?? [];

        // 3. Guardar el mensaje del usuario
        $mensajeUsuario = new Mensaje([
            'dialogo_id' => $dialogo->id,
            'sender_id' => $senderId,
            'sender_name' => $senderId, // Si querés un nombre más amigable, lo podés derivar.
            'content' => $content,
            'status' => 'recibido',
            'message_type' => 'texto',
            'is_resent' => 0,
        ]);

        $mensajeUsuario->save();

        // 4. Análisis con IA
        $servicios = Servicio::find()->select('nombre')->column();
        $datos = $this->analizarConIA($body['content'], $servicios);

        $intencion = $datos['intencion'] ?? 'crear_turno';

        switch ($intencion) {
            case 'crear_turno':
                $respuestaTexto = $this->crearTurno($datos['datos'], $dialogo);
                break;
        
            case 'modificar_turno':
                $respuestaTexto = $this->modificarTurno($datos['datos'], $dialogo);
                break;
        
            case 'cancelar_turno':
                $respuestaTexto = "Entiendo. ¿Podés confirmarme qué turno querés cancelar? Por ejemplo, el día y servicio.";
                break;
        
            case 'saludo':
                $respuestaTexto = "¡Hola! Soy tu asistente virtual para sacar turnos. ¿Querés reservar, modificar o cancelar uno?";
                break;
        
            case 'fuera_de_alcance':
                $respuestaTexto = "Estoy aquí para ayudarte con turnos médicos. ¿Querés reservar, modificar o cancelar uno?";
                break;
        
            default:
                $respuestaTexto = "No entendí tu mensaje. Por favor, indicame si querés reservar, modificar o cancelar un turno.";
                break;
        }


/*
        // 5. Combinar con nuevos datos (sin pisar valores existentes con nulos)
        foreach (['servicio', 'dia', 'horario'] as $campo) {
            if (!empty($datos[$campo])) {
                $estadoActual[$campo] = $datos[$campo];
            }
        }

        // 6. Verificar si faltan datos
        $faltantes = [];
        foreach (['servicio', 'dia', 'horario'] as $campo) {
            if (empty($estadoActual[$campo])) {
                $faltantes[] = $campo;
            }
        }

        // 7. Generar respuesta
        if (!empty($faltantes)) {
            $preguntas = [
                'servicio' => '¿Qué servicio necesitás reservar?',
                'dia' => '¿Qué día preferís?',
                'horario' => '¿En qué horario te vendría bien?',
            ];

            $preguntasTexto = array_map(fn($f) => $preguntas[$f], $faltantes);
            $respuestaTexto = implode(' ', $preguntasTexto);

            // Guardar estado parcial
            $dialogo->estado_json = json_encode($estadoActual);
            $dialogo->save();
        } else {
            // (Opcional) Validar disponibilidad real en otra capa o tabla
            $respuestaTexto = "Listo, turno reservado para el servicio \"{$estadoActual['servicio']}\" el {$estadoActual['dia']} a las {$estadoActual['horario']}.";

            // Limpiar estado
            $dialogo->estado_json = null;
            $dialogo->save();

            // (Opcional) Guardar el turno real en tabla de turnos aquí
        }       
*/
        // 5. Guardar el mensaje del bot
        $mensajeBot = new Mensaje([
            'dialogo_id' => $dialogo->id,
            'sender_id' => 'BOT',
            'sender_name' => 'Bot',
            'content' => $respuestaTexto,
            'status' => 'enviado',
            'message_type' => 'texto',
            'is_resent' => 0,
        ]);
        $mensajeBot->save();
        $mensajeBot->refresh();

        Yii::$app->response->statusCode = 201;

        // 6. Devolver respuesta en formato requerido
        return [
            'success' => true,
            'id' => (string)$mensajeBot->id,
            'senderId' => $mensajeBot->sender_id,
            'senderName' => $mensajeBot->sender_name,
            'content' => $mensajeBot->content,
            'timestamp' => (string)strtotime($mensajeBot->timestamp),
        ];
    }

    private function analizarConIA($mensaje, $servicios)
    {
        $prompt = $this->construirPrompt($mensaje, $servicios);

        $endpointIA = 'http://192.168.1.11:11434/api/generate';
    
        $payload = [
            'model' => 'mistral',
            'prompt' => $prompt,
            'stream' => false
        ];
    
        $client = new \yii\httpclient\Client();
        $response = $client->createRequest()
            ->setMethod('POST')
            ->setUrl($endpointIA)
            ->addHeaders([              
                'Content-Type' => 'application/json',
            ])            
            ->setContent(json_encode($payload))
            ->send();
    
        if ($response->isOk) {            
            return json_decode($response->data["response"], true); // Debe devolver ['servicio' => ..., 'dia' => ..., 'horario' => ...]
        } else {
            \Yii::error('Error en la respuesta de la IA: ' . $response->getStatusCode(), 'chats');
        }
    
        return ['servicio' => null, 'dia' => null, 'horario' => null];
    }

    private function construirPrompt($mensajeUsuario, $servicios)
    {
        $listaServicios = json_encode(array_values($servicios), JSON_UNESCAPED_UNICODE);
        
        return <<<EOT
        Tu rol es analizar mensajes de un usuario que interactúa con un bot para turnos médicos. Tu tarea es:

        1. Detectar la intención del usuario entre:
        - crear_turno
        - modificar_turno
        - cancelar_turno
        - saludo
        - fuera_de_alcance

        2. Extraer los datos necesarios, dependiendo de la intención:

        - Para `crear_turno`: detectar si se menciona `servicio`, `día`, `horario`.
        - Para `modificar_turno`: indicar qué campos desea cambiar (`servicio`, `día`, `horario`) y si ya menciona el nuevo valor.
        - Para `cancelar_turno`: confirmación clara.
        - Para `saludo`: solo marcarlo como saludo, sin buscar más datos.
        - Para `fuera_de_alcance`: cualquier mensaje que no esté relacionado con turnos.

        Devuelve un JSON con este formato:

        {
        "intencion": "...",
        "datos": {
            "servicio": "...",
            "dia": "...",
            "horario": "...",
            "modificaciones": {
            "servicio": "...",
            "dia": "...",
            "horario": "..."
            }
        }
        }

        Si un valor no está presente, usar `null`.

        Servicios disponibles: $listaServicios
        Mensaje del usuario: "$mensajeUsuario"
        EOT;

        /*
        Ejemplos:
        - Usuario: "Hola, quiero un turno para vacunación el martes a las 14"
        → intencion: crear_turno, servicio: vacunación, día: martes, horario: 14

        - Usuario: "¿Puedo cambiar el horario del turno?"
        → intencion: modificar_turno, modificaciones: { "horario": null }

        - Usuario: "Cancela el turno que tenía"
        → intencion: cancelar_turno

        - Usuario: "Hola"
        → intencion: saludo

        - Usuario: "Quiero saber cómo cuidar mi salud"
        → intencion: fuera_de_alcance
         */

        /*
        return <<<EOT
            Extraé del siguiente mensaje el servicio, día y horario para reservar un turno. Usá solo los servicios disponibles. Si falta alguno de esos datos, poné el valor como null.
            Servicios disponibles: $listaServicios
            Mensaje del usuario: "$mensajeUsuario"
            Respuesta esperada en formato JSON:
            {"servicio": "...","dia": "...","horario": "..."}
            EOT;
            */
    }

    private function crearTurno($datos, $dialogo)
    {
        // Cargar estado previo o iniciar uno nuevo
        $estado = json_decode($dialogo->estado_json, true) ?? [
            'intencion' => 'crear_turno',
            'servicio' => null,
            'dia' => null,
            'horario' => null,
        ];

        // Actualizar estado con los datos nuevos detectados
        foreach (['servicio', 'dia', 'horario'] as $campo) {
            if (!empty($datos[$campo])) {
                $estado[$campo] = $datos[$campo];
            }
        }

        // Guardar estado actualizado
        $dialogo->estado_json = json_encode($estado);
        $dialogo->save();

        // Verificar si falta algún dato
        $faltan = [];
        foreach (['servicio', 'dia', 'horario'] as $campo) {
            if (empty($estado[$campo])) {
                $faltan[] = $campo;
            }
        }

        if (!empty($faltan)) {
            // Preguntar por el siguiente dato faltante
            $preguntas = [
                'servicio' => '¿Qué servicio necesitás?',
                'dia' => '¿Para qué día querés el turno?',
                'horario' => '¿En qué horario te gustaría?',
            ];
            $siguiente = $faltan[0];
            $respuestaTexto = $preguntas[$siguiente];
        } else {
            // Todos los datos están completos → crear el turno
            /*$turno = new Turno([
                'usuario_id' => $senderId,
                'servicio' => $estado['servicio'],
                'dia' => $estado['dia'],
                'horario' => $estado['horario'],
            ]);
            $turno->save();*/

            // Generar respuesta de confirmación
            $respuestaTexto = "Tu turno fue reservado para el servicio \"{$estado['servicio']}\" el {$estado['dia']} a las {$estado['horario']}.";

            // Limpiar estado parcial
            $dialogo->estado_json = null;
            $dialogo->save();
        }

        return $respuestaTexto;
    }

    private function modificarTurno($datos, $dialogo)
    {
        // Buscar el turno actual (este ejemplo es genérico)
        $turnoActual = Turno::find()
        ->where(['usuario_id' => $senderId])
        ->orderBy(['id' => SORT_DESC])
        ->one();

        if (!$turnoActual) {
            $respuestaTexto = "No encontré ningún turno tuyo para modificar. ¿Querés sacar uno nuevo?";

            return $respuestaTexto;
        }        

        // Cargar o iniciar estado parcial
        $estado = json_decode($dialogo->estado_json, true) ?? [
            'intencion' => 'modificar_turno',
            'modificaciones' => [
                'servicio' => null,
                'dia' => null,
                'horario' => null
            ]
        ];

        // Aplicar nuevas detecciones del mensaje
        if (!empty($datos['modificaciones'])) {
            foreach (['servicio', 'dia', 'horario'] as $campo) {
                if (isset($datos['modificaciones'][$campo])) {
                    $estado['modificaciones'][$campo] = $datos['modificaciones'][$campo];
                }
            }
        }

        // Guardar el estado actualizado
        $dialogo->estado_json = json_encode($estado);
        $dialogo->save();

        // Verificar qué datos aún faltan
        $faltan = [];
        foreach ($estado['modificaciones'] as $campo => $nuevoValor) {
            if ($nuevoValor === null) {
                $faltan[] = $campo;
            }
        }

        if (!empty($faltan)) {
            $preguntas = [
                'servicio' => '¿Qué nuevo servicio necesitás?',
                'dia' => '¿A qué nuevo día querés cambiarlo?',
                'horario' => '¿Cuál sería el nuevo horario?'
            ];
            $siguiente = $faltan[0];
            $respuestaTexto = $preguntas[$siguiente];
        } else {
            // Aplicar los cambios al turno actual
            foreach ($estado['modificaciones'] as $campo => $nuevoValor) {
                $turnoActual->$campo = $nuevoValor;
            }
            $turnoActual->save();

            $respuestaTexto = "Tu turno fue actualizado: {$turnoActual->servicio}, el {$turnoActual->dia} a las {$turnoActual->horario}.";

            // Limpiar el estado parcial
            $dialogo->estado_json = null;
            $dialogo->save();
        }        
    }

}
