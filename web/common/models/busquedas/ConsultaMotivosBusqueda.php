<?php

namespace common\models\busquedas;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\ConsultaMotivos;

/**
 * ConsultaMotivosBusqueda represents the model behind the search form of `\common\models\ConsultaMotivos`.
 */
class ConsultaMotivosBusqueda extends ConsultaMotivos
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
            ->select(['vcm.id_servicio as idservicio, s.nombre as nombre, sh.conceptId as concepto, sh.term as termino, count(vcm.id_servicio) as cantidad' ])
            ->from(['vcm' => "view_consulta_motivo"])
            ->join('JOIN','snomed_hallazgos sh', 'vcm.codigo = sh.conceptId')
            ->join('JOIN','servicios s', 's.id_servicio = vcm.id_servicio')
            ->where(['IS NOT','sh.conceptId' , null])
            #->andWhere(['=','c.id_efector', $efector])
            ->groupBy(['vcm.id_servicio', 'sh.conceptId', 'sh.term'])
            ->orderBy(['s.nombre' => SORT_ASC, 'cantidad' => SORT_DESC]);

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
