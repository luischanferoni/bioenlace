<?php

namespace common\models\search;

use common\models\User;
use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * Búsqueda de usuarios (tabla `user`).
 */
class UserSearch extends User
{
    public function rules(): array
    {
        return [
            [['id', 'superadmin', 'status', 'created_at', 'updated_at', 'email_confirmed'], 'integer'],
            [['username', 'gridRoleSearch', 'registration_ip', 'email'], 'safe'],
        ];
    }

    public function scenarios(): array
    {
        return Model::scenarios();
    }

    public function search(array $params): ActiveDataProvider
    {
        $query = User::find()->with(['roles']);

        if (!Yii::$app->user->isSuperadmin) {
            $query->andWhere(['superadmin' => 0]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => (int) Yii::$app->request->cookies->getValue('_grid_page_size', 20),
            ],
            'sort' => [
                'defaultOrder' => ['id' => SORT_DESC],
            ],
        ]);

        if (!($this->load($params) && $this->validate())) {
            return $dataProvider;
        }

        if ($this->gridRoleSearch) {
            $query->joinWith(['roles']);
        }

        $query->andFilterWhere([
            'user.id' => $this->id,
            'user.superadmin' => $this->superadmin,
            'user.status' => $this->status,
            'auth_item.name' => $this->gridRoleSearch,
            'user.registration_ip' => $this->registration_ip,
            'user.created_at' => $this->created_at,
            'user.updated_at' => $this->updated_at,
            'user.email_confirmed' => $this->email_confirmed,
        ]);

        $query->andFilterWhere(['like', 'user.username', $this->username])
            ->andFilterWhere(['like', 'user.email', $this->email]);

        return $dataProvider;
    }
}
