<?php

namespace common\models;

/**
 * País geográfico (ISO 3166-1 alpha-2).
 *
 * @property int $id_pais
 * @property string $iso2
 * @property string $nombre
 *
 * @property Provincia[] $provincias
 */
class Pais extends \yii\db\ActiveRecord
{
    public const ID_ARGENTINA = 1;
    public const ID_URUGUAY = 2;
    public const ISO_AR = 'AR';
    public const ISO_UY = 'UY';

    public static function tableName()
    {
        return 'geo_paises';
    }

    public function rules()
    {
        return [
            [['iso2', 'nombre'], 'required'],
            [['iso2'], 'string', 'length' => 2],
            [['nombre'], 'string', 'max' => 80],
            [['iso2'], 'unique'],
        ];
    }

    public function getProvincias()
    {
        return $this->hasMany(Provincia::class, ['id_pais' => 'id_pais']);
    }

    public static function findByIso2(string $iso2): ?self
    {
        $iso2 = strtoupper(trim($iso2));
        if ($iso2 === '') {
            return null;
        }

        return self::findOne(['iso2' => $iso2]);
    }
}
