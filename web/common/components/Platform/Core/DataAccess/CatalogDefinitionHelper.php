<?php

namespace common\components\Platform\Core\DataAccess;

/**
 * Utilidades para leer definiciones del catálogo data-access-config (keywords, etc.).
 */
final class CatalogDefinitionHelper
{
    /**
     * @param array<string, mixed> $def
     * @return list<string>
     */
    public static function keywords(array $def): array
    {
        $raw = [];
        if (isset($def['keywords']) && is_array($def['keywords'])) {
            $raw = $def['keywords'];
        } elseif (isset($def['assistant']) && is_array($def['assistant'])
            && isset($def['assistant']['keywords']) && is_array($def['assistant']['keywords'])) {
            $raw = $def['assistant']['keywords'];
        }

        $out = [];
        foreach ($raw as $kw) {
            if (is_string($kw) && trim($kw) !== '') {
                $out[] = trim($kw);
            }
        }

        return $out;
    }
}
