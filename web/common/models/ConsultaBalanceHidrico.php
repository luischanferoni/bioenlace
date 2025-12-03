<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "consultas_balancehidrico".
 *
 * @property int $id
 * @property int $id_internacion
 * @property string $fecha
 * @property string $tipo_registro
 * @property int|null $cod_ingreso
 * @property int|null $cod_egreso
 * @property string|null $hora_inicio
 * @property string|null $hora_fin
 * @property int|null $cantidad
 *
 * @property SegNivelInternacion $internacion
 */
class ConsultaBalanceHidrico extends \yii\db\ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    const TREG_INGRESO = 'Ingreso';
    const TREG_EGRESO = 'Egreso';
    
    public static $tipos_cod_ingreso = [
        101 => 'SOLUCION FISIOLOGICA',
        102 => 'SOL DEXT AL 5%',
        103 => 'SOL DEXT AL 10%',
        104 => 'SOL DEXT AL 25%',
        105 => 'SOL DEXT AL 30%',
        106 => 'SOLUCION ELECTORLITICA BALANCEADA',
        107 => 'SOLUCION RINGERR',
        108 => 'INFUCOL',
        109 => 'MANITOL',
        110 => 'NUTRICION PARENTERAL',
        111 => 'ANTIBIOTICO',
        112 => 'ALBUM-INA',
        113 => 'HEMO DERIVADAS',
        114 => 'OTROS',
    ];
  
  public static $tipos_cod_egreso = [
      201 => 'DIURESIS',
      202 => 'CATARSIS',
      203 => 'COLOST. ILEOSTOMIA',
      204 => 'TAR FISTULAS',
      205 => 'DIALISIS',
      206 => 'DRENA 1',
      207 => 'DRENA 2',
      208 => 'SUDACION',
      209 => 'FIEBRE',
      210 => 'RESPIRACION',
      211 => 'PERSPIRACION',
  ];
  
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'consultas_balancehidrico';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_consulta', 'fecha', 'tipo_registro', 'cantidad'], 'required'],
            [['id_consulta', 'cod_ingreso', 'cod_egreso', 'cantidad'], 'integer'],
            [['fecha', 'hora_inicio', 'hora_fin'], 'safe'],
            [['tipo_registro'], 'string'],
            [['id_consulta'], 'exist', 
                'skipOnError' => true, 
                'targetClass' => Consulta::className(), 
                'targetAttribute' => ['id_consulta' => 'id_consulta']],
        ];
    }

    /**
     * Retorna los campos requeridos en lenguaje natural para prompts de IA
     * @return array
     */
            public function requeridosPrompt()
    {
        return [
            "['id Consulta",
            "Fecha",
            "Tipo Registro",
            "Cantidad",
        ];
    }


    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_consulta' => 'Id consulta',
            'fecha' => 'Fecha',
            'tipo_registro' => 'Tipo Registro',
            'cod_ingreso' => 'Cod Ingreso',
            'cod_egreso' => 'Cod Egreso',
            'hora_inicio' => 'Hora Inicio',
            'hora_fin' => 'Hora Fin',
            'cantidad' => 'Cantidad',
        ];
    }

    /**
     * Gets query for [[Internacion]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getConsulta()
    {
        return $this->hasOne(Consulta::className(), ['id' => 'id_consulta']);
    }
    
    public function afterFind()
    {
        $this->fecha = date_format(
            date_create_from_format('Y-m-d', $this->fecha),
            'd/m/Y');
        return parent::afterFind();
    }
    
    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $dirty = $this->getDirtyAttributes(['fecha']);
        if(! empty($dirty)) {
            $this->fecha = date_format(
                date_create_from_format('d/m/Y', $this->fecha),
                'Y-m-d');
        }
        return true;
    }
    
    public function getCodigoRegistroDescription() {
        $value = self::$tipos_cod_ingreso[$this->cod_ingreso];
        if($this->tipo_registro == 'Egreso') {
            $value = self::$tipos_cod_egreso[$this->cod_egreso];
        }
        return $value;
    }

    /**
     * Mientras la consulta no este finalizada (nueva o editando) el usuario
     * puede hacer un hard delete
     */
    public static function hardDeleteGrupo($id_consulta, $ids)
    {
        if (count($ids) > 0 && isset($id_consulta) && $id_consulta != "" && $id_consulta != 0) {
            self::hardDeleteAll([
                'AND',
                ['in', 'id', $ids],
                ['=', 'id_consulta', $id_consulta]
            ]);
        }
    }    
}