<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\NotFoundHttpException;
use common\models\Persona;
use common\models\Consulta;

class PersonaController extends BaseController
{
    public $modelClass = 'common\models\Persona';

    /**
     * Obtener personas
     */
    public function actionIndex()
    {
        $request = Yii::$app->request;
        $user = Yii::$app->user->identity;
        
        $query = Persona::find();
        
        // Aplicar filtros de búsqueda
        if ($search = $request->get('search')) {
            $query->andWhere([
                'or',
                ['like', 'nombre', $search],
                ['like', 'apellido', $search],
                ['like', 'documento', $search],
            ]);
        }

        // Paginación
        $page = (int)$request->get('page', 1);
        $perPage = (int)$request->get('per_page', 20);
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
                'pages' => ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * Obtener persona por ID
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
     * Obtener timeline de persona
     */
    public function actionTimeline($id)
    {
        $persona = Persona::findOne($id);
        if (!$persona) {
            return $this->error('Persona no encontrada', null, 404);
        }

        // Obtener consultas de la persona
        $consultas = Consulta::find()
            ->where(['persona_id' => $id])
            ->orderBy(['fecha_consulta' => SORT_DESC])
            ->all();

        $timeline = [];
        foreach ($consultas as $consulta) {
            $timeline[] = [
                'id' => $consulta->id_consulta,
                'tipo' => 'consulta',
                'fecha' => $consulta->fecha_consulta,
                'titulo' => 'Consulta Médica',
                'descripcion' => $consulta->motivo_consulta,
                'status' => $consulta->status,
            ];
        }

        return $this->success([
            'persona' => [
                'id' => $persona->id_persona,
                'nombre' => $persona->getNombreCompleto(),
                'documento' => $persona->documento,
            ],
            'timeline' => $timeline,
        ]);
    }

    /**
     * Crear nueva persona
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
     * Actualizar persona
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
     * Eliminar persona
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
