<?php

namespace common\models\Organization;

use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * Filtros de solo lectura para los checkouts de suscripción en el panel admin.
 */
class BillingCheckoutAttemptBusqueda extends BillingCheckoutAttempt
{
    public function rules()
    {
        return [
            [['id', 'id_user', 'id_persona', 'id_efector', 'id_billing_account', 'id_billing_account_ministerio'], 'integer'],
            [['checkout_key', 'status', 'sector', 'payer_email', 'provider_subscription_id', 'provider_payment_id'], 'safe'],
        ];
    }

    public function scenarios()
    {
        return Model::scenarios();
    }

    public function search($params): ActiveDataProvider
    {
        $query = BillingCheckoutAttempt::find()
            ->where(['deleted_at' => null])
            ->with(['efector']);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => ['defaultOrder' => ['created_at' => SORT_DESC, 'id' => SORT_DESC]],
            'pagination' => ['pageSize' => 40],
        ]);

        $this->load($params);
        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id' => $this->id,
            'id_user' => $this->id_user,
            'id_persona' => $this->id_persona,
            'id_efector' => $this->id_efector,
            'id_billing_account' => $this->id_billing_account,
            'id_billing_account_ministerio' => $this->id_billing_account_ministerio,
            'status' => $this->status,
            'sector' => $this->sector,
        ]);
        $query->andFilterWhere(['like', 'checkout_key', $this->checkout_key])
            ->andFilterWhere(['like', 'payer_email', $this->payer_email])
            ->andFilterWhere(['like', 'provider_subscription_id', $this->provider_subscription_id])
            ->andFilterWhere(['like', 'provider_payment_id', $this->provider_payment_id]);

        return $dataProvider;
    }
}
