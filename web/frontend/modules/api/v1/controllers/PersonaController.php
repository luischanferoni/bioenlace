<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\models\Persona;
use common\components\PersonaTimelineService;

/**
 * API Persona: CRUD y timeline (historia clínica).
 * Lógica migrada desde frontend\controllers\PersonaController.
 */
class PersonaController extends BaseController
{
    public static $authenticatorExcept = ['timeline'];

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update'], $actions['delete']);
        return $actions;
    }

    /**
     * GET /api/v1/persona/index
     */
    public function actionIndex()
    {
        $request = Yii::$app->request;
        $query = Persona::find();
        if ($search = $request->get('search')) {
            $query->andWhere([
                'or',
                ['like', 'nombre', $search],
                ['like', 'apellido', $search],
                ['like', 'documento', $search],
            ]);
        }
        $page = (int) $request->get('page', 1);
        $perPage = (int) $request->get('per_page', 20);
        $offset = ($page - 1) * $perPage;
        $total = $query->count();
        $personas = $query->offset($offset)->limit($perPage)->all();
        $formattedPersonas = [];
        foreach ($personas as $persona) {
            $formattedPersonas[] = [
                'id' => $persona->id_persona,
                'nombre' => $persona->getNombreCompleto(),
                'documento' => $persona->documento,
                'edad' => $persona->edad,
                'telefono' => $persona->telefono,
                'email' => $persona->email,
                'created_at' => $persona->created_at,
            ];
        }
        return $this->success([
            'personas' => $formattedPersonas,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 0,
            ],
        ]);
    }

    /**
     * GET /api/v1/persona/view?id=...
     */
    public function actionView($id)
    {
        $persona = Persona::findOne($id);
        if (!$persona) {
            return $this->error('Persona no encontrada', null, 404);
        }
        return $this->success([
            'id' => $persona->id_persona,
            'nombre' => $persona->getNombreCompleto(),
            'documento' => $persona->documento,
            'fecha_nacimiento' => $persona->fecha_nacimiento,
            'edad' => $persona->edad,
            'sexo' => $persona->sexo,
            'telefono' => $persona->telefono,
            'email' => $persona->email,
            'direccion' => $persona->direccion,
            'created_at' => $persona->created_at,
        ]);
    }

    /**
     * GET /api/v1/persona/timeline?id=...
     */
    public function actionTimeline($id)
    {
        $idEfector = null;
        if (method_exists(Yii::$app->user, 'getIdEfector')) {
            $idEfector = Yii::$app->user->getIdEfector();
        }
        $data = PersonaTimelineService::buildTimelineData($id, $idEfector);
        if ($data === null) {
            return $this->error('Persona no encontrada', null, 404);
        }
        return $this->success($data);
    }

    /**
     * POST /api/v1/persona/create
     */
    public function actionCreate()
    {
        $request = Yii::$app->request;
        $persona = new Persona();
        $persona->load($request->post(), '');
        $persona->created_at = date('Y-m-d H:i:s');
        if (!$persona->save()) {
            return $this->error('Error creando persona', $persona->getErrors(), 422);
        }
        return $this->success([
            'id' => $persona->id_persona,
            'nombre' => $persona->getNombreCompleto(),
        ], 'Persona creada exitosamente', 201);
    }

    /**
     * PUT/PATCH /api/v1/persona/update?id=...
     */
    public function actionUpdate($id)
    {
        $persona = Persona::findOne($id);
        if (!$persona) {
            return $this->error('Persona no encontrada', null, 404);
        }
        $request = Yii::$app->request;
        $persona->load($request->post(), '');
        $persona->updated_at = date('Y-m-d H:i:s');
        if (!$persona->save()) {
            return $this->error('Error actualizando persona', $persona->getErrors(), 422);
        }
        return $this->success([
            'id' => $persona->id_persona,
            'nombre' => $persona->getNombreCompleto(),
        ], 'Persona actualizada exitosamente');
    }

    /**
     * DELETE /api/v1/persona/delete?id=...
     */
    public function actionDelete($id)
    {
        $persona = Persona::findOne($id);
        if (!$persona) {
            return $this->error('Persona no encontrada', null, 404);
        }
        if (!$persona->delete()) {
            return $this->error('Error eliminando persona', null, 500);
        }
        return $this->success(null, 'Persona eliminada exitosamente');
    }
}
