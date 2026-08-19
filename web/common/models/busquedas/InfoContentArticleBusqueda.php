<?php

namespace common\models\busquedas;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\InfoContentArticle;

class InfoContentArticleBusqueda extends InfoContentArticle
{
    public function rules(): array
    {
        return [
            [['id', 'id_provincia', 'id_efector', 'priority'], 'integer'],
            [['activo'], 'boolean'],
            [['topic', 'title', 'scope', 'keywords'], 'safe'],
        ];
    }

    public function scenarios(): array
    {
        return Model::scenarios();
    }

    public function search(array $params): ActiveDataProvider
    {
        $query = InfoContentArticle::find()->orderBy(['priority' => SORT_DESC, 'topic' => SORT_ASC]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id' => $this->id,
            'scope' => $this->scope,
            'id_provincia' => $this->id_provincia,
            'id_efector' => $this->id_efector,
            'activo' => $this->activo,
        ]);

        $query->andFilterWhere(['like', 'topic', $this->topic])
            ->andFilterWhere(['like', 'title', $this->title])
            ->andFilterWhere(['like', 'keywords', $this->keywords]);

        return $dataProvider;
    }
}
