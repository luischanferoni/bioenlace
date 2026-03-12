<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\components\RegistroService;

/**
 * Controlador de registro unificado para pacientes y médicos.
 *
 * Endpoint principal:
 *
 * POST /api/v1/registro/registrar
 *
 * Payload esperado (JSON o x-www-form-urlencoded):
 *
 * {
 *   "tipo": "paciente" | "medico",
 *   "verification_id": "didit-verification-id",
 *   "fecha_nacimiento": "1984-01-01",   // opcional, solo si no viene de Didit
 *   "email": "persona@example.com",     // opcional según caso de uso
 *   "telefono": "+54...",              // opcional
 *   "extras": { ... }                  // opcional, datos adicionales
 * }
 *
 * Respuesta (éxito):
 *
 * {
 *   "success": true,
 *   "message": "Persona registrada exitosamente",
 *   "data": {
 *     "persona": {
 *       "id_persona": 123,
 *       "nombre": "Mercedes",
 *       "apellido": "Diaz",
 *       "documento": "29486884",
 *       "fecha_nacimiento": "1984-01-01",
 *       "tipo": "paciente"
 *     },
 *     "didit": {
 *       "verification_id": "...",
 *       "status": "approved" | "rejected" | "pending"
 *     },
 *     "mpi": {
 *       "empadronado": true,
 *       "detalles": { ... }
 *     },
 *     "refeps": {
 *       "es_profesional": true,
 *       "detalles": { ... }
 *     }
 *   }
 * }
 *
 * Respuesta (error):
 *
 * {
 *   "success": false,
 *   "message": "Motivo del error",
 *   "errors": { ... } // detalles opcionales
 * }
 */
class RegistroController extends BaseController
{
    /**
     * Este controlador trabaja conceptualmente sobre el modelo Persona.
     *
     * @var string
     */
    public $modelClass = 'common\models\Persona';

    /**
     * Acciones sin autenticación: permitimos que el registro se haga sin token.
     *
     * @var string[]
     */
    public static $authenticatorExcept = ['registrar'];

    /**
     * Deshabilitamos las acciones REST por defecto; usamos acciones personalizadas.
     *
     * @return array
     */
    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update'], $actions['delete']);
        return $actions;
    }

    /**
     * Definición de verbs para el endpoint.
     *
     * @return array
     */
    protected function verbs()
    {
        $verbs = parent::verbs();
        $verbs['registrar'] = ['POST', 'OPTIONS'];
        return $verbs;
    }

    /**
     * Endpoint unificado de registro de pacientes y médicos utilizando Didit.
     *
     * Ruta: POST /api/v1/registro/registrar
     *
     * - Valida parámetros mínimos del request.
     * - Delegará la lógica de orquestación a un servicio de registro
     *   (por ejemplo, common\components\RegistroService), que se encargará de:
     *      * llamar a Verifik,
     *      * crear/actualizar Persona en la base,
     *      * sincronizar con MPI,
     *      * y, en caso de médicos, validar contra REFEPS.
     *
     * Por ahora solo valida y estructura el contrato de entrada/salida;
     * la integración concreta se añadirá en pasos posteriores.
     *
     * @return array
     */
    public function actionRegistrar()
    {
        $request = Yii::$app->request;
        $bodyParams = $request->getBodyParams();

        $tipo = $bodyParams['tipo'] ?? null;
        $verificationId = $bodyParams['verification_id'] ?? null;

        if (!$tipo || !in_array($tipo, ['paciente', 'medico'], true)) {
            return $this->error('El campo "tipo" es requerido y debe ser "paciente" o "medico".', null, 400);
        }

        if (!$verificationId) {
            return $this->error('El campo "verification_id" (Didit) es requerido.', null, 400);
        }

        /** @var RegistroService $service */
        $service = Yii::$container->has(RegistroService::class)
            ? Yii::$container->get(RegistroService::class)
            : new RegistroService();

        try {
            $result = $service->registrar($bodyParams);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            Yii::error('Error inesperado en RegistroService: ' . $e->getMessage(), 'registro');
            return $this->error('Error interno al procesar el registro', null, 500);
        }

        return $this->success(
            $result,
            'Solicitud de registro recibida correctamente',
            202
        );
    }
}

