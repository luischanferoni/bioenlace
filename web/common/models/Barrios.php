<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "barrios".
 *
 * @property integer $id_barrio
 * @property string $nombre
 * @property string $rural_urbano
 * @property integer $id_localidad
 */
class Barrios extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'barrios';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            // [['id_barrio'], 'required'],
            [['id_barrio', 'id_localidad'], 'integer'],
            [['rural_urbano'], 'string'],
            [['nombre'], 'string', 'max' => 60]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_barrio' => 'Id Barrio',
            'nombre' => 'Nombre',
            'rural_urbano' => 'Rural Urbano',
            'id_localidad' => 'Id Localidad',
        ];
    }

    public static function depDropBarrios($id_loc){

        $out = [];
        $brrios = Barrios::find()->select("id_barrio, nombre")->where(['id_localidad'=>$id_loc])->orderBy('nombre')->all();
        foreach ($brrios as $brrio) {                    
            $out[] =  ["id" => $brrio->id_barrio, "name" => $brrio->nombre];
        }

        return $out;
    } 

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getLocalidad()
    {
        return $this->hasOne(Localidad::className(), ['id_localidad' => 'id_localidad']);
    }   

    public static function barriosPorLocalidad($idLocalidad)
    {
        $barrios = Barrios::find()
                                ->select("id_barrio, nombre")
                                ->where(['id_localidad' => $idLocalidad])
                                ->andWhere(new yii\db\Expression('nombre != ""'))
                                ->orderBy('nombre')
                                ->groupBy('nombre')
                                ->asArray()->all();

        return $barrios;
    }    
}
