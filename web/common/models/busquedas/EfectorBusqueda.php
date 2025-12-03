<?php

namespace common\models\busquedas;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Efector;


/**
 * EfectorBusqueda represents the model behind the search form about `common\models\Efector`.
 */
class EfectorBusqueda extends Efector
{
    /**
     * @inheritdoc
     */
    public $efectores;
    
    public $localidadNombre; // propiedad agregada
    
    public $departamentoNombre; // propiedad agregada
    
    public $departamentoId; // propiedad agregada 
    
    public function rules()
    {
        return [
            [['id_efector', 'id_localidad'], 'integer'],
            [['codigo_sisa', 'nombre', 
              'dependencia', 'tipologia', 
              'domicilio', 'telefono', 'origen_financiamiento', 'efectores'], 'safe'],
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
     * Busqueda modificada para listar los efectores designados para el usuario logueado
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = Efector::find();     
       
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
           
        ]);
        
        // Agrego dataProvider
        $dataProvider->setSort([
            'attributes' => [
                'nombre',            
                'localidadNombre' => [
                    'asc' => ['localidades.nombre' => SORT_ASC],
                    'desc' => ['localidades.nombre' => SORT_DESC],
                    'label' => 'Localidad'
                ],
                'idLocalidad.departamentoNombre' => [
                    'asc' => ['departamentos.nombre' => SORT_ASC],
                    'desc' => ['departamentos.nombre' => SORT_DESC],
                    'label' => 'Departamento'
                ]
            ]
        ]);


        $this->load($params);

        if (!$this->validate()) {
            //Descomentar esta linea si no quiere devolver ningún registro cuando falle la validación
            // $query->where('0=1');
            $query->joinWith('idLocalidad'); //agrego esta línea para que me haga un join con la tabla localidades
            return $dataProvider;
        }        
                   
        //Filtros para los campos que no son text                
       /* $query->andFilterWhere([
            'id_efector' => $this->id_efector,
            //'id_localidad' => $this->id_localidad, 
        ]);*/
//var_dump($this->efectores);die;
        if ($this->efectores) {
            $query->andFilterWhere([
                'in', 'id_efector', $this->efectores,
            ]);
        }

        //Filtros para los campos que sí son text 
        $query->andFilterWhere(['like', 'codigo_sisa', $this->codigo_sisa])
            ->andFilterWhere(['like', 'efectores.nombre', $this->nombre])
            ->andFilterWhere(['like', 'dependencia', $this->dependencia])
            ->andFilterWhere(['like', 'tipologia', $this->tipologia])
            ->andFilterWhere(['like', 'domicilio', $this->domicilio])
            ->andFilterWhere(['like', 'telefono', $this->telefono])
            ->andFilterWhere(['like', 'origen_financiamiento', $this->origen_financiamiento])
            ->andFilterWhere(['like', 'estado', $this->estado]);
                
            //Esto agrego para filtrar por localidad en el listado
           // ->andFilterWhere(['like', 'localidades.nombre', $this->id_localidad]); 
                
        $query->joinWith(['localidad' => function($q) {
            $q->andFilterWhere(['like', 'localidades.nombre', $this->localidadNombre]);
        }]);      
        
        //Esto agrego para filtrar por departamento en el listado
        $query->joinWith(['localidad.departamento' => function($q) {
                $q->andFilterWhere([
                    'departamentos.id_departamento' => $this->departamentoId
                ]);
            }]);
        //echo $query->createCommand()->getRawSql();die;
        return $dataProvider;
    }
    
    public function searchuserefector($params)
    {
                
        //variable con el usuario que inicio sesion
        $id_user = Yii::$app->user->id;
        //consulta efectores correspondientes al usuario que inicio sesion
        $query = Efector::find()
                        ->select('efectores.*')
                        ->leftJoin('user_efector', '`user_efector`.`id_efector` = `efectores`.`id_efector`')
                        ->where(['user_efector.id_user' => $id_user]);
        
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
            'id_efector' => $this->id_efector,
            'id_localidad' => $this->id_localidad,
        ]);

        $query->andFilterWhere(['like', 'codigo_sisa', $this->codigo_sisa])
            ->andFilterWhere(['like', 'nombre', $this->nombre])
            ->andFilterWhere(['like', 'dependencia', $this->dependencia])
            ->andFilterWhere(['like', 'tipologia', $this->tipologia])
            ->andFilterWhere(['like', 'domicilio', $this->domicilio])
            ->andFilterWhere(['like', 'telefono', $this->telefono])
            ->andFilterWhere(['like', 'origen_financiamiento', $this->origen_financiamiento]);

        return $dataProvider;
    }
}
