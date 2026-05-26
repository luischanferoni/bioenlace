<?php

namespace common\models\busquedas;

use common\models\Clinical\Encounter;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\DiagnosticoConsulta;

class DiagnosticoConsultasBusqueda extends DiagnosticoConsulta
{
    public function rules()
    {
        return [
            [['terminos_motivos', 'id_servicio'], 'string'],
        ];
    }

    public function scenarios()
    {
        return Model::scenarios();
    }

    public function search($params, $servicio)
    {
        $encTable = Encounter::tableName();
        $query = (new \yii\db\Query())
            ->select([
                'id_servicio' => 'enc.service_id',
                'nombre' => 's.nombre',
                'concepto' => 'c.code',
                'termino' => new \yii\db\Expression('COALESCE(NULLIF(c.display, ""), sh.term)'),
                'cantidad' => new \yii\db\Expression('count(enc.id)'),
            ])
            ->from(['enc' => $encTable])
            ->innerJoin('condition c', 'c.encounter_id = enc.id')
            ->leftJoin('snomed_hallazgos sh', 'c.code = sh.conceptId')
            ->innerJoin('servicios s', 'enc.service_id = s.id_servicio')
            ->where(['enc.deleted_at' => null])
            ->groupBy(['enc.service_id', 'c.code', 'c.display', 'sh.term'])
            ->orderBy(['s.nombre' => SORT_ASC, 'cantidad' => SORT_DESC]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere([
            'or',
            ['like', 'c.display', $this->terminos_motivos],
            ['like', 'sh.term', $this->terminos_motivos],
        ]);
        $query->andFilterWhere(['enc.service_id' => $this->id_servicio]);

        return $dataProvider;
    }
}
