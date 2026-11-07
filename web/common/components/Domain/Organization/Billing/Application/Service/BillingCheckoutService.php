<?php

namespace common\components\Domain\Organization\Billing\Application\Service;

use common\components\Domain\Organization\Efector\Application\Service\CommercialSignupDraftService;
use common\components\Domain\Organization\Efector\Application\Service\ContactPhoneValidator;
use common\components\Domain\Organization\Efector\Application\UseCase\SignUpInstitutionalEfector;
use common\components\Domain\Person\Identity\Application\Service\IdentitySignupAttemptStore;
use common\models\Organization\BillingAccount;
use common\models\Organization\BillingCheckoutAttempt;
use common\models\Organization\BillingPayment;
use common\models\Person\Persona;
use common\models\Platform\User;
use Yii;
use yii\db\IntegrityException;

final class BillingCheckoutService
{
    private ?MercadoPagoCheckoutService $mercadoPago;

    public function __construct(?MercadoPagoCheckoutService $mercadoPago = null)
    {
        $this->mercadoPago = $mercadoPago;
    }

    /**
     * Crea una suscripción pendiente para una cuenta móvil previamente verificada.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function start(User $user, Persona $persona, array $payload): array
    {
        if ((int) $persona->id_user !== (int) $user->id || (int) $user->status !== User::STATUS_ACTIVE) {
            throw new \InvalidArgumentException('La sesión no corresponde a una persona activa.');
        }
        if (!(new IdentitySignupAttemptStore())->accountWasCreatedFromApprovedVerification(
            (int) $persona->id_persona,
            (int) $user->id
        )) {
            throw new \DomainException('El checkout requiere una cuenta creada desde una verificación aprobada de la app.');
        }
        if (!empty($payload['pago_cubierto_por_ministerio'])) {
            throw new \InvalidArgumentException('La cobertura ministerial debe tramitarse por el canal de solicitud asistida.');
        }

        $draft = SignUpInstitutionalEfector::prepareDraft($payload);
        $payerEmail = $this->resolvePayerEmail($payload);
        $contactPhone = ContactPhoneValidator::normalizeMobile(
            (string) ($payload['contact_phone'] ?? '')
        ) ?? '';
        ContactPhoneValidator::assertValidMobile($contactPhone, 'Teléfono de contacto');
        $this->promotePayerEmail($user, $payerEmail);

        $checkoutKey = strtolower(trim((string) ($payload['checkout_request_key'] ?? '')));
        if (!preg_match('/^[a-f0-9]{48}$/', $checkoutKey)) {
            throw new \InvalidArgumentException('checkout_request_key debe ser un identificador aleatorio de 48 caracteres hexadecimales.');
        }
        $efector = $draft['efector'];
        $efectorSnapshot = $draft['sector'] === SignUpInstitutionalEfector::SECTOR_PUBLICO
            ? ['codigo_sisa' => $efector['codigo_sisa']]
            : $efector;
        if ($draft['sector'] !== SignUpInstitutionalEfector::SECTOR_PUBLICO) {
            unset($efectorSnapshot['codigo_sisa']);
        }
        $planJson = $this->encodeJson($draft['plan']);
        $efectorJson = $this->encodeJson($efectorSnapshot);
        $existing = BillingCheckoutAttempt::findOne(['checkout_key' => $checkoutKey]);
        if ($existing !== null) {
            $sameRequest = (int) $existing->id_user === (int) $user->id
                && (int) $existing->id_persona === (int) $persona->id_persona
                && $existing->perfil === $draft['perfil']
                && $existing->sector === $draft['sector']
                && $existing->payer_email === $payerEmail
                && $existing->contact_phone === ($contactPhone !== '' ? $contactPhone : null)
                && (int) $existing->id_billing_account_ministerio === (int) ($draft['ministerio']->id ?? 0)
                && $existing->plan_json === $planJson
                && $existing->efector_json === $efectorJson;
            if (!$sameRequest) {
                throw new \InvalidArgumentException('El identificador de checkout ya fue utilizado con otros datos.');
            }
            if ($existing->provider_subscription_id !== null && $existing->checkout_url !== null) {
                if ($existing->status === BillingCheckoutAttempt::STATUS_FAILED) {
                    throw new \InvalidArgumentException('Este intento ya tuvo un pago rechazado. Iniciá un nuevo checkout.');
                }

                return $this->checkoutResponse($existing);
            }
            $attempt = $existing;
        } else {
            if ($draft['sector'] === SignUpInstitutionalEfector::SECTOR_PUBLICO) {
                $pendingEfectorAttempt = BillingCheckoutAttempt::find()
                    ->where([
                        'id_efector' => (int) $efector['id_efector'],
                        'status' => [
                            BillingCheckoutAttempt::STATUS_CREATING,
                            BillingCheckoutAttempt::STATUS_PENDING,
                        ],
                        'deleted_at' => null,
                    ])
                    ->one();
                if ($pendingEfectorAttempt !== null) {
                    if ((int) $pendingEfectorAttempt->id_user !== (int) $user->id) {
                        throw new \InvalidArgumentException(
                            'Este efector ya tiene un checkout pendiente iniciado por otra cuenta.'
                        );
                    }
                    if ($pendingEfectorAttempt->provider_subscription_id === null
                        || $pendingEfectorAttempt->checkout_url === null) {
                        throw new \InvalidArgumentException(
                            'El checkout de este efector todavía se está preparando. Reintentá en unos segundos.'
                        );
                    }

                    return $this->checkoutResponse($pendingEfectorAttempt);
                }
            }

            $amount = $this->mercadoPago()->calculateArsAmount((float) $draft['amount_usd']);
            $attempt = new BillingCheckoutAttempt();
            $attempt->checkout_key = $checkoutKey;
            $attempt->id_user = (int) $user->id;
            $attempt->id_persona = (int) $persona->id_persona;
            $attempt->perfil = $draft['perfil'];
            $attempt->sector = $draft['sector'];
            $attempt->status = BillingCheckoutAttempt::STATUS_CREATING;
            $attempt->id_efector = isset($efector['id_efector'])
                ? (int) $efector['id_efector']
                : null;
            $attempt->reserved_sisa_code = $draft['sector'] === SignUpInstitutionalEfector::SECTOR_PUBLICO
                ? (string) $efector['codigo_sisa']
                : null;
            $attempt->id_billing_account_ministerio = $draft['ministerio'] !== null
                ? (int) $draft['ministerio']->id
                : null;
            $attempt->payer_email = $payerEmail;
            $attempt->contact_phone = $contactPhone !== '' ? $contactPhone : null;
            $attempt->plan_json = $planJson;
            $attempt->efector_json = $efectorJson;
            $attempt->amount_usd = $amount['amount_usd'];
            $attempt->fx_rate = $amount['exchange_rate'];
            $attempt->fx_rate_effective_date = $amount['exchange_rate_effective_date'];
            $attempt->amount_net_ars = $amount['net_ars'];
            $attempt->vat_rate = $amount['vat_rate'];
            $attempt->vat_ars = $amount['vat_ars'];
            $attempt->amount_total_ars = $amount['total_ars'];
            try {
                $saved = $attempt->save();
            } catch (IntegrityException $e) {
                if ($attempt->reserved_sisa_code !== null
                    && BillingCheckoutAttempt::find()
                        ->where(['reserved_sisa_code' => $attempt->reserved_sisa_code])
                        ->exists()) {
                    throw new \InvalidArgumentException(
                        'Este efector ya tiene un checkout pendiente.',
                        0,
                        $e
                    );
                }
                throw $e;
            }
            if (!$saved) {
                if (isset($attempt->getErrors()['reserved_sisa_code'])) {
                    throw new \InvalidArgumentException('Este efector ya tiene un checkout pendiente.');
                }
                throw new \RuntimeException('No se pudo guardar el intento de checkout: ' . $this->encodeJson($attempt->getErrors()));
            }
        }

        $attempt->status = BillingCheckoutAttempt::STATUS_CREATING;
        if (!$attempt->save(false, ['status', 'updated_at'])) {
            throw new \RuntimeException('No se pudo reintentar el intento de checkout.');
        }
        try {
            $config = Yii::$app->params['mercadoPago'] ?? [];
            $omitPayerEmail = !empty($config['sandboxOmitPayerEmail']);
            $subscription = $this->mercadoPago()->createSubscription([
                'amount_ars' => (float) $attempt->amount_total_ars,
                'payer_email' => $omitPayerEmail ? '' : $payerEmail,
                'reason' => mb_substr('Bioenlace - ' . (string) $efector['nombre'], 0, 240),
                'external_reference' => $checkoutKey,
                'back_url' => (string) ($config['backUrl'] ?? ''),
                'notification_url' => (string) ($config['notificationUrl'] ?? ''),
                'idempotency_key' => $checkoutKey,
            ]);
        } catch (\Throwable $e) {
            $attempt->status = BillingCheckoutAttempt::STATUS_FAILED;
            $attempt->provider_status = 'CHECKOUT_CREATION_ERROR';
            $attempt->reserved_sisa_code = null;
            if (!$attempt->save(false, ['status', 'provider_status', 'reserved_sisa_code', 'updated_at'])) {
                Yii::error([
                    'event' => 'mercadopago.checkout_attempt_update_failed',
                    'checkout_key' => $checkoutKey,
                ], 'mercadopago');
            }
            Yii::error([
                'event' => 'mercadopago.checkout_creation_failed',
                'checkout_key' => $checkoutKey,
                'payer_email' => $omitPayerEmail ? '(omitido)' : $payerEmail,
                'error' => $e->getMessage(),
            ], 'mercadopago');
            throw new \RuntimeException('No se pudo iniciar el checkout de Mercado Pago. Reintentá más tarde.', 0, $e);
        }

        $attempt->provider_subscription_id = $subscription['id'];
        $attempt->checkout_url = $subscription['init_point'];
        $attempt->provider_status = $subscription['status'];
        $attempt->status = BillingCheckoutAttempt::STATUS_PENDING;
        if (!$attempt->save()) {
            throw new \RuntimeException('Se creó el checkout, pero no se pudo guardar su referencia.');
        }

        return $this->checkoutResponse($attempt);
    }

    /**
     * Resuelve el e-mail del pagador.
     *
     * En entornos con credenciales de prueba de Mercado Pago, el cobrador es un
     * usuario de prueba y el pagador también debe serlo. `mercadoPago.sandboxBuyerEmail`
     * fija ese pagador (email, o usuario de prueba al que se le agrega
     * `@testuser.com`). En producción el parámetro queda vacío y se usa el email
     * del alta.
     *
     * @param array<string, mixed> $payload
     * @throws \InvalidArgumentException si el email no es válido
     */
    private function resolvePayerEmail(array $payload): string
    {
        $configured = strtolower(trim((string) (Yii::$app->params['mercadoPago']['sandboxBuyerEmail'] ?? '')));
        if ($configured !== '' && !str_contains($configured, '@')) {
            $configured .= '@testuser.com';
        }
        $payerEmail = $configured !== ''
            ? $configured
            : strtolower(trim((string) ($payload['payer_email'] ?? '')));
        if (!filter_var($payerEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Ingresá un e-mail válido para el pago.');
        }

        return $payerEmail;
    }

    /**
     * Guarda el e-mail del pagador en el usuario cuando todavía no tiene uno
     * real (p. ej. el placeholder `@accounts.bioenlace.invalid` de una
     * identidad creada sin e-mail), para poder confirmarlo por mail.
     */
    private function promotePayerEmail(User $user, string $payerEmail): void
    {
        $current = strtolower(trim((string) $user->email));
        if ($current === $payerEmail) {
            return;
        }
        if ($current !== '' && !str_ends_with($current, '@accounts.bioenlace.invalid')) {
            return;
        }

        $user->email = $payerEmail;
        $user->email_confirmed = 0;
        $user->confirmation_token = null;
        $attributes = ['email', 'email_confirmed', 'confirmation_token', 'updated_at'];
        if ($user->hasAttribute('confirmation_sent_at')) {
            $user->confirmation_sent_at = null;
            $attributes[] = 'confirmation_sent_at';
        }
        $user->save(false, $attributes);
    }

    /**
     * @return array<string, mixed>
     */
    public function statusForUser(string $checkoutKey, int $userId): array
    {
        $attempt = BillingCheckoutAttempt::findOne([
            'checkout_key' => $checkoutKey,
            'id_user' => $userId,
            'deleted_at' => null,
        ]);
        if ($attempt === null) {
            throw new \InvalidArgumentException('No se encontró el checkout solicitado.');
        }

        return [
            'checkout_key' => $attempt->checkout_key,
            'status' => $attempt->status,
            'provider_status' => $attempt->provider_status,
            'provider_subscription_status' => $attempt->provider_subscription_status,
            'paid_through_at' => $attempt->paid_through_at,
            'checkout_url' => $attempt->checkout_url,
            'id_efector' => $attempt->id_efector !== null ? (int) $attempt->id_efector : null,
            'amount_usd' => (float) $attempt->amount_usd,
            'amount_net_ars' => (float) $attempt->amount_net_ars,
            'vat_rate' => (float) $attempt->vat_rate,
            'vat_ars' => (float) $attempt->vat_ars,
            'amount_total_ars' => (float) $attempt->amount_total_ars,
            'currency' => 'ARS',
        ];
    }

    /**
     * Cancela la renovación en Mercado Pago sin revocar el período ya pagado.
     *
     * @return array<string, mixed>
     */
    public function cancelForUser(string $checkoutKey, int $userId): array
    {
        $attempt = BillingCheckoutAttempt::findOne([
            'checkout_key' => $checkoutKey,
            'id_user' => $userId,
            'deleted_at' => null,
        ]);
        if ($attempt === null) {
            throw new \InvalidArgumentException('No se encontró el checkout solicitado.');
        }
        if (trim((string) $attempt->provider_subscription_id) === '') {
            throw new \InvalidArgumentException('El checkout no tiene una suscripción para cancelar.');
        }

        $currentStatus = strtolower(trim((string) $attempt->provider_subscription_status));
        if (!in_array($currentStatus, ['cancelled', 'canceled'], true)) {
            $subscription = $this->mercadoPago()->cancelSubscription(
                (string) $attempt->provider_subscription_id
            );
            $providerStatus = strtolower(trim((string) ($subscription['status'] ?? '')));
            $attempt->provider_subscription_status = $providerStatus !== '' ? $providerStatus : null;
            $attempt->last_provider_payload = $this->encodeJson($subscription);
            if ((int) $attempt->id_billing_account <= 0
                && in_array($providerStatus, ['cancelled', 'canceled'], true)) {
                $attempt->status = BillingCheckoutAttempt::STATUS_CANCELLED;
                $attempt->reserved_sisa_code = null;
            }
            if (!$attempt->save()) {
                throw new \RuntimeException('Mercado Pago canceló la suscripción, pero no se pudo guardar el estado.');
            }
        }

        return $this->statusForUser($checkoutKey, $userId);
    }

    /**
     * Suspende cuentas cuyo último período pagado ya venció.
     */
    public function expirePaidThroughSubscriptions(?\DateTimeImmutable $asOf = null): int
    {
        $cutoff = ($asOf ?? new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $attemptIds = BillingCheckoutAttempt::find()
            ->select('id')
            ->where([
                'status' => BillingCheckoutAttempt::STATUS_ACTIVE,
                'deleted_at' => null,
            ])
            ->andWhere(['not', ['id_billing_account' => null]])
            ->andWhere(['not', ['paid_through_at' => null]])
            ->andWhere(['<=', 'paid_through_at', $cutoff])
            ->column();

        $expired = 0;
        foreach ($attemptIds as $attemptId) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                $attempt = BillingCheckoutAttempt::find()
                    ->where(['id' => (int) $attemptId])
                    ->forUpdate()
                    ->one();
                if ($attempt === null
                    || $attempt->status !== BillingCheckoutAttempt::STATUS_ACTIVE
                    || $attempt->paid_through_at === null
                    || $attempt->paid_through_at > $cutoff
                    || (int) $attempt->id_billing_account <= 0) {
                    $transaction->commit();
                    continue;
                }

                $account = BillingAccount::find()
                    ->where(['id' => (int) $attempt->id_billing_account, 'deleted_at' => null])
                    ->forUpdate()
                    ->one();
                if ($account === null) {
                    throw new \RuntimeException('No se encontró la cuenta de licencia del checkout vencido.');
                }

                $account->activo = 0;
                if (!$account->save(false, ['activo', 'updated_at'])) {
                    throw new \RuntimeException('No se pudo suspender la cuenta de licencia vencida.');
                }
                $attempt->status = BillingCheckoutAttempt::STATUS_EXPIRED;
                if (!$attempt->save(false, ['status', 'updated_at'])) {
                    throw new \RuntimeException('No se pudo registrar el vencimiento del checkout.');
                }

                $transaction->commit();
                $expired++;
            } catch (\Throwable $e) {
                if ($transaction->isActive) {
                    $transaction->rollBack();
                }
                throw $e;
            }
        }

        return $expired;
    }

