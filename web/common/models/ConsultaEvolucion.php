<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "consultas_evolucion".
 *
 * @property int $id
 * @property int $id_consulta
 * @property int $id_persona
 * @property string $evolucion
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $deleted_at
 * @property int $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 */
class ConsultaEvolucion extends \yii\db\ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'consultas_evolucion';
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
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_consulta', 'evolucion'], 'required'],
            [['id', 'id_consulta', 'created_by', 'updated_by', 'deleted_by'], 'integer'],
            [['evolucion'], 'string'],
            [['created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['id'], 'unique'],
        ];
    }

    /**
     * Retorna los campos requeridos en lenguaje natural para prompts de IA
     * @return array
     */
    public function requeridosPrompt()
    {
        return [];
    }


    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_consulta' => 'Id Consulta',
            'id_persona' => 'Paciente',
            'evolucion' => 'Evolucion',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'deleted_at' => 'Deleted At',
            'created_by' => 'Created By',
            'updated_by' => 'Updated By',
            'deleted_by' => 'Deleted By',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getConsulta()
    {
        return $this->hasOne(Consulta::className(), ['id_consulta' => 'id_consulta']);
    }
}
