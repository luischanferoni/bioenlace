<?php

namespace frontend\components;
 
 
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
 
class Pentalogic extends Component
{
    const URL = 'https://pacscentral.pentalogic.ar/api/';
    const URL_TEST = 'http://api.pentalogic.tech/';
   

	function caller($metodo, $json, $verb="GET") 
    { 
        
        $token_test = "37061723-12bb-4766-a1df-d12c2e1c0241";
        $token_prod = "950bd0af-d559-41c2-83be-4d8d4abed187";

        $token = YII_ENV_PROD ? $token_prod : $token_test;
        
        $headers = [
        	'Authorization: '. $token,         
        ];
        
        $url = YII_ENV_PROD ? self::URL : self::URL_TEST;
              
        $ch = curl_init($url.$metodo);
        
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $verb);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
     
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);      
        $resp = curl_exec($ch);        
        $respuesta = json_decode($resp, true);
       
        return $respuesta;
    }

    function traerEstudio($dni = 87542132, $accession = 00000001) 
    {
        $params = [
            'pat_id' => $dni,
            'accession_no' => $accession,
        ];

        $get_params = http_build_query($params);

        $respuesta = $this->caller("study/getstudyurl.php?".$get_params,"GET");

        return $respuesta;
    }

    function listaEstudiosPendientes() 
    {
    	
    	return $this->caller("worklist/list.php?","GET");
    }

    
    function listaEstudiosPaciente($pac_id, $desde, $hasta) 
    {    	
    	return $this->caller("study/getstudylist.php?pat_id=$pac_id&fecha_desde=$desde&fecha_hasta=$hasta","GET");
    }

    function traerInforme($dni = 89894545, $accession = 00000001) 
    {
        $params = [
            'pat_id' => $dni,
            'accession_no' => $accession,
        ];

        $get_params = http_build_query($params);
     
        return $this->caller("study/getinformeurl.php?".$get_params,"GET");       
    }

}