<?php

namespace common\models\Clinical;

use yii\db\ActiveQuery;

class EncounterDefinitionQuery extends ActiveQuery
{
    /**
     * @param mixed $condition
     * @return mixed
     */
    private function mapLegacyKeys($condition)
    {
        if (!is_array($condition)) {
            return $condition;
        }
        if (isset($condition['id_servicio'])) {
            $condition['service_id'] = $condition['id_servicio'];
            unset($condition['id_servicio']);
        }

        return $condition;
    }

    public function where($condition, $params = [])
    {
        return parent::where($this->mapLegacyKeys($condition), $params);
    }

    public function andWhere($condition, $params = [])
    {
        return parent::andWhere($this->mapLegacyKeys($condition), $params);
    }
}
