<?php

namespace common\models\busquedas;

use common\models\Clinical\AllergyIntolerance;
use common\models\Clinical\Encounter;
use yii\base\Model;
use yii\data\ActiveDataProvider;

class AlergiasBusqueda extends Model
{
    public $terminos_motivos;
    public $id_servicio;

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
        $aiTable = AllergyIntolerance::tableName();
        $query = (new \yii\db\Query())
            ->select([
                'idservicio' => 'enc.service_id',
                'concepto' => 'ai.code',
                'nombre' => 's.nombre',
                'termino' => new \yii\db\Expression('COALESCE(NULLIF(ai.display, ""), sh.term)'),
                'cantidad' => new \yii\db\Expression('count(DISTINCT ai.id)'),
            ])
            ->from(['enc' => $encTable])
            ->innerJoin(['ai' => $aiTable], 'ai.encounter_id = enc.id AND ai.deleted_at IS NULL')
            ->leftJoin('snomed_hallazgos sh', 'ai.code = sh.conceptId')
            ->innerJoin('servicios s', 'enc.service_id = s.id_servicio')
            ->where(['enc.deleted_at' => null])
            ->groupBy(['enc.service_id', 'ai.code', 'ai.display', 'sh.term'])
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
            ['like', 'ai.display', $this->terminos_motivos],
            ['like', 'sh.term', $this->terminos_motivos],
        ]);
        $query->andFilterWhere(['enc.service_id' => $this->id_servicio]);

        return $dataProvider;
    }
}
