<?php

namespace common\components\Domain\Organization\Efector\Application\Service;

/**
 * Falla de estado del vínculo del alta web.
 *
 * `reason` permite al controlador mapear el error HTTP sin interpretar mensajes.
 */
final class SignupPairingException extends \DomainException
{
    public const NOT_FOUND = 'not_found';
    public const ALREADY_USED = 'already_used';
    public const EXPIRED = 'expired';
    public const INVALID_STATE = 'invalid_state';
    public const INVALID_CODE = 'invalid_code';
    public const ATTEMPTS_EXHAUSTED = 'attempts_exhausted';
    public const DEVICE_MISMATCH = 'device_mismatch';

    public function __construct(
        public readonly string $reason,
        public readonly int $remainingAttempts = 0
    ) {
        parent::__construct(self::messageFor($reason));
    }

    private static function messageFor(string $reason): string
    {
        return match ($reason) {
            self::NOT_FOUND => 'El vínculo no existe o expiró.',
            self::ALREADY_USED => 'Este vínculo ya fue utilizado. Volvé a empezar el alta.',
            self::EXPIRED => 'El vínculo expiró. Volvé a empezar el alta.',
            self::INVALID_STATE => 'El vínculo no está en el estado necesario para continuar.',
            self::INVALID_CODE => 'El código no es correcto.',
            self::ATTEMPTS_EXHAUSTED => 'Se agotaron los intentos del código. Volvé a empezar el alta.',
            self::DEVICE_MISMATCH => 'El vínculo pertenece a otro dispositivo.',
            default => 'No se pudo continuar con el vínculo del alta.',
        };
    }
}
