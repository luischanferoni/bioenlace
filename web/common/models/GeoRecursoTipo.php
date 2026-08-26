<?php

namespace common\models;

/**
 * @property string $tipo
 */
class GeoRecursoTipo extends \yii\db\ActiveRecord
{
    public static function tableName()
    {
        return 'geo_recurso_tipos';
    }

    public function rules()
    {
        return [
            [['tipo'], 'required'],
            [['tipo'], 'string', 'max' => 64],
        ];
    }

    public function getAliases()
    {
        return $this->hasMany(GeoRecursoTipoAlias::class, ['tipo' => 'tipo']);
    }

    public function getRecursos()
    {
        return $this->hasMany(GeoRecursoInstitucional::class, ['tipo' => 'tipo']);
    }
}
