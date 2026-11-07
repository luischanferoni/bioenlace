<?php

namespace common\components\Domain\Organization\Efector\Application\Service;

use common\components\Domain\Organization\Efector\Application\UseCase\CreateIdentityAccount;
use common\models\Person\Persona;
use common\models\Platform\User;
use Yii;

/**
 * Identidad fija de prueba para validar el checkout de Mercado Pago en sandbox
 * sin pasar por Didit.
 *
 * Solo se habilita con `mercadoPago.sandboxSignupEnabled` y nunca en
 * producción. No se usa en el flujo real de alta.
 */
final class SandboxSignupIdentityService
{
    public const VERIFICATION_ID = 'sandbox-signup-test';

    private const TEST_DOCUMENTO = '99999999';
    private const TEST_NOMBRE = 'Prueba';
    private const TEST_APELLIDO = 'Sandbox';
    private const TEST_FECHA_NACIMIENTO = '1990-01-01';
    private const TEST_SEXO_BIOLOGICO = 1;

    /**
     * @throws \DomainException si el alta de prueba no está habilitada
     */
    public function assertEnabled(): void
    {
        $enabled = (bool) (Yii::$app->params['mercadoPago']['sandboxSignupEnabled'] ?? false);
        $isProd = (defined('YII_ENV_PROD') && YII_ENV_PROD)
            || (defined('YII_ENV') && YII_ENV === 'prod');
        if (!$enabled || $isProd) {
            throw new \DomainException('El alta de prueba (sandbox) no está habilitada en este entorno.');
        }
    }

    /**
     * Crea o recupera la persona y usuario de prueba.
     *
     * @return array{persona: Persona, user: User}
     */
    public function resolveAccount(): array
    {
        $this->assertEnabled();
        $account = (new CreateIdentityAccount())->crearDesdeVerificacionAprobada(
            $this->diditResult(),
            self::VERIFICATION_ID
        );

        return [
            'persona' => $account['persona'],
            'user' => $account['user'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function diditResult(): array
    {
        return [
            'success' => true,
            'status' => 'approved',
            'documento' => self::TEST_DOCUMENTO,
            'nombre' => self::TEST_NOMBRE,
            'apellido' => self::TEST_APELLIDO,
            'fecha_nacimiento' => self::TEST_FECHA_NACIMIENTO,
            'sexo_biologico' => self::TEST_SEXO_BIOLOGICO,
            'verification_id' => self::VERIFICATION_ID,
        ];
    }
}
