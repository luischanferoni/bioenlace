<?php

namespace common\models\busquedas;
use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\InfraestructuraCama;

/**
 * InfraestructuraCamaBusqueda represents the model behind the search form of `common\models\InfraestructuraCama`.
 */
class InfraestructuraCamaBusqueda extends InfraestructuraCama
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'nro_cama', 'respirador', 'monitor', 'id_sala'], 'integer'],
            [['estado'], 'safe'],
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
        $id_efector = Yii::$app->user->getIdEfector();

        $query = InfraestructuraCama::find()
        ->select('infraestructura_cama.*')
        ->leftJoin('infraestructura_sala', '`infraestructura_sala`.`id` = `infraestructura_cama`.`id_sala`')
        ->leftJoin('infraestructura_piso', '`infraestructura_piso`.`id` = `infraestructura_sala`.`id_piso`')
        ->where('infraestructura_piso.id_efector = :id_efector',[':id_efector' => $id_efector]);

        // add conditions that should always apply here
        
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            //return $dataProvider;
        }

        // grid filtering conditions
     /*   $query->andFilterWhere([
            'id' => $this->id,
            'nro_cama' => $this->nro_cama,
            'respirador' => $this->respirador,
            'monitor' => $this->monitor,
            'id_sala' => $this->id_sala,
        ]);*/

       $query->andFilterWhere(['like', 'estado', $this->estado]);
//echo $query->createCommand()->getRawSql();die;
        return $dataProvider;
    }

        /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function searchOcupadas($params)
    {

        //variable con el usuario que inicio sesion
        $id_user = Yii::$app->user->id;
        $id_efector = Yii::$app->user->getIdEfector();

        $query = (new \yii\db\Query())
                    ->select([
                        'infraestructura_sala.id_servicio', 
                        'infraestructura_piso.id_efector', 
                        'SUM( IF(estado = "ocupada", 1, 0) ) AS ocupadas', 
                        'SUM( IF(estado = "desocupada", 1, 0) ) AS desocupadas', 
                        'COUNT(*) AS totalcamas'
                    ])
                    ->from('infraestructura_cama')
                    ->leftJoin('infraestructura_sala', '`infraestructura_sala`.`id` = `infraestructura_cama`.`id_sala`')
                    ->leftJoin('infraestructura_piso', '`infraestructura_piso`.`id` = `infraestructura_sala`.`id_piso`')
                    ->where('infraestructura_piso.id_efector = :id_efector',[':id_efector' => $id_efector])
                    ->groupBy('infraestructura_piso.id_efector,infraestructura_sala.id_servicio')
                    ->orderby('infraestructura_piso.id_efector')
                    ;
        /*$query = InfraestructuraCama::find()
        ->select('infraestructura_cama.*, infraestructura_sala.id_servicio as descripcion,infraestructura_piso.id_efector, COUNT(DISTINCT(infraestructura_cama.id)) as cantidad')
        ->leftJoin('infraestructura_sala', '`infraestructura_sala`.`id` = `infraestructura_cama`.`id_sala`')
        ->leftJoin('infraestructura_piso', '`infraestructura_piso`.`id` = `infraestructura_sala`.`id_piso`')
        //->where('infraestructura_cama.estado = "ocupada"')
        ->groupBy('infraestructura_piso.id_efector,infraestructura_sala.id_servicio');*/

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
            'nro_cama' => $this->nro_cama,
            'respirador' => $this->respirador,
            'monitor' => $this->monitor,
            'id_sala' => $this->id_sala,
        ]);

        $query->andFilterWhere(['like', 'estado', $this->estado]);

        return $dataProvider;
    }
}
