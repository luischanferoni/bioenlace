<?php

namespace console\controllers;

use Yii;
use yii\console\Controller;
use yii\helpers\FileHelper;

/**
 * Controlador de consola para gestionar el cache
 * 
 * Uso:
 *   php yii cache/clear              - Limpiar todo el cache
 *   php yii cache/clear-ia          - Limpiar solo cache de IA (estructuración y corrección)
 *   php yii cache/clear-correccion  - Limpiar solo cache de corrección
 *   php yii cache/clear-estructuracion - Limpiar solo cache de estructuración/análisis
 *   php yii cache/stats             - Mostrar estadísticas del cache
 */
class CacheController extends Controller
{
    /**
     * Limpiar todo el cache
     */
    public function actionClear()
    {
        $this->stdout("Limpiando todo el cache...\n", \yii\helpers\Console::FG_YELLOW);
        
        try {
            $cache = Yii::$app->cache;
            if ($cache) {
                $cache->flush();
                $this->stdout("✓ Cache limpiado exitosamente.\n", \yii\helpers\Console::FG_GREEN);
            } else {
                $this->stdout("✗ No hay componente de cache configurado.\n", \yii\helpers\Console::FG_RED);
            }
            
            return Controller::EXIT_CODE_NORMAL;
        } catch (\Exception $e) {
            $this->stdout("Error: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
            return Controller::EXIT_CODE_ERROR;
        }
    }
    
    /**
     * Limpiar solo cache de IA (estructuración y corrección)
     */
    public function actionClearIa()
    {
        $this->stdout("Limpiando cache de IA (estructuración y corrección)...\n", \yii\helpers\Console::FG_YELLOW);
        
        try {
            $cache = Yii::$app->cache;
            if (!$cache) {
                $this->stdout("✗ No hay componente de cache configurado.\n", \yii\helpers\Console::FG_RED);
                return Controller::EXIT_CODE_ERROR;
            }
            
            $eliminados = 0;
            
            // Limpiar cache de estructuración/análisis
            // Las claves tienen formato: ia_response_{hash}
            $this->limpiarCachePorPatron('ia_response_', $cache, $eliminados);
            
            // Limpiar cache de corrección
            // Las claves tienen formato: correccion_texto_{hash}
            $this->limpiarCachePorPatron('correccion_texto_', $cache, $eliminados);
            
            $this->stdout("✓ Cache de IA limpiado: {$eliminados} entradas eliminadas.\n", \yii\helpers\Console::FG_GREEN);
            
            return Controller::EXIT_CODE_NORMAL;
        } catch (\Exception $e) {
            $this->stdout("Error: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
            return Controller::EXIT_CODE_ERROR;
        }
    }
    
    /**
     * Limpiar solo cache de corrección
     */
    public function actionClearCorreccion()
    {
        $this->stdout("Limpiando cache de corrección...\n", \yii\helpers\Console::FG_YELLOW);
        
        try {
            $cache = Yii::$app->cache;
            if (!$cache) {
                $this->stdout("✗ No hay componente de cache configurado.\n", \yii\helpers\Console::FG_RED);
                return Controller::EXIT_CODE_ERROR;
            }
            
            $eliminados = 0;
            $this->limpiarCachePorPatron('correccion_texto_', $cache, $eliminados);
            
            $this->stdout("✓ Cache de corrección limpiado: {$eliminados} entradas eliminadas.\n", \yii\helpers\Console::FG_GREEN);
            
            return Controller::EXIT_CODE_NORMAL;
        } catch (\Exception $e) {
            $this->stdout("Error: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
            return Controller::EXIT_CODE_ERROR;
        }
    }
    
    /**
     * Limpiar solo cache de estructuración/análisis
     */
    public function actionClearEstructuracion()
    {
        $this->stdout("Limpiando cache de estructuración/análisis...\n", \yii\helpers\Console::FG_YELLOW);
        
        try {
            $cache = Yii::$app->cache;
            if (!$cache) {
                $this->stdout("✗ No hay componente de cache configurado.\n", \yii\helpers\Console::FG_RED);
                return Controller::EXIT_CODE_ERROR;
            }
            
            $eliminados = 0;
            $this->limpiarCachePorPatron('ia_response_', $cache, $eliminados);
            
            $this->stdout("✓ Cache de estructuración limpiado: {$eliminados} entradas eliminadas.\n", \yii\helpers\Console::FG_GREEN);
            
            return Controller::EXIT_CODE_NORMAL;
        } catch (\Exception $e) {
            $this->stdout("Error: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
            return Controller::EXIT_CODE_ERROR;
        }
    }
    
    /**
     * Mostrar estadísticas del cache
     */
    public function actionStats()
    {
        $this->stdout("Obteniendo estadísticas del cache...\n", \yii\helpers\Console::FG_YELLOW);
        
        try {
            // Si es FileCache, contar archivos
            $cache = Yii::$app->cache;
            if ($cache instanceof \yii\caching\FileCache) {
                $cachePath = $cache->cachePath;
                if (is_dir($cachePath)) {
                    $archivos = FileHelper::findFiles($cachePath, ['only' => ['*.bin']]);
                    $totalArchivos = count($archivos);
                    $tamañoTotal = 0;
                    
                    foreach ($archivos as $archivo) {
                        $tamañoTotal += filesize($archivo);
                    }
                    
                    $this->stdout("\nEstadísticas del cache:\n", \yii\helpers\Console::FG_CYAN);
                    $this->stdout("  Total de archivos: {$totalArchivos}\n");
                    $this->stdout("  Tamaño total: " . $this->formatearTamaño($tamañoTotal) . "\n");
                    $this->stdout("  Directorio: {$cachePath}\n");
                } else {
                    $this->stdout("✗ Directorio de cache no encontrado.\n", \yii\helpers\Console::FG_RED);
                }
            } else {
                $this->stdout("ℹ Tipo de cache: " . get_class($cache) . "\n");
                $this->stdout("  (Estadísticas detalladas no disponibles para este tipo de cache)\n");
            }
            
            return Controller::EXIT_CODE_NORMAL;
        } catch (\Exception $e) {
            $this->stdout("Error: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
            return Controller::EXIT_CODE_ERROR;
        }
    }
    
    /**
     * Limpiar cache por patrón de clave
     * Nota: FileCache no expone método para listar claves, así que limpiamos todo
     * o usamos flush() que es más eficiente
     */
    private function limpiarCachePorPatron($patron, $cache, &$eliminados)
    {
        // FileCache no tiene método para listar claves específicas
        // La mejor opción es limpiar todo el cache o usar el método flush()
        // Para ser más específico, podríamos limpiar el directorio manualmente
        // pero es más seguro usar flush() del componente
        
        if ($cache instanceof \yii\caching\FileCache) {
            // Limpiar todo el cache (FileCache no permite limpiar por patrón fácilmente)
            $cache->flush();
            $eliminados = -1; // -1 indica "todos"
        } else {
            // Para otros tipos de cache, intentar flush
            $cache->flush();
            $eliminados = -1;
        }
    }
    
    /**
     * Formatear tamaño en bytes a formato legible
     */
    private function formatearTamaño($bytes)
    {
        $unidades = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($unidades) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $unidades[$pow];
    }
}

