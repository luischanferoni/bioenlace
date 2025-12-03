<?php

namespace common\models\sumar;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;


/**
 * Autofacturacion represents the model behind the search form about `common\models\sumar\Autofacturacion`.
 */
class AutofacturacionBusqueda extends Autofacturacion
{
    public $persona;
    public $id_efector;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['fecha_envio', 'id_consulta'], 'integer'],
            [['id_efector'], 'safe'],
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
        $query = Autofacturacion::find();

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
            'fecha_envio' => $this->fecha_envio,
        ]);

        $query->joinWith(['consulta' => function($q) {
            $q->andFilterWhere(['consultas.id_efector' => $this->id_efector]);

            $q->joinWith('paciente');
            $q->andFilterWhere(['like', 'personas.apellido', $this->persona]);
            }], true, 'RIGHT JOIN')
        ->orderBy('personas.apellido');

        return $dataProvider;
    }
}
