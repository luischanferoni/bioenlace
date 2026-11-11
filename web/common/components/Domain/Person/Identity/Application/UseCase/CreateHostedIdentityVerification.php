<?php

namespace common\components\Domain\Person\Identity\Application\UseCase;

use common\components\Domain\Person\FrontDesk\Infrastructure\External\Identity\Connector\DiditClient;

/**
 * Crea una sesión hosted de Didit para verificar identidad desde un navegador.
 *
 * Lo usan el alta asistida de pacientes, el alta web con el teléfono y cualquier flujo
 * que mande al usuario a la página alojada de Didit. No decide nada del dominio: sólo
 * envuelve el ACL y traduce el fallo del proveedor a una excepción.
 */
final class CreateHostedIdentityVerification
{
    private ?DiditClient $client;

    public function __construct(?DiditClient $client = null)
    {
        $this->client = $client;
    }

    /**
     * @param string $vendorData identificador propio del flujo; no debe llevar datos personales
     * @return array{session_id: string, url: string}
     * @throws \RuntimeException si Didit no pudo crear la sesión
     */
    public function crear(string $callback, string $vendorData, string $language = 'es'): array
    {
        $session = $this->client()->createVerificationSession([
            'callback' => $callback,
            'vendor_data' => $vendorData,
            'language' => $language,
        ]);

        if (empty($session['success'])) {
            throw new \RuntimeException(
                (string) ($session['message'] ?? 'No se pudo crear la sesión de verificación.')
            );
        }

        $sessionId = trim((string) ($session['session_id'] ?? ''));
        $url = trim((string) ($session['url'] ?? ''));
        if ($sessionId === '' || $url === '') {
            throw new \RuntimeException('Didit devolvió una sesión incompleta.');
        }

        return [
            'session_id' => $sessionId,
            'url' => $url,
        ];
    }

    private function client(): DiditClient
    {
        return $this->client ?? new DiditClient();
    }
}
