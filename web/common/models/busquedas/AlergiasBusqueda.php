<?php

namespace common\models\busquedas;

use common\models\Clinical\Encounter;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Alergias;

class AlergiasBusqueda extends Alergias
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
                'idservicio' => 'enc.service_id',
                'concepto' => 'sh.conceptId',
                'nombre' => 's.nombre',
                'termino' => 'sh.term',
                'cantidad' => new \yii\db\Expression('count(enc.id)'),
            ])
            ->from(['enc' => $encTable])
            ->leftJoin('consultas_alergias ca', 'enc.id = ca.id_consulta')
            ->leftJoin('snomed_hallazgos sh', 'ca.id_snomed_hallazgo = sh.conceptId')
            ->leftJoin('servicios s', 'enc.service_id = s.id_servicio')
            ->where(['IS NOT', 'sh.conceptId', null])
            ->andWhere(['enc.deleted_at' => null])
            ->groupBy(['enc.service_id', 'sh.conceptId', 'sh.term'])
            ->orderBy(['s.nombre' => SORT_ASC, 'cantidad' => SORT_DESC]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere(['like', 'sh.term', $this->terminos_motivos]);
        $query->andFilterWhere(['enc.service_id' => $this->id_servicio]);

        return $dataProvider;
    }
}
