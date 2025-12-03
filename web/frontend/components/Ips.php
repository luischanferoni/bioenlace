<?php

namespace frontend\components;
 
 
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\helpers\Json;

class Ips extends Component
{
    function getDocumentReference($id,$custodian)
    {
        //$id='412';
        //$custodian ='https://santafe.gob.ar/sica';
        //$custodian ='http://dummy.com.ar';
        $url = 'http://mhd.sisa.msal.gov.ar/fhir/';
        $metodo = 'DocumentReference';
        $parametros = 'subject:identifier=http://sisse.redes-sgo.gob.ar|'.$id.'&custodian='.$custodian.'&type=http://loinc.org|60591-5';      
        $resultado = $this->callerIps($url,$metodo,$parametros,'GET','jwt');

        $resp_json = Json::encode($resultado);
        
        return $resp_json;
    }

    function getHistoriaClinica($param)
    {
        //$patient_json = file_get_contents('ips-example01.json', true);
        //$patient_json = file_get_contents('ips-historia-clinica-stafe.json', true);
        //return $patient_json;   

        $url = 'http://mhd.sisa.msal.gov.ar/fhir/'.$param;
        $metodo = null;
        $param = null;
        $resultado = $this->callerIps($url,$metodo,$param,'GET','jwt');

        $resp_json = Json::encode($resultado);
        Yii::trace($resultado);
        return $resp_json;
    }

    function getDominios($id_mpi){
        //$url = 'https://testapp.hospitalitaliano.org.ar/masterfile-federacion-service/fhir/Patient/';

        $url = 'http://mhd.sisa.msal.gov.ar/fhir/Patient/';
        $metodo = '$patient-location';
        //$id_mpi = 9999;
        $parametros = 'identifier=http://sisse.redes-sgo.gob.ar|'.$id_mpi;
        $resultado = $this->callerIps($url,$metodo,$parametros);
        $resp_json = Json::encode($resultado);

        return $resp_json;
        
    }

	function callerIps($url, $metodo, $parametros,$verb="GET",$token=null) { 
        if (is_null($token))
            $token = 'eyJhbGciOiJSUzI1NiIsInR5cCIgOiAiSldUIiwia2lkIiA6ICJQaDdQQ3VqY1VVaXJQUGRxSldrbTNCSDYyS0k1Q1h0SmZpbnZKTWkxV2kwIn0.eyJqdGkiOiIzNDlkNjZkNi00YzM1LTQyNGQtOGRiYy1kMWIwMDNkYzMyYTYiLCJleHAiOjE1MzEzNDQ4NjEsIm5iZiI6MCwiaWF0IjoxNTMxMzQ0NTYxLCJpc3MiOiJodHRwOi8vNTQuMjA3LjQxLjE0Mjo4MDgwL2F1dGgvcmVhbG1zL2ZlZGVyYWRvciIsImF1ZCI6ImN1cy1tZW5kb3phIiwic3ViIjoiMGEzMzJjYjUtMDY5Zi00YWUyLTlkNDMtMmIzZDI1YTIxNTA1IiwidHlwIjoiQmVhcmVyIiwiYXpwIjoiY3VzLW1lbmRvemEiLCJhdXRoX3RpbWUiOjAsInNlc3Npb25fc3RhdGUiOiJhNDQxNTk0OS1hMzMxLTQ1NTQtOTBjMi0yMjZjM2U5YmU4MjciLCJhY3IiOiIxIiwiYWxsb3dlZC1vcmlnaW5zIjpbXSwicmVhbG1fYWNjZXNzIjp7InJvbGVzIjpbInVtYV9hdXRob3JpemF0aW9uIl19LCJyZXNvdXJjZV9hY2Nlc3MiOnsiYWNjb3VudCI6eyJyb2xlcyI6WyJtYW5hZ2UtYWNjb3VudCIsIm1hbmFnZS1hY2NvdW50LWxpbmtzIiwidmlldy1wcm9maWxlIl19fSwiY2xpZW50SWQiOiJjdXMtbWVuZG96YSIsImNsaWVudEhvc3QiOiIyMDkuMTMuODYuMTAiLCJwcmVmZXJyZWRfdXNlcm5hbWUiOiJzZXJ2aWNlLWFjY291bnQtY3VzLW1lbmRvemEiLCJjbGllbnRBZGRyZXNzIjoiMjA5LjEzLjg2LjEwIiwiZW1haWwiOiJzZXJ2aWNlLWFjY291bnQtY3VzLW1lbmRvemFAcGxhY2Vob2xkZXIub3JnIn0.HhS48SC1B-EtJ65HmfKyLdqzQ0VVp2gp-LKnjXKT_aGpOj0XpvPCgvc3EckkybQjQ41FsM4e6c8_dCGolgSiaUzBRst_5Qz9A1D6iIra2q7VU1MbSUKMUHBCeirGiD-7OXHePo1OArJek09as1-DaPdNUL18SX9Kwz_7MIBz4N9OLpVajlE3KST8mZ4ZLFJoHFSzOkXqFu5004nS5kir4f1KnRxfuocFtfnhjo7_Fu6x66jQOdQMAd3_PMDJ7aMdtcyqOEWEJqsNcNL8gASXljZjyqfXCfAovTlgAgBgZmlewFHzwFnG1au-Ipibspg62F2Wm49ktJCSf93hOacIfA';

        $headers  =  array(    
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        );

        if (!empty($metodo)) {
            $metodo = $metodo.'?'.$parametros;
            $ch = curl_init($url.$metodo);    
        }
        else
            $ch = curl_init($url);


        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $verb);
        //curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);                                                            
        //curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        
     
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);      
        $resp = curl_exec($ch); 
        $respuesta = json_decode($resp,true);

        //Yii::log("respuesta: " . $resp,'error','application.controllers.SiteController');        

        return $respuesta;
    }

}
