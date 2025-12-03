<?php

namespace common\models;

use Yii;
use common\models\Efector;

/**
 * This is the model class for table "servicios".
 *
 * @property string $id_servicio
 * @property string $nombre
 *
 * @property Referencia[] $referencias
 * @property ServiciosEfector[] $serviciosEfectors
 * @property Efectores[] $idEfectors
 * @property Turnos[] $turnos
 */
class Servicio extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'servicios';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['nombre'], 'required'],
            [['nombre'], 'string', 'max' => 40],
            [['acepta_turnos', 'acepta_practicas', 'parametros', 'item_name'], 'string']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_servicio' => 'Codigo de servicio',
            'nombre' => 'Nombre del serivicio',
            'acepta_turnos' => 'Acepta Agenda',
            'acepta_practicas' => 'Acepta Practicas',
            'item_name' => 'Rol'
        ];
    }
    
    public function getRrhhs()
    {
        return $this->hasMany(Rrhh::className(), ['id_rr_hh' => 'id_rr_hh'])
                ->viaTable('rr_hh_efector', ['id_servicio' => 'id_servicio']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getReferencias()
    {
        return $this->hasMany(Referencia::className(), ['id_servicio' => 'id_servicio']);
    }
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getServiciosEfectors()
    {
        return $this->hasMany(ServiciosEfector::className(), ['id_servicio' => 'id_servicio']);
}

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdEfectors()
    {
        return $this->hasMany(Efectores::className(), ['id_efector' => 'id_efector'])->viaTable('ServiciosEfector', ['id_servicio' => 'id_servicio']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTurnos()
    {
        return $this->hasMany(Turnos::className(), ['id_servicio' => 'id_servicio']);
    }
    
    public function getServiciosPorEfector($id) 
    {
        $servicios=Departamento::find()->asArray()
                ->select(['id' => 's.id_servicio', 'name' => 's.nombre'])
                ->from('servicios s')
                ->innerJoin('ServiciosEfector se', 's.id_servicio = se.id_servicio')
                ->where(['se.id_efector' => $id])->all();
        return $servicios;
    }

    public function getEfector()
    {
        return $this->hasMany(Efector::className(), ['id_efector' => 'id_efector'])
            ->viaTable('ServiciosEfector', ['id_servicio' => 'id_servicio']);
    }

    public static function searchServicio($q)
    {
        $results = Servicio::find()
                ->select(['id_servicio AS id', 'nombre AS text'])
                ->where(['like', 'nombre', '%'.$q.'%', false])
                ->asArray()
                ->all();

        return $results;
    }

    public static function puedeAtender($id_servicio){

        $servicio = self::find()->where(['id_servicio'=>$id_servicio])->one();

        if($servicio->item_name == 'Medico' || $servicio->item_name == 'enfermeria'){
            return true;
        }

        return false;

    }


}