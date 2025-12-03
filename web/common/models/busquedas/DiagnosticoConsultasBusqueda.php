<?php

namespace common\models\busquedas;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\DiagnosticoConsulta;

/**
 * DiagnosticoConsultasBusqueda represents the model behind the search form of `\common\models\DiagnosticoConsulta`.
 */
class DiagnosticoConsultasBusqueda extends DiagnosticoConsulta
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['terminos_motivos','id_servicio'], 'string'],
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
            ->select(['vcm.id_servicio, s.nombre as nombre, codigo as concepto, sh.term as termino, count(vcm.id_consulta) as cantidad'])
            ->from([ 'vcm' => 'view_consulta_diagnostico'])
            ->join('JOIN','snomed_hallazgos sh', 'vcm.codigo = sh.conceptId')
            ->join('JOIN','servicios s', 'vcm.id_servicio=s.id_servicio')
            ->where(['IS NOT','sh.conceptId' , null])
            ->groupBy(['vcm.id_servicio', 'sh.conceptId', 'sh.term'])
            ->orderBy(['s.nombre' => SORT_ASC, 'cantidad' => SORT_DESC]);;

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
        $query->andFilterWhere(['vcm.id_servicio' => $this->id_servicio]);
        return $dataProvider;
    }
}
