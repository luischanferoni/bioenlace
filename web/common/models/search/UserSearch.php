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
    /** @var string|null */
    public $persona_apellido;

    /** @var string|null */
    public $persona_nombre;

    /** @var string|null */
    public $persona_documento;

    public function rules(): array
    {
        return [
            [['id', 'superadmin', 'status', 'created_at', 'updated_at', 'email_confirmed'], 'integer'],
            [['username', 'gridRoleSearch', 'registration_ip', 'email', 'persona_apellido', 'persona_nombre', 'persona_documento'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), [
            'persona_apellido' => 'Apellido',
            'persona_nombre' => 'Nombre',
            'persona_documento' => 'Documento',
        ]);
    }

    public function scenarios(): array
    {
        return Model::scenarios();
    }

    public function search(array $params): ActiveDataProvider
    {
        $query = User::find()->with(['roles', 'persona']);

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

        $needsPersonaJoin = $this->persona_apellido !== null && $this->persona_apellido !== ''
            || $this->persona_nombre !== null && $this->persona_nombre !== ''
            || $this->persona_documento !== null && $this->persona_documento !== '';

        if ($this->gridRoleSearch) {
            $query->joinWith(['roles']);
        }

        if ($needsPersonaJoin) {
            $query->joinWith(['persona']);
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
            ->andFilterWhere(['like', 'user.email', $this->email])
            ->andFilterWhere(['like', 'personas.apellido', $this->persona_apellido])
            ->andFilterWhere(['like', 'personas.nombre', $this->persona_nombre])
            ->andFilterWhere(['like', 'personas.documento', $this->persona_documento]);

        return $dataProvider;
    }
}
