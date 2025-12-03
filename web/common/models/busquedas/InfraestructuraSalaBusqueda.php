<?php

namespace common\models\busquedas;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\InfraestructuraSala;

/**
 * InfraestructuraSalaBusqueda represents the model behind the search form of `common\models\InfraestructuraSala`.
 */
class InfraestructuraSalaBusqueda extends InfraestructuraSala
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'nro_sala', 'covid', 'id_responsable', 'id_piso', 'id_servicio'], 'integer'],
            [['descripcion', 'tipo_sala'], 'safe'],
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

        $id_efector = Yii::$app->user->getIdEfector();

        $query = InfraestructuraSala::find()
        ->select('infraestructura_sala.*')
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
            'nro_sala' => $this->nro_sala,
            'covid' => $this->covid,
            'id_responsable' => $this->id_responsable,
            'id_piso' => $this->id_piso,
            'id_servicio' => $this->id_servicio,
        ]);

        $query->andFilterWhere(['like', 'descripcion', $this->descripcion])
            ->andFilterWhere(['like', 'tipo_sala', $this->tipo_sala]);

        return $dataProvider;
    }
}
