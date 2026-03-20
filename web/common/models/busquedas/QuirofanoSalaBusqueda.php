<?php

namespace common\models\busquedas;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\QuirofanoSala;

class QuirofanoSalaBusqueda extends QuirofanoSala
{
    public function rules()
    {
        return [
            [['id', 'id_efector'], 'integer'],
            [['nombre', 'codigo'], 'safe'],
        ];
    }

    public function scenarios()
    {
        return Model::scenarios();
    }

    public function search($params)
    {
        $query = QuirofanoSala::find()->where(['deleted_at' => null]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => ['defaultOrder' => ['nombre' => SORT_ASC]],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id' => $this->id,
            'id_efector' => $this->id_efector,
        ]);
        $query->andFilterWhere(['like', 'nombre', $this->nombre]);
        $query->andFilterWhere(['like', 'codigo', $this->codigo]);

        return $dataProvider;
    }
}
