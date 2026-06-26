<?php

namespace common\components\Domain\Terminology\Snomed;

use common\components\Domain\Clinical\Service\EncounterAutomaticCodingService;
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

            // Procesar codificación automática (IA) si hay encounter asociado
            if ($job->consulta_id) {
                $encounter = \common\models\Clinical\Encounter::findOne($job->consulta_id);
                if ($encounter !== null) {
                    $datos = is_array($datosExtraidos['datosExtraidos'] ?? null)
                        ? $datosExtraidos['datosExtraidos']
                        : [];
                    EncounterAutomaticCodingService::codeAndPersistForEncounter($encounter, $datos);
                }
            }

            $job->resultado = json_encode($datosExtraidos);
            $job->status = 'completed';
            $job->processed_at = date('Y-m-d H:i:s');

            if ($job->save(false)) {
                Yii::info("Trabajo SNOMED {$job->id} procesado correctamente", 'snomed-codificador');

                // Legacy: la persistencia ocurre vía EncounterAutomaticCodingService en el job o en guardar.
                if ($job->consulta_id) {
                    Yii::info("Encounter {$job->consulta_id}: codificación automática procesada en job {$job->id}", 'snomed-codificador');
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
    /**
     * @deprecated La codificación persiste vía {@see EncounterAutomaticCodingService}.
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

