<?php

namespace common\models\busquedas;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Enviados;

/**
 * EnviadosBusqueda represents the model behind the search form about `common\models\Enviados`.
 */
class EnviadosBusqueda extends Enviados
{
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id', 'id_emisor', 'id_receptor'], 'integer'],
            [['texto','fecha'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
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
        $query = Enviados::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id' => $this->id,
           // 'id_emisor' => $this->id_emisor=3,
            'id_emisor' => $this->id_emisor=Yii::$app->user->id,
            'id_receptor' => $this->id_receptor,
            'fecha' => $this->fecha,
        ]);

        $query->andFilterWhere(['like', 'texto', $this->texto]);
          //  ->andFilterWhere(['like', 'estado', $this->estado]);

        return $dataProvider;
    }
}
