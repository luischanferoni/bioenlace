<?php

namespace common\models\busquedas;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\ProfesionalEfectorServicio;
use common\models\ProfesionalEfectorServicioAgenda;

/**
 * Búsqueda paginada sobre {@see ProfesionalEfectorServicioAgenda} (agenda por PES).
 */
class ProfesionalEfectorServicioAgendaBusqueda extends Model
{
    public $id;
    public $id_profesional_efector_servicio;
    public $id_efector;
    public $id_rr_hh;
    public $rrhh;

    public function rules(): array
    {
        return [
            [['id', 'id_profesional_efector_servicio', 'id_efector', 'id_rr_hh'], 'integer'],
            [['rrhh'], 'safe'],
        ];
    }

    public function scenarios()
    {
        return Model::scenarios();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function search($params): ActiveDataProvider
    {
        $query = ProfesionalEfectorServicioAgenda::find()->alias('a');
        $query->innerJoin(
            ['pes' => ProfesionalEfectorServicio::tableName()],
            'pes.id = a.id_profesional_efector_servicio AND pes.deleted_at IS NULL'
        );
        $query->innerJoin(['per' => 'personas'], 'per.id_persona = pes.id_persona');

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);
        if (!$this->validate()) {
            return $dataProvider;
        }

        if ($this->id_rr_hh) {
            $query->innerJoin(
                ['re' => 'rrhh_efector'],
                're.id_persona = pes.id_persona AND re.id_efector = pes.id_efector AND re.deleted_at IS NULL'
            )->andWhere(['re.id_rr_hh' => $this->id_rr_hh]);
        }

        $id_efector = $this->id_efector ? (int) $this->id_efector : (int) Yii::$app->user->getIdEfector();

        $query->andFilterWhere(['a.id' => $this->id])
            ->andFilterWhere(['a.id_profesional_efector_servicio' => $this->id_profesional_efector_servicio])
            ->andFilterWhere(['a.id_efector' => $id_efector])
            ->andWhere(['a.deleted_at' => null]);

        $query->andFilterWhere(['like', 'per.apellido', $this->rrhh]);
        $query->orderBy(['per.apellido' => SORT_ASC, 'a.id' => SORT_ASC]);

        return $dataProvider;
    }
}
