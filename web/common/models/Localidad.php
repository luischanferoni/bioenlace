<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "localidades".
 *
 * @property integer $id_localidad
 * @property string $cod_sisa
 * @property string $cod_bahra
 * @property string $nombre
 * @property string $cod_postal
 * @property integer $id_departamento
 *
 * @property Domicilios[] $domicilios
 * @property Efectores[] $efectores
 * @property Departamentos $idDepartamento
 */
class Localidad extends \yii\db\ActiveRecord
{
   
     /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'localidades';
    }

    public $id_provincia;//ESTA PROPIEDAD FUE AGREGADA
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_localidad', 'nombre', 'cod_postal', 'id_departamento','id_provincia'], 'required'],
            //[['id_localidad', 'cod_sisa', 'cod_bahra', 'nombre', 'cod_postal', 'id_departamento'], 'required'],
            //[['id_departamento'], 'integer'],
            [['id_localidad', 'id_departamento'], 'integer'],
            //[['cod_sisa', 'cod_bahra'], 'string', 'max' => 15],
            [['nombre'], 'string', 'max' => 100],
            [['cod_postal'], 'string', 'max' => 5],
            [['cod_postal'], 'unique'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_localidad' => 'Localidad',
            //'cod_sisa' => 'Código Sisa',
            //'cod_bahra' => 'Código Bahra',
            'nombre' => 'Nombre',
            'cod_postal' => 'Código Postal',
            'id_departamento' => 'Departamento',
            'id_provincia' => 'Provincia',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDomicilios()
    {
        return $this->hasMany(Domicilios::className(), ['id_localidad' => 'id_localidad']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getEfectores()
    {
        return $this->hasMany(Efectores::className(), ['id_localidad' => 'id_localidad']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDepartamento()
    {
        return $this->hasOne(Departamento::className(), ['id_departamento' => 'id_departamento']);
    }    
   
    //Esta funcion fue agregada. Se relaciona con el modelo Departamento, para obtener el nombre
    public function getDepartamentoNombre()
    {
        return $this->departamento ? $this->departamento->nombre : '- no hay departamento -';
    }
        
    public static function getLocalidadesCercanas($idLocalidad)
    {
        $sql = 'SELECT id_localidad, nombre, X(coordenadas) as latitud, Y(coordenadas) as longitud FROM localidades WHERE id_localidad = '.$idLocalidad;        
        $localidad = Yii::$app->db->createCommand($sql)->queryOne();
        
        if ($localidad['latitud'] == NULL) {
            return [$localidad];
        }

        $sql = 'SELECT id_localidad, nombre, ST_DISTANCE(coordenadas, POINT('.$localidad['latitud'].','.$localidad['longitud'].')) AS dist                     
                    FROM localidades 
                    WHERE ST_AsText(coordenadas) IS NOT NULL 
                    ORDER BY dist ASC LIMIT 5';

        $localidades = Yii::$app->db->createCommand($sql)->queryAll();

        return $localidades;
    }
    
}
