<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use common\models\Cirugia;
use common\models\QuirofanoSala;
use common\components\Services\Quirofano\CirugiaAgendaService;
use common\components\Services\Quirofano\UserEfectorAccess;

/**
 * API agenda quirúrgica: salas (CRUD) y cirugías (alta/edición de agenda).
 *
 * Cirugía aquí = ítem de agenda (sala, horarios, estado operativo, vínculos operativos), análogo a turnos.
 * El informe o nota clínica del acto se registra como consulta en historia clínica, no en este recurso.
 */
class QuirofanoController extends BaseController
{
    /** @var CirugiaAgendaService */
    private $agenda;

    public function init()
    {
        parent::init();
        $this->agenda = new CirugiaAgendaService();
    }

    public function actionOptions(): array
    {
        // Preflight CORS: debe ser compatible con BaseController::actionOptions()
        return parent::actionOptions();
    }

    /**
     * GET/POST /api/v1/quirofano/salas
     */
    public function actionListarSalas()
    {
        $req = Yii::$app->request;
        try {
            if ($req->isGet) {
                $idEfector = (int) $req->get('id_efector');
                if (!$idEfector) {
                    throw new BadRequestHttpException('Parámetro id_efector requerido.');
                }
                UserEfectorAccess::requireEfectorAccess($idEfector);
                $rows = QuirofanoSala::find()
                    ->where(['id_efector' => $idEfector])
                    ->andWhere(['deleted_at' => null])
                    ->orderBy(['nombre' => SORT_ASC])
                    ->all();
                return $this->success(array_map([$this, 'serializeSala'], $rows));
            }
            if ($req->isPost) {
                $data = $req->getBodyParams();
                $idEfector = isset($data['id_efector']) ? (int) $data['id_efector'] : 0;
                UserEfectorAccess::requireEfectorAccess($idEfector);
                $model = new QuirofanoSala();
                $model->id_efector = $idEfector;
                $model->nombre = $data['nombre'] ?? '';
                $model->codigo = $data['codigo'] ?? null;
                $model->activo = array_key_exists('activo', $data) ? (bool) $data['activo'] : true;
                if (!$model->save()) {
                    return $this->error('No se pudo crear la sala.', $model->errors, 422);
                }
                return $this->success($this->serializeSala($model), 'Sala creada.', 201);
            }
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        } catch (BadRequestHttpException $e) {
            return $this->error($e->getMessage(), null, 400);
        }
        throw new BadRequestHttpException('Método no soportado.');
    }

    /**
     * GET /api/v1/quirofano/salas/<id>
     */
    public function actionVerSala($id)
    {
        try {
            $model = $this->findSala((int) $id);
            UserEfectorAccess::requireEfectorAccess((int) $model->id_efector);
            return $this->success($this->serializeSala($model));
        } catch (NotFoundHttpException $e) {
            return $this->error($e->getMessage(), null, 404);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        }
    }

    /**
     * PATCH /api/v1/quirofano/salas/<id>
     */
    public function actionActualizarSala($id)
    {
        try {
            $model = $this->findSala((int) $id);
            UserEfectorAccess::requireEfectorAccess((int) $model->id_efector);
            $data = Yii::$app->request->getBodyParams();
            if (isset($data['nombre'])) {
                $model->nombre = $data['nombre'];
            }
            if (array_key_exists('codigo', $data)) {
                $model->codigo = $data['codigo'];
            }
            if (array_key_exists('activo', $data)) {
                $model->activo = (bool) $data['activo'];
            }
            if (!$model->save()) {
                return $this->error('No se pudo actualizar la sala.', $model->errors, 422);
            }
            return $this->success($this->serializeSala($model), 'Sala actualizada.');
        } catch (NotFoundHttpException $e) {
            return $this->error($e->getMessage(), null, 404);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        }
    }

    /**
     * DELETE /api/v1/quirofano/salas/<id>
     */
    public function actionEliminarSala($id)
    {
        try {
            $model = $this->findSala((int) $id);
            UserEfectorAccess::requireEfectorAccess((int) $model->id_efector);
            if (!$model->softDelete()) {
                return $this->error('No se pudo eliminar la sala.', $model->errors, 422);
            }
            return $this->success(null, 'Sala eliminada.');
        } catch (NotFoundHttpException $e) {
            return $this->error($e->getMessage(), null, 404);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        }
    }

