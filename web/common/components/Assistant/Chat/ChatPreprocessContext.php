<?php

namespace common\components\Assistant\Chat;

use Yii;

/**
 * Contexto de preprocess de la última consulta raíz (por sesión web).
 */
final class ChatPreprocessContext
{
    private const SESSION_KEY = 'asistente_chat_preprocess';

    /**
     * @param array<string, mixed> $data
     */
    public static function set(array $data): void
    {
        Yii::$app->session->set(self::SESSION_KEY, $data);
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        $v = Yii::$app->session->get(self::SESSION_KEY, []);
        return is_array($v) ? $v : [];
    }

    public static function clear(): void
    {
        Yii::$app->session->remove(self::SESSION_KEY);
    }

    public static function userGoal(): string
    {
        $d = self::get();
        return isset($d['user_goal']) ? trim((string) $d['user_goal']) : '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function extractions(): array
    {
        $d = self::get();
        $ex = $d['extractions'] ?? [];
        if (!is_array($ex)) {
            return [];
        }
        $out = [];
        foreach ($ex as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }
        return $out;
    }

    public static function normalizedText(): string
    {
        $d = self::get();
        return isset($d['normalized_text']) ? trim((string) $d['normalized_text']) : '';
    }
}
