<?php

namespace common\models\busquedas;
use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\SegNivelInternacionSuministroMedicamento;

/**
 * SigNivelSuministroMedicamentoBusqueda represents the model behind the search form of `common\models\InfraestructuraCama`.
 */
class SegNivelInternacionSuministroMedicamentoBusqueda extends SegNivelInternacionSuministroMedicamento
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'id_internacion', 'id_internacion_medicamento', 'id_rrhh'], 'integer'],
            [['fecha', 'hora', 'observacion'], 'string'],
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

        //variable con el usuario que inicio sesion
        $id_user = Yii::$app->user->id;       

        $query = SegNivelInternacionSuministroMedicamento::find()->orderBy(['fecha' => SORT_DESC,
        'hora'=>SORT_DESC]);

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
        $query->andFilterWhere([
            'id' => $this->id,
            'fecha' => $this->fecha,
            'hora' => $this->hora,
            'id_rrhh' => $this->id_rrhh,
            'id_internacion' => $this->id_internacion,
            'id_internacion_medicamento' => $this->id_internacion_medicamento
        ]);

        $query->andFilterWhere(['like', 'observacion', $this->observacion]);

        return $dataProvider;
    }
}
