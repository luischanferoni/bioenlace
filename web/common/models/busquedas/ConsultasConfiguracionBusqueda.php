<?php

namespace common\models\busquedas;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\ConsultasConfiguracion;

/**
 * ConsultasConfiguracionBusqueda represents the model behind the search form of `common\models\ConsultasConfiguracion`.
 */
class ConsultasConfiguracionBusqueda extends ConsultasConfiguracion
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'id_servicio', 'created_by', 'updated_by', 'deleted_by'], 'integer'],
            [['encounter_class', 'pasos_json', 'created_at', 'updated_at', 'deleted_at', 'pasos'], 'safe'],
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
        $query = ConsultasConfiguracion::find();

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
            'id_servicio' => $this->id_servicio,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'deleted_by' => $this->deleted_by,
        ]);

        $query->andFilterWhere(['like', 'encounter_class', $this->encounter_class])
            ->andFilterWhere(['like', 'pasos_json', $this->pasos_json])
            ->andFilterWhere(['like', 'pasos', $this->pasos]);

        return $dataProvider;
    }
}
