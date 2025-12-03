<?php

namespace common\models\busquedas;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\InfraestructuraPiso;

/**
 * InfraestructuraPisoBusqueda represents the model behind the search form of `common\models\InfraestructuraPiso`.
 */
class InfraestructuraPisoBusqueda extends InfraestructuraPiso
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'nro_piso', 'id_efector'], 'integer'],
            [['descripcion'], 'safe'],
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
        $id_efector = Yii::$app->user->getIdEfector();

        $query = InfraestructuraPiso::find()
        ->select('infraestructura_piso.*')
        ->where('infraestructura_piso.id_efector = :id_efector',[':id_efector' => $id_efector]);;

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
            'nro_piso' => $this->nro_piso,
        ]);

        $query->andFilterWhere(['like', 'descripcion', $this->descripcion]);

        return $dataProvider;
    }
}
