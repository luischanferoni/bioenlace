<?php

namespace common\models;

/**
 * Recurso institucional por país / provincia (lookup runtime).
 *
 * @property int $id_recurso
 * @property int $id_pais
 * @property int|null $id_provincia null = ámbito nacional / país
 * @property string $tipo
 * @property string $nombre
 * @property string|null $direccion
 * @property string|null $telefono
 *
 * @property Pais $pais
 * @property Provincia|null $provincia
 */
class GeoRecursoInstitucional extends \yii\db\ActiveRecord
{
    public static function tableName()
    {
        return 'geo_recursos_institucionales';
    }

    public function rules()
    {
        return [
            [['id_pais', 'tipo', 'nombre'], 'required'],
            [['id_pais', 'id_provincia'], 'integer'],
            [['tipo'], 'string', 'max' => 64],
            [['nombre'], 'string', 'max' => 200],
            [['direccion'], 'string', 'max' => 255],
            [['telefono'], 'string', 'max' => 64],
        ];
    }

    public function getPais()
    {
        return $this->hasOne(Pais::class, ['id_pais' => 'id_pais']);
    }

    public function getProvincia()
    {
        return $this->hasOne(Provincia::class, ['id_provincia' => 'id_provincia']);
    }
}
