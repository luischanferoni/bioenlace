<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\models\ConsultasConfiguracion;
use common\models\RrhhEfector;
use common\models\RrhhServicio;

class ConfigController extends BaseController
{
    // ModelClass requerido por ActiveController, aunque no lo usemos directamente
    public $modelClass = 'common\models\User';
    
    /**
     * Sobrescribir behaviors para permitir acceso sin autenticación a algunos endpoints
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        // Permitir acceso sin autenticación a encounter-classes (es una lista estática)
        // También permitir efectores, servicios y set-session (se manejará la autenticación manualmente si es necesario)
        $behaviors['authenticator']['except'] = ['options', 'encounter-classes', 'efectores', 'servicios', 'set-session'];
        
        // Asegurar formato JSON siempre
        $behaviors['contentNegotiator'] = [
            'class' => \yii\filters\ContentNegotiator::class,
            'formats' => [
                'application/json' => \yii\web\Response::FORMAT_JSON,
            ],
        ];
        
        return $behaviors;
    }
    
    /**
     * Manejar errores de autenticación devolviendo JSON
     */
    public function beforeAction($action)
    {
        // Forzar formato JSON antes de cualquier acción
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        // Manejar excepciones de autenticación
        try {
            return parent::beforeAction($action);
        } catch (\yii\web\UnauthorizedHttpException $e) {
            // Si es una excepción de autenticación, devolver JSON en lugar de HTML
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            Yii::$app->response->statusCode = 401;
            Yii::$app->response->data = [
                'success' => false,
                'message' => $e->getMessage() ?: 'Usuario no autenticado',
                'errors' => null,
            ];
            Yii::$app->response->send();
            return false;
        }
    }

