<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\models\SegNivelInternacion;
use common\models\Guardia;
use common\models\Persona;
use common\models\InfraestructuraCama;
use common\models\InfraestructuraSala;
use common\models\InfraestructuraPiso;

/**
 * API Listado: internación y guardia.
 * Lógica migrada desde frontend\controllers\ListadoController.
 */
class ListadoController extends BaseController
{
    public $modelClass = 'common\models\User';

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update'], $actions['delete']);
        return $actions;
    }

    /**
     * GET /api/v1/listado/internacion
     * Listado de pacientes internados (encounter IMP). Parámetros: efector_id (recomendado).
     */
    public function actionInternacion()
    {
        $request = Yii::$app->request;
        $user = Yii::$app->user->identity;

        if (!$user) {
            return $this->error('Usuario no autenticado.', null, 401);
        }

        $efectorId = (int) $request->get('efector_id', 0);
        if (!$efectorId && method_exists(Yii::$app->user, 'getIdEfector')) {
            $efectorId = (int) Yii::$app->user->getIdEfector();
        }
        if (!$efectorId) {
            return $this->success(['items' => []], 'Indique efector_id o configure sesión.');
        }

        $query = SegNivelInternacion::find()
            ->alias('i')
            ->innerJoin(InfraestructuraCama::tableName() . ' c', 'c.id = i.id_cama')
            ->innerJoin(InfraestructuraSala::tableName() . ' s', 's.id = c.id_sala')
            ->innerJoin(InfraestructuraPiso::tableName() . ' p', 'p.id = s.id_piso')
            ->andWhere(['p.id_efector' => $efectorId])
            ->andWhere(['is', 'i.fecha_fin', null])
            ->with(['paciente', 'cama.sala.piso'])
            ->orderBy(['i.fecha_inicio' => SORT_DESC]);

        $internaciones = $query->all();
        $items = [];
        foreach ($internaciones as $i) {
            $paciente = $i->paciente;
            $cama = $i->cama;
            $items[] = [
                'id' => (int) $i->id,
                'id_persona' => (int) $i->id_persona,
                'nombre_completo' => $paciente ? $paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D) : 'Sin nombre',
                'documento' => $paciente ? $paciente->documento : null,
                'fecha_inicio' => $i->fecha_inicio,
                'hora_inicio' => $i->hora_inicio,
                'cama' => $cama ? $cama->nro_cama : null,
                'sala' => $cama && $cama->sala ? $cama->sala->nro_sala : null,
                'piso' => $cama && $cama->sala && $cama->sala->piso ? $cama->sala->piso->nro_piso : null,
            ];
        }

        return $this->success(['items' => $items]);
    }

    /**
     * GET /api/v1/listado/guardia
     * Listado de ingresos en guardia (encounter EMER). Parámetros: efector_id (recomendado).
     */
    public function actionGuardia()
    {
        $request = Yii::$app->request;
        $user = Yii::$app->user->identity;

        if (!$user) {
            return $this->error('Usuario no autenticado.', null, 401);
        }

        $efectorId = (int) $request->get('efector_id', 0);
        if (!$efectorId && method_exists(Yii::$app->user, 'getIdEfector')) {
            $efectorId = (int) Yii::$app->user->getIdEfector();
        }
        if (!$efectorId) {
            return $this->success(['items' => []], 'Indique efector_id o configure sesión.');
        }

        $guardias = Guardia::find()
            ->andWhere(['id_efector' => $efectorId])
            ->andWhere(['estado' => Guardia::ESTADO_PENDIENTE])
            ->andWhere(['is', 'deleted_at', null])
            ->andWhere(['>=', 'fecha', date('Y-m-d')])
            ->with(['paciente'])
            ->orderBy(['fecha' => SORT_DESC, 'hora' => SORT_DESC])
            ->all();

        $items = [];
        foreach ($guardias as $g) {
            $paciente = $g->paciente;
            $items[] = [
                'id' => (int) $g->id,
                'id_persona' => (int) $g->id_persona,
                'nombre_completo' => $paciente ? $paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N) : 'Sin nombre',
                'documento' => $paciente ? $paciente->documento : null,
                'fecha' => $g->fecha,
                'hora' => $g->hora,
                'estado' => $g->estado,
            ];
        }

        return $this->success(['items' => $items]);
    }
}
