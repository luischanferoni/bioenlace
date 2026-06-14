<?php

namespace common\components\Domain\Terminology\Snomed;

use yii\base\Component;

class SnowstormClient extends Component
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

    /**
     * @return list<array{id: mixed, text: string}>
     */
    public function buscarConceptosPorEcl(string $term, string $ecl, int $limit = 10): array
    {
        $resultados = $this->busquedaFiltradaEcl($term, $ecl, [], $limit);
        $items = $resultados['items'] ?? [];

        $return = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $return[] = [
                'id' => $item['conceptId'] ?? null,
                'text' => (string) ($item['pt']['term'] ?? ''),
            ];
        }

        return $return;
    }

    /**
     * @return list<array{id: mixed, text: string}>|array<string, mixed>
     */
    public function searchByProfile(string $profileKey, string $term, ?int $limit = null)
    {
        $ecl = SnomedSearchProfileCatalog::eclForProfile($profileKey);
        if ($ecl === null) {
            return [];
        }

        $limit = $limit ?? SnomedSearchProfileCatalog::limitForProfile($profileKey);
        if (SnomedSearchProfileCatalog::returnFormatForProfile($profileKey) === 'raw_api') {
            return $this->busquedaFiltradaEcl($term, $ecl, [], $limit);
        }

        return $this->buscarConceptosPorEcl($term, $ecl, $limit);
    }

    /**
     * @return list<array{id: mixed, text: string}>|array<string, mixed>
     */
    private function searchByClientMethod(string $methodName, string $term, ?int $limit = null)
    {
        $profileKey = SnomedSearchProfileCatalog::profileKeyForClientMethod($methodName);
        if ($profileKey === null) {
            return [];
        }

        return $this->searchByProfile($profileKey, $term, $limit);
    }

    // Diagnosticos | Hallazgos
    public function getProblemas($term)
    {
        return $this->searchByClientMethod(__FUNCTION__, (string) $term);
    }
    
    public function getMedicamentosGenericos($term)
    {
        return $this->searchByClientMethod(__FUNCTION__, (string) $term);
    }

    public function getMedicamentosAnmat($term)
    {
        return $this->searchByClientMethod(__FUNCTION__, (string) $term);
    }

    // Practicas | Procedimientos
    public function getPracticas($term)
    {
        return $this->searchByClientMethod(__FUNCTION__, (string) $term);
    }

    public function getInmunizaciones($term)
    {
        return $this->searchByClientMethod(__FUNCTION__, (string) $term);
    }

    public function getAntecedentesPersonales($term)
    {
        return $this->searchByClientMethod(__FUNCTION__, (string) $term);
    }

    public function getAntecedentesFamiliares($term)
    {
        return $this->searchByClientMethod(__FUNCTION__, (string) $term);
    }

    public function getAlergias($term)
    {
        return $this->searchByClientMethod(__FUNCTION__, (string) $term);
    }

    public function getMotivosDeConsulta($term)
    {
        return $this->searchByClientMethod(__FUNCTION__, (string) $term);
    }

    public function getSintomas($term)
    {
        return $this->searchByClientMethod(__FUNCTION__, (string) $term);
    }

    public function getDiagnosticosOdontologia($term)
    {
        return $this->searchByClientMethod(__FUNCTION__, (string) $term);
    }

    public function getPracticasOdontologia($term)
    {
        return $this->searchByClientMethod(__FUNCTION__, (string) $term);
    }
        
}