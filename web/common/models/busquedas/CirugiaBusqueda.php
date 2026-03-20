<?php

namespace common\models\busquedas;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Cirugia;
use common\models\QuirofanoSala;

class CirugiaBusqueda extends Cirugia
{
    public $id_efector;
    public $fecha_desde;
    public $fecha_hasta;

    public function rules()
    {
        return [
            [['id', 'id_quirofano_sala', 'id_persona', 'id_efector'], 'integer'],
            [['estado'], 'safe'],
            [['fecha_desde', 'fecha_hasta'], 'safe'],
        ];
    }

    public function scenarios()
    {
        return Model::scenarios();
    }

    public function search($params)
    {
        $query = Cirugia::find()->alias('c')
            ->innerJoin(['s' => QuirofanoSala::tableName()], 's.id = c.id_quirofano_sala')
            ->andWhere(['s.deleted_at' => null])
            ->with(['sala']);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => ['fecha_hora_inicio' => SORT_DESC],
                'attributes' => [
                    'fecha_hora_inicio' => [
                        'asc' => ['c.fecha_hora_inicio' => SORT_ASC],
                        'desc' => ['c.fecha_hora_inicio' => SORT_DESC],
                    ],
                    'estado' => [
                        'asc' => ['c.estado' => SORT_ASC],
                        'desc' => ['c.estado' => SORT_DESC],
                    ],
                ],
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere([
            'c.id' => $this->id,
            'c.id_quirofano_sala' => $this->id_quirofano_sala,
            'c.id_persona' => $this->id_persona,
            'c.estado' => $this->estado,
            's.id_efector' => $this->id_efector,
        ]);

        if ($this->fecha_desde) {
            $query->andFilterWhere(['>=', 'c.fecha_hora_inicio', $this->fecha_desde . ' 00:00:00']);
        }
        if ($this->fecha_hasta) {
            $query->andFilterWhere(['<=', 'c.fecha_hora_inicio', $this->fecha_hasta . ' 23:59:59']);
        }

        return $dataProvider;
    }
}
