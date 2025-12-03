<?php

namespace common\models\busquedas;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Guardia;
use Yii;

/**
 * GuardiaBusqueda represents the model behind the search form of `common\models\Guardia`.
 */
class GuardiaBusqueda extends Guardia
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'id_persona', 'id_rrhh_asignado', 'created_by', 'updated_by', 'deleted_by', 'id_efector_derivacion', 'notificar_internacion_id_efector', 'id_efector'], 'integer'],
            [['fecha', 'hora', 'created_at', 'updated_at', 'deleted_at', 'cobertura', 'situacion_al_ingresar', 'condiciones_derivacion'], 'safe'],
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
        $query = Guardia::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        

        // grid filtering conditions
        $id_efector = $this->id_efector ? $this->id_efector : Yii::$app->user->getIdEfector();
        $query->andFilterWhere([
            'id' => $this->id,
            'id_persona' => $this->id_persona,
            'fecha' => $this->fecha,
            'hora' => $this->hora,
            'id_rrhh_asignado' => $this->id_rrhh_asignado,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'deleted_by' => $this->deleted_by,
            'id_efector_derivacion' => $this->id_efector_derivacion,
            'notificar_internacion_id_efector' => $this->notificar_internacion_id_efector,
            'id_efector' => $id_efector,
        ]);

        $query->andFilterWhere(['like', 'cobertura', $this->cobertura])
            ->andFilterWhere(['like', 'situacion_al_ingresar', $this->situacion_al_ingresar])
            ->andFilterWhere(['like', 'condiciones_derivacion', $this->condiciones_derivacion]);

        return $dataProvider;
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function searchNoFinalizadas($params)
    {
        $query = Guardia::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        

        // grid filtering conditions
        $id_efector = $this->id_efector ? $this->id_efector : Yii::$app->user->getIdEfector();
        $query->andFilterWhere([
            'id' => $this->id,
            'id_persona' => $this->id_persona,
            'fecha' => $this->fecha,
            'hora' => $this->hora,
            'id_rrhh_asignado' => $this->id_rrhh_asignado,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'deleted_by' => $this->deleted_by,
            'id_efector_derivacion' => $this->id_efector_derivacion,
            'notificar_internacion_id_efector' => $this->notificar_internacion_id_efector,
            'id_efector' => $id_efector,
        ]);

        $query->andFilterWhere(['like', 'cobertura', $this->cobertura])
            ->andFilterWhere(['<>', 'estado','finalizada'])
            ->andFilterWhere(['like', 'situacion_al_ingresar', $this->situacion_al_ingresar])
            ->andFilterWhere(['like', 'condiciones_derivacion', $this->condiciones_derivacion]);

        return $dataProvider;
    }    

        /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function searchLibro($params)
    {
        $query = Guardia::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $id_efector = $this->id_efector ? $this->id_efector : Yii::$app->user->getIdEfector();
        $query->andFilterWhere([
            'id' => $this->id,
            'id_persona' => $this->id_persona,
            //'fecha' => $this->fecha,
            'hora' => $this->hora,
            'id_rrhh_asignado' => $this->id_rrhh_asignado,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'deleted_by' => $this->deleted_by,
            'id_efector_derivacion' => $this->id_efector_derivacion,
            'notificar_internacion_id_efector' => $this->notificar_internacion_id_efector,
            'id_efector' => $id_efector,
        ]);

        if(isset($params['GuardiaBusqueda']['fecha'])){
            $fecha = explode(' - ', $params['GuardiaBusqueda']['fecha']);
            $start = $fecha[0];
            $end = $fecha[1];
            $query->andFilterWhere(['between', 'fecha', $start, $end]);
        }
        

        $query->andFilterWhere(['like', 'cobertura', $this->cobertura])  
            ->andFilterWhere(['=', 'estado','finalizada'])          
            ->andFilterWhere(['like', 'situacion_al_ingresar', $this->situacion_al_ingresar])
            ->andFilterWhere(['like', 'condiciones_derivacion', $this->condiciones_derivacion]);

        return $dataProvider;
    }
}
