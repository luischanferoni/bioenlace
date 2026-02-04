<?php

namespace common\models\busquedas;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Agenda_rrhh;

/**
 * Agenda_rrhhBusqueda represents the model behind the search form about `common\models\Agenda_rrhh`.
 */
class Agenda_rrhhBusqueda extends Agenda_rrhh
{
    public $rrhh;
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_agenda_rrhh', 'id_rr_hh', 'id_tipo_dia', 'id_efector'], 'integer'],
            [['hora_inicio', 'hora_fin', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo', 'fecha_inicio', 'fecha_fin', 'rrhh'], 'safe'],
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
        $query = Agenda_rrhh::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        $id_efector = $this->id_efector ? $this->id_efector : Yii::$app->user->getIdEfector();
        $query->andFilterWhere([
            'id_agenda_rrhh' => $this->id_agenda_rrhh,
            'id_rr_hh' => $this->id_rr_hh,
            'hora_inicio' => $this->hora_inicio,
            'hora_fin' => $this->hora_fin,
            'id_tipo_dia' => $this->id_tipo_dia,
            'fecha_inicio' => $this->fecha_inicio,
            'fecha_fin' => $this->fecha_fin,
            'id_efector' => $id_efector,
        ]);

        $query->andFilterWhere(['like', 'lunes', $this->lunes])
            ->andFilterWhere(['like', 'martes', $this->martes])
            ->andFilterWhere(['like', 'miercoles', $this->miercoles])
            ->andFilterWhere(['like', 'jueves', $this->jueves])
            ->andFilterWhere(['like', 'viernes', $this->viernes])
            ->andFilterWhere(['like', 'sabado', $this->sabado])
            ->andFilterWhere(['like', 'domingo', $this->domingo]);

        $query->joinWith(['rrhh' => function($q) {
            $q->joinWith([
                'persona']);
            $q->andFilterWhere(['like', 'personas.apellido', $this->rrhh]);
        }])->orderBy('personas.apellido');

        return $dataProvider;
    }
}
