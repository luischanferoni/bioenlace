<?php

namespace common\models\busquedas;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\PersonasAntecedente;

/**
 * PersonasAntecedenteBusqueda represents the model behind the search form of `\common\models\PersonasAntecedente`.
 */
class PersonasAntecedenteBusqueda extends PersonasAntecedente
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
    public function search($params,$servicio,$value)
    {
        $query = (new \yii\db\Query())
            ->select(['c.id_servicio as idservicio, s.nombre as nombre, ss.conceptId as concepto, ss.term as termino, count(c.id_consulta) as cantidad'])
            ->from('consultas c')
            ->join('INNER JOIN','personas_antecedentes pa', 'c.id_consulta = pa.id_consulta')
            ->join('INNER JOIN','snomed_situacion ss', 'pa.codigo = ss.conceptId')
            ->join('INNER JOIN','servicios s', 'c.id_servicio=s.id_servicio')
            ->where(['IS NOT','ss.conceptId' , null])
            #->andWhere(['=','c.id_efector', $efector])
            ->andWhere(['=','pa.tipo_antecedente', $value])
            ->groupBy(['c.id_servicio', 'ss.conceptId', 'ss.term'])
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

        // grid filtering conditions
        // grid filtering conditions

        $query->andFilterWhere(['like', 'ss.term', $this->terminos_motivos]);
        $query->andFilterWhere(['c.id_servicio' => $this->id_servicio]);

        return $dataProvider;
    }
}
