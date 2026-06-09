<?php

namespace common\components\Assistant\Catalog;

use common\components\Ui\ApiV1HttpRoute;

/**
 * Acciones API de representación paciente (tutela A, delegación B, contexto sujeto).
 */
final class PersonRepresentationUiActionCatalog
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

        $hubNative = [
            'kind' => 'native',
            'mobile' => ['screen_id' => 'person_representation_hub'],
            'web' => ['path' => '/configuracion#representacion'],
        ];

        self::$definitions = [
            self::def(
                'person-representation.hub',
                'Gestionar representación',
                'Vínculos de tutela, representantes designados y preferencias de notificación.',
                '/api/person-representation/pacientes-a-cargo',
                ['representación', 'representante', 'tutela', 'menor', 'a cargo de', 'vínculo familiar'],
                false,
                $hubNative
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
                ['designar representante', 'delegar cuenta', 'autorizar familiar', 'representante']
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
        ];

        return self::$definitions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forUser(int $userId): array
    {
        return YamlIntentCatalogService::filterByRbac(self::discoverAll(), $userId);
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
