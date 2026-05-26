<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * Plantilla de epicrisis para alta de internación.
 *
 * @property int $id
 * @property int $id_efector
 * @property int|null $id_servicio
 * @property string $nombre
 * @property string $cuerpo
 * @property bool $activo
 * @property int $orden
 * @property int $created_at
 * @property int $updated_at
 */
class InternacionEpicrisisPlantilla extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%internacion_epicrisis_plantilla}}';
    }

    public function rules(): array
    {
        return [
            [['nombre', 'cuerpo'], 'required'],
            [['id_efector', 'id_servicio', 'orden', 'created_at', 'updated_at'], 'integer'],
            [['cuerpo'], 'string'],
            [['activo'], 'boolean'],
            [['nombre'], 'string', 'max' => 120],
        ];
    }
}
