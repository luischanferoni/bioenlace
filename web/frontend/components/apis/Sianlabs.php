<?php

namespace frontend\components\apis;
 
 
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\helpers\Json;
use yii\httpclient\Client;
use yii\authclient\OAuth2;

class Sianlabs extends OAuth2
{
    /**
     * {@inheritdoc}
    */
    public $authUrl = 'https://sianlabs.msalsgo.gob.ar/oauth/token';
    /**
     * {@inheritdoc}
     */
    public $tokenUrl = 'https://sianlabs.msalsgo.gob.ar/oauth/token';
    /**
     * {@inheritdoc}
     */
    public $apiBaseUrl = 'https://sianlabs.msalsgo.gob.ar/api/fhir/';
    
    
        /**
         * {@inheritdoc}
         */
        public function init()
        {
            parent::init();
            $this->scope = '';
        }
    
        /**
         * {@inheritdoc}
         */
        protected function initUserAttributes()
        {
            return null;#$this->api('account/verify_credentials.json', 'GET');
        }
    
        /**
         * {@inheritdoc}
         */
        protected function defaultName()
        {
            return 'sianlabs';
        }
    
        /**
         * {@inheritdoc}
         */
        protected function defaultTitle()
        {
            return 'sianlabs';
        }

        /**
         * {@inheritdoc}
         */
        public function applyAccessTokenToRequest($request, $accessToken)
        {
            $request->getHeaders()->set('Authorization', 'Bearer '. $accessToken->getToken());
        }

        function caller($metodo, $token, $verb="GET") 
        { 
            $headers = [
                'Authorization: Bearer '.$token,         
                'Content-Type: application/json',
            ];
            //$ch = curl_init(YII_ENV_PROD ? self::URL : self::URL_TEST.$metodo);
            
            $ch = curl_init($this->apiBaseUrl.$metodo);
            
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $verb);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);      
            $resp = curl_exec($ch);

            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpcode !== 200) {            
                Yii::error("Error de sianlabs: httpcode: " . $httpcode);
            }

            $respuesta = json_decode($resp, true);

            return $respuesta;
        }

        function getDiagnosticReport($id,$token) 
        {
            return $this->caller("DiagnosticReport?patient=".$id,$token,"GET");
        }

        function getIdPatient($dni,$token) 
        {
            return $this->caller("Patient?identifier=".$dni,$token,"GET");
        }

}