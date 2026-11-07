<?php

namespace frontend\modules\api\v1\controllers\organization;

use frontend\modules\api\v1\controllers\BaseController;
use common\components\Domain\Geo\Application\Service\GeoLocationResolver;
use common\components\Domain\Organization\Efector\Application\UseCase\SwitchBillingMembership;
use common\components\Domain\Organization\Efector\Application\UseCase\SignUpInstitutionalEfector;
use common\components\Domain\Organization\Efector\Application\Service\CommercialSignupDraftService;
use common\components\Domain\Organization\Billing\Application\Service\BillingCheckoutService;
use common\components\Domain\Organization\Billing\Application\Service\MercadoPagoCheckoutService;
use common\components\Domain\Organization\Efector\Application\UseCase\RequestMinistrySignup;
use common\components\Domain\Organization\Efector\Application\UseCase\CreateIdentityAccount;
use common\components\Domain\Person\Identity\Application\Service\IdentityVerificationException;
use common\components\Domain\Organization\Efector\Application\UseCase\CompleteMinistrySignupIdentity;
use common\components\Domain\Organization\Efector\Application\Service\SignupAbuseGuard;
use common\components\Domain\Organization\Efector\Application\Service\SandboxSignupIdentityService;
use common\components\Domain\Organization\Efector\Application\Service\StaffAffiliationRequestService;
use common\components\Platform\Core\Auth\DemoSandboxAccessService;
use common\components\Platform\Core\Auth\DemoSandboxCaptchaService;
use common\components\Platform\Core\Auth\StaffMobileAccessService;
use common\components\Platform\Core\Auth\EmailConfirmationSender;
use common\components\Platform\Core\Auth\EmailDeliveryUnavailableException;
use common\components\Platform\Core\Http\Contract\ApiException;
use common\components\Platform\Core\Product\PricingPesByEncounterClassMetadata;
use common\models\Geo\Pais;
use common\models\Geo\Provincia;
use common\models\Platform\User;
use common\models\Person\Persona;
use Yii;

/**
 * Onboarding comercial de licencia (institucional + AdminEfector).
 *
 * Públicas (sin auth): catalogo-ministerios, planes, crear-cuenta,
 * solicitar-ministerio, demo-perfiles, demo-captcha, demo-acceso, demo-acceso-mobile.
 * Auth cuenta móvil: registrar-efector, estado-checkout.
 * Auth AdminEfector: mi-licencia, desvincular-pago-ministerio, asociar-pago-ministerio.
 * Prueba sandbox (sin Didit, solo no productivo): registrar-efector-sandbox,
 * estado-checkout-sandbox, cancelar-suscripcion-sandbox.
 */
class LicenciaController extends BaseController
{
    public static $authenticatorExcept = [
        'catalogo-ministerios',
        'catalogo-efectores-publicos',
        'catalogo-centros',
        'catalogo-provincias',
        'planes',
        'cotizar',
        'crear-cuenta',
        'guardar-borrador',
        'registrar-efector-didit',
        'estado-checkout-didit',
        'cancelar-suscripcion-didit',
        'registrar-efector-sandbox',
        'estado-checkout-sandbox',
        'cancelar-suscripcion-sandbox',
        'enviar-verificacion-email',
        'estado-email',
        'ubicacion',
        'completar-ministerio',
        'solicitar-ministerio',
        'demo-perfiles',
        'demo-captcha',
        'demo-acceso',
        'demo-acceso-mobile',
    ];

