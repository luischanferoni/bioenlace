<?php

namespace common\models\file;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;

use common\models\file\DengueImport;

/**
 * DengueBusqueda represents the model behind the search form about `common\models\file\DengueImport`.
 */
class DengueBusqueda extends DengueImport
{
     public $rrhh;
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['dni'], 'required'],
            [['dni'], 'integer'],
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
        $query = DengueImport::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            $query->where('0=1');
            //return $dataProvider;
        }

        $query->andFilterWhere([
            'dni' => $this->dni,
        ]);

        return $dataProvider;
    }
}
