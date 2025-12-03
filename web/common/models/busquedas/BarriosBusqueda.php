<?php

namespace common\models\busquedas;

//use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Barrios;


/**
 * BarriosBusqueda represents the model behind the search form about `common\models\Barrios`.
 */
class BarriosBusqueda extends Barrios
{
        
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_localidad'], 'integer'],
            [['rural_urbano', 'nombre'], 'safe'],
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
        $query = Barrios::find();
       
        $dataProvider = new ActiveDataProvider([
            'query' => $query
        ]);
        
        // este dataProvider fue agregado
        $dataProvider->setSort([
            'attributes' => [
                'nombre',
                'rural_urbano',
               
            ]
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            //esto se agrego
            //$query->joinWith('idDepartamento');//este join se puso dentro del if, antes estaba afuera
            return $dataProvider;
        }
        
        $query->andFilterWhere([
            'id_localidad' => $this->id_localidad,
            //'id_departamento' => $this->id_departamento,
        ]);

        $query->andFilterWhere(['like', 'nombre', $this->nombre])
              ->andFilterWhere(['like', 'rural_urbano', $this->rural_urbano]);
             
             // ->andFilterWhere(['like', 'departamentos.nombre', $this->id_departamento]);
        

        
        return $dataProvider;
    }
}
