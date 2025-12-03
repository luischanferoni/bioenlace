<?php

namespace common\models;

use Yii;

use yii\behaviors\AttributeBehavior;
use yii\behaviors\BlameableBehavior;

use common\validators\DependentFieldsValidator;
use DateTime;

/**
 * This is the model class for table "encuesta_parches_mamarios".
 *
 * @property int $id
 * @property int|null $id_rr_hh
 * @property int|null $id_persona
 * @property string|null $fecha_prueba
 * @property string|null $numero_serie
 * @property int|null $id_efector
 * @property string|null $antecedente_cancer_mama
 * @property string|null $antecedente_cirugia_mamaria
 * @property string|null $actualmente_amamantando
 * @property string|null $sintomas_enfermedad_mamaria
 * @property int|null $edad_primer_periodo
 * @property string|null $tiene_hijos
 * @property int|null $edad_primer_parto
 * @property string|null $paso_menospausia
 * @property int|null $edad_menospausia
 * @property string|null $terapia_remplazo_hormonal
 * @property string|null $senos_densos
 * @property string|null $biopsia_mamaria
 * @property string|null $fecha_biopsia
 * @property string|null $resultado_biopsia
 * @property string|null $antecedente_familiar_cancer_mamario_ovarico
 * @property string|null $consume_alcohol
 * @property string|null $consume_tabaco
 * @property string|null $mamografia
 * @property string|null $fecha_ultima_mamografia
 * @property string|null $prueba_adicional
 * @property string|null $prueba_adicional_tipo
 * @property float|null $a_izquierdo
 * @property float|null $a_derecho
 * @property float|null $a_diferencia
 * @property float|null $b_izquierdo
 * @property float|null $b_derecho
 * @property float|null $b_diferencia
 * @property float|null $c_izquierdo
 * @property float|null $c_derecho
 * @property float|null $c_diferencia
 * @property string|null $observaciones
 * @property string|null $resultado
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by_id
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 *
 * @property Personas $persona
 * @property RrHh $rrHh
 * @property Efectores $efector
 */


// TODO: Agregar verificacion para que numero de serie sea único

