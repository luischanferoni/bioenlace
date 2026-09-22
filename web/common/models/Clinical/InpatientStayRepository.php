<?php

namespace common\models\Clinical;

use common\models\Organization\InfraestructuraCama;
use common\components\Domain\Clinical\CarePlan\Application\Service\CarePlanLifecycleService;
use common\components\Domain\Clinical\Inpatient\Application\Service\InpatientEncounterAuxService;
use Yii;

/*
  Logica de Negocio de procesos que involucra actualizacion de varias
  tablas relacionadas con InpatientStay.
 */
class InpatientStayRepository
{
    public static function getBalancesHidricos(InpatientStay $internacion)
    {
        return (new InpatientEncounterAuxService())->listFluidBalancesForInpatientStay($internacion);
    }

    public static function getRegimenes(InpatientStay $internacion)
    {
        return (new InpatientEncounterAuxService())->listRegimensForInpatientStay($internacion);
    }
    
    public static function doExternacion(InpatientStay $model) {
        $model->fecha_fin = date("d/m/Y");

        $transaction = \Yii::$app->db->beginTransaction();
        try {
            if (!$model->save())
                throw new Exception('Error saving InpatientStay.');

            $model_cama = InfraestructuraCama::findOne($model->id_cama);
            $model_cama->estado = 'desocupada';
            if (!$model_cama->save())
                throw new Exception('Error saving Cama.');

            $alta_defuncion_id = 6;
            if($model->id_tipo_alta == $alta_defuncion_id) {
                $model->paciente->scenario = 'scenarioregistrar';
                $model->paciente->fecha_defuncion = $model->fecha_fin;

                if (!$model->paciente->save())
                throw new Exception('Error saving paciente.');
            }

            $transaction->commit();

            try {
                (new \common\components\Domain\Clinical\CarePlan\Application\Service\CarePlanLifecycleService())
                    ->completeOnDischarge($model);
            } catch (\Throwable $e) {
                Yii::error(
                    'CarePlanLifecycle tras alta internación #' . $model->id . ': ' . $e->getMessage(),
                    __METHOD__
                );
            }
        } catch (Exception $e) {
            $transaction->rollBack();
            throw new Exception('Error en proceso de externación.');
        }
    }

    public static function doCambioCama(
            InpatientStay $internacion,
            InpatientBedStay $hcama
            ) {
        $transaction = \Yii::$app->db->beginTransaction();
        try {
            $cama_anterior = $internacion->cama;
            $cama_anterior->estado = 'desocupada';
            $cama_nueva = $hcama->cama;
            $cama_nueva->estado = 'ocupada';
            $internacion->id_cama = $cama_nueva->id;
            
            $saved_ok = false;
            if($hcama->save(false)
               && $cama_anterior->save(false)
               && $cama_nueva->save(false)
               && $internacion->save(false)) {
                $saved_ok = true;
            }
            if (!$saved_ok) {
                throw new Exception('Error en proceso de cambio de cama.');
            }
            $transaction->commit();
        } catch (Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }
    
    public static function doAgregarHistoriaCama(
            InpatientStay $internacion,
            $throw=True) {
        $hcama = new InpatientBedStay();
        $hcama->id_internacion = $internacion->id;
        $hcama->id_cama = $internacion->id_cama;
        $hcama->fecha_ingreso = date('Y-m-d H:i:s');
        $hcama->motivo = "<Ingreso a Internación>";
        if(!$hcama->save()) {
            if($throw) {
                throw Exception('Error al crear Historia Cama.');
            }
        }
    }

}