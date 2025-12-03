<?php

namespace common\models\busquedas;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\ConsultasRecetaLentes;

/**
 * ConsultasRecetaLentesBusqueda represents the model behind the search form of `common\models\ConsultasRecetaLentes`.
 */
class ConsultasRecetaLentesBusqueda extends ConsultasRecetaLentes
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id'], 'integer'],
            [['oi_esfera', 'od_esfera', 'oi_cilindro', 'od_cilindro', 'oi_eje', 'od_eje'], 'number'],
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
        $query = ConsultasRecetaLentes::find();

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
            'oi_esfera' => $this->oi_esfera,
            'od_esfera' => $this->od_esfera,
            'oi_cilindro' => $this->oi_cilindro,
            'od_cilindro' => $this->od_cilindro,
            'oi_eje' => $this->oi_eje,
            'od_eje' => $this->od_eje,
        ]);

        return $dataProvider;
    }
}
