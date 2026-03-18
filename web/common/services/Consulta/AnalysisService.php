<?php

namespace common\services\Consulta;

use Yii;
use common\components\Chatbot\RespuestaPredefinidaManager;

final class AnalysisService
{
    /**
     * Replica la lógica de análisis de `ConsultaController`, pero como servicio reutilizable.
     *
     * @param string $texto
     * @param string $servicio
     * @param array $categorias
     * @return array
     */
    public static function analizarConsultaConIA($texto, $servicio, $categorias)
    {
        try {
            $similitudMinima = Yii::$app->params['similitud_minima_respuestas'] ?? 0.85;
            $respuestaPredefinida = RespuestaPredefinidaManager::obtenerRespuesta($texto, $servicio, $similitudMinima);

            if ($respuestaPredefinida) {
                Yii::info("Respuesta predefinida encontrada para consulta similar (sin GPU)", 'consulta-ia');
                RespuestaPredefinidaManager::incrementarUsos($respuestaPredefinida['id']);
                return $respuestaPredefinida['respuesta_json'];
            }

            $promptData = PromptBuilder::generarPromptEspecializado($texto, $servicio, $categorias);
            if ($promptData === null) {
                Yii::error('No se pudo generar el prompt debido a errores en el JSON de ejemplo', 'consulta-ia');
                return self::errorConfiguracion();
            }

            $resultado = self::intentarAnalisisConIA($promptData['prompt']);

            if ($resultado && !isset($resultado['error'])) {
                try {
                    RespuestaPredefinidaManager::guardarRespuesta($texto, $resultado, $servicio);
                } catch (\Exception $e) {
                    Yii::warning("No se pudo guardar respuesta predefinida: " . $e->getMessage(), 'respuestas-predefinidas');
                }
                return $resultado;
            }

            return self::errorIA();
        } catch (\Exception $e) {
            Yii::error("Error en analizarConsultaConIA (service): " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'consulta-ia');
            return self::errorSistema();
        }
    }

    /**
     * @param string $prompt
     * @return array|null
     */
    public static function intentarAnalisisConIA($prompt)
    {
        return Yii::$app->iamanager->consultar($prompt, 'analisis-consulta', 'analysis');
    }

    private static function errorConfiguracion()
    {
        return [
            'datosExtraidos' => [
                'Error' => [
                    'texto' => 'Error en la configuración del sistema. Por favor, contacte al administrador.',
                    'detalle' => 'No se pudo procesar la consulta debido a un error en la configuración.',
                    'tipo' => 'error_configuracion',
                ],
            ],
        ];
    }

    private static function errorIA()
    {
        return [
            'datosExtraidos' => [
                'Error' => [
                    'texto' => 'No se pudo procesar la consulta con inteligencia artificial en este momento.',
                    'detalle' => 'Por favor, intente nuevamente en unos momentos o revise la consulta manualmente.',
                    'tipo' => 'error_ia',
                ],
            ],
        ];
    }

    private static function errorSistema()
    {
        return [
            'datosExtraidos' => [
                'Error' => [
                    'texto' => 'Ocurrió un error al procesar la consulta.',
                    'detalle' => 'Por favor, intente nuevamente. Si el problema persiste, contacte al soporte técnico.',
                    'tipo' => 'error_sistema',
                ],
            ],
        ];
    }
}

