<?php

namespace common\models;

use Yii;
use common\models\ServiciosEfector;

/**
 * This is the model class for table "efectores".
 *
 * @property integer $id_efector
 * @property string $codigo_sisa
 * @property string $nombre
 * @property string $dependencia
 * @property string $tipologia
 * @property string $domicilio
 * @property string $telefono
 * @property string $origen_financiamiento
 * @property integer $id_localidad
 * @property string $estado
 *
 * @property AgendaRrhh[] $agendaRrhhs
 * @property Localidades $idLocalidad
 * @property ServiciosEfector[] $serviciosEfectors
 * @property Servicios[] $idServicios
 * @property Turnos[] $turnos
 */
class Efector extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'efectores';
        
    }
    
    public $id_departamento;//este atributo agrego

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['codigo_sisa', 'nombre', 'dependencia', 'tipologia', 'domicilio', 'origen_financiamiento', 'id_localidad'], 'required'],
            [['id_localidad'], 'integer'],
            [['estado','grupo','formas_acceso','telefono','telefono2','telefono3','mail1',
              'mail2','mail3','dias_horario'], 'string'],
            [['codigo_sisa'], 'string', 'max' => 15],
            [['nombre', 'domicilio'], 'string', 'max' => 100],
            [['dependencia', 'origen_financiamiento'], 'string', 'max' => 40],
            [['tipologia'], 'string', 'max' => 10],
            [['telefono'], 'string', 'max' => 50],
            
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_efector' => 'Codigo del Efector',
            'codigo_sisa' => 'Código SISA',
            'nombre' => 'Nombre',
            'dependencia' => 'Dependencia',
            'tipologia' => 'Tipologia',
            'domicilio' => 'Domicilio',
            'formas_acceso' => 'Como Llegar',
            'grupo' => 'Grupo',
            'dias_horario' => 'Horarios de Atencion',
            'telefono' => 'Numero de Telefono',
            'telefono2' => 'Numero de Telefono 2',
            'telefono3' => 'Numero de Telefono 3',
            'mail1' => 'Correo Electronico',
            'mail2' => 'Correo Electronico 2',
            'mail3' => 'Correo Electronico 3',
            'origen_financiamiento' => 'Origen del financiamiento',
            'id_localidad' => 'Localidad',            
            'id_departamento'=>'Departamento',
            'estado' => 'Estado: Activo o Inactivo',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAgendaRrhhs()
    {
        return $this->hasMany(Agenda_Rrhh::className(), ['id_efector' => 'id_efector']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getLocalidad()
    {
        return $this->hasOne(Localidad::className(), ['id_localidad' => 'id_localidad']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRrHhEfectors()
    {
        return $this->hasMany(RrHh_Efector::className(), ['id_efector' => 'id_efector']);
    }
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getServiciosEfectors()
    {
        return $this->hasMany(ServiciosEfector::className(), ['id_efector' => 'id_efector']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdServicios()
    {
        return $this->hasMany(Servicio::className(), ['id_servicio' => 'id_servicio'])->viaTable('ServiciosEfector', ['id_efector' => 'id_efector']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTurnos()
    {
        return $this->hasMany(Turno::className(), ['id_efector_referencia' => 'id_efector']);
    }
    
    //Esta funcion fue agregada, se relaciona con el modelo Localidad para obtener el nombre
    public function getLocalidadNombre()
    {
        return $this->idLocalidad ? $this->idLocalidad->nombre : '- no hay localidad -';
    }
  
      /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdRrHhs()
    {
        return $this->hasMany(RrHh::className(), ['id_rr_hh' => 'id_rr_hh'])->viaTable('rr_hh_efector', ['id_efector' => 'id_efector']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUserEfectors()
    {
        return $this->hasMany(UserEfector::className(), ['id_efector' => 'id_efector']);
        
        
    }
    
    public function getEfectoresImplementados()
    {
        $efectores = self::find()->asArray()->select(['id_efector' => 'id_efector', 'nombre' => 'nombre'])
                        ->from('efectores')
                        ->where(['implementado' => 'V'])
                        ->orderBy('nombre')->all();
        return $efectores;
    }

    public static function getTodosLosEfectores()
    {
        $efectores = self::find()->asArray()->select(['id_efector' => 'id_efector', 'nombre' => 'nombre'])
                        ->from('efectores')
                        ->orderBy('nombre')->all();
        return $efectores;
    }

    public static function liveSearch($q)
    {
        $results = self::find()
                ->select(['id_efector AS id', 'nombre AS text'])
                ->where(['like', 'nombre', '%'.$q.'%', false])
                ->asArray()
                ->all();

        return $results;
    }

}
