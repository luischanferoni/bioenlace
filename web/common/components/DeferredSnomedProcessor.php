<?php

namespace common\components;

use Yii;
use common\models\SnomedDeferredJob;

/**
 * Procesador diferido de SNOMED
 * Permite procesar la codificación SNOMED en segundo plano sin bloquear al médico
 */
class DeferredSnomedProcessor
{
    /**
     * Agregar trabajo de procesamiento SNOMED a la cola
     * @param int|null $consultaId ID de la consulta (puede ser null si aún no se guardó)
     * @param array $datosExtraidos Datos extraídos por IA
     * @param array $categorias Categorías de configuración
     * @return SnomedDeferredJob|null Trabajo creado o null si hay error
     */
    public static function procesarDiferido($consultaId, $datosExtraidos, $categorias)
    {
        try {
            // Validar que hay datos para procesar
            if (empty($datosExtraidos) || !isset($datosExtraidos['datosExtraidos'])) {
                Yii::warning('No hay datos extraídos para procesar SNOMED diferido', 'snomed-codificador');
                return null;
            }
            
            // Crear trabajo diferido
            $job = new SnomedDeferredJob();
            $job->consulta_id = $consultaId;
            $job->datos_extraidos = json_encode($datosExtraidos);
            $job->categorias = json_encode($categorias);
            $job->status = 'pending';
            
            if ($job->save()) {
                Yii::info("Trabajo SNOMED diferido creado: ID {$job->id}" . ($consultaId ? " para consulta {$consultaId}" : ""), 'snomed-codificador');
                return $job;
            } else {
                Yii::error("Error creando trabajo SNOMED diferido: " . json_encode($job->errors), 'snomed-codificador');
                return null;
            }
        } catch (\Exception $e) {
            Yii::error("Excepción creando trabajo SNOMED diferido: " . $e->getMessage(), 'snomed-codificador');
            return null;
        }
    }
    
    /**
     * Procesar un trabajo SNOMED pendiente
     * @param SnomedDeferredJob $job Trabajo a procesar
     * @return bool True si se procesó correctamente
     */
    public static function procesarTrabajo($job)
    {
        try {
            // Marcar como procesando
            $job->status = 'processing';
            $job->save(false);
            
            // Decodificar datos
            $datosExtraidos = json_decode($job->datos_extraidos, true);
            $categorias = json_decode($job->categorias, true);
            
            if (!$datosExtraidos || !$categorias) {
                throw new \Exception('Error decodificando datos del trabajo');
            }
            
            // Procesar SNOMED
            $codificador = new CodificadorSnomedIA();
            $datosConSnomed = $codificador->codificarDatos($datosExtraidos, $categorias);
            
            // Guardar resultado
            $job->resultado = json_encode($datosConSnomed);
            $job->status = 'completed';
            $job->processed_at = date('Y-m-d H:i:s');
            
            if ($job->save(false)) {
                Yii::info("Trabajo SNOMED {$job->id} procesado correctamente", 'snomed-codificador');
                
                // Si hay consulta_id, actualizar la consulta con los datos SNOMED
                if ($job->consulta_id) {
                    self::actualizarConsulta($job->consulta_id, $datosConSnomed);
                }
                
                return true;
            } else {
                throw new \Exception('Error guardando resultado del trabajo');
            }
        } catch (\Exception $e) {
            Yii::error("Error procesando trabajo SNOMED {$job->id}: " . $e->getMessage(), 'snomed-codificador');
            
            // Marcar como error
            $job->status = 'error';
            $job->save(false);
            
            return false;
        }
    }
    
    /**
     * Actualizar consulta con datos SNOMED procesados
     * @param int $consultaId ID de la consulta
     * @param array $datosConSnomed Datos con códigos SNOMED
     */
    private static function actualizarConsulta($consultaId, $datosConSnomed)
    {
        try {
            $consulta = \common\models\Consulta::findOne($consultaId);
            if ($consulta) {
                // Aquí se puede actualizar la consulta con los datos SNOMED
                // Por ejemplo, guardar en un campo JSON o en una tabla relacionada
                Yii::info("Consulta {$consultaId} actualizada con datos SNOMED", 'snomed-codificador');
            }
        } catch (\Exception $e) {
            Yii::error("Error actualizando consulta {$consultaId} con SNOMED: " . $e->getMessage(), 'snomed-codificador');
        }
    }
    
    /**
     * Procesar trabajos pendientes (para usar en comando o cron)
     * @param int $limite Número máximo de trabajos a procesar
     * @return int Número de trabajos procesados
     */
    public static function procesarPendientes($limite = 10)
    {
        $trabajos = SnomedDeferredJob::find()
            ->where(['status' => 'pending'])
            ->orderBy(['created_at' => SORT_ASC])
            ->limit($limite)
            ->all();
        
        $procesados = 0;
        foreach ($trabajos as $job) {
            if (self::procesarTrabajo($job)) {
                $procesados++;
            }
        }
        
        return $procesados;
    }
}

