<?php

namespace console\controllers;

use Yii;
use yii\console\Controller;
use common\components\ActionDiscoveryService;
use common\components\ActionMappingService;

/**
 * Controlador de consola para descubrir y actualizar acciones del sistema
 * 
 * Uso:
 *   php yii action-discovery/update    - Actualizar catálogo de acciones
 *   php yii action-discovery/clear     - Limpiar cache de acciones
 */
class ActionDiscoveryController extends Controller
{
    /**
     * Actualizar catálogo de acciones descubiertas
     */
    public function actionUpdate()
    {
        $this->stdout("Iniciando descubrimiento de acciones...\n", \yii\helpers\Console::FG_YELLOW);

        try {
            // Invalidar cache existente
            ActionDiscoveryService::invalidateCache();
            ActionMappingService::invalidateAllCache();

            $this->stdout("Cache invalidado.\n", \yii\helpers\Console::FG_GREEN);

            // Descubrir todas las acciones
            $this->stdout("Escaneando controladores...\n", \yii\helpers\Console::FG_YELLOW);
            $actions = ActionDiscoveryService::discoverAllActions(false);

            $this->stdout("Acciones descubiertas: " . count($actions) . "\n", \yii\helpers\Console::FG_GREEN);

            // Mostrar resumen
            $controllers = [];
            foreach ($actions as $action) {
                $controller = $action['controller'];
                if (!isset($controllers[$controller])) {
                    $controllers[$controller] = 0;
                }
                $controllers[$controller]++;
            }

            $this->stdout("\nResumen por controlador:\n", \yii\helpers\Console::FG_CYAN);
            foreach ($controllers as $controller => $count) {
                $this->stdout("  - {$controller}: {$count} acciones\n");
            }

            $this->stdout("\n¡Catálogo actualizado exitosamente!\n", \yii\helpers\Console::FG_GREEN);
            return Controller::EXIT_CODE_NORMAL;

        } catch (\Exception $e) {
            $this->stdout("Error: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
            $this->stdout($e->getTraceAsString() . "\n", \yii\helpers\Console::FG_RED);
            return Controller::EXIT_CODE_ERROR;
        }
    }

    /**
     * Limpiar cache de acciones
     */
    public function actionClear()
    {
        $this->stdout("Limpiando cache de acciones...\n", \yii\helpers\Console::FG_YELLOW);

        try {
            ActionDiscoveryService::invalidateCache();
            ActionMappingService::invalidateAllCache();

            $this->stdout("Cache limpiado exitosamente.\n", \yii\helpers\Console::FG_GREEN);
            return Controller::EXIT_CODE_NORMAL;

        } catch (\Exception $e) {
            $this->stdout("Error: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
            return Controller::EXIT_CODE_ERROR;
        }
    }

    /**
     * Mostrar estadísticas de acciones
     */
    public function actionStats()
    {
        $this->stdout("Obteniendo estadísticas de acciones...\n", \yii\helpers\Console::FG_YELLOW);

        try {
            $actions = ActionDiscoveryService::discoverAllActions();

            $this->stdout("\nEstadísticas:\n", \yii\helpers\Console::FG_CYAN);
            $this->stdout("  Total de acciones: " . count($actions) . "\n");

            // Contar por controlador
            $controllers = [];
            foreach ($actions as $action) {
                $controller = $action['controller'];
                if (!isset($controllers[$controller])) {
                    $controllers[$controller] = 0;
                }
                $controllers[$controller]++;
            }

            $this->stdout("  Controladores: " . count($controllers) . "\n");
            $this->stdout("  Promedio de acciones por controlador: " . round(count($actions) / max(count($controllers), 1), 2) . "\n");

            // Top 10 controladores con más acciones
            arsort($controllers);
            $topControllers = array_slice($controllers, 0, 10, true);

            $this->stdout("\nTop 10 controladores:\n", \yii\helpers\Console::FG_CYAN);
            foreach ($topControllers as $controller => $count) {
                $this->stdout("  - {$controller}: {$count} acciones\n");
            }

            return Controller::EXIT_CODE_NORMAL;

        } catch (\Exception $e) {
            $this->stdout("Error: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
            return Controller::EXIT_CODE_ERROR;
        }
    }
}

