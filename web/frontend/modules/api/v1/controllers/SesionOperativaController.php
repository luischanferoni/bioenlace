<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\models\User;
use common\components\Services\Rrhh\RrhhHabilitacionService;
use common\components\Services\SesionOperativa\SesionOperativaService;

/**
 * API Sesión Operativa: contexto operativo en sesión y opciones validadas para el wizard.
 *
 * POST /api/v1/sesion-operativa/establecer
 * Header opcional: X-Client: web | mobile (en mobile: Médico, Enfermería o AdminEfector, salvo superadmin).
 *
 * Sin selección (sin cuerpo, JSON vacío o faltan efector_id / servicio_id / encounter_class):
 *   respuesta con encounter_classes, efectores (con servicios validados), efectores_con_problemas.
 *
 * Con body completo: { "efector_id": 123, "servicio_id": 456, "encounter_class": "AMB" }
 *   mismo comportamiento que SesionOperativaService::establecer (sesión + redirect_url + context_token).
 */
class SesionOperativaController extends BaseController
{
    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update']);
        return $actions;
    }

    public function actionEstablecer()
    {
        $client = strtolower((string) Yii::$app->request->headers->get('X-Client', 'web'));
        if ($client === 'mobile' && !Yii::$app->user->isSuperadmin) {
            if (!User::hasRole(['Medico', 'Enfermeria', 'AdminEfector'])) {
                return $this->error(
                    'La aplicación móvil no está disponible para este rol.',
                    null,
                    403
                );
            }
        }

        $body = [];
        try {
            $body = Yii::$app->request->bodyParams;
            if (!is_array($body)) {
                $body = [];
            }

            if ($this->isModoOpciones($body)) {
                /** @var RrhhHabilitacionService $hab */
                $hab = Yii::$container->has(RrhhHabilitacionService::class)
                    ? Yii::$container->get(RrhhHabilitacionService::class)
                    : new RrhhHabilitacionService();

                $idPersona = (int) Yii::$app->user->getIdPersona();
                $data = $hab->buildOpcionesIniciales($idPersona);
                $hab->syncSessionEfectoresDesdeOpciones($data['efectores']);

                $msg = 'Opciones de sesión operativa';
                if (($data['efectores'] ?? []) === []) {
                    $msg = 'No hay efectores con configuración completa para operar en este momento.';
                }

                return $this->success($data, $msg);
            }

            /** @var SesionOperativaService $service */
            $service = Yii::$container->has(SesionOperativaService::class)
                ? Yii::$container->get(SesionOperativaService::class)
                : new SesionOperativaService();

            $data = $service->establecer($body);

            return $this->success($data, 'Sesión operativa establecida correctamente');
        } catch (\InvalidArgumentException $e) {
            return $this->errorFijarSesionOperativa(
                $e->getMessage(),
                400,
                $this->resolveContactFromBody($body)
            );
        } catch (\RuntimeException $e) {
            return $this->errorFijarSesionOperativa(
                $e->getMessage(),
                404,
                $this->resolveContactFromBody($body)
            );
        } catch (\Throwable $e) {
            Yii::error('Error estableciendo sesión operativa: ' . $e->getMessage());
            return $this->error('Error al establecer sesión operativa', null, 500);
        }
    }

    /**
     * Respuesta de error al modo «fijar contexto»; añade `contact` solo en este flujo cuando aplica.
     *
     * @param list<array{nombre_completo:string}>|null $contact
     */
    private function errorFijarSesionOperativa(string $message, int $code, ?array $contact): array
    {
        $out = $this->error($message, null, $code);
        if ($contact !== null) {
            $out['contact'] = $contact;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function isModoOpciones(array $body): bool
    {
        $ef = $body['efector_id'] ?? $body['id_efector'] ?? null;
        $sv = $body['servicio_id'] ?? null;
        $ec = $body['encounter_class'] ?? null;

        if ($ef !== null && $ef !== '' && $sv !== null && $sv !== '' && $ec !== null && $ec !== '') {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $body
     * @return list<array{nombre_completo:string}>|null
     */
    private function resolveContactFromBody(array $body): ?array
    {
        $ef = $body['efector_id'] ?? $body['id_efector'] ?? null;
        if ($ef === null || $ef === '') {
            return null;
        }
        $idEfector = (int) $ef;
        if ($idEfector <= 0) {
            return null;
        }

        /** @var RrhhHabilitacionService $hab */
        $hab = Yii::$container->has(RrhhHabilitacionService::class)
            ? Yii::$container->get(RrhhHabilitacionService::class)
            : new RrhhHabilitacionService();

        return $hab->contactForEfectorPayload($idEfector);
    }
}