    public function init()
    {
        parent::init();
        $this->contractResponses = true;
    }

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
        $verbs['catalogo-efectores-publicos'] = ['GET', 'OPTIONS'];
        $verbs['catalogo-centros'] = ['GET', 'OPTIONS'];
        $verbs['catalogo-provincias'] = ['GET', 'OPTIONS'];
        $verbs['planes'] = ['GET', 'OPTIONS'];
        $verbs['cotizar'] = ['POST', 'OPTIONS'];
        $verbs['crear-cuenta'] = ['POST', 'OPTIONS'];
        $verbs['guardar-borrador'] = ['POST', 'OPTIONS'];
        $verbs['registrar-efector-didit'] = ['POST', 'OPTIONS'];
        $verbs['estado-checkout-didit'] = ['POST', 'OPTIONS'];
        $verbs['cancelar-suscripcion-didit'] = ['POST', 'OPTIONS'];
        $verbs['registrar-efector-sandbox'] = ['POST', 'OPTIONS'];
        $verbs['estado-checkout-sandbox'] = ['POST', 'OPTIONS'];
        $verbs['cancelar-suscripcion-sandbox'] = ['POST', 'OPTIONS'];
        $verbs['enviar-verificacion-email'] = ['POST', 'OPTIONS'];
        $verbs['estado-email'] = ['POST', 'OPTIONS'];
        $verbs['ubicacion'] = ['POST', 'OPTIONS'];
        $verbs['completar-ministerio'] = ['POST', 'OPTIONS'];
        $verbs['registrar-efector'] = ['POST', 'OPTIONS'];
        $verbs['estado-checkout'] = ['GET', 'OPTIONS'];
        $verbs['cancelar-suscripcion'] = ['POST', 'OPTIONS'];
        $verbs['solicitar-ministerio'] = ['POST', 'OPTIONS'];
        $verbs['demo-perfiles'] = ['GET', 'OPTIONS'];
        $verbs['demo-captcha'] = ['GET', 'OPTIONS'];
        $verbs['demo-acceso'] = ['POST', 'OPTIONS'];
        $verbs['demo-acceso-mobile'] = ['POST', 'OPTIONS'];
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
            'items' => SignUpInstitutionalEfector::listMinisteriosActivos(),
        ]);
    }

    /**
     * Busca efectores públicos disponibles en el catálogo local SISA.
     */
    public function actionCatalogoEfectoresPublicos()
    {
        $query = trim((string) Yii::$app->request->get('q', ''));
        if (strlen($query) > 80) {
            return $this->error('La búsqueda supera el largo permitido.', null, 400);
        }

        return $this->success([
            'items' => SignUpInstitutionalEfector::searchAvailablePublicEfectores($query),
        ]);
    }

    /**
     * Centros activos de Bioenlace para que el personal solicite incorporarse.
     * Filtros opcionales: sector (PUBLICO|PRIVADO) y provincia.
     */
    public function actionCatalogoCentros()
    {
        $query = trim((string) Yii::$app->request->get('q', ''));
        if (strlen($query) > 80) {
            return $this->error('La búsqueda supera el largo permitido.', null, 400);
        }

        $sector = strtoupper(trim((string) Yii::$app->request->get('sector', '')));
        if ($sector !== '' && !in_array($sector, [
            SignUpInstitutionalEfector::SECTOR_PUBLICO,
            SignUpInstitutionalEfector::SECTOR_PRIVADO,
        ], true)) {
            return $this->error('El sector indicado es inválido.', null, 400);
        }

        $idProvincia = (int) Yii::$app->request->get('id_provincia', 0);

        try {
            return $this->success([
                'items' => SignUpInstitutionalEfector::listAvailableCenters(
                    $query,
                    $sector !== '' ? $sector : null,
                    $idProvincia > 0 ? $idProvincia : null
                ),
            ]);
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'billing.centers');

            return $this->error('No pudimos cargar los centros. Reintentá más tarde.', null, 503);
        }
    }

    /**
     * Provincias del país por defecto para el filtro de centros.
     */
    public function actionCatalogoProvincias()
    {
        try {
            $pais = Pais::defaultPais();
            $rows = Provincia::find()
                ->filterWhere(['id_pais' => $pais !== null ? (int) $pais->id_pais : null])
                ->orderBy(['nombre' => SORT_ASC])
                ->asArray()
                ->all();
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'billing.centers');

            return $this->error('No pudimos cargar las provincias. Reintentá más tarde.', null, 503);
        }

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) $row['id_provincia'],
                'nombre' => (string) $row['nombre'],
            ];
        }

        return $this->success(['items' => $items]);
    }

    /**
     * @action_name Planes / precios de licencia (metadata)
     */
    public function actionPlanes()
    {
        // Sólo metadata pública: sin COGS, márgenes ni tramos internos.
        return $this->success(PricingPesByEncounterClassMetadata::publicConfig());
    }

    /**
     * Calcula una estimación mensual con las reglas vigentes del backend.
     */
    public function actionCotizar()
    {
        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            return $this->error('Los datos del plan son inválidos.', null, 400);
        }

        $profile = strtoupper(trim((string) ($body['perfil'] ?? '')));
        $plan = $body['plan'] ?? null;
        if ($profile !== '' && $profile !== SignUpInstitutionalEfector::PERFIL_ESPACIO_ATENCION) {
            return $this->error(
                'El alta comercial usa el contrato unificado ESPACIO_ATENCION.',
                null,
                400
            );
        }
        if (!is_array($plan)) {
            return $this->error('Indicá un plan válido.', null, 400);
        }

        try {
            $estimate = SignUpInstitutionalEfector::estimatePlanMonthlyQuote($plan);
            $pricing = (new MercadoPagoCheckoutService())->calculateArsAmount($estimate['amount_usd']);
            $catalog = SignUpInstitutionalEfector::planesCatalog();

            return $this->success([
                'perfil' => SignUpInstitutionalEfector::PERFIL_ESPACIO_ATENCION,
                'billing_period' => 'month',
                'pricing_version' => (int) ($catalog['version'] ?? 1),
                'amount_usd' => $pricing['amount_usd'],
                'amount_net_ars' => $pricing['net_ars'],
                'vat_rate' => $pricing['vat_rate'],
                'vat_ars' => $pricing['vat_ars'],
                'amount_total_ars' => $pricing['total_ars'],
                'exchange_rate_effective_date' => $pricing['exchange_rate_effective_date'],
                'list_amount_usd' => $estimate['list_amount_usd'],
                'discount_amount_usd' => $estimate['discount_amount_usd'],
                'discount_percent' => $estimate['discount_percent'],
                'total_attentions' => $estimate['total_attentions'],
                'items' => $estimate['items'],
            ], 'Estimación mensual calculada.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (\yii\base\InvalidConfigException $e) {
            Yii::error($e->getMessage(), 'billing.quote');

            return $this->error('La cotización no está disponible temporalmente.', null, 503);
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'billing.quote');

            return $this->error('No se pudo calcular el precio. Reintentá más tarde.', null, 503);
        }
    }

    /**
     * @action_name Cuenta de identidad (Persona + User passwordless, sin efector)
     */
    public function actionCrearCuenta()
    {
        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            $body = [];
        }

        $idEfector = (int) ($body['id_efector'] ?? 0);
        if ($idEfector > 0 && SignUpInstitutionalEfector::findAvailableCenter($idEfector) === null) {
            return $this->error('El centro seleccionado no está disponible.', null, 400);
        }

        try {
            (new SignupAbuseGuard())->assertAllowed($body);
            $data = (new CreateIdentityAccount())->crear($body);
            $solicitud = $idEfector > 0
                ? (new StaffAffiliationRequestService())->record(
                    (int) $data['persona']->id_persona,
                    (int) $data['user']->id,
                    $idEfector
                )
                : null;
            $draft = (new CommercialSignupDraftService())->findForPersona(
                (int) $data['persona']->id_persona
            );

            return $this->success([
                'es_nueva_persona' => (bool) $data['es_nueva_persona'],
                'es_nuevo_usuario' => (bool) $data['es_nuevo_usuario'],
                'borrador' => $draft,
                'solicitud' => $solicitud,
            ], 'Cuenta de identidad creada.');
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), null, str_contains($e->getMessage(), 'Demasiados') ? 429 : 400);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (IdentityVerificationException $e) {
            if ($e->isRetryable()) {
                return $this->fail('service_unavailable');
            }

            return $this->error($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'billing.signup');

            return $this->error('No se pudo crear la cuenta. Reintentá o escribinos.', null, 500);
        }
    }

    /**
     * Saves or recovers the commercial draft after revalidating the Didit proof.
     */
    public function actionGuardarBorrador()
    {
        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            return $this->error('Los datos del borrador son inválidos.', null, 400);
        }

        try {
            (new SignupAbuseGuard())->assertAllowed($body);
            $draftInput = $body['borrador'] ?? null;
            if (!is_array($draftInput)) {
                return $this->error('Los datos del borrador son inválidos.', null, 400);
            }
            $identity = (new CreateIdentityAccount())->crear($body);
            $draft = (new CommercialSignupDraftService())->save(
                (int) $identity['persona']->id_persona,
                $draftInput
            );

            return $this->success(['borrador' => $draft], 'Borrador guardado.');
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), null, 429);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (IdentityVerificationException $e) {
            return $e->isRetryable()
                ? $this->fail('service_unavailable')
                : $this->error($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'billing.signup');
            return $this->error('No se pudo guardar el borrador. Reintentá más tarde.', null, 503);
        }
    }

    /**
     * @action_name Completar identidad de solicitud de ministerio (token + Didit)
     */
    public function actionCompletarMinisterio()
    {
        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            $body = [];
        }

        try {
            (new SignupAbuseGuard())->assertAllowed($body);
            $token = trim((string) ($body['token'] ?? ''));
            $verificationId = trim((string) ($body['verification_id'] ?? ''));
            $req = RequestMinistrySignup::resolvePendingByToken($token);
            $data = (new CompleteMinistrySignupIdentity())->complete((int) $req->id, $verificationId);

            return $this->success([
                'id_request' => (int) $data['id_request'],
                'id_persona' => (int) $data['id_persona'],
                'id_user' => (int) $data['id_user'],
            ], 'Identidad verificada. Un administrador aprobará tu solicitud.');
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), null, str_contains($e->getMessage(), 'Demasiados') ? 429 : 400);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'billing.signup');

            return $this->error('No se pudo completar la solicitud. Reintentá o escribinos.', null, 500);
        }
    }

    /**
     * Inicia el checkout mensual. La cuenta y el efector se crean cuando el webhook confirma el primer pago.
     */
    public function actionRegistrarEfector()
    {
        $userId = (int) Yii::$app->user->id;
        $user = User::findOne($userId);
        $persona = Persona::findOne(['id_user' => $userId]);
        if ($user === null || $persona === null) {
            return $this->error('La sesión no está asociada a una persona verificada.', null, 403);
        }

        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            $body = [];
        }

        try {
            $checkout = (new BillingCheckoutService())->start($user, $persona, $body);

            return $this->success($checkout, 'Checkout de Mercado Pago iniciado.', 201);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), null, 403);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (\yii\base\InvalidConfigException $e) {
            Yii::error($e->getMessage(), 'mercadopago');

            return $this->error('El checkout no está configurado para este entorno.', null, 503);
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'mercadopago');

            return $this->error('No se pudo iniciar el checkout. Reintentá más tarde.', null, 503);
        }
    }

    /**
     * Starts checkout after validating the Didit proof; no app session token is issued.
     */
    public function actionRegistrarEfectorDidit()
    {
        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            return $this->error('Los datos del alta son inválidos.', null, 400);
        }

        try {
            (new SignupAbuseGuard())->assertAllowed($body);
            $identity = (new CreateIdentityAccount())->crear($body);
            $draftService = new CommercialSignupDraftService();
            $draftService->save((int) $identity['persona']->id_persona, $body);
            $checkout = (new BillingCheckoutService())->start(
                $identity['user'],
                $identity['persona'],
                $body
            );
            $draftService->markCheckoutPending(
                (int) $identity['persona']->id_persona,
                (string) $checkout['checkout_key']
            );

            return $this->success($checkout, 'Checkout de Mercado Pago iniciado.', 201);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), null, 403);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (IdentityVerificationException $e) {
            return $e->isRetryable()
                ? $this->fail('service_unavailable')
                : $this->error($e->getMessage(), null, 422);
        } catch (\yii\base\InvalidConfigException $e) {
            Yii::error($e->getMessage(), 'mercadopago');
            return $this->error('El checkout no está configurado para este entorno.', null, 503);
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'mercadopago');
            return $this->error('No se pudo iniciar el checkout. Reintentá más tarde.', null, 503);
        }
    }

    /**
     * Reads a checkout status after revalidating the Didit proof and its owner.
     */
    public function actionEstadoCheckoutDidit()
    {
        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            return $this->error('Los datos de consulta son inválidos.', null, 400);
        }
        $checkoutKey = trim((string) ($body['checkout_key'] ?? ''));
        if ($checkoutKey === '') {
            return $this->error('checkout_key es requerido.', null, 400);
        }
        if (trim((string) ($body['verification_id'] ?? '')) === '') {
            return $this->error('verification_id es requerido.', null, 400);
        }

        try {
            (new SignupAbuseGuard())->assertAllowed($body);
            $identity = (new CreateIdentityAccount())->crear($body);
            $status = (new BillingCheckoutService())->statusForUser(
                $checkoutKey,
                (int) $identity['user']->id
            );
            (new CommercialSignupDraftService())->syncCheckoutStatus(
                (int) $identity['persona']->id_persona,
                (string) ($status['status'] ?? '')
            );
            return $this->success($status);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 404);
        } catch (IdentityVerificationException $e) {
            return $e->isRetryable()
                ? $this->fail('service_unavailable')
                : $this->error($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'billing.signup');
            return $this->error('No se pudo consultar el checkout. Reintentá más tarde.', null, 503);
        }
    }

    /**
     * Alta de prueba para validar el checkout de Mercado Pago en sandbox sin
     * pasar por Didit. Solo habilitada con `mercadoPago.sandboxSignupEnabled`
     * y nunca en producción.
     *
     * @action_name Alta de prueba sandbox (Mercado Pago)
     */
    public function actionRegistrarEfectorSandbox()
    {
        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            return $this->error('Los datos del alta son inválidos.', null, 400);
        }

        try {
            $identity = (new SandboxSignupIdentityService())->resolveAccount();
            // El alta de prueba no usa borrador: es descartable y así puede
            // repetirse aunque un intento anterior haya activado la licencia.
            $checkout = (new BillingCheckoutService())->start(
                $identity['user'],
                $identity['persona'],
                $body
            );

            return $this->success($checkout, 'Checkout de prueba iniciado.', 201);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), null, 403);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (\yii\base\InvalidConfigException $e) {
            Yii::error($e->getMessage(), 'mercadopago');
            return $this->error('El checkout no está configurado para este entorno.', null, 503);
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'mercadopago');
            return $this->error('No se pudo iniciar el checkout. Reintentá más tarde.', null, 503);
        }
    }

    /**
     * Estado de un checkout de prueba (sandbox) resuelto por la identidad fija.
     *
     * @action_name Estado de checkout sandbox (Mercado Pago)
     */
    public function actionEstadoCheckoutSandbox()
    {
        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            return $this->error('Los datos de consulta son inválidos.', null, 400);
        }
        $checkoutKey = trim((string) ($body['checkout_key'] ?? ''));
        if ($checkoutKey === '') {
            return $this->error('checkout_key es requerido.', null, 400);
        }

        try {
            $identity = (new SandboxSignupIdentityService())->resolveAccount();
            $status = (new BillingCheckoutService())->statusForUser(
                $checkoutKey,
                (int) $identity['user']->id
            );

            return $this->success($status);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), null, 403);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 404);
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'billing.signup');
            return $this->error('No se pudo consultar el checkout. Reintentá más tarde.', null, 503);
        }
    }

    /**
     * Cancela la renovación de un checkout de prueba (sandbox).
     *
     * @action_name Cancelar renovación sandbox (Mercado Pago)
     */
    public function actionCancelarSuscripcionSandbox()
    {
        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            return $this->error('Los datos de cancelación son inválidos.', null, 400);
        }
        $checkoutKey = trim((string) ($body['checkout_key'] ?? ''));
        if ($checkoutKey === '') {
            return $this->error('checkout_key es requerido.', null, 400);
        }

        try {
            $identity = (new SandboxSignupIdentityService())->resolveAccount();

            return $this->success(
                (new BillingCheckoutService())->cancelForUser(
                    $checkoutKey,
                    (int) $identity['user']->id
                ),
                'Se solicitó cancelar la renovación.'
            );
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), null, 403);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 404);
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'mercadopago');
            return $this->error('No se pudo cancelar la renovación. Reintentá más tarde.', null, 503);
        }
    }

    /**
     * Resuelve país/provincia/departamento/localidad a partir de las coordenadas
     * elegidas en el mapa del alta comercial (reverse geocoding + base geo).
     */
    public function actionUbicacion()
    {
        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            return $this->error('Los datos de la ubicación son inválidos.', null, 400);
        }

        $lat = $body['lat'] ?? $body['latitud'] ?? null;
        $lng = $body['lng'] ?? $body['longitud'] ?? null;
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return $this->error('Indicá latitud y longitud.', null, 400);
        }
        $lat = (float) $lat;
        $lng = (float) $lng;
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return $this->error('Las coordenadas están fuera de rango.', null, 400);
        }

        try {
            return $this->success((new GeoLocationResolver())->resolve($lat, $lng));
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'geo.location');

            return $this->error('No pudimos resolver la ubicación. Reintentá.', null, 503);
        }
    }

    /**
     * Envía (o reenvía) el mail de confirmación del e-mail de la cuenta en alta.
     *
     * No bloquea el pago: sólo informa si se envió y el cooldown restante.
     */
    public function actionEnviarVerificacionEmail()
    {
        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            return $this->error('Los datos de la solicitud son inválidos.', null, 400);
        }
        if (trim((string) ($body['verification_id'] ?? '')) === '') {
            return $this->error('verification_id es requerido.', null, 400);
        }

        try {
            (new SignupAbuseGuard())->assertAllowed($body);
            $identity = (new CreateIdentityAccount())->crear($body);
            $result = EmailConfirmationSender::send($identity['user'], !empty($body['force']));

            if ($result['sent']) {
                return $this->success($result, 'Te enviamos un mail para confirmar tu e-mail.');
            }
            if ($result['cooldown_seconds'] > 0) {
                return $this->success($result, 'Esperá unos segundos antes de pedir otro envío.');
            }

            return $this->success($result, 'No fue necesario enviar el mail: el e-mail ya está confirmado.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (IdentityVerificationException $e) {
            return $e->isRetryable()
                ? $this->fail('service_unavailable')
                : $this->error($e->getMessage(), null, 422);
        } catch (EmailDeliveryUnavailableException $e) {
            Yii::error($e->getMessage(), 'billing.email');

            return $this->error(
                'El envío de e-mails no está disponible. Reintentá más tarde.',
                null,
                503
            );
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'billing.email');

            return $this->error('No se pudo enviar el mail de confirmación.', null, 503);
        }
    }

    /**
     * Estado de confirmación del e-mail de la cuenta en alta.
     */
    public function actionEstadoEmail()
    {
        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            return $this->error('Los datos de consulta son inválidos.', null, 400);
        }
        if (trim((string) ($body['verification_id'] ?? '')) === '') {
            return $this->error('verification_id es requerido.', null, 400);
        }

        try {
            (new SignupAbuseGuard())->assertAllowed($body);
            $identity = (new CreateIdentityAccount())->crear($body);

            return $this->success(EmailConfirmationSender::status($identity['user']));
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (IdentityVerificationException $e) {
            return $e->isRetryable()
                ? $this->fail('service_unavailable')
                : $this->error($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'billing.email');

            return $this->error('No se pudo consultar el estado del e-mail.', null, 503);
        }
    }

    public function actionCancelarSuscripcionDidit()
    {
        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            return $this->error('Los datos de cancelación son inválidos.', null, 400);
        }
        $checkoutKey = trim((string) ($body['checkout_key'] ?? ''));
        if ($checkoutKey === '') {
            return $this->error('checkout_key es requerido.', null, 400);
        }
        if (trim((string) ($body['verification_id'] ?? '')) === '') {
            return $this->error('verification_id es requerido.', null, 400);
        }

        try {
            (new SignupAbuseGuard())->assertAllowed($body);
            $identity = (new CreateIdentityAccount())->crear($body);
            return $this->success(
                (new BillingCheckoutService())->cancelForUser(
                    $checkoutKey,
                    (int) $identity['user']->id
                ),
                'Se solicitó cancelar la renovación.'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 404);
        } catch (IdentityVerificationException $e) {
            return $e->isRetryable()
                ? $this->fail('service_unavailable')
                : $this->error($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'mercadopago');
            return $this->error('No se pudo cancelar la renovación. Reintentá más tarde.', null, 503);
        }
    }

    /**
     * Consulta el estado de un checkout perteneciente a la cuenta autenticada.
     */
    public function actionEstadoCheckout()
    {
        $checkoutKey = trim((string) Yii::$app->request->get('checkout_key', ''));
        if ($checkoutKey === '') {
            return $this->error('checkout_key es requerido.', null, 400);
        }

        try {
            return $this->success(
                (new BillingCheckoutService())->statusForUser($checkoutKey, (int) Yii::$app->user->id)
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 404);
        }
    }

    /**
     * Cancela la renovación futura sin quitar el acceso al período ya pagado.
     */
    public function actionCancelarSuscripcion()
    {
        $body = Yii::$app->request->getBodyParams();
        $checkoutKey = is_array($body) ? trim((string) ($body['checkout_key'] ?? '')) : '';
        if ($checkoutKey === '') {
            return $this->error('checkout_key es requerido.', null, 400);
        }

        try {
            return $this->success(
                (new BillingCheckoutService())->cancelForUser($checkoutKey, (int) Yii::$app->user->id),
                'Se solicitó cancelar la renovación. El acceso continúa hasta el fin del período pagado.'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 404);
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'mercadopago');

            return $this->error('No se pudo cancelar la renovación. Reintentá más tarde.', null, 503);
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
            $req = RequestMinistrySignup::createRequest($body);

            return $this->success([
                'id' => (int) $req->id,
                'status' => (string) $req->status,
            ], 'Solicitud recibida. Te contactaremos para verificar y activar la cuenta.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }
    }

    /**
     * Perfiles demo disponibles (staff / paciente) según params.
     *
     * GET /api/v1/licencia/demo-perfiles
     *
     * @action_name Perfiles de acceso demo sandbox
     */
    public function actionDemoPerfiles()
    {
        if (!DemoSandboxAccessService::isEnabled()) {
            return $this->success([
                'enabled' => false,
                'items' => [],
            ], 'Acceso demo deshabilitado');
        }

        return $this->success([
            'enabled' => true,
            'require_captcha' => (new DemoSandboxCaptchaService())->isRequired(),
            'items' => DemoSandboxAccessService::listProfiles(),
        ]);
    }

    /**
     * Emite challenge de captcha (imagen data-URI + id) para el CTA demo.
     *
     * GET /api/v1/licencia/demo-captcha
     *
     * @action_name Captcha acceso demo sandbox
     */
    public function actionDemoCaptcha()
    {
        if (!DemoSandboxAccessService::isEnabled()) {
            return $this->error('Acceso demo no habilitado en este entorno', null, 403);
        }

        try {
            $data = (new DemoSandboxCaptchaService())->issue();

            return $this->success($data);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            Yii::error($e->getMessage(), 'demo.sandbox');

            return $this->error('No se pudo generar el captcha.', null, 500);
        }
    }

    /**
     * Emite URL de un solo uso para entrar al sandbox (sitio institucional).
     *
     * POST /api/v1/licencia/demo-acceso
     * Body: { "role": "staff"|"paciente", "email"?: "...", "website"?: "", "captcha"?: "...", "captcha_challenge_id"?: "..." }
     *
     * @action_name Solicitar acceso demo sandbox
     */
    public function actionDemoAcceso()
    {
        if (!DemoSandboxAccessService::isEnabled()) {
            return $this->error('Acceso demo no habilitado en este entorno', null, 403);
        }

        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            $body = [];
        }

        try {
            $data = (new DemoSandboxAccessService())->issue($body);

            return $this->success($data, 'Listo. Abrí el enlace para entrar a la demo.');
        } catch (\DomainException $e) {
            $msg = $e->getMessage();
            $status = str_contains($msg, 'Demasiados') ? 429 : 400;

            return $this->error($msg, null, $status);
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'demo.sandbox');

            return $this->error('No se pudo generar el acceso demo.', null, 500);
        }
    }

    /**
     * Acceso demo staff para app Personal de Salud (JWT + contexto AMB, sin password).
     *
     * POST /api/v1/licencia/demo-acceso-mobile
     * Body: { "role"?: "staff"|"enfermeria"|"administrativo", "website"?: "", "email"?: "..." }
     *
     * @action_name Solicitar acceso demo sandbox (móvil staff)
     */
    public function actionDemoAccesoMobile()
    {
        if (!DemoSandboxAccessService::isEnabled()) {
            return $this->error('Acceso demo no habilitado en este entorno', null, 403);
        }

        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            $body = [];
        }

        try {
            $data = (new DemoSandboxAccessService())->issueMobileStaff($body);

            return $this->success($data, 'Demo lista. Sesión temporal de staff.');
        } catch (\DomainException $e) {
            $msg = $e->getMessage();
            $status = str_contains($msg, 'Demasiados') ? 429 : 400;

            return $this->error($msg, null, $status);
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'demo.sandbox');

            return $this->error('No se pudo abrir la demo móvil.', null, 500);
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
            throw new ApiException('request_invalid', ['detail' => 'Establecé el efector en la sesión operativa.']);
        }

        return $this->success(SwitchBillingMembership::summaryForEfector($idEfector));
    }

    /**
     * @action_name Desvincular pago del ministerio (pasa a cuenta propia + cobro simulado)
     */
    public function actionDesvincularPagoMinisterio()
    {
        $this->assertAdminEfector();
        $idEfector = (int) Yii::$app->user->getIdEfector();
        if ($idEfector <= 0) {
            throw new ApiException('request_invalid', ['detail' => 'Establecé el efector en la sesión operativa.']);
        }

        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            $body = [];
        }

        try {
            $data = SwitchBillingMembership::desvincularPagoMinisterio(
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
            throw new ApiException('request_invalid', ['detail' => 'Establecé el efector en la sesión operativa.']);
        }

        $body = Yii::$app->request->getBodyParams();
        $idMinisterio = (int) ($body['id_billing_account_ministerio'] ?? 0);
        if ($idMinisterio <= 0) {
            return $this->error('Indicá id_billing_account_ministerio.', null, 400);
        }

        try {
            $req = SwitchBillingMembership::solicitarAsociarPagoMinisterio(
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
                && \common\components\Domain\Organization\SesionOperativa\Application\UseCase\EstablishOperativeSession::isServicioAdminEfector($idServicio)
            ) {
                return;
            }
            throw new ApiException('forbidden_detail', ['detail' => 'Se requiere rol AdminEfector.']);
        }
    }
}
