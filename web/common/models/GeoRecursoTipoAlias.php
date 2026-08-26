<?php

namespace common\models;

/**
 * @property string $tipo
 * @property string $alias
 */
class GeoRecursoTipoAlias extends \yii\db\ActiveRecord
{
    public static function tableName()
    {
        return 'geo_recurso_tipo_aliases';
    }

    public function rules()
    {
        return [
            [['tipo', 'alias'], 'required'],
            [['tipo'], 'string', 'max' => 64],
            [['alias'], 'string', 'max' => 120],
        ];
    }
}
