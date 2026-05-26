<?php

namespace common\models\busquedas;

use common\models\Clinical\Encounter;
use common\models\PersonasAntecedente;
use yii\base\Model;
use yii\data\ActiveDataProvider;

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
        return Model::scenarios();
    }

    /**
     * @param array $params
     * @param mixed $servicio
     * @param string $value
     */
    public function search($params, $servicio, $value)
    {
        $fk = PersonasAntecedente::encounterFkAttribute();
        $encTable = Encounter::tableName();

        $query = (new \yii\db\Query())
            ->select([
                'idservicio' => 'enc.service_id',
                'nombre' => 's.nombre',
                'concepto' => 'ss.conceptId',
                'termino' => 'ss.term',
                'cantidad' => new \yii\db\Expression('count(enc.id)'),
            ])
            ->from(['enc' => $encTable])
            ->innerJoin('personas_antecedentes pa', "enc.id = pa.{$fk}")
            ->innerJoin('snomed_situacion ss', 'pa.codigo = ss.conceptId')
            ->innerJoin('servicios s', 'enc.service_id = s.id_servicio')
            ->where(['IS NOT', 'ss.conceptId', null])
            ->andWhere(['pa.tipo_antecedente' => $value])
            ->andWhere(['enc.deleted_at' => null])
            ->groupBy(['enc.service_id', 'ss.conceptId', 'ss.term'])
            ->orderBy(['s.nombre' => SORT_ASC, 'cantidad' => SORT_DESC]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere(['like', 'ss.term', $this->terminos_motivos]);
        $query->andFilterWhere(['enc.service_id' => $this->id_servicio]);

        return $dataProvider;
    }
}
