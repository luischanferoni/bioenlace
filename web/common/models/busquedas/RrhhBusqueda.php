<?php

namespace common\models\busquedas;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Rrhh;

/**
 * RrhhBusqueda represents the model behind the search form about `common\models\rrhh`.
 */
class RrhhBusqueda extends rrhh
{
    public $nombre;
    public $apellido;
    public $profesion;
    public $especialidad;
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['nombre','apellido','profesion','especialidad'],'safe'],
            [['id_rr_hh', 'id_persona', 'id_profesion', 'id_especialidad'], 'integer'],
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
        $query = rrhh::find();
        $query->joinWith(['persona', 'profesion', 'especialidad']);
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);
        $dataProvider->sort->attributes['nombre'] = [
            'asc' => ['personas.nombre' => SORT_ASC],
            'desc' => ['personas.nombre' => SORT_DESC],
        ];
        $dataProvider->sort->attributes['apellido'] = [
            'asc' => ['personas.apellido' => SORT_ASC],
            'desc' => ['personas.apellido' => SORT_DESC],
        ];
        $dataProvider->sort->attributes['profesion'] = [
            'asc' => ['profesiones.nombre' => SORT_ASC],
            'desc' => ['profesiones.nombre' => SORT_DESC],
        ];
        $dataProvider->sort->attributes['especialidad'] = [
            'asc' => ['especialidades.nombre' => SORT_ASC],
            'desc' => ['especialidades.nombre' => SORT_DESC],
        ];
        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id_rr_hh' => $this->id_rr_hh,
            'id_persona' => $this->id_persona,
            'id_profesion' => $this->id_profesion,
            'id_especialidad' => $this->id_especialidad,
        ]);
        $query->andFilterWhere(['like', 'personas.nombre', $this->nombre])
               ->andFilterWhere(['like', 'personas.apellido', $this->apellido])
               ->andFilterWhere(['like', 'profesiones.nombre', $this->profesion])
               ->andFilterWhere(['like', 'especialidades.nombre', $this->especialidad]);

        return $dataProvider;
    }
}
