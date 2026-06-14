<?php

namespace common\models;

use common\models\Clinical\Encounter;
use Yii;
use common\models\Terminology\Snomed\SnomedMedicamentos;


/**
 * This is the model class for table "seg_nivel_internacion_medicamento".
 *
 * @property int $id
 * @property string|null $id_internacion_medicamento
 * @property string|null $id_internacion
 * @property string|null $fecha
 * @property string|null $hora
 * @property string|null $observaciones
 */
class ConsultaSuministroMedicamento extends \yii\db\ActiveRecord
{
    use \common\traits\LegacyConsultaIdAsEncounterFkTrait;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'consultas_suministro_medicamento';
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        if ($insert
            || $this->isAttributeChanged('id_consulta', false)
        ) {
            $this->syncProfesionalEfectorServicioFromContext();
        }
        return true;
    }

    public function syncProfesionalEfectorServicioFromContext(): void
    {
        if ($this->id_consulta) {
            $encounter = Encounter::findOne((int) $this->id_consulta);
            if ($encounter && (int) $encounter->id_profesional_efector_servicio > 0) {
                $this->id_profesional_efector_servicio = (int) $encounter->id_profesional_efector_servicio;
                return;
            }
        }
        $this->id_profesional_efector_servicio = null;
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            //[['id_internacion_medicamento'], 'required'],
            [['id', 'id_internacion_medicamento', 'id_consulta', 'id_profesional_efector_servicio'], 'integer'],
            [['fecha', 'hora'], 'required'],
            [['observacion'], 'safe'],
            [['id'], 'unique'],
        ];
    }

    /**
     * Retorna los campos requeridos en lenguaje natural para prompts de IA
     * @return array
     */
    public function requeridosPrompt()
    {
        return [
            "Fecha",
            "Hora",
        ];
    }


    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'fecha' => 'Fecha',
            'Hora' => 'Hora',
            'observacion' => 'Observación',
        ];
    }

    /**
     * Persona del profesional vía PES asignado al suministro.
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPersonaProfesional()
    {
        return $this->hasOne(Persona::className(), ['id_persona' => 'id_persona'])
            ->viaTable(ProfesionalEfectorServicio::tableName(), ['id' => 'id_profesional_efector_servicio']);
    }

        /**
     * Gets query for [[InternacionMedicamento]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getInternacionMedicamento()
    {
        return $this->hasOne(SegNivelInternacionMedicamento::className(), ['id' => 'id_internacion_medicamento']);
    }

    public function getProfesionalEfectorServicio()
    {
        return $this->hasOne(ProfesionalEfectorServicio::className(), ['id' => 'id_profesional_efector_servicio']);
    }
    
}
