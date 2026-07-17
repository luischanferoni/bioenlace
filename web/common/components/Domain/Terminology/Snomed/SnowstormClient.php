<?php

namespace common\components\Domain\Terminology\Snomed;

use RuntimeException;
use Yii;
use yii\base\Component;

class SnowstormClient extends Component
{
    public ?string $baseUrl = null;
    public ?string $token = null;
    public int $timeoutSeconds = 30;
    public int $connectTimeoutSeconds = 5;

    public function init(): void
    {
        parent::init();

        $config = Yii::$app !== null ? (Yii::$app->params['snowstorm'] ?? []) : [];
        if (!is_array($config)) {
            throw new RuntimeException('La configuración de Snowstorm debe ser un arreglo.');
        }

        $this->baseUrl = $this->baseUrl ?? ($config['baseUrl'] ?? null);
        $this->token = $this->token ?? ($config['token'] ?? null);
        $this->timeoutSeconds = (int) ($config['timeoutSeconds'] ?? $this->timeoutSeconds);
        $this->connectTimeoutSeconds = (int) ($config['connectTimeoutSeconds'] ?? $this->connectTimeoutSeconds);
    }

    /**
     * @param mixed $json Conservado por compatibilidad; los endpoints actuales usan query string.
     * @return array<string, mixed>
     */
    public function caller($metodo, $json = null, $verb = 'GET'): array
    {
        $baseUrl = rtrim(trim((string) $this->baseUrl), '/') . '/';
        if ($baseUrl === '/') {
            throw new RuntimeException('Snowstorm no tiene baseUrl configurada.');
        }

        $headers = [
            'Accept' => 'application/json',
            'Accept-Language' => 'es',
        ];
        $token = trim((string) $this->token);
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $this->request(
            $baseUrl . ltrim((string) $metodo, '/'),
            strtoupper((string) $verb),
            $headers
        );
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    protected function request(string $url, string $verb, array $headers): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('No fue posible inicializar la conexión con Snowstorm.');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $verb,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => max(1, $this->connectTimeoutSeconds),
            CURLOPT_TIMEOUT => max(1, $this->timeoutSeconds),
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $content = curl_exec($handle);
        $errorNumber = curl_errno($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($content === false || $errorNumber !== 0) {
            throw new RuntimeException('No fue posible conectar con Snowstorm.');
        }
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException('Snowstorm respondió con HTTP ' . $statusCode . '.');
        }

        $data = json_decode($content, true);
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Snowstorm devolvió una respuesta JSON inválida.');
        }

        return $data;
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