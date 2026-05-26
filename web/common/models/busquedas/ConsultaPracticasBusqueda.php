<?php

namespace common\models\busquedas;

use common\models\Clinical\Encounter;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\ConsultaPracticas;

class ConsultaPracticasBusqueda extends ConsultaPracticas
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
                'nombre' => 's.nombre',
                'concepto' => 'sp.conceptId',
                'termino' => 'sp.term',
                'cantidad' => new \yii\db\Expression('count(enc.id)'),
            ])
            ->from(['enc' => $encTable])
            ->innerJoin('service_request sr', 'sr.encounter_id = enc.id')
            ->innerJoin('snomed_procedimientos sp', 'sr.code = sp.conceptId')
            ->innerJoin('servicios s', 'enc.service_id = s.id_servicio')
            ->where(['IS NOT', 'sp.conceptId', null])
            ->andWhere(['enc.deleted_at' => null])
            ->groupBy(['enc.service_id', 'sp.conceptId', 'sp.term'])
            ->orderBy(['s.nombre' => SORT_ASC, 'cantidad' => SORT_DESC]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere(['like', 'sp.term', $this->terminos_motivos]);
        $query->andFilterWhere(['enc.service_id' => $this->id_servicio]);

        return $dataProvider;
    }
}
