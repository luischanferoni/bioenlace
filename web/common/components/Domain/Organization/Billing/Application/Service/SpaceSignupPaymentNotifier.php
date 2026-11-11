<?php

namespace common\components\Domain\Organization\Billing\Application\Service;

use common\components\Domain\Organization\Billing\Application\Catalog\SpaceSignupPaymentNotificationCatalog;
use common\components\Shared\Infrastructure\Mail\PlainTextMailer;
use Yii;

/**
 * Avisa al titular que su espacio quedó habilitado después de confirmarse el pago.
 *
 * El e-mail es el único canal que no depende de que la persona tenga la app; por eso
 * también el alta paga lo exige confirmado (`SignupEmailGate`). Nunca lanza: un fallo
 * de envío no puede revertir un alta ya paga.
 */
final class SpaceSignupPaymentNotifier
{
    private PlainTextMailer $mailer;

    public function __construct(?PlainTextMailer $mailer = null)
    {
        $this->mailer = $mailer ?? new PlainTextMailer();
    }

    /**
     * @return array{sent: bool, reason: string}
     */
    public function notifyPaymentConfirmed(string $email, string $efectorNombre): array
    {
        try {
            $message = (new SpaceSignupPaymentNotificationCatalog())->paymentConfirmed($efectorNombre);
        } catch (\Throwable $e) {
            Yii::error('Aviso de pago: ' . $e->getMessage(), 'billing.signup');

            return ['sent' => false, 'reason' => 'catalog_unavailable'];
        }

        $result = $this->mailer->send($email, $message['subject'], $message['body']);

        if (!$result['sent']) {
            Yii::warning(
                sprintf(
                    'Aviso de pago no enviado (motivo: %s) para %s',
                    $result['reason'],
                    $email
                ),
                'billing.signup'
            );
        }

        return $result;
    }
}