    /**
     * GET/POST /api/v1/quirofano/cirugias
     */
    public function actionListarCirugias()
    {
        $req = Yii::$app->request;
        try {
            if ($req->isGet) {
                $idEfector = (int) $req->get('id_efector');
                if (!$idEfector) {
                    throw new BadRequestHttpException('Parámetro id_efector requerido.');
                }
                UserEfectorAccess::requireEfectorAccess($idEfector);
                $fechaDesde = $req->get('fecha_desde', date('Y-m-d', strtotime('-7 days')));
                $fechaHasta = $req->get('fecha_hasta', date('Y-m-d', strtotime('+90 days')));
                $idSala = $req->get('id_quirofano_sala');
                $q = Cirugia::find()->alias('c')
                    ->innerJoin(['s' => QuirofanoSala::tableName()], 's.id = c.id_quirofano_sala')
                    ->where(['s.id_efector' => $idEfector])
                    ->andWhere(['s.deleted_at' => null])
                    ->andWhere(['>=', 'c.fecha_hora_inicio', $fechaDesde . ' 00:00:00'])
                    ->andWhere(['<=', 'c.fecha_hora_inicio', $fechaHasta . ' 23:59:59']);
                if ($idSala !== null && $idSala !== '') {
                    $q->andWhere(['c.id_quirofano_sala' => (int) $idSala]);
                }
                $rows = $q->orderBy(['c.fecha_hora_inicio' => SORT_ASC])->all();
                return $this->success(array_map([$this, 'serializeCirugia'], $rows));
            }
            if ($req->isPost) {
                $data = $req->getBodyParams();
                $model = new Cirugia();
                $model->load($data, '');
                if (!isset($data['estado']) || $data['estado'] === '') {
                    $model->estado = Cirugia::ESTADO_LISTA_ESPERA;
                }
                $this->normalizeOptionalIntAttrs($model);
                if (!$model->validate()) {
                    return $this->error('Validación fallida.', $model->errors, 422);
                }
                $sala = QuirofanoSala::find()->where(['id' => $model->id_quirofano_sala, 'deleted_at' => null])->one();
                if (!$sala) {
                    return $this->error('Sala no encontrada.', null, 404);
                }
                UserEfectorAccess::requireEfectorAccess((int) $sala->id_efector);
                if ($this->agenda->haySolapamientoParaCirugia($model, null)) {
                    throw new ConflictHttpException(Cirugia::MENSAJE_SOLAPAMIENTO_SALA);
                }
                if (!$model->save(false)) {
                    return $this->error('No se pudo crear la cirugía.', $model->errors, 422);
                }
                return $this->success($this->serializeCirugia($model), 'Cirugía creada.', 201);
            }
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        } catch (ConflictHttpException $e) {
            return $this->error($e->getMessage(), null, 409);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (BadRequestHttpException $e) {
            return $this->error($e->getMessage(), null, 400);
        }
        throw new BadRequestHttpException('Método no soportado.');
    }

    /**
     * GET /api/v1/quirofano/cirugias/<id>
     */
    public function actionVerCirugia($id)
    {
        try {
            $model = $this->findCirugia((int) $id);
            UserEfectorAccess::requireEfectorAccess($this->efectorIdFromCirugia($model));
            return $this->success($this->serializeCirugia($model));
        } catch (NotFoundHttpException $e) {
            return $this->error($e->getMessage(), null, 404);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        }
    }

    /**
     * PATCH /api/v1/quirofano/cirugias/<id>
     */
    public function actionActualizarCirugia($id)
    {
        try {
            $model = $this->findCirugia((int) $id);
            UserEfectorAccess::requireEfectorAccess($this->efectorIdFromCirugia($model));
            $data = Yii::$app->request->getBodyParams();
            $attrs = [
                'id_quirofano_sala', 'id_persona', 'id_seg_nivel_internacion', 'id_practica',
                'fecha_hora_inicio', 'fecha_hora_fin_estimada',
            ];
            foreach ($attrs as $attr) {
                if (array_key_exists($attr, $data)) {
                    $model->$attr = $data[$attr];
                }
            }
            if (array_key_exists('estado', $data)) {
                $this->agenda->applyEstado($model, (string) $data['estado']);
            }
            $this->normalizeOptionalIntAttrs($model);
            if (!$model->validate()) {
                return $this->error('Validación fallida.', $model->errors, 422);
            }
            $sala = QuirofanoSala::find()->where(['id' => $model->id_quirofano_sala, 'deleted_at' => null])->one();
            if (!$sala) {
                return $this->error('Sala no encontrada.', null, 404);
            }
            UserEfectorAccess::requireEfectorAccess((int) $sala->id_efector);
            if ($this->agenda->haySolapamientoParaCirugia($model, (int) $model->id)) {
                throw new ConflictHttpException(Cirugia::MENSAJE_SOLAPAMIENTO_SALA);
            }
            if (!$model->save(false)) {
                return $this->error('No se pudo actualizar la cirugía.', $model->errors, 422);
            }
            return $this->success($this->serializeCirugia($model), 'Cirugía actualizada.');
        } catch (NotFoundHttpException $e) {
            return $this->error($e->getMessage(), null, 404);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        } catch (ConflictHttpException $e) {
            return $this->error($e->getMessage(), null, 409);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        }
    }

    /**
     * PATCH /api/v1/quirofano/cirugias/<id>/estado
     */
    public function actionEstadoCirugia($id)
    {
        try {
            $model = $this->findCirugia((int) $id);
            UserEfectorAccess::requireEfectorAccess($this->efectorIdFromCirugia($model));
            $data = Yii::$app->request->getBodyParams();
            $nuevo = $data['estado'] ?? null;
            if (!$nuevo || !is_string($nuevo)) {
                throw new BadRequestHttpException('Campo estado requerido.');
            }
            $viejo = $model->estado;
            $this->agenda->applyEstado($model, $nuevo);
            if (!$model->validate()) {
                $model->estado = $viejo;
                return $this->error('Validación fallida.', $model->errors, 422);
            }
            $sala = QuirofanoSala::find()->where(['id' => $model->id_quirofano_sala, 'deleted_at' => null])->one();
            if (!$sala) {
                return $this->error('Sala no encontrada.', null, 404);
            }
            if ($this->agenda->haySolapamientoParaCirugia($model, (int) $model->id)) {
                throw new ConflictHttpException(Cirugia::MENSAJE_SOLAPAMIENTO_SALA);
            }
            if (!$model->save(false)) {
                $model->estado = $viejo;
                return $this->error('No se pudo actualizar el estado.', $model->errors, 422);
            }
            return $this->success($this->serializeCirugia($model), 'Estado actualizado.');
        } catch (NotFoundHttpException $e) {
            return $this->error($e->getMessage(), null, 404);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (ConflictHttpException $e) {
            return $this->error($e->getMessage(), null, 409);
        } catch (BadRequestHttpException $e) {
            return $this->error($e->getMessage(), null, 400);
        }
    }

    private function findSala(int $id): QuirofanoSala
    {
        $m = QuirofanoSala::find()->where(['id' => $id])->andWhere(['deleted_at' => null])->one();
        if (!$m) {
            throw new NotFoundHttpException('Sala no encontrada.');
        }
        return $m;
    }

    private function findCirugia(int $id): Cirugia
    {
        $m = Cirugia::findOne($id);
        if (!$m) {
            throw new NotFoundHttpException('Cirugía no encontrada.');
        }
        return $m;
    }

    private function efectorIdFromCirugia(Cirugia $c): int
    {
        $sala = $c->sala;
        if (!$sala || $sala->deleted_at !== null) {
            throw new NotFoundHttpException('Sala asociada no disponible.');
        }
        return (int) $sala->id_efector;
    }

    private function serializeSala(QuirofanoSala $m): array
    {
        return [
            'id' => (int) $m->id,
            'id_efector' => (int) $m->id_efector,
            'nombre' => $m->nombre,
            'codigo' => $m->codigo,
            'activo' => (bool) $m->activo,
        ];
    }

    private function normalizeOptionalIntAttrs(Cirugia $model): void
    {
        foreach (['id_seg_nivel_internacion', 'id_practica'] as $attr) {
            if ($model->$attr === '' || $model->$attr === false) {
                $model->$attr = null;
            }
        }
    }

    private function serializeCirugia(Cirugia $m): array
    {
        $idEfector = null;
        if ($m->sala && $m->sala->deleted_at === null) {
            $idEfector = (int) $m->sala->id_efector;
        }
        return [
            'id' => (int) $m->id,
            'id_efector' => $idEfector,
            'id_quirofano_sala' => (int) $m->id_quirofano_sala,
            'id_persona' => (int) $m->id_persona,
            'id_seg_nivel_internacion' => $m->id_seg_nivel_internacion !== null ? (int) $m->id_seg_nivel_internacion : null,
            'id_practica' => $m->id_practica !== null ? (int) $m->id_practica : null,
            'procedimiento_descripcion' => $m->procedimiento_descripcion,
            'observaciones' => $m->observaciones,
            'estado' => $m->estado,
            'estado_label' => $m->getEstadoLabel(),
            'fecha_hora_inicio' => $m->fecha_hora_inicio,
            'fecha_hora_fin_estimada' => $m->fecha_hora_fin_estimada,
        ];
    }
}
