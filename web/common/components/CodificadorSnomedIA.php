<?php

namespace common\components;

use Yii;
use frontend\components\Snowstorm;
use common\components\IAManager;
use common\components\EmbeddingsManager;

/**
 * Componente para codificación automática de conceptos médicos con SNOMED CT
 * Utiliza matching directo, fuzzy y semántico para asociar términos con códigos SNOMED
 */
class CodificadorSnomedIA
{
    private $snowstorm;
    private $cache;
    private $estadisticas;
    
    // Configuración de confianza mínima para matching semántico
    const CONFIANZA_SEMANTICA = 0.7;
    
    // Categorías SNOMED y sus ECL correspondientes
    const CATEGORIAS_SNOMED = [
        'diagnosticos' => [
            'ecl' => '<<404684003 |hallazgo clinico (hallazgo)| OR <272379006 |Event (event)| OR <243796009 |Situation with explicit context (situation)|',
            'metodo' => 'getProblemas'
        ],
        'medicamentos' => [
            'ecl' => '(<763158003 |producto medicinal (producto)|: 732943007 |tiene base de sustancia de la potencia (atributo)|=*, [0..0] 774159003 |tiene proveedor (atributo)|=*) OR (^ 425091000221109 |conjunto de referencias simples de fármacos de uso clínico sin unidad de presentación definida (metadato fundacional)|)',
            'metodo' => 'getMedicamentosGenericos'
        ],
        'procedimientos' => [
            'ecl' => '< 71388002 | procedimiento (procedimiento) |',
            'metodo' => 'getPracticas'
        ],
        'sintomas' => [
            'ecl' => '<< 404684003 | hallazgo clínico (hallazgo)|',
            'metodo' => 'getSintomas'
        ]
    ];

    public function __construct()
    {
        $this->snowstorm = new Snowstorm();
        $this->cache = Yii::$app->cache;
        $this->estadisticas = [
            'total_conceptos' => 0,
            'codificados_semantico' => 0,
            'requieren_validacion' => 0,
            'tiempo_total' => 0
        ];
    }

    /**
     * Codificar datos extraídos por IA con códigos SNOMED
     * @param array $datosExtraidos Datos extraídos por la IA
     * @param array $categorias Categorías de configuración
     * @return array Datos con códigos SNOMED asociados
     */
    public function codificarDatos($datosExtraidos, $categorias = [])
    {
        $inicio = microtime(true);
        $this->estadisticas['total_conceptos'] = 0;
        
        if (!isset($datosExtraidos['datosExtraidos'])) {
            \Yii::warning('Datos extraídos no tienen estructura esperada', 'snomed-codificador');
            return $datosExtraidos;
        }

        $datosConSnomed = $datosExtraidos;
        
        foreach ($datosExtraidos['datosExtraidos'] as $categoria => $conceptos) {
            if (empty($conceptos)) continue;
            
            $categoriaSnomed = $this->mapearCategoriaSnomed($categoria);
            if (!$categoriaSnomed) continue;
            
            $conceptosCodificados = [];
            
            foreach ($conceptos as $concepto) {
                $this->estadisticas['total_conceptos']++;
                
                // Convertir string a array si es necesario
                if (is_string($concepto)) {
                    $concepto = ['texto' => $concepto];
                }
                
                // Si ya tiene conceptId, mantenerlo
                if (isset($concepto['conceptId']) && !empty($concepto['conceptId'])) {
                    $conceptosCodificados[] = $concepto;
                    continue;
                }
                
                $texto = $concepto['texto'] ?? $concepto;
                if (empty($texto)) continue;
                
                $codificacion = $this->buscarCodigoSnomed($texto, $categoriaSnomed);
                
                if ($codificacion) {
                    $concepto['conceptId'] = $codificacion['conceptId'];
                    $concepto['term_snomed'] = $codificacion['term'];
                    $concepto['confianza_snomed'] = $codificacion['confianza'];
                    $concepto['metodo_snomed'] = $codificacion['metodo'];
                    
                    // Actualizar estadísticas
                    $this->actualizarEstadisticas($codificacion['metodo']);
                } else {
                    $concepto['conceptId'] = null;
                    $concepto['confianza_snomed'] = 0;
                    $concepto['requiere_validacion'] = true;
                    $this->estadisticas['requieren_validacion']++;
                }
                
                $conceptosCodificados[] = $concepto;
            }
            
            $datosConSnomed['datosExtraidos'][$categoria] = $conceptosCodificados;
        }
        
        $this->estadisticas['tiempo_total'] = microtime(true) - $inicio;
        
        \Yii::info("Codificación SNOMED completada: {$this->estadisticas['total_conceptos']} conceptos en {$this->estadisticas['tiempo_total']}s", 'snomed-codificador');
        
        return $datosConSnomed;
    }

