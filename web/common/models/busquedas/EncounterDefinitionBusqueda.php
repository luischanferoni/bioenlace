<?php

namespace common\models\busquedas;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Clinical\EncounterDefinition;

class EncounterDefinitionBusqueda extends EncounterDefinition
{
    public function rules(): array
    {
        return [
            [['id', 'service_id', 'created_by', 'updated_by', 'deleted_by'], 'integer'],
            [['encounter_class', 'workflow_json', 'created_at', 'updated_at', 'deleted_at'], 'safe'],
        ];
    }

    public function scenarios(): array
    {
        return Model::scenarios();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function search(array $params): ActiveDataProvider
    {
        $query = EncounterDefinition::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id' => $this->id,
            'service_id' => $this->service_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'deleted_by' => $this->deleted_by,
        ]);

        $query->andFilterWhere(['like', 'encounter_class', $this->encounter_class])
            ->andFilterWhere(['like', 'workflow_json', $this->workflow_json]);

        return $dataProvider;
    }
}
