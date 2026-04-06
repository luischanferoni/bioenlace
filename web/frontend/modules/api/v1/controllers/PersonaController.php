<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\models\Persona;
use common\components\Services\Persona\PersonaSignosVitalesService;
// Nota: la historia clínica agregada vive en PacientesController (persona en rol paciente).

/**
 * API Persona: CRUD y signos vitales. Historia clínica: {@see PacientesController::actionHistoriaClinica}.
 * Lógica migrada desde frontend\controllers\PersonaController.
 */
class PersonaController extends BaseController
{
    public static $authenticatorExcept = [];

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update'], $actions['delete']);
        return $actions;
    }

    /**
     * GET /api/v1/persona/index
     */
    public function actionListar()
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
                'nombre' => $persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D),
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
    public function actionVer($id)
    {
        $persona = Persona::findOne($id);
        if (!$persona) {
            return $this->error('Persona no encontrada', null, 404);
        }
        return $this->success([
            'id' => $persona->id_persona,
            'nombre' => $persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D),
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
     * GET /api/v1/personas/{id}/signos-vitales
     * Permiso RBAC: /api/persona/signos-vitales
     * Query opcional (solo YII_DEBUG): simular_signos=1
     */
    public function actionSignosVitales($id)
    {
        $persona = Persona::findOne($id);
        if (!$persona) {
            return $this->error('Persona no encontrada', null, 404);
        }

        $simular = false;
        if (defined('YII_DEBUG') && YII_DEBUG) {
            $simular = (bool) Yii::$app->request->get('simular_signos', false);
        }

        $service = new PersonaSignosVitalesService();
        $payload = $service->getSignosVitalesData($persona, $simular);

        return $this->success($payload, 'Signos vitales obtenidos');
    }

    // Historia clínica: GET /api/v1/personas/{id}/historia-clinica → PacientesController::actionHistoriaClinica

    /**
     * POST /api/v1/persona/create
     */
    public function actionCrear()
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
            'nombre' => $persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D),
        ], 'Persona creada exitosamente', 201);
    }

    /**
     * PUT/PATCH /api/v1/persona/update?id=...
     */
    public function actionActualizar($id)
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
            'nombre' => $persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D),
        ], 'Persona actualizada exitosamente');
    }

    /**
     * DELETE /api/v1/persona/delete?id=...
     */
    public function actionEliminar($id)
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
