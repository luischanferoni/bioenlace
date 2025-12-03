<?php

namespace common\models\busquedas;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\ServiciosEfector;
use common\models\Efector;
use common\models\Servicio;

/**
 * ServiciosEfectorBusqueda represents the model behind the search form about `common\models\ServiciosEfector`.
 */
class ServiciosEfectorBusqueda extends ServiciosEfector
{
    public $nombreServicio;
    public $nombreEfector;
    public $deleted_at;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_servicio'], 'integer'],
            [['nombreServicio','nombreEfector', 'deleted_at'],'safe']
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
        $query = ServiciosEfector::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);
         $dataProvider->setSort([
            'attributes' => [            
                'nombreEfector' => [
                    'asc' => ['efectores.nombre' => SORT_ASC],
                    'desc' => ['efectores.nombre' => SORT_DESC],
                    'label' => 'Efector',
                    'default' => SORT_ASC
                ],
                'nombreServicio' => [
                    'asc' => ['servicios.nombre' => SORT_ASC],
                    'desc' => ['servicios.nombre' => SORT_DESC],
                    'label' => 'Servicio',
                    'default' => SORT_ASC
                ],
                
            ]
        ]);

        $this->load($params);


        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id_servicio' => $this->id_servicio,
            'id_efector' => Yii::$app->user->getIdEfector() ?  Yii::$app->user->getIdEfector() : $this->id_efector,
        ]);
        
        if ($this->nombreServicio != "") {
            // filter by servicio
            $query->joinWith(['servicio' => function ($q) {
                $q->where('servicios.nombre LIKE "%' . $this->nombreServicio . '%"');
            }]);
        }

        if ($this->nombreEfector != "") {
            $query->joinWith(['efector' => function ($q) {
                $q->where('efectores.nombre LIKE "%' . $this->nombreEfector . '%"');
            }]);
        }

        if ($this->deleted_at == "null" || is_null($this->deleted_at)) {
            $query->andWhere('servicios_efector.deleted_at IS NULL');
        } else {
            $query->andWhere('servicios_efector.deleted_at IS NOT NULL');
        }

        return $dataProvider;
    }
}
