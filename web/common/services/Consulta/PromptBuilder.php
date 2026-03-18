<?php

namespace common\services\Consulta;

use Yii;

final class PromptBuilder
{
    /**
     * @param string $texto
     * @param string $servicio
     * @param array $categorias
     * @return array|null ['prompt'=>string,'json_ejemplo'=>string]
     */
    public static function generarPromptEspecializado($texto, $servicio, $categorias)
    {
        $categoriasTexto = self::construirCategoriasTexto($categorias);
        $jsonEjemplo = self::generarJsonEjemplo($categorias);

        if ($jsonEjemplo === false) {
            return null;
        }

        $prompt = "Extrae datos en JSON. Categorías: " . $categoriasTexto . ". Sin datos: [].

IMPORTANTE: Genera un JSON completo y válido. Asegúrate de cerrar todas las llaves, corchetes y comillas.

Formato:
{\"datosExtraidos\":{\"categoria\":[\"valor\"]}}

Texto: \"" . $texto . "\"

Responde SOLO con el JSON, sin texto adicional antes o después.";

        return [
            'prompt' => $prompt,
            'json_ejemplo' => $jsonEjemplo,
        ];
    }

    /**
     * @param array $categorias
     * @return string|false
     */
    public static function generarJsonEjemplo($categorias)
    {
        $datosExtraidos = [];
        foreach ($categorias as $categoria) {
            $titulo = $categoria['titulo'];
            $datosExtraidos[$titulo] = [];
        }

        $jsonEjemplo = [
            "datosExtraidos" => $datosExtraidos,
        ];

        $jsonString = json_encode($jsonEjemplo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($jsonString === false) {
            $error = json_last_error_msg();
            Yii::error(
                'Error al generar JSON de ejemplo: ' . $error . ' - Datos: ' . print_r($jsonEjemplo, true),
                'consulta-ia'
            );
            return false;
        }

        return $jsonString;
    }

    /**
     * @param array $categorias
     * @return string
     */
    public static function construirCategoriasTexto($categorias)
    {
        $texto = '';
        foreach ($categorias as $categoria) {
            $camposRequeridos = '';
            if (!empty($categoria['campos_requeridos'])) {
                $camposRequeridos = ' con los siguientes subdatos: (' . implode(', ', $categoria['campos_requeridos']) . ')';
            }

            $texto .= "{$categoria['titulo']}{$camposRequeridos}, ";
        }

        return substr($texto, 0, -2);
    }
}

