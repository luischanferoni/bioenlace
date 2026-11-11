<?php

namespace common\components\Domain\Organization\Billing\Application\Service;

use Yii;

/**
 * Exige un e-mail real y confirmado antes de iniciar el checkout de un alta paga.
 *
 * Es la única regla de este tipo: cuando el pago se confirma, el e-mail es el único
 * canal que no depende de que la persona tenga la app instalada.
 *
 * Se puede apagar con `signupRequireConfirmedEmail` mientras el envío de e-mails no
 * esté verificado en el entorno; encendido, el checkout no avanza sin confirmación.
 */
final class SignupEmailGate
{
    public const PLACEHOLDER_DOMAIN = '@accounts.bioenlace.invalid';

    public static function isRequired(): bool
    {
        return (bool) (Yii::$app->params['signupRequireConfirmedEmail'] ?? false);
    }

    /**
     * @throws \DomainException cuando el alta exige confirmación y el e-mail no sirve
     */
    public static function assertReady(
        ?string $accountEmail,
        string $payerEmail,
        bool $confirmed,
        ?bool $required = null
    ): void {
        $required = $required ?? self::isRequired();
        if (!$required) {
            return;
        }

        $payerEmail = strtolower(trim($payerEmail));
        $accountEmail = strtolower(trim((string) $accountEmail));

        $usable = $accountEmail !== ''
            && !str_ends_with($accountEmail, self::PLACEHOLDER_DOMAIN)
            && filter_var($accountEmail, FILTER_VALIDATE_EMAIL);

        if (!$usable) {
            if (!filter_var($payerEmail, FILTER_VALIDATE_EMAIL)) {
                throw new \DomainException('Ingresá un e-mail válido: lo vamos a usar para avisarte cuando el pago se acredite.');
            }

            throw new \DomainException(
                'Antes de pagar tenés que agregar y confirmar tu e-mail. Te enviamos un enlace de confirmación a '
                . $payerEmail . '.'
            );
        }

        if ($accountEmail !== $payerEmail) {
            throw new \DomainException(
                'El e-mail de pago tiene que ser el de tu cuenta (' . $accountEmail . ').'
            );
        }

        if (!$confirmed) {
            throw new \DomainException(
                'Confirmá tu e-mail antes de pagar: revisá el enlace que te enviamos a ' . $accountEmail . '.'
            );
        }
    }
}
