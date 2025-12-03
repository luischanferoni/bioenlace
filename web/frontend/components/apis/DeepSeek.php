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
use common\models\Servicio;

class DeepSeek extends Component
{
    private $deepSeekApiKey;
    private $deepSeekBaseUrl;
    private $turnosApiBaseUrl;
    private $httpClient;

    public function __construct() 
    {
        $this->deepSeekApiKey = 'sk-c6ad8b4e3f47451d9aa0f3df2a25a34c';
        $this->deepSeekBaseUrl = 'https://api.deepseek.com/v1/chat';        
        
        $this->httpClient = new Client();
    }

    public function obtenerIntencion($prompt)
    {
        $finalPrompt = 'Identifica la intención (CREATE, READ, UPDATE, DELETE) y devuelve solo la palabra clave de este mensaje ' . $prompt . '"';

        $data = [
            'model' => 'deepseek-chat', // o el modelo que uses
            'stream' => false,
            'messages' => [
                ['role' => 'user', 'content' => $finalPrompt]
            ]
        ];

        $response = $this->httpClient->createRequest()
            ->setMethod('POST')
            ->setUrl($this->deepSeekBaseUrl . '/completions')
            ->setHeaders([
                'Authorization' => 'Bearer ' . $this->deepSeekApiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->setData($data)
            ->setFormat(Client::FORMAT_JSON)
            ->send();

        if (!$response->isOk) {
            // Manejar el error
            echo "Error en la solicitud: " . $response->statusCode;
            echo "Detalles: " . $response->content;
            return $response->content;
        }

        $responseData = json_decode($response->data, true);

        $generatedText = $responseData['choices'][0]['text'];

        return $generatedText;
    }

    public function procesarMensajePaciente($mensaje)
    {
        // Obtener servicios desde la BD
        $servicios = Servicio::find()->select('nombre')->column();

        // Llamada a la API externa que analiza el mensaje con IA
        $respuestaIA = $this->analizarConIA($mensaje, $servicios);
    
        $faltantes = [];
        foreach (['servicio', 'dia', 'horario'] as $campo) {
            if (empty($respuestaIA[$campo])) {
                $faltantes[] = $campo;
            }
        }
    
        $timestamp = time();

        if (!empty($faltantes)) {
            
            $content = 'Falta especificar: ' . implode(', ', $faltantes) . '.';

        } else {
            
            $content = "Turno reservado para el servicio \"{$respuestaIA['servicio']}\" el {$respuestaIA['dia']} a las {$respuestaIA['horario']}.";

        }
    
        // Aquí podrías guardar el turno o continuar con lógica adicional
        return [
            'id' => '1',
            'senderId' => '1',
            'senderName' => 'Bot',
            'content' => $content,
            'timestamp' => "$timestamp",
            //'datos' => $respuestaIA,
        ];
    }



}