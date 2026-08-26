<?php

namespace common\models;

/**
 * País geográfico (ISO 3166-1 alpha-2). Fuente de verdad: tabla geo_paises.
 *
 * No declarar países concretos aquí: se agregan por seed/migración.
 *
 * @property int $id_pais
 * @property string $iso2
 * @property string $nombre
 *
 * @property Provincia[] $provincias
 */
class Pais extends \yii\db\ActiveRecord
{
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

    /**
     * @throws \RuntimeException si el país no está sembrado en geo_paises
     */
    public static function requireByIso2(string $iso2): self
    {
        $pais = self::findByIso2($iso2);
        if ($pais === null) {
            throw new \RuntimeException('País no encontrado en geo_paises (iso2=' . strtoupper(trim($iso2)) . '). Ejecutá el seed correspondiente.');
        }

        return $pais;
    }

    /**
     * País por defecto de producto ({@see params geoDefaultIso2}).
     */
    public static function defaultPais(): ?self
    {
        $iso2 = 'AR';
        if (class_exists(\Yii::class, false) && \Yii::$app !== null && \Yii::$app->has('params')) {
            $configured = \Yii::$app->params['geoDefaultIso2'] ?? null;
            if (is_string($configured) && trim($configured) !== '') {
                $iso2 = trim($configured);
            }
        }

        return self::findByIso2($iso2);
    }
}
