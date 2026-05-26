<?php

namespace common\models;

use common\models\Clinical\Encounter;
use Yii;
use common\models\snomed\SnomedProcedimientos;

/**
 * This is the model class for table "consultas_regimen".
 *
 * @property int $id
 * @property int $id_consulta
 * @property string|null $concept_id
 * @property string $indicaciones
 *
 * @property Consulta $consulta
 * @property SnomedProcedimientos $concept
 */
class ConsultaRegimen extends \yii\db\ActiveRecord
{
    use \common\traits\LegacyConsultaIdAsEncounterFkTrait;
    use \common\traits\QueryExtraDataTrait;
    use \common\traits\SoftDeleteDateTimeTrait;
    
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'consultas_regimen';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_consulta', 'indicaciones','concept_id'], 'required'],
            [['id_consulta'], 'integer'],
            [['concept_id'], 'string', 'max' => 25],
            [['indicaciones'], 'string', 'max' => 512],
            [['id_consulta'], 'exist', 'skipOnError' => true, 'targetClass' => Encounter::class, 'targetAttribute' => ['id_consulta' => 'id']],
            [['concept_id'], 'exist', 'skipOnError' => true, 'targetClass' => SnomedProcedimientos::className(), 'targetAttribute' => ['concept_id' => 'conceptId']],
        ];
    }

    /**
     * Retorna los campos requeridos en lenguaje natural para prompts de IA
     * @return array
     */
    public function requeridosPrompt()
    {
        return [
            "Indicaciones"
        ];
    }


    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_consulta' => 'Consulta ID',
            'concept_id' => 'Concept ID',
            'indicaciones' => 'Indicaciones',
        ];
    }

    /**
     * Gets query for [[Concept]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getConcept()
    {
        return $this->hasOne(SnomedProcedimientos::className(), ['conceptId' => 'concept_id']);
    }
    
    public function getConceptTerm()
    {
        return !$this->concept ? '' : $this->concept->term;
    }

    public function setQueryExtraValue(string $name, $value): void
    {
        $this->_query_extra_data[$name] = $value;
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
