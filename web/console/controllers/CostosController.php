<?php

namespace console\controllers;

use Yii;
use yii\console\Controller;
use common\components\ConversacionLoader;
use common\components\ConsultaIntentRouter;
use common\components\AICostTracker;

/**
 * Pruebas de costos de IA: ejecutar conversaciones sin llamar a la IA real.
 *
 * Uso:
 *   php yii costos/ejecutar-conversacion --conversacion=pre_turno/sacar_turno_completo
 */
class CostosController extends Controller
{
    /**
     * @var string Identificador de conversación (tipo/archivo sin .json)
     */
    public $conversacion;

    /**
     * {@inheritdoc}
     */
    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['conversacion']);
    }

    /**
     * {@inheritdoc}
     */
    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), ['c' => 'conversacion']);
    }

    /**
     * Ejecutar una conversación de prueba (siempre simula IA; nunca llama al proveedor).
     */
    public function actionEjecutarConversacion()
    {
        if (empty($this->conversacion)) {
            $this->stderr("Indique la conversación: --conversacion=tipo/archivo (ej. pre_turno/sacar_turno_completo)\n", \yii\helpers\Console::FG_RED);
            $this->stdout("Conversaciones disponibles:\n", \yii\helpers\Console::FG_YELLOW);
            foreach (ConversacionLoader::listar() as $item) {
                $this->stdout("  - {$item['id']}: {$item['nombre']}\n");
            }
            return self::EXIT_CODE_ERROR;
        }

        $data = ConversacionLoader::cargar($this->conversacion);
        if ($data === null) {
            $this->stderr("No se pudo cargar la conversación: {$this->conversacion}\n", \yii\helpers\Console::FG_RED);
            return self::EXIT_CODE_ERROR;
        }

        $userId = $data['userId'] ?? 'test-costos';
        $mensajes = $data['mensajes'];
        $nombre = $data['nombre'] ?? $this->conversacion;

        $this->stdout("Ejecutando: {$nombre} (" . count($mensajes) . " mensajes). Siempre simula IA (no se llama al proveedor).\n\n", \yii\helpers\Console::FG_GREEN);

        AICostTracker::iniciarEjecucionPrueba();
        AICostTracker::reset();

        $respuestas = [];
        foreach ($mensajes as $i => $mensaje) {
            $this->stdout("  [{$i}] Usuario: {$mensaje}\n");
            try {
                $result = ConsultaIntentRouter::process($mensaje, $userId, 'BOT');
                $texto = $result['response']['text'] ?? ($result['error'] ?? json_encode($result));
                $respuestas[] = ['mensaje' => $mensaje, 'respuesta' => $texto];
                $this->stdout("      Bot: " . mb_substr($texto, 0, 120) . (mb_strlen($texto) > 120 ? '...' : '') . "\n");
            } catch (\Throwable $e) {
                $this->stderr("      Error: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
                $respuestas[] = ['mensaje' => $mensaje, 'error' => $e->getMessage()];
            }
        }

        AICostTracker::finalizarEjecucionPrueba();
        $resumen = AICostTracker::getResumen();

        $this->stdout("\n--- Resumen de costos (simulación) ---\n", \yii\helpers\Console::FG_CYAN);
        $this->stdout("Evitadas por cache: {$resumen['evitada_por_cache']}\n");
        $this->stdout("Evitadas por dedup: {$resumen['evitada_por_dedup']}\n");
        $this->stdout("Evitadas por CPU: {$resumen['evitada_por_cpu']}\n");
        $this->stdout("Evitadas por validación: {$resumen['evitada_por_validacion']}\n");
        $this->stdout("Llamadas simuladas: {$resumen['llamada_simulada']}\n");
        $this->stdout("Total evitadas: {$resumen['total_evitadas']}\n");

        return self::EXIT_CODE_NORMAL;
    }
}
