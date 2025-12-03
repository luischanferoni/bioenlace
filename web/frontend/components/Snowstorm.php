<?php

namespace frontend\components;
 
 
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
 
class Snowstorm extends Component
{
    const URL = 'https://snowstorm.msal.gob.ar/MAIN/';
    const URL_TEST = 'https://snowstorm.msal.gob.ar/MAIN/';
    const URL_LOCAL = 'http://snowstorm.ciidse.ar/api/snowstorm/MAIN/';
    //const URL_TEST = 'https://snowstorm-test.msal.gob.ar/MAIN/';

	function caller($metodo, $json, $verb="GET") 
    { 
        //  $headers  =  array(    
        //  	'Accept-Language: es', 
        //  	'app_id: 66844980',        
        //  	'app_key: 07e137b2603035574877a423e6389616',
        //  );

        //  $ch = curl_init(YII_ENV_PROD ? self::URL : self::URL_TEST.$metodo);
    
        $token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiI2IiwianRpIjoiY2YzNmVlZDQyOTExMDhiMmNkNzk1ZTAzYjVkMjVlMzIwOGVkOGE3YTkzMTg0ZjFlYzI2YmJiMmU0YzVlMGUxNTM0NTI4MTQwNjQzZDYxOGYiLCJpYXQiOjE3MjQzNDk2NDAuMTM4NzEzLCJuYmYiOjE3MjQzNDk2NDAuMTM4NzE1LCJleHAiOjE3NTU4ODU2NDAuMTI5MTgsInN1YiI6IjQiLCJzY29wZXMiOlsiKiJdfQ.D0MitZqG8hBo3CojGiQbAiAqaDoP6-YmstOtncIm2GKA1aakwdrsW4Y2q-w3i6o3yDGuhF-ngbjPbv_atZt9DsG2dv0JqgtTHuFHkXo6RmCm43XCAQHF1qObnm_8DAs4w2ZqRmphzD7aqRsddGYwwsX5B0eYkMSegQh_dvsriI85rrs6nbYWDaSq0HkBjO-ztdP--AJptSZGlAfE6TZjOCZrOcCMVICj249iOcIJM4idl23c-zDej9QUfadz-rfaOqBgM7UnriijbF78NcMB949mvySr0Yp9ODSLIFyhX-ewxIhDDlvAXSKCYAemtAsCPSMP28l2gXVTBn2asYBRw85tbEPDrzHFmG9mm_bA5er2zFEqsd8m4DZquzT9Pi6AG-qwwOJ-s4i1TRFyfpND-xyqphA-KozoTLvwiNtzBudWBnJDtsFX-L5W7BUMInJzDNO6ilW51fTIusqz17gFOIBiP4obvmr201zzJAJ4DVDc_P0I6pisAJCrlc4KXKMvh7xYB3Dsjqybp425PTyTE6ePcHqVFoGfLjWlkn3iqTIcNkzzWUokd3AmlUYRDtQQCNz2j6zb4m2M-jgrXX7aGUrDjlSqsQKXlUYr_8gfL0PV2aum_VtHvocNGuQIW_J3Ph0t-CdqKEuAeMyqrZwvh0Sm9W4BDGsSubK32Uz5SSw";

        $headers = [
        	'Authorization: Bearer '.$token,         
        	'Content-Type: application/json',
        ];
        
        $ch = curl_init(self::URL_LOCAL.$metodo);
        
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $verb);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
     
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);      
        $resp = curl_exec($ch);        
        $respuesta = json_decode($resp, true);

        return $respuesta;
    }

    function busquedaPorConceptId($conceptId) 
    {
        $params = [
            'activeFilter' => true,
            'ecl' => $conceptId,
            'limit' => 1,
            'offset' => 0,
        ];
        $get_params = http_build_query($params);
        $respuesta = $this->caller("concepts?".$get_params,"GET");

        return $respuesta['items'][0]['pt']['term'];
    }

    function busquedaSinFiltrar($term, $limit=10, $offset=0) 
    {
    	$params = [
    		'activeFilter' => true,
    		'term' => $term,
    		'limit' => $limit,
    		'offset' => $offset,
    	];

    	$get_params = http_build_query($params);
    	return $respuesta = $this->caller("concepts?".$get_params,"GET");
    }

    function busquedaFiltradaEcl($term, $ecl, $conceptIds = [], $limit=10, $offset=0)
    {
    	$params = [
    		'activeFilter' => true,
    		'term' => $term,
            //'conceptIds' => $conceptIds,
    		'ecl' => $ecl,
    		'limit' => $limit,
    		'offset' => $offset,
    	];
    	$get_params = http_build_query($params);        
    	return $this->caller("concepts?".$get_params, "GET");
    }

    // Diagnosticos | Hallazgos
    public function getProblemas($term)
    {
        $ECL = "<<404684003 |hallazgo clinico (hallazgo)| OR <272379006 |Event (event)| OR <243796009 |Situation with explicit context (situation)|";
        $resultados = $this->busquedaFiltradaEcl($term, $ECL);
       // var_dump($resultados); die;
        $resultados = $resultados['items']??[];
        //var_dump($resultados); die();
        $return = [];
        for ($i=0; $i < count($resultados); $i++) { 
        	$return[] = ['id' => $resultados[$i]['conceptId'], 'text' => $resultados[$i]['pt']['term']];
        }
        return $return;
    }
    
    public function getMedicamentosGenericos($term)
    {

        // ecl de los medicamentos genéricos + medicamentos de uso clinico (contiene ibuprofeno, paracetamol, etc gotas y otros)
        $ECL = "(<763158003 |producto medicinal (producto)|: 732943007 |tiene base de sustancia de la potencia (atributo)|=*, [0..0] 774159003 |tiene proveedor (atributo)|=*) OR (^ 425091000221109 |conjunto de referencias simples de fármacos de uso clínico sin unidad de presentación definida (metadato fundacional)|)";
        $resultados = $this->busquedaFiltradaEcl($term, $ECL);
        $resultados = $resultados['items']??[];

        $return = [];
        for ($i=0; $i < count($resultados); $i++) { 
        	$return[] = ['id' => $resultados[$i]['conceptId'], 'text' => $resultados[$i]['pt']['term']];
        }

        return $return;
    }

    public function getMedicamentosAnmat($term)
    {
        $ECL = "^ 331101000221109 |conjunto de referencias simples de presentaciones farmacéuticas comerciales del Vademecum Nacional de Medicamentos en estado comercializado (metadato fundacional)|";
        $resultados = $this->busquedaFiltradaEcl($term, $ECL);
        $resultados = $resultados['items']??[];

        $return = [];
        for ($i=0; $i < count($resultados); $i++) { 
        	$return[] = ['id' => $resultados[$i]['conceptId'], 'text' => $resultados[$i]['pt']['term']];
        }

        return $return;
    }
    

    // Practicas | Procedimientos
    public function getPracticas($term)
    {
        $ECL = "< 71388002 | procedimiento (procedimiento) |";
        $resultados = $this->busquedaFiltradaEcl($term, $ECL);
        $resultados = $resultados['items']??[];
        
        $return = [];
        for ($i=0; $i < count($resultados); $i++) { 
        	$return[] = ['id' => $resultados[$i]['conceptId'], 'text' => $resultados[$i]['pt']['term']];
        }

        return $return;        
    }

    public function getInmunizaciones($term)
    {
        $ECL = "%5E 2281000221106";
        return $this->busquedaFiltradaEcl($term, $ECL);
    }

    public function getAntecedentesPersonales($term)
    {
    	$ECL = "<< 417662000 |antecedente de hallazgo clínico en el sujeto (situación)|";
    	$resultados = $this->busquedaFiltradaEcl($term, $ECL);
        $resultados = $resultados['items']??[];
        
        $return = [];
        for ($i=0; $i < count($resultados); $i++) { 
            $return[] = ['id' => $resultados[$i]['conceptId'], 'text' => $resultados[$i]['pt']['term']];
        }

        return $return;        
    }

    public function getAntecedentesFamiliares($term)
    {
    	$ECL = "<< 57177007 |antecedente familiar con contexto explícito (situación)|";
    	$resultados =  $this->busquedaFiltradaEcl($term, $ECL);
        $resultados = $resultados['items']??[];
        
        $return = [];
        for ($i=0; $i < count($resultados); $i++) { 
            $return[] = ['id' => $resultados[$i]['conceptId'], 'text' => $resultados[$i]['pt']['term']];
        }

        return $return;        
    }

    public function getAlergias($term)
    {
    	$ECL = "< 420134006 | propensión a experimentar reacciones adversas (hallazgo) |";
    	$resultados =  $this->busquedaFiltradaEcl($term, $ECL);
        $resultados = $resultados['items']??[];
        
        $return = [];
        for ($i=0; $i < count($resultados); $i++) { 
            $return[] = ['id' => $resultados[$i]['conceptId'], 'text' => $resultados[$i]['pt']['term']];
        }

        return $return;        
    }

    public function getMotivosDeConsulta($term)
    {
    	$ECL = "(<< 71388002 OR << 243796009 OR << 272379006 OR << 404684003)";
    	$resultados =  $this->busquedaFiltradaEcl($term, $ECL);
        
        $resultados = $resultados['items']??[];
        
        $return = [];
        for ($i=0; $i < count($resultados); $i++) { 
            $return[] = ['id' => $resultados[$i]['conceptId'], 'text' => $resultados[$i]['pt']['term']];
        }

        return $return;        
    }

    public function getSintomas($term)
    {
    	$ECL = "<< 404684003 | hallazgo clínico (hallazgo)|";
    	$resultados =  $this->busquedaFiltradaEcl($term, $ECL);
        //var_dump($resultados);die;
        $resultados = $resultados['items']??[];
        
        $return = [];
        for ($i=0; $i < count($resultados); $i++) { 
            $return[] = ['id' => $resultados[$i]['conceptId'], 'text' => $resultados[$i]['pt']['term']];
        }

        return $return;        
    }

    public function getDiagnosticosOdontologia($term)
    {
        // (posible para los diagnosticos generales)$ECL = "^ 398711000221107 |conjunto de referencias simples de diagnósticos de odontología de Argentina (metadato fundacional)|";

    	$ECL = "< 278544002 |hallazgo de diente (hallazgo)|";
    	$resultados =  $this->busquedaFiltradaEcl($term, $ECL);
        
        $resultados = $resultados['items']??[];
        
        $return = [];
        for ($i=0; $i < count($resultados); $i++) { 
            $return[] = ['id' => $resultados[$i]['conceptId'], 'text' => $resultados[$i]['pt']['term']];
        }

        return $return;        
    }

    public function getPracticasOdontologia($term)
    {
    	$ECL = "^399211000221109";
    	$resultados =  $this->busquedaFiltradaEcl($term, $ECL);
        
        $resultados = $resultados['items']??[];
        
        $return = [];
        for ($i=0; $i < count($resultados); $i++) { 
            $return[] = ['id' => $resultados[$i]['conceptId'], 'text' => $resultados[$i]['pt']['term']];
        }

        return $return;        
    }
        
}