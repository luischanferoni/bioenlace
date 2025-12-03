<?php

namespace common\models\busquedas;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\ConsultaPracticas;

/**
 * ConsultaPracticasBusqueda represents the model behind the search form of `\common\models\ConsultaPracticas`.
 */
class ConsultaPracticasBusqueda extends ConsultaPracticas
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['terminos_motivos', 'id_servicio'], 'string'],
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
    public function search($params,$servicio)
    {
        $query = (new \yii\db\Query())
            ->select(['c.id_servicio as idservicio, s.nombre as nombre, sp.conceptId as concepto, sp.term as termino, count(c.id_consulta) as cantidad'])
            ->from('consultas c')
            ->join('JOIN','consultas_practicas cp', 'c.id_consulta = cp.id_consulta')
            ->join('JOIN','snomed_procedimientos sp', 'cp.codigo = sp.conceptId')
            ->join('JOIN','servicios s', 'c.id_servicio=s.id_servicio')
            ->where(['IS NOT','sp.conceptId' , null])
            #->andWhere(['=','c.id_efector', $efector])
            ->groupBy(['c.id_servicio', 'sp.conceptId', 'sp.term'])
            ->orderBy(['s.nombre' => SORT_ASC, 'count(c.id_consulta)' => SORT_DESC]);

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


        $query->andFilterWhere(['like', 'sp.term', $this->terminos_motivos]);
        $query->andFilterWhere([ 'c.id_servicio' => $this->id_servicio]);

        return $dataProvider;
    }
}
