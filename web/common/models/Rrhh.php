<?php

namespace common\models;

/**
 * Recurso humano canónico (`rr_hh`), distinto del vínculo legacy `rrhh_efector`.
 *
 * @property int $id_rr_hh
 * @property int $id_persona
 */
class Rrhh extends \yii\db\ActiveRecord
{
    public static function tableName(): string
    {
        return 'rr_hh';
    }

    public function getPersona()
    {
        return $this->hasOne(Persona::class, ['id_persona' => 'id_persona']);
    }
}
