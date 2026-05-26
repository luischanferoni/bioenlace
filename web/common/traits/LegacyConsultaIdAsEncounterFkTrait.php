<?php

namespace common\traits;

use common\models\Clinical\Encounter;

/**
 * FK legacy `id_consulta` en tablas hijas = {@see Encounter::$id}.
 */
trait LegacyConsultaIdAsEncounterFkTrait
{
    public function getEncounter(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Encounter::class, ['id' => 'id_consulta']);
    }

    /** @deprecated use {@see getEncounter()} */
    public function getConsulta(): \yii\db\ActiveQuery
    {
        return $this->getEncounter();
    }
}
