<?php

namespace common\models\busquedas;

use common\models\ConsultaSuministroMedicamento;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * SegNivelInternacionMedicamentoBusqueda represents the model behind the search form of `common\models\SegNivelInternacionMedicamento`.
 */
class ConsultaSuministroMedicamentoBusqueda extends ConsultaSuministroMedicamento
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'cantidad', 'id_consulta'], 'integer'],
            [['conceptId', 'dosis_diaria'], 'safe'],
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
        $query = ConsultaSuministroMedicamento::find();

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
            'cantidad' => $this->cantidad,
            'id_consulta' => $this->id_consulta,
        ]);

        $query->andFilterWhere(['like', 'conceptId', $this->conceptId])
            ->andFilterWhere(['like', 'dosis_diaria', $this->dosis_diaria]);

        return $dataProvider;
    }
}
