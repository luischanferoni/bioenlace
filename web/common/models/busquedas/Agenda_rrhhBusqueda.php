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
        $t = Agenda_rrhh::tableName();
        // Tras joinWith(rrhh→rrhh_efector), id_efector e id_rr_hh son ambiguos; calificar siempre con la tabla agenda.
        $query->andFilterWhere([
            $t . '.id_agenda_rrhh' => $this->id_agenda_rrhh,
            $t . '.id_rr_hh' => $this->id_rr_hh,
            $t . '.hora_inicio' => $this->hora_inicio,
            $t . '.hora_fin' => $this->hora_fin,
            $t . '.id_tipo_dia' => $this->id_tipo_dia,
            $t . '.fecha_inicio' => $this->fecha_inicio,
            $t . '.fecha_fin' => $this->fecha_fin,
            $t . '.id_efector' => $id_efector,
        ]);

        $query->andFilterWhere(['like', $t . '.lunes', $this->lunes])
            ->andFilterWhere(['like', $t . '.martes', $this->martes])
            ->andFilterWhere(['like', $t . '.miercoles', $this->miercoles])
            ->andFilterWhere(['like', $t . '.jueves', $this->jueves])
            ->andFilterWhere(['like', $t . '.viernes', $this->viernes])
            ->andFilterWhere(['like', $t . '.sabado', $this->sabado])
            ->andFilterWhere(['like', $t . '.domingo', $this->domingo]);

        $query->joinWith(['rrhh' => function($q) {
            $q->joinWith([
                'persona']);
            $q->andFilterWhere(['like', 'personas.apellido', $this->rrhh]);
        }])->orderBy('personas.apellido');

        return $dataProvider;
    }
}
