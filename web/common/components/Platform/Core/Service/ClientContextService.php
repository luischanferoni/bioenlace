<?php

namespace common\components\Platform\Core\Service;

use common\components\Domain\Person\Ventanilla\VentanillaSesionMetadata;
use common\components\Domain\Person\Ventanilla\VentanillaSesionService;
use common\components\Platform\Core\Product\ClientContextMetadata;
use Yii;

/**
 * Contexto de cliente (web vs móvil) para RBAC y descubrimiento de acciones.
 *
 * En web el rol `paciente` se omite: el staff opera con roles PES / especiales.
 * Reglas de flows/notificaciones paciente: {@see ClientContextMetadata} (metadata producto).
 *
 * Canales paciente declarados en client-context (`mobile_paciente`, `whatsapp_paciente`, …)
 * nunca se tratan como web staff, aunque falte `X-Client` (default histórico = web).
 */
final class ClientContextService
{
    public const HEADER_CLIENT = 'X-Client';

    public const HEADER_APP_CLIENT = 'X-App-Client';

    public const CLIENT_WEB = 'web';

    public const CLIENT_MOBILE = 'mobile';

    /**
     * @return list<string>
     */
    public static function pacienteNotificacionTipos(): array
    {
        return ClientContextMetadata::pacienteNotificacionTipos();
    }

    public static function isWebClient(): bool
    {
        if (!Yii::$app->has('request')) {
            return false;
        }
        $req = Yii::$app->request;
        if (!$req instanceof \yii\web\Request) {
            return false;
        }

        $appClient = trim((string) $req->headers->get(self::HEADER_APP_CLIENT, ''));
        if (ClientContextMetadata::isPacienteFacingAppClient($appClient !== '' ? $appClient : null)) {
            return false;
        }

        $client = strtolower(trim((string) $req->headers->get(self::HEADER_CLIENT, self::CLIENT_WEB)));

        return $client === self::CLIENT_WEB;
    }

    /**
     * Web: no inyectar rol paciente dinámico ni permisos derivados.
     */
    public static function shouldOmitPacienteRole(): bool
    {
        return self::isWebClient();
    }

    /**
     * Web sin efector en sesión: union de roles/permisos PES activos de la persona (todos los efectores).
     */
    public static function shouldMergeAllPesRolesForPerson(): bool
    {
        return self::isWebClient();
    }

    /**
     * Sufijo para claves de caché RBAC (web staff vs resto).
     */
    public static function rbacCacheSuffix(): string
    {
        return self::shouldOmitPacienteRole() ? '_client_web' : '_client_all';
    }

    /**
     * @param array<string, mixed> $flow
     */
    public static function isPacienteOnlyFlow(array $flow): bool
    {
        return ClientContextMetadata::isPacienteOnlyFlow($flow);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    public static function filterPacienteFlows(array $items): array
    {
        if (!self::shouldOmitPacienteRole()) {
            return $items;
        }
        $unhide = self::ventanillaUnhideIntentMap();
        $out = [];
        foreach ($items as $flow) {
            if (!is_array($flow)) {
                continue;
            }
            if (self::isPacienteOnlyFlow($flow) && !self::isUnhiddenPacienteFlow($flow, $unhide)) {
                continue;
            }
            $out[] = $flow;
        }

        return $out;
    }

    /**
     * @return array<string, true>
     */
    private static function ventanillaUnhideIntentMap(): array
    {
        try {
            $subject = (new VentanillaSesionService())->sujetoActivo();
        } catch (\Throwable $e) {
            return [];
        }
        if ($subject === null || $subject <= 0) {
            return [];
        }
        $map = [];
        foreach (VentanillaSesionMetadata::unhidePacienteIntentIds() as $id) {
            $map[$id] = true;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $flow
     * @param array<string, true> $unhide
     */
    private static function isUnhiddenPacienteFlow(array $flow, array $unhide): bool
    {
        if ($unhide === []) {
            return false;
        }
        $intentId = trim((string) ($flow['action_id'] ?? $flow['intent_id'] ?? ''));

        return $intentId !== '' && isset($unhide[$intentId]);
    }
}
