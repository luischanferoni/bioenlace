<?php

namespace common\models\busquedas;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Rrhh_efector;

/**
 * Rrhh_efectorBusqueda represents the model behind the search form of `common\models\Rrhh_efector`.
 */
class Rrhh_efectorBusqueda extends Rrhh_efector
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_rr_hh', 'id_efector', 'id_condicion_laboral', 'id_servicio'], 'integer'],
            [['horario'], 'safe'],
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
        $query = Rrhh_efector::find();

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
            'id_rr_hh' => $this->id_rr_hh,
            'id_efector' => $this->id_efector,
            'id_condicion_laboral' => $this->id_condicion_laboral,
            'id_servicio' => $this->id_servicio,
        ]);

        $query->andFilterWhere(['like', 'horario', $this->horario]);

        return $dataProvider;
    }
}
