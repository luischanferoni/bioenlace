<?php

namespace common\models\busquedas;
use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\SegNivelInternacion;

/**
 * SegNivelInternacionBusqueda represents the model behind the search form of `common\models\SegNivelInternacion`.
 */
class SegNivelInternacionBusqueda extends SegNivelInternacion
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'id_cama', 'id_persona'], 'integer'],
            [['fecha_inicio', 'hora_inicio', 'fecha_fin', 'hora_fin'], 'safe'],
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
        $query = SegNivelInternacion::find();

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
            'fecha_inicio' => $this->fecha_inicio,
            'hora_inicio' => $this->hora_inicio,
            'fecha_fin' => $this->fecha_fin,
            'hora_fin' => $this->hora_fin,
            'id_cama' => $this->id_cama,
            'id_persona' => $this->id_persona,
        ]);

        return $dataProvider;
    }


    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function searchFinalizadas($params)
    {
        //variable con el usuario que inicio sesion
        $id_user = Yii::$app->user->id;
        $id_efector = Yii::$app->user->getIdEfector();

        $query = SegNivelInternacion::find()
        ->select('seg_nivel_internacion.*')
        ->leftJoin('infraestructura_cama', '`infraestructura_cama`.`id` = `seg_nivel_internacion`.`id_cama`')
        ->leftJoin('infraestructura_sala', '`infraestructura_sala`.`id` = `infraestructura_cama`.`id_sala`')
        ->leftJoin('infraestructura_piso', '`infraestructura_piso`.`id` = `infraestructura_sala`.`id_piso`')
        ->where('infraestructura_piso.id_efector = :id_efector',[':id_efector' => $id_efector]);;
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
            'fecha_inicio' => $this->fecha_inicio,
            'hora_inicio' => $this->hora_inicio,
            //'fecha_fin' => $this->fecha_fin,
            'hora_fin' => $this->hora_fin,
            'id_cama' => $this->id_cama,
            'id_persona' => $this->id_persona,
        ]);
        $query->andWhere(['is not', 'fecha_fin', new \yii\db\Expression('null')]);

        return $dataProvider;
    }

        /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function searchPorPersona($params, $idpersona)
    {
        //variable con el usuario que inicio sesion
        $id_user = Yii::$app->user->id;
        $id_efector = Yii::$app->user->getIdEfector();

        $query = SegNivelInternacion::find();
        /*->select('seg_nivel_internacion.*')
        ->leftJoin('infraestructura_cama', '`infraestructura_cama`.`id` = `seg_nivel_internacion`.`id_cama`')
        ->leftJoin('infraestructura_sala', '`infraestructura_sala`.`id` = `infraestructura_cama`.`id_sala`')
        ->leftJoin('infraestructura_piso', '`infraestructura_piso`.`id` = `infraestructura_sala`.`id_piso`')
        ->where('infraestructura_piso.id_efector = :id_efector',[':id_efector' => $id_efector]);*/
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
            'fecha_inicio' => $this->fecha_inicio,
            'hora_inicio' => $this->hora_inicio,
            //'fecha_fin' => $this->fecha_fin,
            'hora_fin' => $this->hora_fin,
            'id_cama' => $this->id_cama,
            'id_persona' => $idpersona,
        ]);       

        return $dataProvider;
    }
}
