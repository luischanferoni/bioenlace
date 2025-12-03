<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "covid_entrevista_telefonica".
 *
 * @property int $id
 * @property string|null $id_persona
 * @property int|null $convivientes
 * @property string|null $convivientes_datos
 * @property string|null $resultado
 * @property string|null $telefono_contacto
 * @property int|null $vacunado
 * @property string|null $fecha_primera_dosis
 * @property string|null $fecha_segunda_dosis
 * @property int|null $factores_riesgo
 * @property int|null $continua_sintomas
 * @property int|null $falta_aire
 * @property int|null $falta_aire_reposo
 * @property int|null $falta_aire_caminar
 * @property int|null $dolor_pecho
 * @property int|null $taquicardia_palpitaciones
 * @property int|null $perdida_memoria
 * @property int|null $cefalea_dolor_cabeza
 * @property int|null $falta_fuerza
 * @property int|null $dolor_muscular
 * @property int|null $secrecion_rinitis_constante
 * @property int|null $llanto_espontaneo
 * @property int|null $cuesta_salir_casa
 * @property int|null $tristeza_angustia
 * @property int|null $dificultad_realizar_tareas
 *
 * @property CovidFactoresRiesgo[] $covidFactoresRiesgos
 * @property CovidInvestigacionEpidemiologica[] $covidInvestigacionEpidemiologicas
 */
