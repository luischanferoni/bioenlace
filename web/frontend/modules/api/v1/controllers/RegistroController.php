<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\components\Domain\Person\Service\RegistroService;
use common\components\Domain\Person\Service\RegistroStaffPacienteService;
use common\components\Domain\Integrations\Identity\DiditClient;

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
    /**
     * Acciones sin autenticación: permitimos que el registro se haga sin token.
     *
     * @var string[]
     */
    public static $authenticatorExcept = ['registrar', 'config-movil'];

    /**
     * Acciones staff autenticadas (registro paciente desde admin).
     *
     * @var string[]
     */
    public static $staffRegistroActions = [
        'registrar-como-staff',
        'preview-renaper-como-staff',
        'crear-sesion-didit-como-staff',
    ];

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
        $verbs['config-movil'] = ['GET', 'OPTIONS'];
        $verbs['registrar-como-staff'] = ['POST', 'OPTIONS'];
        $verbs['preview-renaper-como-staff'] = ['POST', 'OPTIONS'];
        $verbs['crear-sesion-didit-como-staff'] = ['POST', 'OPTIONS'];

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

    /**
     * GET /api/v1/registro/config-movil
     *
     * Config pública para apps móviles (workflow Didit). Sin auth.
     * Fuente: params-local `didit_paciente_kyc_workflow_id`.
     */
    public function actionConfigMovil(): array
    {
        $kyc = trim((string) (Yii::$app->params['didit_paciente_kyc_workflow_id'] ?? ''));
        if ($kyc === '') {
            return $this->error(
                'Registro móvil no configurado (didit_paciente_kyc_workflow_id).',
                null,
                503
            );
        }

        $biometric = trim((string) (Yii::$app->params['didit_paciente_biometric_workflow_id'] ?? ''));
        if ($biometric === '') {
            $biometric = $kyc;
        }

        return $this->success([
            'didit_paciente_kyc_workflow_id' => $kyc,
            'didit_paciente_biometric_workflow_id' => $biometric,
        ], 'Configuración móvil Didit', 200);
    }

    /**
     * POST /api/v1/registro/registrar-como-staff
     *
     * Alta de paciente por personal (admin): modo didit | dni_lector.
     */
    public function actionRegistrarComoStaff(): array
    {
        $bodyParams = Yii::$app->request->getBodyParams();
        $modo = trim((string) ($bodyParams['modo'] ?? ''));
        if ($modo === '') {
            return $this->error('El campo "modo" es requerido (didit | dni_lector).', null, 400);
        }

        try {
            $result = (new RegistroStaffPacienteService())->registrar($bodyParams);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            Yii::error('registrar-como-staff: ' . $e->getMessage(), 'registro');

            return $this->error('Error interno al registrar paciente.', null, 500);
        }

        return $this->success($result, 'Paciente registrado correctamente.', 201);
    }

    /**
     * POST /api/v1/registro/preview-renaper-como-staff
     *
     * Consulta RENAPER para previsualizar datos antes del alta (lector DNI).
     */
    public function actionPreviewRenaperComoStaff(): array
    {
        try {
            $result = (new RegistroStaffPacienteService())->previewRenaper(Yii::$app->request->getBodyParams());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Yii::error('preview-renaper-como-staff: ' . $e->getMessage(), 'registro');

            return $this->error('Error al consultar RENAPER.', null, 500);
        }

        return $this->success($result, 'Consulta RENAPER', 200);
    }

    /**
     * POST /api/v1/registro/crear-sesion-didit-como-staff
     *
     * Crea sesión Didit hosted para foto del DNI (sin lector).
     */
    public function actionCrearSesionDiditComoStaff(): array
    {
        $body = Yii::$app->request->getBodyParams();
        $callback = trim((string) ($body['callback'] ?? ''));

        $didit = Yii::$container->has(DiditClient::class)
            ? Yii::$container->get(DiditClient::class)
            : new DiditClient();

        $session = $didit->createVerificationSession([
            'callback' => $callback,
            'vendor_data' => 'staff-' . (int) Yii::$app->user->id,
            'language' => 'es',
        ]);

        if (empty($session['success'])) {
            return $this->error((string) ($session['message'] ?? 'No se pudo crear sesión Didit.'), null, 422);
        }

        return $this->success([
            'session_id' => $session['session_id'] ?? '',
            'url' => $session['url'] ?? '',
        ], 'Sesión Didit creada', 201);
    }
}

