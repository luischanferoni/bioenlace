<?php

namespace common\models;

use Yii;


/**
 * This is the model class for table "paciente_historial".
 *
 * @property integer $parent_id
 * @property string $parent_class
 * 
 */

class PacienteHistorial extends \yii\db\ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'paciente_historial';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['parent_id'], 'integer'],
            [['parent_class'], 'string'],            
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'parent_id' => 'Parent Id',
            'parent_class' => 'Parent',
        ];
    }

    public function getParent()
    {
        $parentIdAttr = 'id';
        switch ($this->parent_class) {
            case 'Consulta':
                $parentIdAttr = 'id_consultas';
                break;
            case 'Turno':
                $parentIdAttr = 'id_turnos';
                break;

            case 'SegNivelInternacion':
                $parentIdAttr = 'id';
                break;    
        }

        return $this->hasOne("\\common\\models\\".$this->parent_class, [$parentIdAttr => 'parent_id']);
    }

    public static function actualizarTipo($tipo, $class, $id)
    {
        $connection = new yii\db\Query;
        $connection->createCommand()
            ->update(self::tableName(), ['tipo' => $tipo], 'parent_class = "'.$class.'" AND parent_id = '.$id)
            ->execute();
    }

   
}
