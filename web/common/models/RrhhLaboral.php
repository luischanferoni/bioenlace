<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "rrhh_laboral".
 *
 * @property int $id_rr_hh
 * @property int $id_condicion_laboral 
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $deleted_at
 * @property int $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 */
class RrhhLaboral extends \yii\db\ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'rrhh_laboral';
    }
    
    public function behaviors()
    {
        return [
            'blames' => [
                'class' => 'yii\behaviors\AttributeBehavior',
                'attributes' => [
                    \yii\db\ActiveRecord::EVENT_BEFORE_INSERT => ['created_by'],
                    \yii\db\ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_by'],
                ],
                'value' => Yii::$app->user->id,
            ],
            [
                'class' => 'yii\behaviors\AttributesBehavior',
                'attributes' => [
                    'fecha_inicio' => [
                        \yii\db\ActiveRecord::EVENT_BEFORE_INSERT => [$this, 'formatearFechaMysql'],
                        \yii\db\ActiveRecord::EVENT_BEFORE_UPDATE => [$this, 'formatearFechaMysql'],
                    ],
                    'fecha_fin' => [
                        \yii\db\ActiveRecord::EVENT_BEFORE_INSERT => [$this, 'formatearFechaMysql'],
                        \yii\db\ActiveRecord::EVENT_BEFORE_UPDATE => [$this, 'formatearFechaMysql'],
                    ],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_condicion_laboral', 'id_rr_hh', 'fecha_inicio'], 'required'],
            [['id_rr_hh', 'id_rrhh_efector', 'id_condicion_laboral', 'created_by', 'updated_by', 'deleted_by'], 'integer'],
            //[['fecha_fin'], 'default', 'value' => null],
           // [['fecha_inicio', 'fecha_fin'], 'date'],
            
            //['fecha_fin', 'validarFechaMayorQue', 'skipOnEmpty' => true],
            [['created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['id_rr_hh', 'id_condicion_laboral'], 'unique', 'targetAttribute' => ['id_rr_hh', 'id_condicion_laboral']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_rr_hh' => 'Id RRHH',
            'id_rrhh_efector' => 'Id RRHH',
            'id_condicion_laboral' => 'Condición laboral',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'deleted_at' => 'Deleted At',
            'created_by' => 'Created By',
            'updated_by' => 'Updated By',
            'deleted_by' => 'Deleted By',
        ];
    }

    /**
     * Gets query for [[RrhhEfector]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRrhhEfector()
    {
        return $this->hasOne(RrhhEfector::className(), ['id_rrhh_efector' => 'id_rrhh_efector']);
    }

    /**
     * Gets query for [[Condiciones_laborales]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCondicionLaboral()
    {
        return $this->hasOne(Condiciones_laborales::className(), ['id_condicion_laboral' => 'id_condicion_laboral']);
    }

    public function validarFechaMayorQue()
    {
        if ($this->fecha_fin != null && $this->fecha_fin != "") {
            $fecha_fin = date_create_from_format('d/m/Y', $this->fecha_fin);
            $fecha_inicio = date_create_from_format('d/m/Y', $this->fecha_inicio);
            
            if(strtotime(date_format($fecha_fin, 'Y-m-d')) <= strtotime(date_format($fecha_inicio, 'Y-m-d'))) {
                $this->addError('fecha_fin', 'Fecha de fin debería de ser mayor a fecha de inicio');
            }
        }
    }

    public function formatearFechaMysql($event, $attribute)
    {
        if ($this->$attribute != "" && $this->$attribute != null) {
            $fecha = date_create_from_format('d/m/Y', $this->$attribute);
            return date_format($fecha, 'Y-m-d');
        }   
    }

    public function afterFind()
    {
        if (preg_match('/[1-9]/', $this->fecha_inicio)) {
            $this->fecha_inicio = Yii::$app->formatter->asDate($this->fecha_inicio, Yii::$app->formatter->dateFormat);
        }
        else {
            $this->fecha_inicio = null;
        }

        if (preg_match('/[1-9]/', $this->fecha_fin)) {
            $this->fecha_fin = Yii::$app->formatter->asDate($this->fecha_fin, Yii::$app->formatter->dateFormat);
        } 
        else {
            $this->fecha_fin = null;
        }

        parent::afterFind ();
    } 
}
