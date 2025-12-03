<?php

namespace common\models\busquedas;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;

use common\models\RrhhEfector;

/**
 * RrhhEfectorBusqueda represents the model behind the search form of `common\models\RrhhEfector`.
 */
class RrhhEfectorBusqueda extends RrhhEfector
{
    const EFECTOR_SEARCH = 'EFECTOR_SEARCH';

    public $nombrePersona;
    public $nombreEfector;
    public $idServicio;
    public $deleted_at;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_persona', 'idServicio'], 'integer'],            
            [['nombrePersona', 'deleted_at'], 'safe'],
            [['nombreEfector'],'safe', 'on' => self::EFECTOR_SEARCH]
        ];
    }

    /**
     * {@inheritdoc}
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
        $query = RrhhEfector::find();        

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);
        //var_dump($params);die;
        $this->load($params);

        // grid filtering conditions
        $query->andFilterWhere([
            'id_efector' => $this->id_efector,
            'id_persona' => $this->id_persona,            
        ]);
//var_dump($params);var_dump($this->deleted_at);die;
        if ($this->deleted_at == "null" || is_null($this->deleted_at)) {
            $query->andWhere('rrhh_efector.deleted_at IS NULL');
        } else {
            $query->andWhere('rrhh_efector.deleted_at IS NOT NULL');
        }
        
        if ($this->nombrePersona != "") {
            $query->joinWith(['persona' => function ($q) {
                $q->where(['like', 'CONCAT(personas.apellido," ",personas.nombre)', '%'.$this->nombrePersona.'%', false])
                    ->orwhere(['like', 'personas.nombre', '%'.$this->nombrePersona.'%', false])
                    ->orwhere(['like', 'personas.apellido', $this->nombrePersona.'%', false])
                    ->orWhere(['like', 'personas.documento', $this->nombrePersona.'%', false]);
            }]);
        }

        if ($this->nombreEfector != "") {
            $query->joinWith(['efector' => function ($q) {
                $q->where('efectores.nombre LIKE "%' . $this->nombreEfector . '%"');
            }]);
        }

        if ($this->idServicio != "") {
            $query->joinWith(['rrhhServicio' => function ($q) {
                $q->where('rrhh_servicio.id_servicio = ' . $this->idServicio)
                  ->andWhere('rrhh_servicio.deleted_at is NULL');
            }]);
        }

        return $dataProvider;
    }
}
