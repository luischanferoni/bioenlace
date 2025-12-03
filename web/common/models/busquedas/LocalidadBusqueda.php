<?php

namespace common\models\busquedas;

//use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Localidad;
//use common\models\Departamento;
//use common\models\Provincia;

/**
 * LocalidadBusqueda represents the model behind the search form about `common\models\Localidad`.
 */
class LocalidadBusqueda extends Localidad
{
    public $departamentoName; // propiedad agregada
    
    public $provinciaName; // propiedad agregada
    
    public $provinciaId; // propiedad agregada
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_localidad'], 'integer'],
            [['cod_sisa', 'cod_bahra', 'nombre', 'cod_postal', 'id_departamento', 'departamentoName', 'id_provincia', 'provinciaName', 'provinciaId'], 'safe'],
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
        $query = Localidad::find();
       
        $dataProvider = new ActiveDataProvider([
            'query' => $query
        ]);
        
        // este dataProvider fue agregado
        $dataProvider->setSort([
            'attributes' => [
                'nombre',
                'cod_postal',
                'departamentoName' => [
                    'asc' => ['departamentos.nombre' => SORT_ASC],
                    'desc' => ['departamentos.nombre' => SORT_DESC],
                    'label' => 'Departamento'
                ],
                'departamento.provinciaName' => [
                    'asc' => ['provincias.nombre' => SORT_ASC],
                    'desc' => ['provincias.nombre' => SORT_DESC],
                    'label' => 'Provincia'
                ]
            ]
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            //esto se agrego
            $query->joinWith('idDepartamento');//este join se puso dentro del if, antes estaba afuera
            return $dataProvider;
        }
        
        $query->andFilterWhere([
            'id_localidad' => $this->id_localidad,
            //'id_departamento' => $this->id_departamento,
        ]);

        $query->andFilterWhere(['like', 'cod_sisa', $this->cod_sisa])
              ->andFilterWhere(['like', 'cod_bahra', $this->cod_bahra])
              ->andFilterWhere(['like', 'localidades.nombre', $this->nombre])
              ->andFilterWhere(['like', 'cod_postal', $this->cod_postal]);
             // ->andFilterWhere(['like', 'departamentos.nombre', $this->id_departamento]);
        
        
        //esto se agrego, para filtrar por departamento en el listado
        $query->joinWith(['idDepartamento' => function($q) {
            $q->andFilterWhere(['like', 'departamentos.nombre', $this->departamentoName]);
        }]);
        
        
        //esto se agrego, para filtrar por provincia en el listado
        $query->joinWith(['departamento.provincia' => function($q) {
            $q->andFilterWhere([
                'provincias.id_provincia' => $this->provinciaId
            ]);
        }]);
        /*$query->joinWith(['idDepartamento.provincia' => function($q) {
            $q->andFilterWhere(['like', 'provincias.nombre', $this->provinciaName]);
        }]);*/
        
        return $dataProvider;
    }
}
