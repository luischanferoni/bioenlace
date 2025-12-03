<?php

namespace common\models\busquedas;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\SegNivelInternacionDiagnostico;

/**
 * SegNivelInternacionDiagnosticoBusqueda represents the model behind the search form of `common\models\SegNivelInternacionDiagnostico`.
 */
class SegNivelInternacionDiagnosticoBusqueda extends SegNivelInternacionDiagnostico
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'id_internacion'], 'integer'],
            [['conceptId'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = SegNivelInternacionDiagnostico::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'id' => $this->id,
            'id_internacion' => $this->id_internacion,
        ]);

        $query->andFilterWhere(['like', 'conceptId', $this->conceptId]);

        return $dataProvider;
    }
}