class EncuestaParchesMamarios extends \yii\db\ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;
    /**
     * @var array usar range no funcionaba al tener key comenzando en 0.
     */
    public $rango_valores_parche = [0 => 0, 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10, 11 => 11, 12 => 12, 13 => 13, 14 => 14, 15 => 15, 16 => 16, 17 => 17, 18 => 18];
    const RESULTADO_SIGNIFICATIVA = 'Significativa';
    const RESULTADO_NO_SIGNIFICATIVA = 'No Significativa';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'encuesta_parches_mamarios';
    }

    public function behaviors()
    {
        return [
            'blames' => [
                'class' => 'yii\behaviors\AttributeBehavior',
                'attributes' => [
                    \yii\db\ActiveRecord::EVENT_BEFORE_INSERT => ['created_by_id'],
                    \yii\db\ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_by_id'],
                    \yii\db\ActiveRecord::EVENT_BEFORE_DELETE => ['deleted_by_id'],
                ],
                'value' => Yii::$app->user->id,
            ],
            'fechas' => [
                'class' => 'yii\behaviors\AttributesBehavior',
                'attributes' => [
                    'fecha_prueba' => [
                        \yii\db\ActiveRecord::EVENT_AFTER_VALIDATE => function ($event, $attribute) {
                            if ($this->$attribute == "") {
                                return $this->$attribute;
                            }

                            if ($this->hasErrors($attribute)) {
                                return "";
                            }
                            $fecha = date_create_from_format('d/m/Y', $this->$attribute);
                            return date_format($fecha, 'Y-m-d');
                        }
                    ],
                    'fecha_biopsia' => [
                        \yii\db\ActiveRecord::EVENT_AFTER_VALIDATE => function ($event, $attribute) {
                            if ($this->$attribute == "") {
                                return $this->$attribute;
                            }
                            if ($this->hasErrors($attribute)) {
                                return "";
                            }
                            $fecha = date_create_from_format('d/m/Y', $this->$attribute);
                            return date_format($fecha, 'Y-m-d');
                        }
                    ],
                    'fecha_ultima_mamografia' => [
                        \yii\db\ActiveRecord::EVENT_AFTER_VALIDATE => function ($event, $attribute) {
                            if ($this->$attribute == "") {
                                return $this->$attribute;
                            }
                            if ($this->hasErrors($attribute)) {
                                return "";
                            }
                            $fecha = date_create_from_format('d/m/Y', $this->$attribute);
                            return date_format($fecha, 'Y-m-d');
                        }
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
            [[
                'numero_serie', 'fecha_prueba', 'edad_primer_periodo',
                'antecedente_cancer_mama', 'antecedente_cirugia_mamaria',
                'actualmente_amamantando', 'sintomas_enfermedad_mamaria',
                'tiene_hijos', 'paso_menospausia', 'terapia_remplazo_hormonal',
                'senos_densos', 'biopsia_mamaria', 'antecedente_familiar_cancer_mamario_ovarico',
                'consume_alcohol', 'consume_tabaco', 'terapia_remplazo_hormonal', 'resultado', 'resultado_indicado', 'id_operador', 'prueba_adicional'
            ], 'required'],
            [['paso_menospausia'], 'safe'],
            [['id_operador', 'id_rr_hh', 'id_persona', 'id_efector', 'edad_primer_periodo', 'edad_primer_parto', 'edad_menospausia'], 'integer'],
            ['edad_primer_periodo', 'in', 'range' => range(7, 19)],
            ['edad_primer_parto', 'in', 'range' => range(9, 60)],
            ['edad_primer_parto', 'required', 'when' => function ($model) {
                if ($model->tiene_hijos == 'SI') {
                    return true;
                }
                return false;
            }, 'whenClient' => "function (attribute, value) {
                    var radioVal = $('input[name=\'EncuestaParchesMamarios[tiene_hijos]\']:checked').val();
                    if (radioVal == 'SI') {
                        return true;
                    }
                    return false;                
            }"],

            ['edad_menospausia', 'in', 'range' => range(20, 60)],
            ['edad_menospausia', 'required', 'when' => function ($model) {
                if ($model->paso_menospausia == 'SI') {
                    return true;
                }
                return false;
            }, 'whenClient' => "function (attribute, value) {
                    var radioVal = $('input[name=\'EncuestaParchesMamarios[paso_menospausia]\']:checked').val();
                    if (radioVal == 'SI') {
                        return true;
                    }
                    return false;                
            }"],

            ['fecha_prueba', 'date', 'min' => strtotime(date("Y-m-d") . ' - 12 months'), 'tooSmall' => 'Solamente hasta 12 meses anteriores a la fecha actual', 'max' => time(), 'tooBig' => 'Fecha futura no esta permitida'],
            ['fecha_biopsia', 'date', 'min' => strtotime(date("Y-m-d") . ' - 20 years'), 'tooSmall' => 'Solamente hasta 20 años anteriores a la fecha actual', 'max' => time(), 'tooBig' => 'Fecha futura no esta permitida'],
            ['fecha_biopsia', 'required', 'when' => function ($model) {
                if ($model->biopsia_mamaria == 'SI') {
                    return true;
                }
                return false;
            }, 'whenClient' => "function (attribute, value) {
                    var radioVal = $('input[name=\'EncuestaParchesMamarios[biopsia_mamaria]\']:checked').val();
                    if (radioVal == 'SI') {
                        return true;
                    }
                    return false;
            }"],
            ['fecha_ultima_mamografia', 'date', 'min' => strtotime(date("Y-m-d") . ' - 20 years'), 'tooSmall' => 'Solamente hasta 20 años anteriores a la fecha actual', 'max' => time(), 'tooBig' => 'Fecha futura no esta permitida'],
            ['fecha_ultima_mamografia', 'required', 'when' => function ($model) {
                if ($model->mamografia == 'SI') {
                    return true;
                }
                return false;
            }, 'whenClient' => "function (attribute, value) {
                    var radioVal = $('input[name=\'EncuestaParchesMamarios[mamografia]\']:checked').val();
                    if (radioVal == 'SI') {
                        return true;
                    }
                    return false;
            }"],
            [['antecedente_cancer_mama', 'antecedente_cirugia_mamaria', 'actualmente_amamantando', 'sintomas_enfermedad_mamaria', 'tiene_hijos', 'paso_menospausia', 'terapia_remplazo_hormonal', 'senos_densos', 'biopsia_mamaria', 'resultado_biopsia', 'antecedente_familiar_cancer_mamario_ovarico', 'consume_alcohol', 'consume_tabaco', 'mamografia', 'prueba_adicional', 'prueba_adicional_tipo', 'observaciones', 'resultado'], 'string'],
            [['tiene_hijos'], DependentFieldsValidator::className(), 'compareAttribute' => 'actualmente_amamantando', 'triggerValue' => '"SI"', 'forcedValue' => '"SI"'],
            [['a_izquierdo', 'a_derecho', 'b_izquierdo', 'b_derecho', 'c_izquierdo', 'c_derecho'], 'in', 'range' => $this->rango_valores_parche],
            [['a_diferencia', 'b_diferencia', 'c_diferencia'], 'in', 'range' => range(0, 17)],
            [['numero_serie'], 'string', 'max' => 15],
            [['id_persona'], 'exist', 'skipOnError' => true, 'targetClass' => Persona::className(), 'targetAttribute' => ['id_persona' => 'id_persona']],
            [['id_rr_hh'], 'exist', 'skipOnError' => true, 'targetClass' => RrhhEfector::className(), 'targetAttribute' => ['id_rr_hh' => 'id_rr_hh']],
            [['id_efector'], 'exist', 'skipOnError' => true, 'targetClass' => Efector::className(), 'targetAttribute' => ['id_efector' => 'id_efector']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_operador' => 'Operador',
            'id_persona' => 'Persona',
            'fecha_prueba' => 'Fecha Prueba',
            'numero_serie' => 'Numero Serie',
            'id_efector' => 'Id Efector',
            'antecedente_cancer_mama' => 'Antecedente Cancer Mama',
            'antecedente_cirugia_mamaria' => 'Antecedente Cirugia Mamaria',
            'actualmente_amamantando' => 'Actualmente Amamantando',
            'sintomas_enfermedad_mamaria' => 'Sintomas Enfermedad Mamaria',
            'edad_primer_periodo' => 'Edad Primer Periodo',
            'tiene_hijos' => 'Tiene Hijos',
            'edad_primer_parto' => 'Edad Primer Parto',
            'paso_menospausia' => 'Pasó Menospausia',
            'edad_menospausia' => 'Edad Menospausia',
            'terapia_remplazo_hormonal' => 'Terapia Remplazo Hormonal',
            'senos_densos' => 'Senos Densos',
            'biopsia_mamaria' => 'Biopsia Mamaria',
            'fecha_biopsia' => 'Fecha Biopsia',
            'resultado_biopsia' => 'Resultado Biopsia',
            'antecedente_familiar_cancer_mamario_ovarico' => 'Antecedente Cancer Mamario Ovarico',
            'consume_alcohol' => 'Consume Alcohol',
            'consume_tabaco' => 'Consume Tabaco',
            'mamografia' => 'Mamografia',
            'fecha_ultima_mamografia' => 'Fecha Ultima Mamografia',
            'prueba_adicional' => 'Prueba Adicional',
            'prueba_adicional_tipo' => 'Prueba Adicional Tipo',
            'a_izquierdo' => 'A Izquierdo',
            'a_derecho' => 'A Derecho',
            'a_diferencia' => 'A Diferencia',
            'b_izquierdo' => 'B Izquierdo',
            'b_derecho' => 'B Derecho',
            'b_diferencia' => 'B Diferencia',
            'c_izquierdo' => 'C Izquierdo',
            'c_derecho' => 'C Derecho',
            'c_diferencia' => 'C Diferencia',
            'observaciones' => 'Observaciones',
            'resultado' => 'Resultado',
            'resultado_indicado' => 'Resultado Indicado Manualmente',
            'id_rr_hh' => 'Id Rr Hh',
        ];
    }

    /**
     * Gets query for [[RrHh]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getOperador()
    {
        if ($this->id_rr_hh == NULL) {
            return $this->hasOne(RrhhEfector::className(), ['id_rr_hh_viejo' => 'id_operador']);
        } else {
            return $this->hasOne(RrhhEfector::className(), ['id_rr_hh' => 'id_operador']);
        }
    }

    /**
     * Gets query for [[Persona]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPersona()
    {
        return $this->hasOne(Persona::className(), ['id_persona' => 'id_persona']);
    }

    /**
     * Gets query for [[RrHh]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRrHh()
    {
        return $this->hasOne(RrhhEfector::className(), ['id_rr_hh' => 'id_rr_hh']);
    }

    /**
     * Gets query for [[Efector]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getEfector()
    {
        return $this->hasOne(Efector::className(), ['id_efector' => 'id_efector']);
    }

    public function getAtencionEnfermeria()
    {
        return $this
            ->hasOne(ConsultaAtencionesEnfermeria::className(), ['parent_id' => 'id'])
            ->onCondition(['parent_class' => static::className()]);
    }

    public function getPersonasAntecedentes()
    {
        return $this
            ->hasOne(PersonasAntecedente::className(), ['parent_id' => 'id'])
            ->onCondition(['parent_class' => static::className()]);
    }

    /**
     * Vincula con las clases consideradas en la condicion instanceof
     * al establecer el campo parent en dichas relaciones
     */
    public function link($name, $model, $extraColumns = [])
    {
        if ((
                $model instanceof ConsultaAtencionesEnfermeria ||
                $model instanceof PersonasAntecedente
            )
            && !$model->isRelationPopulated('parent')
        ) {
            $model->populateRelation('parent', $this);
        }

        parent::link($name, $model, $extraColumns);
    }

    public function fechaUltimaEPM(){

        //Metodo que devuelve la cantidad de dias que pasaron desde la ultima prueba de parches.

        $fecha_hoy = new DateTime(date('Y-m-d'));

        $fecha_prueba = date_create_from_format('d/m/Y', $this->fecha_prueba);
        date_format($fecha_prueba, 'Y-m-d');

        $diferencia = $fecha_hoy->diff($fecha_prueba);
        
        return $diferencia->days;
        
    }

    public function afterFind()
    {

        if (preg_match('/[1-9]/', $this->fecha_prueba)) {
            $this->fecha_prueba = Yii::$app->formatter->asDate($this->fecha_prueba, Yii::$app->formatter->dateFormat);
        } else {
            $this->fecha_prueba = null;
        }

        if (preg_match('/[1-9]/', $this->fecha_biopsia)) {
            $this->fecha_biopsia = Yii::$app->formatter->asDate($this->fecha_biopsia, Yii::$app->formatter->dateFormat);
        } else {
            $this->fecha_biopsia = null;
        }

        if (preg_match('/[1-9]/', $this->fecha_ultima_mamografia)) {
            $this->fecha_ultima_mamografia = Yii::$app->formatter->asDate($this->fecha_ultima_mamografia, Yii::$app->formatter->dateFormat);
        } else {
            $this->fecha_ultima_mamografia = null;
        }

        parent::afterFind();
    }

    public function beforeSave($insert)
    {

        parent::beforeSave($insert);

        $session = Yii::$app->getSession();
        $persona = unserialize($session['persona']);

        // Si por alguna razon se pierde la session del paciente
        if (!$persona) {
            return false;
        }

        $this->id_persona = $persona->id_persona;
        $this->id_efector = Yii::$app->user->getIdEfector();

        // Unsetting fields according to other fields values
        if ($this->paso_menospausia == 'NO') {
            $this->edad_menospausia = null;
        }

        if ($this->biopsia_mamaria == 'NO') {
            $this->fecha_biopsia = null;
            $this->resultado_biopsia = null;
        }

        if ($this->mamografia == 'NO') {
            $this->fecha_ultima_mamografia = null;
        }

        if ($this->prueba_adicional == 'NO') {
            $this->prueba_adicional_tipo = null;
        }

        // Corrijo los formatos de las fechas
        /* if ($this->fecha_prueba != null) {
            $fecha = date_create_from_format('d/m/Y', $this->fecha_prueba);                        
            $this->fecha_prueba = date_format($fecha, 'Y-m-d');                        
        }
        if ($this->fecha_biopsia != null) {
            $fecha = date_create_from_format('d/m/Y', $this->fecha_biopsia);
            $this->fecha_biopsia = date_format($fecha, 'Y-m-d');
        }
        if ($this->fecha_ultima_mamografia != null) {
            $fecha = date_create_from_format('d/m/Y', $this->fecha_ultima_mamografia);
            $this->fecha_ultima_mamografia = date_format($fecha, 'Y-m-d');
        }   */

        // hago los calculos de nuevo, desconfío de los valores calculados que puedan venir por post
        $this->a_diferencia = abs($this->a_izquierdo - $this->a_derecho);
        $this->b_diferencia = abs($this->b_izquierdo - $this->b_derecho);
        $this->c_diferencia = abs($this->c_izquierdo - $this->c_derecho);

        if ($this->a_diferencia > 3 || $this->b_diferencia > 3 || $this->c_diferencia > 3) {
            $this->resultado = self::RESULTADO_SIGNIFICATIVA;
        } else {
            $this->resultado = self::RESULTADO_NO_SIGNIFICATIVA;
        }

        return true;
    }
}