class CovidEntrevistaTelefonica extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'covid_entrevista_telefonica';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'convivientes', 'vacunado', 'factores_riesgo','continua_sintomas', 'falta_aire', 'falta_aire_reposo', 'falta_aire_caminar', 'dolor_pecho', 'taquicardia_palpitaciones', 'perdida_memoria', 'cefalea_dolor_cabeza', 'falta_fuerza', 'dolor_muscular', 'secrecion_rinitis_constante', 'llanto_espontaneo', 'cuesta_salir_casa', 'tristeza_angustia', 'dificultad_realizar_tareas'], 'integer'],
            [[ 'convivientes', 'resultado', 'vacunado', 'factores_riesgo','continua_sintomas', 'falta_aire', 'falta_aire_reposo', 'falta_aire_caminar', 'dolor_pecho', 'taquicardia_palpitaciones', 'perdida_memoria', 'cefalea_dolor_cabeza', 'falta_fuerza', 'dolor_muscular', 'secrecion_rinitis_constante', 'llanto_espontaneo', 'cuesta_salir_casa', 'tristeza_angustia', 'dificultad_realizar_tareas', 'create_efector'], 'required'],
            [['convivientes_datos', 'resultado','ocupacion'], 'string'],
            [['fecha_primera_dosis', 'fecha_segunda_dosis'], 'safe'],
           // ['fecha_primera_dosis', 'date', 'timestampAttribute' => 'fecha_primera_dosis'],
            //['fecha_segunda_dosis', 'date', 'timestampAttribute' => 'fecha_segunda_dosis'],
            [['id_persona'], 'string', 'max' => 45],
            [['telefono_contacto'], 'string', 'max' => 15],
            [['id'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_persona' => 'Id Persona',
            'convivientes' => 'Convivientes',
            'convivientes_datos' => 'Datos',
            'resultado' => 'Resultado',
            'telefono_contacto' => 'Telefono Contacto',
            'vacunado' => 'Vacunado',
            'fecha_primera_dosis' => 'Fecha Primera Dosis',
            'fecha_segunda_dosis' => 'Fecha Segunda Dosis',
            'continua_sintomas' => '¿Continúa con Síntomas?',
            'falta_aire' => '¿Le Falta el Aire?',
            'falta_aire_reposo' => '¿En Reposo?',
            'falta_aire_caminar' => '¿Al Caminar?',
            'dolor_pecho' => '¿Le duele el pecho?',
            'taquicardia_palpitaciones' => '¿Tiene Taquicardia o Palpitaciones?',
            'perdida_memoria' => '¿Tiene Pérdida de Memoria?',
            'cefalea_dolor_cabeza' => '¿Cefalea / Dolor Cabeza?',
            'falta_fuerza' => '¿Falta de Fuerza?',
            'dolor_muscular' => '¿Dolor Muscular?',
            'secrecion_rinitis_constante' => 'Secreción nasal / Rinitis Constante',
            'llanto_espontaneo' => '¿Llanto Espontaneo?',
            'cuesta_salir_casa' => '¿Le Cuesta Salir de su Casa?',
            'tristeza_angustia' => '¿Siente Tristeza o Angustia?',
            'dificultad_realizar_tareas' => '¿Es difícil tomar iniciativa para realizar tareas?',
        ];
    }

    /**
     * Gets query for [[CovidFactoresRiesgos]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCovidFactoresRiesgo()
    {
        return $this->hasOne(CovidFactoresRiesgo::className(), ['id_entrevista_telefonica' => 'id']);
    }

    /**
     * Gets query for [[CovidInvestigacionEpidemiologicas]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCovidInvestigacionEpidemiologica()
    {
        return $this->hasOne(CovidInvestigacionEpidemiologica::className(), ['id_entrevista_telefonica' => 'id']);
    }

    public function getPersona()
    {
        return $this->hasOne(Persona::className(), ['id_persona' => 'id_persona']);
    }
    public function getEfector()
    {
        return $this->hasOne(Efector::className(), ['id_efector' => 'create_efector']);
    }

    public function getPuntajeEntrevista () {
        $puntaje = 0;
        if ($this->vacunado == 0) {
            $puntaje++;
        }
        if ($this->factores_riesgo == 1) {
            $puntaje++;
        }
        if ($this->covidInvestigacionEpidemiologica->internacion == 1) {
            $puntaje++;
        }
        if ($this->covidInvestigacionEpidemiologica->requiere_oxigeno == 1) {
            $puntaje++;
        }
        if ($this->covidInvestigacionEpidemiologica->respirador == 1) {
            $puntaje++;
        }
        if ($this->covidInvestigacionEpidemiologica->internacion_uti == 1) {
            $puntaje++;
        }
        if ($this->covidInvestigacionEpidemiologica->medicamentos == 1) {
            $puntaje++;
        }
        if ($this->continua_sintomas == 1) {
            $puntaje++;
        }
        if ($this->falta_aire == 1) {
            $puntaje++;
        }
        if ($this->falta_aire_reposo == 1) {
            $puntaje++;
        }
        if ($this->falta_aire_caminar == 1) {
            $puntaje++;
        }
        if ($this->dolor_pecho == 1) {
            $puntaje++;
        }
        if ($this->taquicardia_palpitaciones == 1) {
            $puntaje++;
        }
        if ($this->perdida_memoria == 1) {
            $puntaje++;
        }
        if ($this->cefalea_dolor_cabeza == 1) {
            $puntaje++;
        }
        if ($this->falta_fuerza == 1) {
            $puntaje++;
        }
        if ($this->dolor_muscular == 1) {
            $puntaje++;
        }
        if ($this->secrecion_rinitis_constante == 1) {
            $puntaje++;
        }
        if ($this->llanto_espontaneo == 1) {
            $puntaje++;
        }
        if ($this->cuesta_salir_casa == 1) {
            $puntaje++;
        }
        if ($this->tristeza_angustia == 1) {
            $puntaje++;
        }
        if ($this->dificultad_realizar_tareas == 1) {
            $puntaje++;
        }
        switch (true) {
            case ($puntaje == 23):
                return ["Puntaje: $puntaje --Riesgo alto, consulta inmediata.",'danger'];
                break;
            case ($puntaje >= 15 && $puntaje <= 22):
                return ["Puntaje: $puntaje -- Riesgo alto, consulta hasta 72hs",'danger'];
                break;
            case ($puntaje >= 10 && $puntaje <= 14):
                return ["Puntaje: $puntaje -- Riesgo moderado, consulta a los 14 días",'warning'];
                break;
            case ($puntaje < 10):
                return ["Puntaje: $puntaje -- Riesgo bajo, consulta a los 30 días",'success'];
                break;
            default:
                // code...
                break;
        }
       
    }
}
