<?php

namespace common\models\busquedas;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Efector;
use common\models\Person\PersonaPacienteContexto;


/**
 * EfectorBusqueda represents the model behind the search form about `common\models\Efector`.
 */
class EfectorBusqueda extends Efector
{
    /**
     * @inheritdoc
     */
    public $efectores;
    
    public $localidadNombre;
    
    public $departamentoNombre;
    
    public $departamentoId;

    /** @var int|null Filtro por provincias.id_provincia */
    public $provinciaId;

    /** @var string|null PUBLICO|PRIVADO */
    public $sectorSalud;
    
    public function rules()
    {
        return [
            [['id_efector', 'id_localidad', 'departamentoId', 'provinciaId'], 'integer'],
            [['codigo_sisa', 'nombre', 
              'dependencia', 'tipologia', 
              'domicilio', 'telefono', 'origen_financiamiento', 'efectores',
              'localidadNombre', 'departamentoNombre', 'sectorSalud', 'estado'], 'safe'],
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
                'origen_financiamiento',
                'localidadNombre' => [
                    'asc' => ['geo_localidades.nombre' => SORT_ASC],
                    'desc' => ['geo_localidades.nombre' => SORT_DESC],
                    'label' => 'Localidad'
                ],
                'idLocalidad.departamentoNombre' => [
                    'asc' => ['geo_departamentos.nombre' => SORT_ASC],
                    'desc' => ['geo_departamentos.nombre' => SORT_DESC],
                    'label' => 'Departamento'
                ],
                'localidad.departamento.provincia.nombre' => [
                    'asc' => ['geo_provincias.nombre' => SORT_ASC],
                    'desc' => ['geo_provincias.nombre' => SORT_DESC],
                    'label' => 'Provincia'
                ],
            ]
        ]);


        $this->load($params);

        if (!$this->validate()) {
            //Descomentar esta linea si no quiere devolver ningún registro cuando falle la validación
            // $query->where('0=1');
            $this->applyGeoJoins($query);
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

        $this->applyGeoJoins($query);

        $sector = strtoupper(trim((string) $this->sectorSalud));
        if ($sector === PersonaPacienteContexto::SECTOR_SALUD_PUBLICO
            || $sector === PersonaPacienteContexto::SECTOR_SALUD_PRIVADO) {
            Efector::applySectorSaludFilterToQuery($query, $sector);
        }

        return $dataProvider;
    }

    /**
     * @param \yii\db\ActiveQuery $query
     */
    private function applyGeoJoins($query): void
    {
        $query->joinWith(['localidad' => function ($q) {
            $q->andFilterWhere(['like', 'geo_localidades.nombre', $this->localidadNombre]);
        }]);

        $query->joinWith(['localidad.departamento' => function ($q) {
            $q->andFilterWhere([
                'geo_departamentos.id_departamento' => $this->departamentoId,
            ]);
        }]);

        $query->joinWith(['localidad.departamento.provincia' => function ($q) {
            $q->andFilterWhere([
                'geo_provincias.id_provincia' => $this->provinciaId,
            ]);
        }]);
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
