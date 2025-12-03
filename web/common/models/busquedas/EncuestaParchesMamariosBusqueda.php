<?php

namespace common\models\busquedas;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\EncuestaParchesMamarios;

/**
 * EncuestaParchesMamariosBusqueda represents the model behind the search form about `frontend\models\EncuestaParchesMamarios`.
 */
class EncuestaParchesMamariosBusqueda extends EncuestaParchesMamarios
{
    public $dni;
    public $apellido;
    public $efector; // el nombre del efector

    public $rango_fechas; // para filtrar la fecha de prueba por un rango

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['dni'], 'integer'],
            [['apellido', 'efector', 'resultado', 'resultado_indicado', 'rango_fechas'], 'string'],
            //[['fecha_prueba'], 'safe'],
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
        $query = EncuestaParchesMamarios::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        if ($this->resultado !== 'todos') {
            $query->andFilterWhere(['resultado' => $this->resultado]);
        }

        if ($this->resultado_indicado !== 'todos') {
            $query->andFilterWhere(['resultado_indicado' => $this->resultado_indicado]);
        }

        if (!is_null($this->rango_fechas) && strpos($this->rango_fechas, ' - ') !== false ) {
            list($start_date, $end_date) = explode(' - ', $this->rango_fechas);
            $query->andFilterWhere(['between', 'fecha_prueba', $start_date, $end_date]);
        }

        //$query->andFilterWhere(['between', 'fecha_prueba', $this->rango_fechas, $this->end_date]);
       

        $query->joinWith(['persona']);
        $query->andFilterWhere(['like', 'personas.apellido', $this->apellido]);
        $query->andFilterWhere(['personas.documento' => $this->dni]);

        $query->joinWith(['efector']);
        $query->andFilterWhere(['like', 'efectores.nombre', $this->efector]);
        $query->andFilterWhere([
            'efectores.id_efector' => $this->id_efector            
        ]);

        return $dataProvider;
    }
}
