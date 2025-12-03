<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "covid_factores_riesgo".
 *
 * @property int $id
 * @property int|null $asma
 * @property int|null $diabetes
 * @property int|null $dialisis
 * @property int|null $embarazo_puerperio
 * @property int|null $enfermedad_hepatica
 * @property int|null $enfermedad_neurologica
 * @property int|null $oncologico
 * @property int|null $enfermedad_renal
 * @property int|null $epoc
 * @property int|null $fumador_exfumador
 * @property int|null $enfermedad_cardiovascular
 * @property int|null $inmunosuprimido
 * @property int|null $obeso
 * @property int|null $neumonia_previa
 * @property int|null $tuberculosis
 * @property int|null $hta
 * @property int|null $otro
 * @property string|null $otro_texto
 * @property string|null $medicacion
 * @property int $id_entrevista_telefonica
 *
 * @property CovidEntrevistaTelefonica $entrevistaTelefonica
 */
class CovidFactoresRiesgo extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'covid_factores_riesgo';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [[ 'id_entrevista_telefonica'], 'required'],
            [['id', 'asma', 'diabetes', 'dialisis', 'embarazo_puerperio', 'enfermedad_hepatica', 'enfermedad_neurologica', 'oncologico', 'enfermedad_renal', 'epoc', 'fumador_exfumador', 'enfermedad_cardiovascular', 'inmunosuprimido', 'obeso', 'neumonia_previa', 'tuberculosis', 'hta', 'otro', 'id_entrevista_telefonica'], 'integer'],
            [['otro_texto', 'medicacion'], 'string', 'max' => 255],
            [['id', 'id_entrevista_telefonica'], 'unique', 'targetAttribute' => ['id', 'id_entrevista_telefonica']],
            [['id_entrevista_telefonica'], 'exist', 'skipOnError' => true, 'targetClass' => CovidEntrevistaTelefonica::className(), 'targetAttribute' => ['id_entrevista_telefonica' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'asma' => 'Asma',
            'diabetes' => 'Diabetes',
            'dialisis' => 'Diálisis',
            'embarazo_puerperio' => 'Embarazo / Puerperio',
            'enfermedad_hepatica' => 'Enfermedad Hepática',
            'enfermedad_neurologica' => 'Enfermedad Neurológica',
            'oncologico' => 'Oncológico',
            'enfermedad_renal' => 'Enfermedad Renal',
            'epoc' => 'EPOC',
            'fumador_exfumador' => 'Fumador / Ex fumador',
            'enfermedad_cardiovascular' => 'Enfermedad Cardiovascular',
            'inmunosuprimido' => 'Inmunosuprimido',
            'obeso' => 'Obeso',
            'neumonia_previa' => 'Neumonía Previa',
            'tuberculosis' => 'Tuberculosis',
            'hta' => 'HTA',
            'otro' => 'Otro',
            'otro_texto' => 'Indique otro factor de riesgo',
            'medicacion' => '¿Está medicado?',
            'id_entrevista_telefonica' => 'Id Entrevista Telefonica',
        ];
    }

    /**
     * Gets query for [[EntrevistaTelefonica]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getEntrevistaTelefonica()
    {
        return $this->hasOne(CovidEntrevistaTelefonica::className(), ['id' => 'id_entrevista_telefonica']);
    }
}