    /**
     * Buscar código SNOMED para un término específico usando solo matching semántico
     * @param string $texto Término a buscar
     * @param string $categoria Categoría SNOMED
     * @return array|null Resultado de la búsqueda
     */
    public function buscarCodigoSnomed($texto, $categoria)
    {
        // Solo matching semántico real con embeddings
        $resultado = $this->matchingSemantico($texto, $categoria);
        if ($resultado && $resultado['confianza'] >= self::CONFIANZA_SEMANTICA) {
            return $resultado;
        }
        
        return null;
    }

    /**
     * Matching semántico REAL: usar embeddings para encontrar términos similares
     * @param string $texto
     * @param string $categoria
     * @return array|null
     */
    private function matchingSemantico($texto, $categoria)
    {
        try {
            \Yii::info("Iniciando matching semántico real para: '{$texto}' en categoría '{$categoria}'", 'snomed-codificador');
            
            // 1. Generar embedding del término del usuario
            $embeddingUsuario = EmbeddingsManager::generarEmbedding($texto);
            if (!$embeddingUsuario) {
                \Yii::warning("No se pudo generar embedding para: '{$texto}'", 'snomed-codificador');
                return null;
            }
            
            // 2. Buscar candidatos en Snowstorm con contexto de categoría
            $candidatos = $this->buscarCandidatosEnSnowstorm($texto, $categoria);
            
            if (empty($candidatos)) {
                \Yii::info("No se encontraron candidatos en Snowstorm para: '{$texto}'", 'snomed-codificador');
                return null;
            }
            
            // 3. Encontrar el candidato más similar usando embeddings
            $mejorMatch = $this->encontrarMejorCandidato($embeddingUsuario, $candidatos);
            
            if (!$mejorMatch) {
                \Yii::info("No se encontró match semántico suficiente para: '{$texto}'", 'snomed-codificador');
                return null;
            }
            
            // 4. Devolver resultado con metadatos semánticos
            $resultado = [
                'conceptId' => $mejorMatch['id'],
                'term' => $mejorMatch['text'],
                'confianza' => $mejorMatch['similitud'],
                'metodo' => 'semantico_real',
                'similitud_embedding' => $mejorMatch['similitud'],
                'termino_original' => $texto,
                'termino_semantico' => $mejorMatch['text']
            ];
            
            \Yii::info("Match semántico exitoso: '{$texto}' -> '{$mejorMatch['text']}' (similitud: {$mejorMatch['similitud']})", 'snomed-codificador');
            return $resultado;
            
        } catch (\Exception $e) {
            \Yii::error("Error en matching semántico real SNOMED: " . $e->getMessage(), 'snomed-codificador');
            return null;
        }
    }

    /**
     * Buscar candidatos en Snowstorm con contexto de categoría
     * @param string $texto
     * @param string $categoria
     * @return array
     */
    private function buscarCandidatosEnSnowstorm($texto, $categoria)
    {
        try {
            $metodo = self::CATEGORIAS_SNOMED[$categoria]['metodo'];
            $resultados = $this->snowstorm->$metodo($texto, 20); // Limitar a 20 candidatos
            
            \Yii::info("Encontrados " . count($resultados) . " candidatos en Snowstorm para: '{$texto}'", 'snomed-codificador');
            return $resultados;
            
        } catch (\Exception $e) {
            \Yii::error("Error buscando candidatos en Snowstorm: " . $e->getMessage(), 'snomed-codificador');
            return [];
        }
    }