    public static function paidThroughAt(string $paidAt): string
    {
        try {
            $timezone = new \DateTimeZone(date_default_timezone_get());
            $paidDate = (new \DateTimeImmutable($paidAt))->setTimezone($timezone);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException('La fecha de aprobación del pago es inválida.', 0, $e);
        }

        $nextMonth = $paidDate->modify('first day of next month');
        $day = min((int) $paidDate->format('j'), (int) $nextMonth->format('t'));

        return $nextMonth
            ->setDate((int) $nextMonth->format('Y'), (int) $nextMonth->format('n'), $day)
            ->setTime(
                (int) $paidDate->format('H'),
                (int) $paidDate->format('i'),
                (int) $paidDate->format('s')
            )
            ->format('Y-m-d H:i:s');
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutResponse(BillingCheckoutAttempt $attempt): array
    {
        return [
            'checkout_key' => $attempt->checkout_key,
            'checkout_url' => $attempt->checkout_url,
            'status' => $attempt->status,
            'provider_subscription_status' => $attempt->provider_subscription_status,
            'paid_through_at' => $attempt->paid_through_at,
            'currency' => 'ARS',
            'amount_usd' => (float) $attempt->amount_usd,
            'exchange_rate' => (float) $attempt->fx_rate,
            'exchange_rate_effective_date' => $attempt->fx_rate_effective_date,
            'amount_net_ars' => (float) $attempt->amount_net_ars,
            'vat_rate' => (float) $attempt->vat_rate,
            'vat_ars' => (float) $attempt->vat_ars,
            'amount_total_ars' => (float) $attempt->amount_total_ars,
        ];
    }

    /**
     * Procesa únicamente eventos firmados de suscripción y pago autorizado.
     *
     * @param array<string, mixed> $payload
     */
    public function processWebhook(array $payload, ?string $signature, ?string $requestId): void
    {
        if (!$this->mercadoPago()->validateWebhookSignature($payload, $signature, $requestId)) {
            throw new \DomainException('Firma de webhook inválida.');
        }

        $topic = trim((string) ($payload['type'] ?? $payload['topic'] ?? ''));
        $resourceId = trim((string) ($payload['data']['id'] ?? ''));
        if ($resourceId === '') {
            throw new \InvalidArgumentException('La notificación no contiene el identificador del recurso.');
        }
        if (!in_array($topic, ['subscription_preapproval', 'subscription_authorized_payment'], true)) {
            Yii::info(['event' => 'mercadopago.webhook_ignored', 'topic' => $topic], 'mercadopago');
            return;
        }

        $providerResource = $topic === 'subscription_preapproval'
            ? $this->mercadoPago()->getSubscription($resourceId)
            : $this->mercadoPago()->getAuthorizedPayment($resourceId);

        $eventId = trim((string) ($payload['id'] ?? ''));
        if ($eventId === '') {
            throw new \InvalidArgumentException('La notificación no contiene un identificador de evento.');
        }
        $eventKey = $topic . ':' . $eventId;
        $database = Yii::$app->db;
        $transaction = $database->beginTransaction();
        try {
            $event = (new \yii\db\Query())
                ->from('{{%billing_webhook_event}}')
                ->where(['event_key' => $eventKey])
                ->one($database);
            if ($event !== false) {
                $transaction->commit();
                return;
            }

            $database->createCommand()->insert('{{%billing_webhook_event}}', [
                'event_key' => $eventKey,
                'provider_event_id' => $eventId,
                'topic' => $topic,
                'resource_id' => $resourceId,
                'status' => 'RECEIVED',
                'payload_json' => $this->encodeJson($payload),
            ])->execute();

            if ($topic === 'subscription_preapproval') {
                $this->processSubscriptionUpdate($resourceId, $providerResource);
            } else {
                $this->processAuthorizedPayment($resourceId, $providerResource);
            }

            $database->createCommand()->update('{{%billing_webhook_event}}', [
                'status' => 'PROCESSED',
                'processed_at' => date('Y-m-d H:i:s'),
            ], ['event_key' => $eventKey])->execute();
            $transaction->commit();
        } catch (IntegrityException $e) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            if ((new \yii\db\Query())->from('{{%billing_webhook_event}}')->where(['event_key' => $eventKey])->exists($database)) {
                return;
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $subscription
     */
    private function processSubscriptionUpdate(string $subscriptionId, array $subscription): void
    {
        $attempt = BillingCheckoutAttempt::findOne(['provider_subscription_id' => $subscriptionId]);
        if ($attempt === null) {
            throw new \RuntimeException('No se encontró el intento asociado a la suscripción recibida.');
        }

        $providerStatus = strtolower(trim((string) ($subscription['status'] ?? '')));
        $attempt->provider_subscription_status = $providerStatus !== '' ? $providerStatus : null;
        $attempt->last_provider_payload = $this->encodeJson($subscription);
        if (in_array($providerStatus, ['cancelled', 'canceled'], true)) {
            if ((int) $attempt->id_billing_account <= 0) {
                $attempt->status = BillingCheckoutAttempt::STATUS_CANCELLED;
                $attempt->reserved_sisa_code = null;
            } elseif ($attempt->status !== BillingCheckoutAttempt::STATUS_EXPIRED) {
                $attempt->status = BillingCheckoutAttempt::STATUS_ACTIVE;
            }
        } elseif (!in_array($attempt->status, [
            BillingCheckoutAttempt::STATUS_ACTIVE,
            BillingCheckoutAttempt::STATUS_EXPIRED,
        ], true)) {
            $attempt->status = BillingCheckoutAttempt::STATUS_PENDING;
        }
        if (!$attempt->save()) {
            throw new \RuntimeException('No se pudo actualizar el estado de la suscripción.');
        }
    }

    /**
     * @param array<string, mixed> $paymentData
     */
    private function processAuthorizedPayment(string $paymentId, array $paymentData): void
    {
        $subscriptionId = trim((string) ($paymentData['preapproval_id'] ?? ''));
        if ($subscriptionId === '') {
            throw new \RuntimeException('El pago autorizado no contiene preapproval_id.');
        }
        $attempt = BillingCheckoutAttempt::find()
            ->where(['provider_subscription_id' => $subscriptionId])
            ->forUpdate()
            ->one();
        if ($attempt === null) {
            throw new \RuntimeException('No se encontró el intento asociado al pago recibido.');
        }

        $status = strtolower(trim((string) ($paymentData['status'] ?? '')));
        $attempt->provider_status = $status !== '' ? $status : null;
        $attempt->last_provider_payload = $this->encodeJson($paymentData);
        if (!in_array($status, ['approved', 'processed'], true)) {
            if (!in_array($attempt->status, [
                BillingCheckoutAttempt::STATUS_ACTIVE,
                BillingCheckoutAttempt::STATUS_EXPIRED,
            ], true)) {
                $attempt->status = BillingCheckoutAttempt::STATUS_FAILED;
                $attempt->reserved_sisa_code = null;
            }
            if (!$attempt->save()) {
                throw new \RuntimeException('No se pudo registrar el rechazo del pago.');
            }
            return;
        }

        $amount = round((float) ($paymentData['transaction_amount'] ?? 0), 2);
        $currency = strtoupper(trim((string) ($paymentData['currency_id'] ?? '')));
        if ($currency !== 'ARS'
            || number_format($amount, 2, '.', '') !== number_format((float) $attempt->amount_total_ars, 2, '.', '')) {
            throw new \RuntimeException('El pago recibido no coincide con el importe y moneda del checkout.');
        }

        if (BillingPayment::find()->where(['provider_payment_id' => $paymentId])->exists()) {
            return;
        }
        $paidAt = trim((string) ($paymentData['date_approved'] ?? ''));
        if ($paidAt === '') {
            $paidAt = trim((string) ($paymentData['date_created'] ?? ''));
        }
        if ($paidAt === '') {
            throw new \RuntimeException('El pago aprobado no contiene una fecha válida.');
        }
        $paidThroughAt = self::paidThroughAt($paidAt);
        if ($attempt->paid_through_at !== null && $attempt->paid_through_at > $paidThroughAt) {
            $paidThroughAt = $attempt->paid_through_at;
        }
        try {
            $paidAt = (new \DateTimeImmutable($paidAt))
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            throw new \RuntimeException('El pago aprobado contiene una fecha inválida.', 0, $e);
        }

        $confirmedPayment = [
            'provider_payment_id' => $paymentId,
            'provider_subscription_id' => $subscriptionId,
            'amount_usd' => (float) $attempt->amount_usd,
            'amount_ars' => $amount,
            'external_reference' => $attempt->checkout_key,
            'paid_at' => $paidAt,
            'provider_payload' => $paymentData,
        ];

        if ((int) $attempt->id_billing_account > 0) {
            $this->recordRecurringPayment((int) $attempt->id_billing_account, $attempt, $confirmedPayment);
            $account = BillingAccount::findOne((int) $attempt->id_billing_account);
            if ($account === null) {
                throw new \RuntimeException('No se encontró la cuenta de licencia del pago recurrente.');
            }
        } else {
            $user = User::findOne((int) $attempt->id_user);
            $persona = Persona::findOne((int) $attempt->id_persona);
            if ($user === null || $persona === null) {
                throw new \RuntimeException('No se encontró la identidad que inició el checkout.');
            }

            $payload = [
                'perfil' => $attempt->perfil,
                'sector' => $attempt->sector,
                'id_billing_account_ministerio' => $attempt->id_billing_account_ministerio,
                'contact_phone' => $attempt->contact_phone,
                'payer_email' => $attempt->payer_email,
                'efector' => json_decode($attempt->efector_json, true),
                'plan' => json_decode($attempt->plan_json, true),
            ];
            if (!is_array($payload['efector']) || !is_array($payload['plan'])) {
                throw new \RuntimeException('Los datos guardados del alta no son válidos.');
            }

            $result = SignUpInstitutionalEfector::register($payload, $user, $persona, $confirmedPayment);
            $attempt->id_efector = (int) $result['id_efector'];
            $attempt->id_billing_account = (int) $result['id_billing_account'];
        }

        $account = BillingAccount::findOne((int) $attempt->id_billing_account);
        if ($account === null) {
            throw new \RuntimeException('No se encontró la cuenta de licencia del pago aprobado.');
        }
        $hasPaidAccess = $paidThroughAt > date('Y-m-d H:i:s');
        $account->activo = $hasPaidAccess ? 1 : 0;
        if (!$account->save(false, ['activo', 'updated_at'])) {
            throw new \RuntimeException('No se pudo actualizar la licencia tras el pago aprobado.');
        }

        $attempt->provider_payment_id = $paymentId;
        $attempt->provider_status = $status;
        $attempt->status = $hasPaidAccess
            ? BillingCheckoutAttempt::STATUS_ACTIVE
            : BillingCheckoutAttempt::STATUS_EXPIRED;
        $attempt->reserved_sisa_code = null;
        $attempt->paid_through_at = $paidThroughAt;
        $attempt->last_provider_payload = $this->encodeJson($paymentData);
        if (!$attempt->save()) {
            throw new \RuntimeException('No se pudo guardar la confirmación del pago.');
        }

        if ($attempt->status === BillingCheckoutAttempt::STATUS_ACTIVE) {
            $this->syncDraftActivation((int) $attempt->id_persona, (string) $attempt->checkout_key);
        }
    }

    /**
     * Keeps the onboarding draft consistent with the reconciled payment.
     *
     * The draft is auxiliary state: a failure here must never roll back an already
     * authorized payment or its provisioning, so it is logged and retried by the
     * next status poll.
     */
    private function syncDraftActivation(int $idPersona, string $checkoutKey): void
    {
        try {
            (new CommercialSignupDraftService())->markActivated($idPersona, $checkoutKey);
        } catch (\Throwable $e) {
            Yii::warning([
                'event' => 'billing.signup.draft_activation_failed',
                'id_persona' => $idPersona,
                'checkout_key' => $checkoutKey,
                'error' => $e->getMessage(),
            ], 'billing.signup');
        }
    }

    /**
     * @param array<string, mixed> $confirmedPayment
     */
    private function recordRecurringPayment(
        int $idBillingAccount,
        BillingCheckoutAttempt $attempt,
        array $confirmedPayment
    ): void {
        if (BillingPayment::find()->where([
            'provider_payment_id' => $confirmedPayment['provider_payment_id'],
        ])->exists()) {
            return;
        }

        $payment = new BillingPayment();
        $payment->id_billing_account = $idBillingAccount;
        $payment->provider = BillingPayment::PROVIDER_MERCADOPAGO;
        $payment->status = BillingPayment::STATUS_APPROVED;
        $payment->amount_usd = (float) $attempt->amount_usd;
        $payment->amount_ars = (float) $confirmedPayment['amount_ars'];
        $payment->currency = 'ARS';
        $payment->external_reference = $attempt->checkout_key;
        $payment->provider_subscription_id = $confirmedPayment['provider_subscription_id'];
        $payment->provider_payment_id = $confirmedPayment['provider_payment_id'];
        $payment->paid_at = $confirmedPayment['paid_at'];
        $payment->payload_json = $this->encodeJson($confirmedPayment['provider_payload']);
        if (!$payment->save()) {
            throw new \RuntimeException('No se pudo registrar el pago recurrente.');
        }
    }

    /**
     * @param mixed $value
     */
    private function encodeJson($value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new \RuntimeException('No se pudo serializar la información del checkout.');
        }

        return $json;
    }

    private function mercadoPago(): MercadoPagoCheckoutService
    {
        if ($this->mercadoPago === null) {
            $this->mercadoPago = new MercadoPagoCheckoutService();
        }

        return $this->mercadoPago;
    }
}
