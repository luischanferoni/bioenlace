<?php

namespace common\models\busquedas;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Alergias;

/**
 * AlergiasBusqueda represents the model behind the search form of `\common\models\Alergias`.
 */
class AlergiasBusqueda extends Alergias
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
            ->select(['c.id_servicio as idservicio,id_snomed_hallazgo as concepto, s.nombre as nombre, sh.term as termino, count(c.id_consulta) as cantidad'])
            ->from('consultas c')
            ->join('LEFT JOIN','consultas_alergias ca', 'c.id_consulta = ca.id_consulta')
            ->join('LEFT JOIN','snomed_hallazgos sh', 'ca.id_snomed_hallazgo = sh.conceptId')
            ->join('LEFT JOIN','servicios s', 'c.id_servicio=s.id_servicio')
            ->where(['IS NOT','sh.conceptId' , null])
            #->andWhere(['=','c.id_efector', $efector])
            ->groupBy(['c.id_servicio', 'sh.conceptId', 'sh.term'])
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

        $query->andFilterWhere(['like', 'sh.term', $this->terminos_motivos]);
        $query->andFilterWhere(['=', 'c.id_servicio', $this->id_servicio]);

        return $dataProvider;
    }
}