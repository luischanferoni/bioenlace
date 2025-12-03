<?php

namespace common\models;

use Yii;

/*
  Logica de Negocio de procesos que involucra actualizacion de varias
  tablas relacionadas con SegNivelInternacion.
 */
class SegNivelInternacionRepository
{
    public static function getBalancesHidricos(SegNivelInternacion $internacion)
    {
        $query = ConsultaBalanceHidrico::find()
            ->alias('bh')
            ->leftJoin(
                'consultas',
                'consultas.id_consulta = bh.id_consulta')
            ->where(
                'consultas.parent_class = "\\\common\\\models\\\SegNivelInternacion"')
            ->andWhere(
                'consultas.parent_id = :internacion_id')
            ->orderBy('bh.fecha')
            ->addOrderBy('bh.hora_inicio')
            ->addParams(
                [':internacion_id' => $internacion->id]
            );
        return $query->all();
    }
    
    public static function getRegimenes(SegNivelInternacion $internacion)
    {
        $query = ConsultaRegimen::find()
            ->alias('r')
            ->addSelect([
                '*', 
                'DATE_FORMAT(consultas.created_at, "%d/%m/%Y %H:%i") as consulta_fecha'
                ])
            ->leftJoin(
                'consultas',
                'consultas.id_consulta = r.id_consulta')
            ->where(
                'consultas.parent_class = "\\\common\\\models\\\SegNivelInternacion"')
            ->andWhere(
                'consultas.parent_id = :internacion_id')
            ->addParams(
                [':internacion_id' => $internacion->id]
            );
        return $query->all();
    }
    
    public static function doExternacion(SegNivelInternacion $model) {
        $model->fecha_fin = date("d/m/Y");

        $transaction = \Yii::$app->db->beginTransaction();
        try {
            if (!$model->save())
                throw new Exception('Error saving SegNivelInternacion.');

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
        } catch (Exception $e) {
            $transaction->rollBack();
            throw new Exception('Error en proceso de externación.');
        }
    }

    public static function doCambioCama(
            SegNivelInternacion $internacion,
            SegNivelInternacionHcama $hcama
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
            SegNivelInternacion $internacion,
            $throw=True) {
        $hcama = new SegNivelInternacionHcama();
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