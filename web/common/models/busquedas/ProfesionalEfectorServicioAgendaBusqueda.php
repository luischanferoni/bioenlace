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
    /** Filtro por persona del profesional: cualquier PES de ese profesional. */
    public $id_profesional_contexto;
    /** Filtro opcional por apellido (LIKE). */
    public $apellido;

    public function rules(): array
    {
        return [
            [['id', 'id_profesional_efector_servicio', 'id_efector', 'id_profesional_contexto'], 'integer'],
            [['apellido'], 'safe'],
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

        if ($this->id_profesional_contexto) {
            $idPersona = ProfesionalEfectorServicio::resolveIdPersonaFromStaffContextId((int) $this->id_profesional_contexto);
            if ($idPersona !== null && $idPersona > 0) {
                $query->andWhere(['pes.id_persona' => $idPersona]);
            } else {
                $query->andWhere('0=1');
            }
        }

        $id_efector = $this->id_efector ? (int) $this->id_efector : (int) Yii::$app->user->getIdEfector();

        $query->andFilterWhere(['a.id' => $this->id])
            ->andFilterWhere(['a.id_profesional_efector_servicio' => $this->id_profesional_efector_servicio])
            ->andFilterWhere(['a.id_efector' => $id_efector])
            ->andWhere(['a.deleted_at' => null]);

        $query->andFilterWhere(['like', 'per.apellido', $this->apellido]);
        $query->orderBy(['per.apellido' => SORT_ASC, 'a.id' => SORT_ASC]);

        return $dataProvider;
    }
}
