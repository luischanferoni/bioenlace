<?php

namespace common\models\busquedas;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Efector;
use common\models\ProfesionalEfectorServicio;

/**
 * Búsqueda de filas `profesional_efector_servicio` (PES).
 */
class ProfesionalEfectorServicioBusqueda extends Model
{
    const EFECTOR_SEARCH = 'EFECTOR_SEARCH';

    public $id_efector;
    /** @var int|null PK PES (filtro directo en listado RRHH/PES). */
    public $id_profesional_efector_servicio;
    public $id_persona;
    public $nombrePersona;
    public $nombreEfector;
    public $idServicio;
    public $deleted_at;

    public function rules(): array
    {
        return [
            [['id_persona', 'idServicio', 'id_efector', 'id_profesional_efector_servicio'], 'integer'],
            [['nombrePersona', 'deleted_at'], 'safe'],
            [['nombreEfector'], 'safe', 'on' => self::EFECTOR_SEARCH],
        ];
    }

    public function scenarios()
    {
        return Model::scenarios();
    }

    /**
     * Efector del contexto de listado (p. ej. `EfectoresController::actionProfesionales` fija `id_efector`).
     */
    public function getEfector(): ?Efector
    {
        if ($this->id_efector === null || (int) $this->id_efector <= 0) {
            return null;
        }

        return Efector::findOne((int) $this->id_efector);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function search($params): ActiveDataProvider
    {
        $query = ProfesionalEfectorServicio::find()->alias('pes');

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);
        $query->andFilterWhere([
            'pes.id_efector' => $this->id_efector,
            'pes.id_persona' => $this->id_persona,
        ]);
        if ($this->id_profesional_efector_servicio !== null && $this->id_profesional_efector_servicio !== '') {
            $query->andWhere(['pes.id' => (int) $this->id_profesional_efector_servicio]);
        }

        if ($this->deleted_at === 'null' || $this->deleted_at === null) {
            $query->andWhere(['pes.deleted_at' => null]);
        } else {
            $query->andWhere(['not', ['pes.deleted_at' => null]]);
        }

        if ($this->nombrePersona !== null && $this->nombrePersona !== '') {
            $query->joinWith(['persona' => function ($q) {
                $q->where(['like', 'CONCAT(personas.apellido," ",personas.nombre)', '%' . $this->nombrePersona . '%', false])
                    ->orWhere(['like', 'personas.nombre', '%' . $this->nombrePersona . '%', false])
                    ->orWhere(['like', 'personas.apellido', $this->nombrePersona . '%', false])
                    ->orWhere(['like', 'personas.documento', $this->nombrePersona . '%', false]);
            }]);
        }

        if ($this->nombreEfector !== null && $this->nombreEfector !== '') {
            $query->joinWith(['efector' => function ($q) {
                $q->where('efectores.nombre LIKE "%' . $this->nombreEfector . '%"');
            }]);
        }

        if ($this->idServicio !== '' && $this->idServicio !== null) {
            $query->andWhere(['pes.id_servicio' => (int) $this->idServicio]);
        }

        $query->with(['persona', 'efector', 'servicio']);

        return $dataProvider;
    }
}
