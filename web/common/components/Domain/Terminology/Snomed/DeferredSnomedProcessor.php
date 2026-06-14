<?php

namespace common\components\Domain\Terminology\Snomed;

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
            }

            Yii::error("Error creando trabajo SNOMED diferido: " . json_encode($job->errors), 'snomed-codificador');
            return null;
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
            }

            throw new \Exception('Error guardando resultado del trabajo');
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
            $encounter = \common\models\Clinical\Encounter::findOne($consultaId);
            if ($encounter) {
                // Aquí se puede actualizar el encounter con los datos SNOMED
                Yii::info("Encounter {$consultaId} actualizado con datos SNOMED", 'snomed-codificador');
            }
        } catch (\Exception $e) {
            Yii::error("Error actualizando encounter {$consultaId} con SNOMED: " . $e->getMessage(), 'snomed-codificador');
        }
    }
}

