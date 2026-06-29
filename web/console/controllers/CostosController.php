<?php

namespace console\controllers;

use common\components\Platform\Ai\Cost\ConversacionCostosService;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Pruebas de costos de IA (conversaciones simuladas, sin HTTP a proveedores).
 *
 * @see web/docs/costos/pruebas-costos-ia.md
 */
class CostosController extends Controller
{
    /** @var string Ruta relativa sin .json (ej. pre_turno/sacar_turno_completo) */
    public $conversacion = '';

    /** @var bool Ejecutar todas las conversaciones del catálogo */
    public $todas = false;

    /** @var int ID de usuario para permisos del asistente (default: 1) */
    public $userId = 1;

    /**
     * @param string $actionID
     * @return list<string>
     */
    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['conversacion', 'todas', 'userId']);
    }

    /**
     * @param string $actionID
     * @return list<string>
     */
    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), [
            'c' => 'conversacion',
            'u' => 'userId',
        ]);
    }

    /**
     * Ejecuta una o todas las conversaciones de prueba y muestra el resumen de costos.
     */
    public function actionEjecutarConversacion(): int
    {
        if ($this->todas) {
            $batch = ConversacionCostosService::ejecutarTodas((int) $this->userId);
            $this->stdout("=== Todas las conversaciones ===\n", Console::BOLD);
            foreach ($batch['resultados'] as $resultado) {
                $this->imprimirResultado($resultado);
                $this->stdout("\n");
            }
            $this->stdout("=== Resumen agregado ===\n", Console::BOLD);
            $this->imprimirResumen($batch['resumen_agregado'], $batch['estimacion_agregada']);

            return ExitCode::OK;
        }

        if (trim($this->conversacion) === '') {
            $this->stderr("Indique --conversacion=tipo/archivo o --todas=1\n", Console::FG_RED);

            return ExitCode::USAGE;
        }

        try {
            $resultado = ConversacionCostosService::ejecutar(trim($this->conversacion), (int) $this->userId);
        } catch (\Throwable $e) {
            $this->stderr($e->getMessage() . "\n", Console::FG_RED);

            return ExitCode::DATAERR;
        }

        $this->imprimirResultado($resultado);

        return ExitCode::OK;
    }

    /**
     * @param array<string, mixed> $resultado
     */
    private function imprimirResultado(array $resultado): void
    {
        $conv = is_array($resultado['conversacion'] ?? null) ? $resultado['conversacion'] : [];
        $nombre = (string) ($conv['nombre'] ?? $resultado['ruta'] ?? 'conversación');
        $this->stdout($nombre . "\n", Console::BOLD);
        $this->imprimirResumen(
            is_array($resultado['resumen'] ?? null) ? $resultado['resumen'] : [],
            is_array($resultado['estimacion'] ?? null) ? $resultado['estimacion'] : []
        );
    }

    /**
     * @param array<string, mixed> $resumen
     * @param array<string, mixed> $estimacion
     */
    private function imprimirResumen(array $resumen, array $estimacion): void
    {
        $this->stdout(sprintf(
            "Evitadas: %d | Simuladas: %d | Reales: %d\n",
            (int) ($resumen['total_evitadas'] ?? 0),
            (int) ($resumen['llamada_simulada'] ?? 0),
            (int) ($resumen['llamada_real'] ?? 0)
        ));

        $tokens = is_array($resumen['tokens'] ?? null) ? $resumen['tokens'] : [];
        $this->stdout(sprintf(
            "Tokens prompt=%d cached=%d candidates=%d\n",
            (int) ($tokens['prompt_token_count'] ?? 0),
            (int) ($tokens['cached_content_token_count'] ?? 0),
            (int) ($tokens['candidates_token_count'] ?? 0)
        ));

        $usd = is_array($estimacion['usd'] ?? null) ? $estimacion['usd'] : [];
        $this->stdout(sprintf(
            "Estimación USD (fuente %s): $%s\n",
            (string) ($estimacion['fuente_tokens'] ?? 'n/d'),
            number_format((float) ($usd['total'] ?? 0), 6)
        ));
    }
}
