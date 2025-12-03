<?php

namespace common\helpers;

/**
 * Utilidades para normalizar texto médico y palabras individuales.
 */
class TextoMedicoHelper
{
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
}


