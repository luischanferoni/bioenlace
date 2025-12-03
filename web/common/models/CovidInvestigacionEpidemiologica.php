<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "covid_investigacion_epidemiologica".
 *
 * @property int $id
 * @property string|null $fecha_inicio_sintomas
 * @property string|null $fecha_notificacion_positivo
 * @property string|null $fecha_fin_aislamiento
 * @property int|null $internacion
 * @property int|null $requiere_oxigeno
 * @property int|null $respirador
 * @property int|null $internacion_uti
 * @property int|null $sintomas
 * @property int|null $fiebre
 * @property int|null $tos
 * @property int|null $diarrea_vomitos
 * @property int|null $anosmia_disgeusia
 * @property int|null $dificultad_respiratoria
 * @property int|null $malestar_general
 * @property int|null $cefalea
 * @property int|null $rinitis_secrecion_nasal
 * @property int|null $medicamentos
 * @property int|null $indicado_por_medico
 * @property int|null $indicado_equipo_seguimiento
 * @property int|null $indicado_familiar
 * @property int|null $indicado_automedicado
 * @property int|null $paracetamol
 * @property int|null $azitromicina
 * @property int|null $corticoides
 * @property int|null $aspirina
 * @property int|null $ivermectina
 * @property int|null $levofloxacina
 * @property int|null $amoxicilina_clavulanico
 * @property string|null $otro
 * @property int $id_entrevista_telefonica
 *
 * @property CovidEntrevistaTelefonica $entrevistaTelefonica
 */
class CovidInvestigacionEpidemiologica extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'covid_investigacion_epidemiologica';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_entrevista_telefonica', 'internacion', 'requiere_oxigeno', 'respirador', 'internacion_uti', 'sintomas', 'medicamentos'], 'required'],
            [['id', 'internacion', 'requiere_oxigeno', 'respirador', 'internacion_uti', 'sintomas', 'fiebre', 'tos', 'diarrea_vomitos', 'anosmia_disgeusia', 'dificultad_respiratoria', 'malestar_general', 'cefalea', 'rinitis_secrecion_nasal', 'medicamentos', 'indicado_por_medico', 'indicado_equipo_seguimiento', 'indicado_familiar', 'indicado_automedicado', 'paracetamol', 'azitromicina', 'corticoides', 'aspirina', 'ivermectina', 'levofloxacina', 'amoxicilina_clavulanico', 'id_entrevista_telefonica'], 'integer'],
            [['fecha_inicio_sintomas', 'fecha_notificacion_positivo', 'fecha_fin_aislamiento'], 'safe'],
            [['otro'], 'string', 'max' => 255],
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
            'fecha_inicio_sintomas' => 'Fecha Inicio Síntomas',
            'fecha_notificacion_positivo' => 'Fecha Notificación Positivo',
            'fecha_fin_aislamiento' => 'Fecha Fin Aislamiento',
            'internacion' => 'Internación',
            'requiere_oxigeno' => 'Requiere Oxígeno',
            'respirador' => 'Respirador',
            'internacion_uti' => 'Internación UTI',
            'sintomas' => 'Signos y síntomas durante la enfermedad',
            'fiebre' => 'Fiebre igual o mayor de 37,5°C',
            'tos' => 'Tos',
            'diarrea_vomitos' => 'Diarrea / Vómitos',
            'anosmia_disgeusia' => 'Falta de percepción de gusto/olfato',
            'dificultad_respiratoria' => 'Dificultad Respiratoria',
            'malestar_general' => 'Malestar General',
            'cefalea' => 'Cefalea / Dolor de cabeza',
            'rinitis_secrecion_nasal' => 'Rinitis / Secrecion Nasal',
            'medicamentos' => 'Medicamentos utilizados durante aislamiento/internación',
            'indicado_por_medico' => 'Medico',
            'indicado_equipo_seguimiento' => 'Equipo de Seguimiento',
            'indicado_familiar' => 'Familiar',
            'indicado_automedicado' => 'Automedicado',
            'paracetamol' => 'Paracetamol',
            'azitromicina' => 'Azitromicina',
            'corticoides' => 'Corticoides',
            'aspirina' => 'Aspirina',
            'ivermectina' => 'Ivermectina',
            'levofloxacina' => 'Levofloxacina',
            'amoxicilina_clavulanico' => 'Amoxicilina / Clavulanico',
            'otro' => 'Otro',
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
