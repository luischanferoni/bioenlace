<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "domicilios".
 *
 * @property string $id_domicilio
 * @property string $calle
 * @property string $numero
 * @property string $manzana
 * @property string $lote
 * @property string $sector
 * @property string $grupo
 * @property string $torre
 * @property string $depto
 * @property string $barrio
 * @property integer $id_localidad
 * @property string $latitud
 * @property string $longitud
 * @property string $urbano_rural
 * @property string $usuario_alta
 * @property string $fecha_alta
 *
 * @property Localidades $idLocalidad
 * @property PersonasDomicilios[] $personasDomicilios
 * @property Personas[] $idPersonas
 */
class Domicilio extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'domicilios';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_localidad', 'barrio'], 'required'],
            [['id_localidad'], 'integer'],
            [['entre_calle_1', 'entre_calle_2'], 'string'],
            [['fecha_alta'], 'safe'],
            [['calle', 'barrio'], 'string', 'max' => 60],
            [['numero', 'manzana', 'lote', 'torre', 'depto'], 'string', 'max' => 10],
            [['sector', 'grupo', 'latitud', 'longitud'], 'string', 'max' => 20],
            [['usuario_alta'], 'string', 'max' => 40],
            [['urbano_rural'], 'string', 'skipOnEmpty' => true],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_domicilio' => 'Id Domicilio',
            'calle' => 'Calle',
            'numero' => 'Numero',
            'manzana' => 'Manzana',
            'lote' => 'Lote',
            'sector' => 'Sector',
            'grupo' => 'Grupo',
            'torre' => 'Torre',
            'depto' => 'Depto',
            'entre_calle_1' => 'Entre calle 1', 
            'entre_calle_2' => 'Entre calle 2',
            'barrio' => 'Barrio',
            'id_localidad' => 'Localidad',
            'latitud' => 'Latitud',
            'longitud' => 'Longitud',
            'urbano_rural' => 'Zona de Residencia',
            'usuario_alta' => 'Usuario Alta',
            'fecha_alta' => 'Fecha Alta',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getLocalidad()
    {
        //return $this->hasOne(Localidades::className(), ['id_localidad' => 'id_localidad']);
        return $this->hasOne(Localidad::className(), ['id_localidad' => 'id_localidad']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getModelBarrio()
    {
        //return $this->hasOne(Localidades::className(), ['id_localidad' => 'id_localidad']);
        return $this->hasOne(Barrios::className(), ['id_barrio' => 'barrio']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPersonasDomicilios()
    {
        return $this->hasMany(PersonasDomicilios::className(), ['id_domicilio' => 'id_domicilio']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdPersonas()
    {
        return $this->hasMany(Personas::className(), ['id_persona' => 'id_persona'])
                ->viaTable('personas_domicilios', ['id_domicilio' => 'id_domicilio']);
    }
    
    public function getDomiciliosPorPersona($id_persona)
    {
        $sql = 'SELECT * FROM `domicilios` INNER JOIN `personas_domicilios` ON '
                . 'domicilios.id_domicilio = personas_domicilios.id_domicilio '
                . 'WHERE personas_domicilios.id_persona='.$id_persona;
        $domicilios = PersonaTelefono::findBySql($sql)->asArray()->all();
        return $domicilios;
    }

    public function getDomicilioCompleto()
    {
        $domicilio = '';
        if($this->calle != ''){
            $domicilio .= ' '.$this->calle . ' N° ' . $this->numero;
        }
        if($this->manzana != ''){
            $domicilio .= ' Mza '.$this->manzana . ' Lote ' . $this->lote;
        }        
        if($this->depto != ''){
            $domicilio .= 'Dpto: '.$this->depto . ' Torre: ' . $this->torre;
        }
        if($this->entre_calle_1 != ''){
            $domicilio .= ' Entre '.$this->entre_calle_1 . ' y ' . $this->entre_calle_2;
        }
        if($this->id_localidad != ''){
            $domicilio .= ', '.$this->localidad->nombre;
        }
        return $domicilio;
              
    }
    
    public function beforeSave($insert) {
        parent::beforeSave($insert);
		Yii::debug('paso 3, antes de guardar domicilio');
		/*
        $model = new Domicilio();
        $model_localidad = new Localidad();
        $model_persona = new Persona();
        $model_persona->load(Yii::$app->request->post());
        $model_localidad->load(Yii::$app->request->post());
		*/
//        extract($_GET);
        
        if ($insert) {
			
			Yii::debug('paso 4, se esta insertando');
			/*
            if($model->isNewRecord){
                    $this->usuario_alta = Yii::$app->user->userName;
                    $this->id_localidad = $this->id_localidad;                  
            }  
			*/
            $this->usuario_alta = Yii::$app->user->userName;
        }
        
        return true;
    }
    
     /*public function afterSave($insert, $changedAttributes) {
        parent::afterSave($insert, $changedAttributes);
       // print_r(extract($_GET));
        $model = new Domicilio();
        $model_persona = new Persona();
        $persona_domicilio = new Persona_domicilio();
        
         $model->load(Yii::$app->request->post());
         $model_persona->load(Yii::$app->request->post());
         $model->load(Yii::$app->request->post());
 
        if($insert){       
           $persona_domicilio->id_domicilio = $this->id_domicilio;
           //$persona_domicilio->id_persona = $persona_domicilio->id_persona;
           //$persona_domicilio->id_persona = $idp;
           $persona_domicilio->id_persona = isset($idp)? $idp:$model_persona->id_persona;
           $persona_domicilio->activo = "SI";
           $persona_domicilio->fecha_alta = date("Y-m-d");
           $persona_domicilio->usuario_alta = date("Y-m-d");
         //  var_dump($persona_domicilio);
           $persona_domicilio->save();
           // var_dump($persona_domicilio->getErrors());
       } else{
           $persona_domicilio = Persona_domicilio::findOne($this->id_domicilio);
            $persona_domicilio->load(Yii::$app->request->post());
            $persona_domicilio->activo =$persona_domicilio->activo;
            $persona_domicilio->id_domicilio = $this->id_domicilio;
            $persona_domicilio->id_persona = $idp;
            $persona_domicilio->update();
//            var_dump( $persona_domicilio->getErrors());
       }
        return true;
    }*/

}
