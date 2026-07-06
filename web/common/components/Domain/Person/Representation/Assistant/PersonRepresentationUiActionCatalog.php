<?php

namespace common\components\Domain\Person\Representation\Assistant;

use common\components\Platform\Assistant\Catalog\UiActionCatalogProviderInterface;
use common\components\Platform\Assistant\Catalog\YamlIntentCatalogService;
use common\components\Platform\Assistant\UiActions\ActionMappingService;
use common\components\Platform\Ui\ApiV1HttpRoute;

/**
 * Acciones API de representación paciente (tutela A, delegación B, contexto sujeto).
 */
final class PersonRepresentationUiActionCatalog implements UiActionCatalogProviderInterface
{
    /** @var list<array<string, mixed>>|null */
    private static ?array $definitions = null;

    /**
     * @return list<array<string, mixed>>
     */
    public static function discoverAll(): array
    {
        if (self::$definitions !== null) {
            return self::$definitions;
        }

        self::$definitions = [
            self::def(
                'person-representation.hub',
                'Gestionar representación',
                'Vínculos de tutela, representantes designados y preferencias de notificación.',
                '/api/person-representation/pacientes-a-cargo',
                ['representación', 'representante', 'tutela', 'menor', 'a cargo de', 'vínculo familiar'],
                false,
                self::hubClientOpen()
            ),
            self::def(
                'person-representation.solicitar-menor-como-tutor',
                'Solicitar tutela de menor',
                'Alta de vínculo padre/madre/tutor sobre menor sin cuenta (pendiente verificación staff).',
                '/api/person-representation/solicitar-menor-como-tutor',
                ['vincular hijo', 'vincular menor', 'agregar hijo', 'tutela menor', 'mi hijo']
            ),
            self::def(
                'person-representation.mis-vinculos-como-tutor',
                'Mis vínculos como tutor',
                'Listado de menores vinculados en régimen de tutela verificada.',
                '/api/person-representation/mis-vinculos-como-tutor',
                ['mis hijos', 'vínculos tutor', 'menores a cargo tutela']
            ),
            self::def(
                'person-representation.designar-representante',
                'Designar representante',
                'El paciente delega operación a otra persona con cuenta.',
                '/api/person-representation/designar-representante',
                [
                    'designar representante',
                    'delegar cuenta',
                    'delegar gestión de turnos',
                    'delegar turnos',
                    'autorizar familiar',
                    'representante',
                ]
            ),
            self::def(
                'person-representation.mis-representantes',
                'Mis representantes',
                'Representantes activos designados por el paciente.',
                '/api/person-representation/mis-representantes',
                ['mis representantes', 'quien opera por mí', 'revocar representante']
            ),
            self::def(
                'person-representation.pacientes-a-cargo',
                'Pacientes a mi cargo',
                'Personas por las que el usuario puede operar como representante.',
                '/api/person-representation/pacientes-a-cargo',
                ['pacientes a cargo', 'operar por otro', 'a cargo de']
            ),
            self::def(
                'person-representation.establecer-sujeto-paciente',
                'Establecer sujeto de atención',
                'Fija en sesión el paciente sobre el que se actúa (yo u otro con representación).',
                '/api/person-representation/establecer-sujeto-paciente',
                ['cambiar paciente', 'operar por', 'contexto paciente']
            ),
            self::def(
                'person-representation.preferencias-como-paciente',
                'Preferencias de representación',
                'Notificación cuando un representante actúa por el paciente.',
                '/api/person-representation/preferencias-como-paciente',
                ['notificación representante', 'avisar representante', 'preferencias representación']
            ),
            self::def(
                'person-representation.verificar-vinculo-para-staff',
                'Verificar tutela de menor',
                'Staff del centro aprueba una solicitud de tutela pendiente (régimen A).',
                '/api/person-representation/verificar-vinculo-para-staff',
                ['verificar tutela', 'aprobar tutela', 'solicitud tutela', 'vínculo pendiente menor']
            ),
            self::def(
                'person-representation.solicitudes-tutela-pendientes-para-staff',
                'Solicitudes de tutela pendientes',
                'Bandeja staff: solicitudes de tutela (régimen A) en estado pending.',
                '/api/person-representation/solicitudes-tutela-pendientes-para-staff',
                ['solicitudes tutela', 'tutela pendiente', 'aprobar tutela', 'verificar tutela']
            ),
            self::def(
                'person-representation.vinculos-paciente-para-staff',
                'Vínculos de representación (staff)',
                'Listar solicitudes y vínculos de tutela/delegación de un paciente para gestión operativa.',
                '/api/person-representation/vinculos-paciente-para-staff',
                ['vínculos paciente', 'solicitudes tutela', 'representación paciente staff']
            ),
        ];

        return self::$definitions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forUser(int $userId): array
    {
        $out = [];
        foreach (self::discoverAll() as $def) {
            if (!is_array($def) || !self::userCanAccessDefinition($userId, $def)) {
                continue;
            }
            $out[] = $def;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function definitionByActionId(string $actionId): ?array
    {
        $actionId = trim($actionId);
        if ($actionId === '') {
            return null;
        }
        foreach (self::discoverAll() as $def) {
            if (trim((string) ($def['action_id'] ?? '')) === $actionId) {
                return $def;
            }
        }

        return null;
    }

    public static function httpRouteForActionId(string $actionId): string
    {
        if (self::clientOpenForActionId($actionId) !== null) {
            return '';
        }

        $def = self::definitionByActionId($actionId);
        if ($def === null) {
            return '';
        }

        return ApiV1HttpRoute::normalize(trim((string) ($def['route'] ?? '')));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function clientOpenForActionId(string $actionId): ?array
    {
        $def = self::definitionByActionId($actionId);
        if ($def === null) {
            return null;
        }
        $co = $def['client_open'] ?? null;

        return is_array($co) && trim((string) ($co['kind'] ?? '')) !== '' ? $co : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function hubClientOpen(): array
    {
        return [
            'kind' => 'native',
            'mobile' => ['screen_id' => 'person_representation_hub'],
        ];
    }

    /**
     * @param array<string, mixed> $def
     */
    private static function userCanAccessDefinition(int $userId, array $def): bool
    {
        $actionId = trim((string) ($def['action_id'] ?? ''));
        if ($actionId === 'person-representation.hub') {
            return self::userCanAccessHub($userId);
        }

        $rbacRoute = trim((string) ($def['rbac_route'] ?? ''));
        if ($rbacRoute !== '' && ActionMappingService::userIdCanAccessRoute($userId, $rbacRoute)) {
            return true;
        }

        return $actionId !== '' && YamlIntentCatalogService::userIdCanPermissionKey($userId, $actionId);
    }

    private static function userCanAccessHub(int $userId): bool
    {
        foreach ([
            '/api/person-representation/designar-representante',
            '/api/person-representation/mis-representantes',
            '/api/person-representation/solicitar-menor-como-tutor',
            '/api/person-representation/mis-vinculos-como-tutor',
            '/api/person-representation/preferencias-como-paciente',
            '/api/person-representation/pacientes-a-cargo',
        ] as $route) {
            if (ActionMappingService::userIdCanAccessRoute($userId, $route)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $keywords
     * @param array<string, mixed>|null $clientOpen
     * @return array<string, mixed>
     */
    private static function def(
        string $actionId,
        string $actionName,
        string $description,
        string $rbacRoute,
        array $keywords,
        bool $uiJsonDescriptor = false,
        ?array $clientOpen = null
    ): array {
        $httpRoute = ApiV1HttpRoute::normalize($rbacRoute);

        $row = [
            'action_id' => $actionId,
            'action_name' => $actionName,
            'display_name' => $actionName,
            'description' => $description,
            'entity' => 'person-representation',
            'route' => $httpRoute,
            'rbac_route' => $rbacRoute,
            'keywords' => $keywords,
            'synonyms' => [],
            'tags' => ['person', 'representación', 'paciente'],
            'parameters' => [
                'expected' => [],
                'provided' => [
                    'subject_persona_id' => ['description' => 'Paciente sobre el que se opera'],
                ],
            ],
            'intent_semantics' => null,
            'flow_capable' => false,
        ];

        if ($uiJsonDescriptor) {
            $row['client_open'] = [
                'kind' => 'ui_json',
                'api' => [
                    'route' => $httpRoute,
                    'method' => 'GET|POST',
                ],
            ];
            $row['client_interaction'] = 'ui_asistente_json';
        } elseif ($clientOpen !== null) {
            $row['client_open'] = $clientOpen;
            $row['client_interaction'] = ($clientOpen['kind'] ?? '') === 'native'
                ? 'native_screen'
                : 'open';
        }

        return $row;
    }
}
