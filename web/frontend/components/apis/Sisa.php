<?php

namespace frontend\components\apis;
 
 
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\helpers\Json;
use yii\httpclient\Client;

class Sisa extends Component
{

    const URL_NOMIVAC = 'https://apisalud.msal.gob.ar/nomivacWs201/v1/api/aplicacionesVacunasCiudadano';    

    const URL = 'https://sisa.msal.gov.ar/sisa/services/rest';

    public $appId;
    public $appKey;    

    public function init()
    {
        $this->appId = Yii::$app->params['SISA_APP_ID'];
        $this->appKey = Yii::$app->params['SISA_APP_KEY'];
    }

    public function getVacunas($nro_doc, $sexo)
    {
        $client = new Client();
        $response = $client->createRequest()
            ->setMethod('POST')
            ->setUrl(self::URL_NOMIVAC)
            ->addHeaders(['content-type' => 'application/json', 'APP_ID' => $this->appId, 'APP_KEY' => $this->appKey])
            ->setContent(json_encode(['idTipoDoc' => 1, 'nroDoc' => $nro_doc, 'sexo' => $sexo]))
            ->send();

        return $response->content;
    }

    public function getProfesionalesDeSantiago($apellido, $nombre, $codigo, $nrodoc)
    {
        $client = new Client();
        $response = $client->createRequest()
            ->setMethod('GET')
            ->setUrl(self::URL.'/profesional/obtener')
            ->addHeaders(['content-type' => 'application/json', 'APP_ID' => $this->appId, 'APP_KEY' => $this->appKey])
            ->setContent(json_encode([
                    'apellido' => $apellido, 
                    'nombre' => $nombre, 
                    'codigo' => $codigo,
                    'nrodoc' => $nrodoc,
                    ]))
            ->send();
//var_dump($response);die;
        return $response->content;
    }    

}