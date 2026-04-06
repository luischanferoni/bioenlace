<?php

namespace common\components\Actions;

/**
 * Enriquece acciones del asistente con {@see $action['client_open']} para que web y apps
 * abran pantallas nativas en lugar de tratar la URL de API como destino de navegación.
 */
final class AssistantClientOpenEnricher
{
    /**
     * @param array<string, mixed> $action acción ya pasada por formatActionsForResponse (action_id, route, parameters, …)
     * @return array<string, mixed>
     */
    public static function enrich(array $action): array
    {
        $actionId = (string) ($action['action_id'] ?? '');
        $route = (string) ($action['route'] ?? '');

        if (self::matchesAgendaLaboral($actionId, $route)) {
            $query = self::agendaQueryFromAction($action);
            $action['client_open'] = [
                'kind' => 'yii_web_screen',
                'web' => [
                    'path' => '/agenda',
                    'query' => $query,
                ],
                'mobile' => [
                    'path' => '/agenda',
                    'query' => $query,
                ],
            ];
            $action['client_interaction'] = 'native_web';

            $dn = (string) ($action['display_name'] ?? '');
            if ($dn === '' || strncmp($dn, 'RBAC:', 5) === 0) {
                $action['display_name'] = 'Abrir agenda laboral';
            }
        }

        return $action;
    }

    private static function matchesAgendaLaboral(string $actionId, string $route): bool
    {
        if (preg_match('/^agenda\.(actualizar|listar|crear)(-para-recurso)?$/', $actionId) === 1) {
            return true;
        }

        return (bool) preg_match('#/api/v\d+/agenda/(actualizar|listar|crear)(-para-recurso)?#', $route);
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, string>
     */
    private static function agendaQueryFromAction(array $action): array
    {
        $out = [];
        $provided = self::extractProvidedScalarMap($action);
        if (isset($provided['id']) && $provided['id'] !== '' && $provided['id'] !== null) {
            $out['id_agenda_rrhh'] = (string) $provided['id'];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>
     */
    private static function extractProvidedScalarMap(array $action): array
    {
        $params = $action['parameters'] ?? null;
        if (!is_array($params)) {
            return [];
        }
        if (isset($params['provided']) && is_array($params['provided'])) {
            return $params['provided'];
        }

        return [];
    }
}
