<?php

namespace common\helpers;

use Yii;

/**
 * Utilidades para normalizar texto médico y palabras individuales.
 * Optimizado para procesamiento local (CPU) sin requerir GPU.
 */
class TextoMedicoHelper
{
    /**
     * Cache para detección de idioma
     */
    private static $idiomaCache = [];
    
    /**
     * Palabras comunes en español para detección de idioma
     */
    private const PALABRAS_ES = [
        'el', 'la', 'de', 'que', 'y', 'a', 'en', 'un', 'es', 'se', 'no', 'te', 'lo', 'le', 'da', 'su', 'por', 'son', 'con', 'para', 'al', 'del', 'los', 'las',
        'una', 'uno', 'como', 'más', 'pero', 'sus', 'le', 'ha', 'me', 'si', 'sin', 'sobre', 'este', 'entre', 'cuando', 'todo', 'esta', 'ser', 'son', 'dos', 'también',
        'fue', 'había', 'era', 'muy', 'años', 'hasta', 'desde', 'está', 'mi', 'porque', 'qué', 'sólo', 'han', 'yo', 'hay', 'vez', 'puede', 'todos', 'así', 'nos',
        'ni', 'parte', 'tiene', 'él', 'uno', 'donde', 'bien', 'tiempo', 'mismo', 'ese', 'ahora', 'cada', 'e', 'vida', 'otro', 'después', 'te', 'otros', 'aunque',
        'esa', 'esos', 'estas', 'estos', 'otra', 'otras', 'otro', 'otros', 'mismo', 'misma', 'mismos', 'mismas', 'tanto', 'tanta', 'tantos', 'tantas'
    ];
    
    /**
     * Palabras comunes en inglés para detección de idioma
     */
    private const PALABRAS_EN = [
        'the', 'be', 'to', 'of', 'and', 'a', 'in', 'that', 'have', 'i', 'it', 'for', 'not', 'on', 'with', 'he', 'as', 'you', 'do', 'at',
        'this', 'but', 'his', 'by', 'from', 'they', 'we', 'say', 'her', 'she', 'or', 'an', 'will', 'my', 'one', 'all', 'would', 'there', 'their',
        'what', 'so', 'up', 'out', 'if', 'about', 'who', 'get', 'which', 'go', 'me', 'when', 'make', 'can', 'like', 'time', 'no', 'just', 'him', 'know',
        'take', 'people', 'into', 'year', 'your', 'good', 'some', 'could', 'them', 'see', 'other', 'than', 'then', 'now', 'look', 'only', 'come', 'its', 'over', 'think'
    ];
    
    /**
     * Limpiar y normalizar texto médico completo.
     *
     * @param string $texto
     * @return string
     */
    public static function limpiarTexto(string $texto): string
    {
        // Normalizar espacios y saltos de línea
        $texto = preg_replace('/\s+/', ' ', $texto);

        // Remover caracteres de control
        $texto = preg_replace('/[\x00-\x1F\x7F]/', '', $texto);

        // Normalizar puntuación repetida
        $texto = preg_replace('/[.]{2,}/', '.', $texto);
        $texto = preg_replace('/[,]{2,}/', ',', $texto);

        // Normalizar acentos y caracteres especiales
        $texto = self::normalizarAcentos($texto);

        return trim($texto);
    }

    /**
     * Limpiar una palabra para procesamiento (sin puntuación ni caracteres no alfabéticos).
     *
     * @param string $palabra
     * @return string
     */
    public static function limpiarPalabra(string $palabra): string
    {
        $palabra = rtrim($palabra, '.,;:!?');
        $palabra = strtolower($palabra);
        
        // Normalizar acentos antes de limpiar
        $palabra = self::normalizarAcentos($palabra);

        // Permitir letras básicas y acentos normalizados
        return preg_replace('/[^a-z]/', '', $palabra);
    }

    /**
     * Normalizar acentos y caracteres especiales del español.
     *
     * @param string $texto
     * @return string
     */
    public static function normalizarAcentos(string $texto): string
    {
        // Mapeo de caracteres con acentos a sus equivalentes sin acento
        $acentos = [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n',
            'ç' => 'c',
            // Versiones mayúsculas
            'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A',
            'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
            'Ñ' => 'N',
            'Ç' => 'C'
        ];

        return strtr($texto, $acentos);
    }
    
