<?php

namespace common\components\MensajeIntent;

use Yii;
use common\components\Actions\ActionMappingService;

/**
 * Arma el catálogo de destinos permitidos para clasificar un mensaje.
 *
 * - Intents de conversación: desde intent-categories (opcional `required_routes` por intent).
 * - Acciones descubiertas: filtradas por RBAC vía ActionMappingService.
 */
final class MensajeCatalogBuilder
{
    /**
     * Catálogo para mensajería (chat / mismo pipeline que acciones): conversación + rutas permitidas.
     *
     * @param int|string|null $userId
     * @return MensajeCatalogItem[]
     */
    public static function buildForMessaging($userId, bool $includeConversationIntents = true): array
    {
        $items = [];
        if ($includeConversationIntents) {
            $items = array_merge($items, self::conversationItems($userId));
        }
        $items = array_merge($items, self::actionItems($userId));

        return $items;
    }

    /**
     * Solo acciones web descubiertas que el usuario puede ejecutar (p. ej. UniversalQueryAgent).
     *
     * @param int|string|null $userId
     * @return MensajeCatalogItem[]
     */
    public static function buildAllowedActionsOnly($userId): array
    {
        return self::actionItems($userId);
    }

    /**
     * @param int|string|null $userId
     * @return MensajeCatalogItem[]
     */
    private static function conversationItems($userId): array
    {
        $categories = require Yii::getAlias('@common/config/chatbot/intent-categories.php');
        $items = [];

        foreach ($categories as $categoryKey => $category) {
            foreach ($category['intents'] as $intentKey => $intentConfig) {
                if (!empty($intentConfig['is_fallback'])) {
                    continue;
                }
                $requiredRoutes = $intentConfig['required_routes'] ?? [];
                if (is_array($requiredRoutes) && $requiredRoutes !== [] && !self::userMayAccessAnyRoute($userId, $requiredRoutes)) {
                    continue;
                }

                $name = $intentConfig['name'] ?? $intentKey;
                $keywords = $intentConfig['keywords'] ?? [];
                if (!is_array($keywords)) {
                    $keywords = [];
                }
                $patterns = $intentConfig['patterns'] ?? [];
                if (!is_array($patterns)) {
                    $patterns = [];
                }
                $priority = $intentConfig['priority'] ?? 'low';

                $items[] = new MensajeCatalogItem(
                    '',
                    self::conversationActionId($categoryKey, $intentKey),
                    is_string($name) ? $name : (string) $intentKey,
                    (string) ($category['description'] ?? '') . ' — ' . (is_string($name) ? $name : $intentKey),
                    $keywords,
                    $patterns,
                    (string) $categoryKey,
                    (string) $intentKey,
                    is_string($priority) ? $priority : 'low'
                );
            }
        }

        return $items;
    }

    /**
     * @param int|string|null $userId
     * @return MensajeCatalogItem[]
     */
    private static function actionItems($userId): array
    {
        $uid = self::normalizeUserId($userId);
        if ($uid === null) {
            return [];
        }

        $actions = ActionMappingService::getAvailableActionsForUser($uid, true);
        $items = [];

        foreach ($actions as $action) {
            $route = (string) ($action['route'] ?? '');
            $actionId = (string) ($action['action_id'] ?? '');
            if ($actionId === '') {
                continue;
            }
            $title = (string) ($action['display_name'] ?? $actionId);
            $description = (string) ($action['description'] ?? '');

            $kw = [];
            foreach (['keywords', 'tags', 'synonyms'] as $field) {
                if (empty($action[$field]) || !is_array($action[$field])) {
                    continue;
                }
                foreach ($action[$field] as $v) {
                    if (is_string($v) && $v !== '') {
                        $kw[] = $v;
                    }
                }
            }
            $kw = array_values(array_unique($kw));

            $items[] = new MensajeCatalogItem(
                $route,
                $actionId,
                $title,
                $description,
                $kw,
                [],
                null,
                null,
                'low'
            );
        }

        return $items;
    }

    private static function conversationActionId(string $category, string $intent): string
    {
        return 'conv:' . $category . '.' . $intent;
    }

    /**
     * @param string[] $routes
     */
    private static function userMayAccessAnyRoute($userId, array $routes): bool
    {
        $uid = self::normalizeUserId($userId);
        if ($uid === null) {
            return false;
        }
        foreach ($routes as $r) {
            if (!is_string($r) || $r === '') {
                continue;
            }
            if (ActionMappingService::userIdCanAccessRoute($uid, $r)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param int|string|null $userId
     */
    private static function normalizeUserId($userId): ?int
    {
        if ($userId === null || $userId === '') {
            return null;
        }
        if (is_int($userId)) {
            return $userId > 0 ? $userId : null;
        }
        if (is_string($userId) && ctype_digit($userId)) {
            $n = (int) $userId;

            return $n > 0 ? $n : null;
        }

        return null;
    }
}
