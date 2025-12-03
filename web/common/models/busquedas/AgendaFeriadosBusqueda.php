<?php

namespace common\models\busquedas;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\AgendaFeriados;

class AgendaFeriadosBusqueda extends AgendaFeriados{

    public function rules()
    {
        return [
            [['id', 'created_by', 'updated_by', 'deleted_by'], 'integer'],
            [['titulo', 'repite_todos_anios', 'horario'], 'string'],
            [['fecha', 'created_at', 'updated_at', 'deleted_at'], 'safe'],
        ];
    }


    public function search($params)
    {
        $query = AgendaFeriados::find();

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
            'id_' => $this->id,
            'titulo' => $this->titulo,
            'fecha' => $this->fecha,
            'repite_todos_anios' => $this->repite_todos_anios,
            'horario' => $this->horario,
        ]);

        return $dataProvider;
    }

}