    /**
     * Obtener efectores del usuario autenticado
     * GET /api/v1/config/efectores?user_id=5748 (para desarrollo)
     */
    public function actionEfectores()
    {
        // Forzar formato JSON
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $request = Yii::$app->request;
        $user = Yii::$app->user->identity;
        
        // Para desarrollo: permitir usar user_id directamente si no hay autenticación válida
        $userId = null;
        if (!$user) {
            $userId = $request->get('user_id');
            if ($userId) {
                // Buscar usuario directamente
                $user = \webvimark\modules\UserManagement\models\User::findOne($userId);
                if ($user) {
                    // Simular login para este usuario
                    Yii::$app->user->login($user);
                }
            }
        }
        
        if (!$user) {
            return $this->error('Usuario no autenticado. Verifique que el token sea válido o proporcione user_id para desarrollo.', null, 401);
        }

        try {
            // Obtener efectores desde sesión si están disponibles
            $efectores = Yii::$app->user->getEfectores();
            
            // Si no hay efectores en sesión, obtenerlos directamente de la base de datos
            if (empty($efectores)) {
                $idPersona = Yii::$app->user->getIdPersona();
                
                if (!$idPersona) {
                    // Intentar obtener idPersona desde la persona asociada al usuario
                    $persona = \common\models\Persona::findOne(['id_user' => $user->id]);
                    if ($persona) {
                        $idPersona = $persona->id_persona;
                        // Guardar en sesión para próximas consultas
                        Yii::$app->session->set('idPersona', $idPersona);
                    }
                }
                
                if ($idPersona) {
                    $rrhhEfectores = \common\models\RrhhEfector::getEfectores($idPersona);
                    if (!empty($rrhhEfectores)) {
                        Yii::$app->user->setEfectores($rrhhEfectores);
                        $efectores = $rrhhEfectores;
                    }
                }
            }
            
            if (empty($efectores)) {
                return $this->error('No se encontraron efectores asignados para este usuario', null, 404);
            }

            // Formatear efectores para la respuesta
            $formattedEfectores = [];
            
            // Si getEfectores() devuelve un array de arrays (formato de RrhhEfector::getEfectores)
            if (isset($efectores[0]) && is_array($efectores[0])) {
                foreach ($efectores as $efector) {
                    $formattedEfectores[] = [
                        'id_efector' => (int)$efector['id_efector'],
                        'id' => (int)$efector['id_efector'],
                        'nombre' => (string)$efector['nombre'],
                        'id_localidad' => isset($efector['id_localidad']) ? (int)$efector['id_localidad'] : null,
                    ];
                }
            } else {
                // Si es un array asociativo [id_efector => nombre] (formato de sesión)
                foreach ($efectores as $idEfector => $nombre) {
                    $formattedEfectores[] = [
                        'id_efector' => (int)$idEfector,
                        'id' => (int)$idEfector,
                        'nombre' => (string)$nombre,
                    ];
                }
            }

            return $this->success([
                'efectores' => $formattedEfectores,
            ]);
        } catch (\Exception $e) {
            Yii::error("Error obteniendo efectores: " . $e->getMessage());
            Yii::error("Stack trace: " . $e->getTraceAsString());
            return $this->error('Error al obtener efectores: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Obtener servicios de un efector específico
     * GET /api/v1/config/servicios?efector_id=123&user_id=5748 (para desarrollo)
     */
    public function actionServicios()
    {
        // Forzar formato JSON
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $request = Yii::$app->request;
        $efectorId = $request->get('efector_id');
        
        if (!$efectorId) {
            return $this->error('El parámetro efector_id es requerido', null, 400);
        }

        $user = Yii::$app->user->identity;
        
        // Para desarrollo: permitir usar user_id directamente si no hay autenticación válida
        $userId = null;
        if (!$user) {
            $userId = $request->get('user_id');
            if ($userId) {
                // Buscar usuario directamente
                $user = \webvimark\modules\UserManagement\models\User::findOne($userId);
                if ($user) {
                    // Simular login para este usuario
                    Yii::$app->user->login($user);
                }
            }
        }
        
        if (!$user) {
            return $this->error('Usuario no autenticado. Verifique que el token sea válido o proporcione user_id para desarrollo.', null, 401);
        }

        // Obtener el rrhh_efector para este usuario y efector
        $rrhhEfector = RrhhEfector::find()
            ->where([
                'id_efector' => $efectorId,
                'id_persona' => Yii::$app->user->getIdPersona()
            ])
            ->one();

        if (!$rrhhEfector) {
            return $this->error('No se encontró relación con el efector especificado', null, 404);
        }

        // Obtener servicios del efector
        $servicios = [];
        foreach ($rrhhEfector->rrhhServicio as $rrhhServicio) {
            $servicios[] = [
                'id' => $rrhhServicio->id_servicio,
                'nombre' => $rrhhServicio->servicio->nombre,
                'id_rrhh_servicio' => $rrhhServicio->id,
            ];
        }

        return $this->success([
            'servicios' => $servicios,
        ]);
    }

    /**
     * Obtener encounter classes disponibles
     * GET /api/v1/config/encounter-classes
     * No requiere autenticación (lista estática)
     */
    public function actionEncounterClasses()
    {
        // Forzar formato JSON
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $encounterClasses = ConsultasConfiguracion::ENCOUNTER_CLASS;
        
        $formatted = [];
        foreach ($encounterClasses as $code => $label) {
            $formatted[] = [
                'code' => $code,
                'label' => $label,
            ];
        }

        return $this->success([
            'encounter_classes' => $formatted,
        ]);
    }

    /**
     * Establecer configuración de sesión (efector, servicio, encounter class)
     * POST /api/v1/config/set-session
     * Body: { "efector_id": 123, "servicio_id": 456, "encounter_class": "AMB", "user_id": 5748 } (user_id opcional para desarrollo)
     */
    public function actionSetSession()
    {
        // Forzar formato JSON
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $request = Yii::$app->request;
        $user = Yii::$app->user->identity;
        
        // Para desarrollo: permitir usar user_id directamente si no hay autenticación válida
        $userId = null;
        if (!$user) {
            $userId = $request->post('user_id');
            if ($userId) {
                // Buscar usuario directamente
                $user = \webvimark\modules\UserManagement\models\User::findOne($userId);
                if ($user) {
                    // Simular login para este usuario
                    Yii::$app->user->login($user);
                }
            }
        }
        
        if (!$user) {
            return $this->error('Usuario no autenticado. Verifique que el token sea válido o proporcione user_id para desarrollo.', null, 401);
        }

        $efectorId = $request->post('efector_id');
        $servicioId = $request->post('servicio_id');
        $encounterClass = $request->post('encounter_class');

        if (!$efectorId || !$servicioId || !$encounterClass) {
            return $this->error('Todos los parámetros son requeridos: efector_id, servicio_id, encounter_class', null, 400);
        }

        // Validar encounter class
        $validEncounterClasses = array_keys(ConsultasConfiguracion::ENCOUNTER_CLASS);
        if (!in_array($encounterClass, $validEncounterClasses)) {
            return $this->error('Encounter class inválido', null, 400);
        }

        // Obtener rrhh_efector
        $rrhhEfector = RrhhEfector::find()
            ->where([
                'id_efector' => $efectorId,
                'id_persona' => Yii::$app->user->getIdPersona()
            ])
            ->one();

        if (!$rrhhEfector) {
            return $this->error('No se encontró relación con el efector especificado', null, 404);
        }

        // Validar que el servicio pertenezca al efector
        $rrhhServicio = RrhhServicio::find()
            ->where([
                'id_servicio' => $servicioId,
                'id_rr_hh' => $rrhhEfector->id_rr_hh
            ])
            ->one();

        if (!$rrhhServicio) {
            return $this->error('El servicio especificado no está disponible para este efector', null, 400);
        }

        // Establecer en sesión (similar a SiteController::actionEstablecerSessionFinal)
        Yii::$app->user->setIdEfector($rrhhEfector->id_efector);
        Yii::$app->user->setNombreEfector($rrhhEfector->efector->nombre);
        Yii::$app->user->setIdRecursoHumano($rrhhEfector->id_rr_hh);
        Yii::$app->user->setServicioActual($servicioId);
        Yii::$app->user->setIdRrhhServicio($rrhhServicio->id);
        Yii::$app->user->setEncounterClass($encounterClass);
        
        // Establecer servicios disponibles
        Yii::$app->user->setServicios(\yii\helpers\ArrayHelper::map($rrhhEfector->rrhhServicio, 'id_servicio', 'servicio.nombre'));

        return $this->success([
            'efector' => [
                'id' => (int)$rrhhEfector->id_efector,
                'nombre' => (string)$rrhhEfector->efector->nombre,
            ],
            'servicio' => [
                'id' => (int)$servicioId,
                'nombre' => (string)$rrhhServicio->servicio->nombre,
                'id_rrhh_servicio' => (int)$rrhhServicio->id,
            ],
            'encounter_class' => [
                'code' => (string)$encounterClass,
                'label' => (string)ConsultasConfiguracion::ENCOUNTER_CLASS[$encounterClass],
            ],
            'rrhh_id' => (int)$rrhhEfector->id_rr_hh,
        ], 'Configuración establecida correctamente');
    }
}

