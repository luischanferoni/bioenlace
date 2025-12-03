<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "profesional_salud".
 *
 * @property int $id_persona Codigo de persona
 * @property int|null $id_especialidad Codigo de especialidad
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $deleted_at
 * @property int $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 */
class ProfesionalSalud extends \yii\db\ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'profesional_salud';
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
            [['id_persona', 'id_profesion'], 'required'],
            [['id_persona', 'id_especialidad', 'id_especialidad', 'created_by', 'updated_by', 'deleted_by'], 'integer'],
            [['created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['id_persona', 'id_profesion', 'id_especialidad'], 'unique', 'targetAttribute' => ['id_persona', 'id_profesion', 'id_especialidad']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_persona' => 'Persona',
            'id_profesion' => 'Profesion',
            'id_especialidad' => 'Especialidad',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'deleted_at' => 'Deleted At',
            'created_by' => 'Created By',
            'updated_by' => 'Updated By',
            'deleted_by' => 'Deleted By',
        ];
    }

    public function getEspecialidad() {
        return $this->hasOne(Especialidades::className(), ['id_especialidad' => 'id_especialidad']);
    }

    public function getProfesion() {
        return $this->hasOne(Profesiones::className(), ['id_profesion' => 'id_profesion']);
    }

    public function getPersona() {
        return $this->hasOne(Persona::className(), ['id_persona' => 'id_persona']);
    }

}
