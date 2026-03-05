<?php

namespace common\models\busquedas;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\SensibilidadMapeoSnomed;

/**
 * Búsqueda para mapeos SNOMED → categoría de sensibilidad.
 */
class SensibilidadMapeoSnomedBusqueda extends SensibilidadMapeoSnomed
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'id_categoria'], 'integer'],
            [['tabla_snomed', 'codigo'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        return Model::scenarios();
    }

    /**
     * @param array $params
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = SensibilidadMapeoSnomed::find()->with('categoria');

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => ['tabla_snomed' => SORT_ASC, 'codigo' => SORT_ASC],
            ],
            'pagination' => ['pageSize' => 20],
        ]);

        $this->load($params);
        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id' => $this->id,
            'id_categoria' => $this->id_categoria,
        ]);
        $query->andFilterWhere(['like', 'tabla_snomed', $this->tabla_snomed]);
        $query->andFilterWhere(['like', 'codigo', $this->codigo]);

        return $dataProvider;
    }
}