    /**
     * Encontrar el mejor candidato usando embeddings
     * @param array $embeddingUsuario
     * @param array $candidatos
     * @return array|null
     */
    private function encontrarMejorCandidato($embeddingUsuario, $candidatos)
    {
        try {
            $mejorMatch = null;
            $mejorSimilitud = 0;
            
            // Usar batch processing para generar embeddings de todos los candidatos a la vez
            $textosCandidatos = array_map(function($candidato) {
                return $candidato['text'];
            }, $candidatos);
            
            // Generar embeddings en batch (más eficiente)
            $embeddingsBatch = EmbeddingsManager::generarEmbeddingsBatch($textosCandidatos, true);
            
            foreach ($candidatos as $candidato) {
                $textoCandidato = $candidato['text'];
                
                // Obtener embedding del batch
                $embeddingCandidato = $embeddingsBatch[$textoCandidato] ?? null;
                
                // Si no está en batch, generar individualmente
                if (!$embeddingCandidato) {
                    $embeddingCandidato = EmbeddingsManager::generarEmbedding($textoCandidato);
                }
                
                if (!$embeddingCandidato) {
                    continue;
                }
                
                // Calcular similitud coseno
                $similitud = EmbeddingsManager::calcularSimilitudCoseno($embeddingUsuario, $embeddingCandidato);
                
                if ($similitud > $mejorSimilitud) {
                    $mejorSimilitud = $similitud;
                    $mejorMatch = $candidato;
                    $mejorMatch['similitud'] = $similitud;
                }
            }
            
            // Solo devolver si supera el umbral mínimo
            if ($mejorSimilitud >= self::CONFIANZA_SEMANTICA) {
                \Yii::info("Mejor candidato encontrado: '{$mejorMatch['text']}' (similitud: {$mejorSimilitud})", 'snomed-codificador');
                return $mejorMatch;
            }
            
            \Yii::info("No se encontró candidato con similitud suficiente (mejor: {$mejorSimilitud})", 'snomed-codificador');
            return null;
            
        } catch (\Exception $e) {
            \Yii::error("Error encontrando mejor candidato: " . $e->getMessage(), 'snomed-codificador');
            return null;
        }
    }

    /**
     * Mapear categoría de configuración a categoría SNOMED
     * @param string $categoria
     * @return string|null
     */
    private function mapearCategoriaSnomed($categoria)
    {
        $mapeo = [
            'Diagnóstico' => 'diagnosticos',
            'Diagnósticos' => 'diagnosticos',
            'Síntomas' => 'sintomas',
            'Medicamentos' => 'medicamentos',
            'Prácticas' => 'procedimientos',
            'Procedimientos' => 'procedimientos'
        ];
        
        return $mapeo[$categoria] ?? null;
    }

    /**
     * Actualizar estadísticas de codificación
     * @param string $metodo
     */
    private function actualizarEstadisticas($metodo)
    {
        if ($metodo === 'semantico_real') {
            $this->estadisticas['codificados_semantico']++;
        }
    }

    /**
     * Obtener estadísticas de codificación
     * @return array
     */
    public function getEstadisticasCodificacion()
    {
        return $this->estadisticas;
    }

    /**
     * Verificar si hay conceptos con baja confianza que requieren validación
     * @return bool
     */
    public function hayBajaConfianza()
    {
        return $this->estadisticas['requieren_validacion'] > 0;
    }

    /**
     * Obtener conceptos que requieren validación manual
     * @param array $datosConSnomed
     * @return array
     */
    public function getConceptosParaValidacion($datosConSnomed)
    {
        $conceptos = [];
        
        if (!isset($datosConSnomed['datosExtraidos'])) {
            return $conceptos;
        }
        
        foreach ($datosConSnomed['datosExtraidos'] as $categoria => $items) {
            foreach ($items as $item) {
                if (isset($item['requiere_validacion']) && $item['requiere_validacion']) {
                    $conceptos[] = [
                        'categoria' => $categoria,
                        'texto' => $item['texto'] ?? $item,
                        'conceptId' => $item['conceptId'] ?? null,
                        'confianza' => $item['confianza_snomed'] ?? 0
                    ];
                }
            }
        }
        
        return $conceptos;
    }
}
