<?php

namespace frontend\modules\api\v1\controllers;

use common\components\Domain\Organization\Service\Billing\BillingMembershipSwitchService;
use common\components\Domain\Organization\Service\Billing\InstitutionalEfectorSignupService;
use common\components\Domain\Organization\Service\Billing\MinistrySignupRequestService;
use common\models\User;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

/**
 * Onboarding comercial de licencia (institucional + AdminEfector).
 *
 * Públicas (sin auth): catalogo-ministerios, planes, registrar-efector, solicitar-ministerio.
 * Auth AdminEfector: mi-licencia, desvincular-pago-ministerio, asociar-pago-ministerio.
 */
class LicenciaController extends BaseController
{
    public static $authenticatorExcept = [
        'catalogo-ministerios',
        'planes',
        'registrar-efector',
        'solicitar-ministerio',
    ];

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update'], $actions['delete']);

        return $actions;
    }

    protected function verbs()
    {
        $verbs = parent::verbs();
        $verbs['catalogo-ministerios'] = ['GET', 'OPTIONS'];
        $verbs['planes'] = ['GET', 'OPTIONS'];
        $verbs['registrar-efector'] = ['POST', 'OPTIONS'];
        $verbs['solicitar-ministerio'] = ['POST', 'OPTIONS'];
        $verbs['mi-licencia'] = ['GET', 'OPTIONS'];
        $verbs['desvincular-pago-ministerio'] = ['POST', 'OPTIONS'];
        $verbs['asociar-pago-ministerio'] = ['POST', 'OPTIONS'];

        return $verbs;
    }

    /**
     * @action_name Catálogo de ministerios activos (onboarding)
     */
    public function actionCatalogoMinisterios()
    {
        return $this->success([
            'items' => InstitutionalEfectorSignupService::listMinisteriosActivos(),
        ]);
    }

    /**
     * @action_name Planes / precios de licencia (metadata)
     */
    public function actionPlanes()
    {
        return $this->success(InstitutionalEfectorSignupService::planesCatalog());
    }

    /**
     * @action_name Alta self-service efector + AdminEfector + pago simulado
     */
    public function actionRegistrarEfector()
    {
        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            $body = [];
        }

        try {
            $data = InstitutionalEfectorSignupService::register($body);

            return $this->success($data, 'Cuenta creada. Ya podés ingresar a la plataforma.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'billing.signup');

            return $this->error('No se pudo completar el alta. Reintentá o escribinos.', null, 500);
        }
    }

    /**
     * @action_name Solicitud asistida de cuenta ministerio
     */
    public function actionSolicitarMinisterio()
    {
        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            $body = [];
        }

        try {
            $req = MinistrySignupRequestService::createRequest($body);

            return $this->success([
                'id' => (int) $req->id,
                'status' => (string) $req->status,
            ], 'Solicitud recibida. Te contactaremos para verificar y activar la cuenta.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }
    }

    /**
     * @action_name Resumen de licencia del efector de sesión (AdminEfector)
     */
    public function actionMiLicencia()
    {
        $this->assertAdminEfector();
        $idEfector = (int) Yii::$app->user->getIdEfector();
        if ($idEfector <= 0) {
            throw new BadRequestHttpException('Establecé el efector en la sesión operativa.');
        }

        return $this->success(BillingMembershipSwitchService::summaryForEfector($idEfector));
    }

    /**
     * @action_name Desvincular pago del ministerio (pasa a cuenta propia + cobro simulado)
     */
    public function actionDesvincularPagoMinisterio()
    {
        $this->assertAdminEfector();
        $idEfector = (int) Yii::$app->user->getIdEfector();
        if ($idEfector <= 0) {
            throw new BadRequestHttpException('Establecé el efector en la sesión operativa.');
        }

        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            $body = [];
        }

        try {
            $data = BillingMembershipSwitchService::desvincularPagoMinisterio(
                $idEfector,
                (int) Yii::$app->user->id,
                is_array($body['plan'] ?? null) ? $body['plan'] : [],
                is_array($body['payment'] ?? null) ? $body['payment'] : []
            );

            return $this->success($data, 'Pago desvinculado del ministerio. Ahora facturás en cuenta propia.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }
    }

    /**
     * @action_name Solicitar asociar el pago (POOL) a un ministerio
     */
    public function actionAsociarPagoMinisterio()
    {
        $this->assertAdminEfector();
        $idEfector = (int) Yii::$app->user->getIdEfector();
        if ($idEfector <= 0) {
            throw new BadRequestHttpException('Establecé el efector en la sesión operativa.');
        }

        $body = Yii::$app->request->getBodyParams();
        $idMinisterio = (int) ($body['id_billing_account_ministerio'] ?? 0);
        if ($idMinisterio <= 0) {
            return $this->error('Indicá id_billing_account_ministerio.', null, 400);
        }

        try {
            $req = BillingMembershipSwitchService::solicitarAsociarPagoMinisterio(
                $idEfector,
                (int) Yii::$app->user->id,
                $idMinisterio
            );

            return $this->success([
                'id_request' => (int) $req->id,
                'status' => (string) $req->status,
            ], 'Solicitud enviada. El ministerio / Bioenlace debe aprobar el cambio de cupo.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }
    }

    private function assertAdminEfector(): void
    {
        if (!User::hasRole(['AdminEfector'], false)) {
            // Fallback: PES AdminEfector en sesión (API sin roles de sesión web)
            $idServicio = (int) (Yii::$app->user->getServicioActual() ?? 0);
            if ($idServicio > 0
                && \common\components\Domain\Organization\Service\SesionOperativa\SesionOperativaService::isServicioAdminEfector($idServicio)
            ) {
                return;
            }
            throw new ForbiddenHttpException('Se requiere rol AdminEfector.');
        }
    }
}
