<?php

namespace common\models\busquedas;

use common\models\Clinical\Encounter;
use yii\base\Model;
use yii\data\ActiveDataProvider;

class ConsultaMotivosBusqueda extends Model
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
        $query = (new \yii\db\Query())
            ->select([
                'idservicio' => 'enc.service_id',
                'nombre' => 's.nombre',
                'concepto' => new \yii\db\Expression("''"),
                'termino' => 'enc.reason_text',
                'cantidad' => new \yii\db\Expression('count(enc.id)'),
            ])
            ->from(['enc' => $encTable])
            ->innerJoin('servicios s', 'enc.service_id = s.id_servicio')
            ->where(['enc.deleted_at' => null])
            ->andWhere(['not', ['enc.reason_text' => null]])
            ->andWhere(['<>', 'enc.reason_text', ''])
            ->groupBy(['enc.service_id', 's.nombre', 'enc.reason_text'])
            ->orderBy(['s.nombre' => SORT_ASC, 'cantidad' => SORT_DESC]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere(['like', 'enc.reason_text', $this->terminos_motivos]);
        $query->andFilterWhere(['enc.service_id' => $this->id_servicio]);

        return $dataProvider;
    }
}