    /**
     * Detectar idioma del texto (español o inglés)
     * Optimizado para procesamiento local (CPU) sin requerir GPU
     * 
     * @param string $texto
     * @return string Código de idioma ('es' o 'en')
     */
    public static function detectarIdioma(string $texto): string
    {
        // Verificar cache
        $cacheKey = md5($texto);
        if (isset(self::$idiomaCache[$cacheKey])) {
            return self::$idiomaCache[$cacheKey];
        }
        
        $textoLower = mb_strtolower($texto, 'UTF-8');
        $palabras = self::tokenizar($texto);
        
        $countEspanol = 0;
        $countIngles = 0;
        
        // Contar palabras comunes en español
        foreach (self::PALABRAS_ES as $palabra) {
            if (stripos($textoLower, ' ' . $palabra . ' ') !== false || 
                stripos($textoLower, $palabra . ' ') === 0 ||
                stripos($textoLower, ' ' . $palabra) === strlen($textoLower) - strlen(' ' . $palabra)) {
                $countEspanol++;
            }
        }
        
        // Contar palabras comunes en inglés
        foreach (self::PALABRAS_EN as $palabra) {
            if (stripos($textoLower, ' ' . $palabra . ' ') !== false || 
                stripos($textoLower, $palabra . ' ') === 0 ||
                stripos($textoLower, ' ' . $palabra) === strlen($textoLower) - strlen(' ' . $palabra)) {
                $countIngles++;
            }
        }
        
        // Detectar caracteres específicos del español
        $caracteresEspanol = ['ñ', 'á', 'é', 'í', 'ó', 'ú', 'ü', 'Ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ü'];
        foreach ($caracteresEspanol as $char) {
            if (mb_strpos($texto, $char) !== false) {
                $countEspanol += 2; // Peso extra para caracteres específicos
            }
        }
        
        // Determinar idioma
        $idioma = ($countEspanol >= $countIngles) ? 'es' : 'en';
        
        // Guardar en cache
        self::$idiomaCache[$cacheKey] = $idioma;
        
        // Limpiar cache si es muy grande
        if (count(self::$idiomaCache) > 1000) {
            self::$idiomaCache = array_slice(self::$idiomaCache, -500, null, true);
        }
        
        return $idioma;
    }
    
    /**
     * Tokenizar texto en palabras (procesamiento local)
     * Optimizado para procesamiento rápido sin GPU
     * 
     * @param string $texto
     * @param bool $removerStopwords Si true, remueve palabras comunes
     * @return array Array de tokens (palabras)
     */
    public static function tokenizar(string $texto, bool $removerStopwords = false): array
    {
        // Normalizar texto primero
        $texto = self::limpiarTexto($texto);
        
        // Tokenizar por espacios y puntuación
        $tokens = preg_split('/[\s\p{P}]+/u', $texto, -1, PREG_SPLIT_NO_EMPTY);
        
        // Limpiar tokens
        $tokensLimpios = [];
        foreach ($tokens as $token) {
            $tokenLimpio = trim($token);
            if (strlen($tokenLimpio) > 0) {
                // Convertir a minúsculas y normalizar
                $tokenLimpio = mb_strtolower($tokenLimpio, 'UTF-8');
                $tokenLimpio = self::normalizarAcentos($tokenLimpio);
                
                // Remover stopwords si se solicita
                if ($removerStopwords) {
                    $stopwords = array_merge(self::PALABRAS_ES, self::PALABRAS_EN);
                    if (in_array($tokenLimpio, $stopwords)) {
                        continue;
                    }
                }
                
                $tokensLimpios[] = $tokenLimpio;
            }
        }
        
        return $tokensLimpios;
    }
    
    /**
     * Normalizar texto completo (alias para compatibilidad con CPUProcessor)
     * 
     * @param string $texto
     * @return string
     */
    public static function normalizarTexto(string $texto): string
    {
        return self::limpiarTexto($texto);
    }
    
    /**
     * Extraer palabras clave del texto (procesamiento local)
     * 
     * @param string $texto
     * @param int $limite Cantidad máxima de palabras clave
     * @return array Array de palabras clave ordenadas por frecuencia
     */
    public static function extraerPalabrasClave(string $texto, int $limite = 10): array
    {
        $tokens = self::tokenizar($texto, true); // Remover stopwords
        
        // Contar frecuencias
        $frecuencias = array_count_values($tokens);
        
        // Ordenar por frecuencia (descendente)
        arsort($frecuencias);
        
        // Retornar top N
        return array_slice(array_keys($frecuencias), 0, $limite);
    }
}


