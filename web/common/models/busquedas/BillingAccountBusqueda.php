<?php

namespace common\models\busquedas;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\BillingAccount;

class BillingAccountBusqueda extends BillingAccount
{
    public function rules()
    {
        return [
            [['id', 'activo'], 'integer'],
            [['nombre', 'tipo'], 'safe'],
        ];
    }

    public function scenarios()
    {
        return Model::scenarios();
    }

    public function search($params): ActiveDataProvider
    {
        $query = BillingAccount::find()->where(['deleted_at' => null]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => ['defaultOrder' => ['nombre' => SORT_ASC]],
            'pagination' => ['pageSize' => 40],
        ]);

        $this->load($params);
        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id' => $this->id,
            'activo' => $this->activo,
            'tipo' => $this->tipo,
        ]);
        $query->andFilterWhere(['like', 'nombre', $this->nombre]);

        return $dataProvider;
    }
}
