<?php

namespace common\components\Domain\Organization\Service\Billing;

use common\models\BillingPayment;
use Yii;

/**
 * Pasarela de pago simulada (MVP). Sustituible por Mercado Pago / Stripe sin cambiar el alta.
 */
final class SimulatedPaymentGateway
{
    public const SIM_FAIL_PAN = '4000000000000002';

    /**
     * @param array{
     *   id_billing_account: int,
     *   amount_usd: float|int|string,
     *   card_number?: string|null,
     *   card_holder?: string|null,
     *   currency?: string|null
     * } $input
     */
    public static function charge(array $input): BillingPayment
    {
        $idAccount = (int) ($input['id_billing_account'] ?? 0);
        if ($idAccount <= 0) {
            throw new \InvalidArgumentException('Cuenta de facturación inválida para el cobro.');
        }

        $amount = round((float) ($input['amount_usd'] ?? 0), 2);
        if ($amount < 0) {
            throw new \InvalidArgumentException('Monto inválido.');
        }

        $pan = preg_replace('/\D+/', '', (string) ($input['card_number'] ?? '4242424242424242')) ?? '';
        if (strlen($pan) < 12 || strlen($pan) > 19) {
            throw new \InvalidArgumentException('Número de tarjeta inválido.');
        }

        $approved = $pan !== self::SIM_FAIL_PAN;
        $ref = 'sim_' . bin2hex(random_bytes(8));

        $payment = new BillingPayment();
        $payment->id_billing_account = $idAccount;
        $payment->provider = BillingPayment::PROVIDER_SIMULATED;
        $payment->status = $approved ? BillingPayment::STATUS_APPROVED : BillingPayment::STATUS_REJECTED;
        $payment->amount_usd = $amount;
        $payment->currency = strtoupper(trim((string) ($input['currency'] ?? 'USD'))) ?: 'USD';
        $payment->external_reference = $ref;
        $payment->card_last4 = substr($pan, -4);
        $payment->payload_json = json_encode([
            'simulated' => true,
            'card_holder' => (string) ($input['card_holder'] ?? ''),
            'message' => $approved ? 'Pago simulado aprobado' : 'Pago simulado rechazado (tarjeta de prueba)',
        ], JSON_UNESCAPED_UNICODE);
        $payment->paid_at = $approved ? date('Y-m-d H:i:s') : null;

        if (!$payment->save()) {
            throw new \InvalidArgumentException('No se pudo registrar el pago: ' . json_encode($payment->getErrors()));
        }

        if (!$approved) {
            throw new \InvalidArgumentException(
                'El pago fue rechazado. Usá otra tarjeta de prueba (p. ej. 4242…4242) o contactanos.'
            );
        }

        Yii::info([
            'event' => 'billing.payment.simulated_approved',
            'id_billing_account' => $idAccount,
            'amount_usd' => $amount,
            'reference' => $ref,
        ], 'billing');

        return $payment;
    }
}
