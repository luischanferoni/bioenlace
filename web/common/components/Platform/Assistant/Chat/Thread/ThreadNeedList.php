<?php

namespace common\components\Platform\Assistant\Chat\Thread;

/**
 * Necesidades del hilo: oración + estado. La guía lee solo las activas.
 */
final class ThreadNeedList
{
    public const ACTIVA = 'activa';
    public const SATISFECHA = 'satisfecha';
    public const DESCARTADA = 'descartada';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::ACTIVA, self::SATISFECHA, self::DESCARTADA];
    }

    public static function isValid(string $estado): bool
    {
        return in_array($estado, self::all(), true);
    }

    /**
     * Acepta objetos {expresion, estado} y, mientras el prompt siga pidiendo
     * oraciones sueltas, cada string cuenta como activa.
     *
     * @param mixed $raw
     * @return list<array{expresion: string, estado: string}>
     */
    public static function normalize($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $byExpression = [];
        foreach ($raw as $item) {
            $need = self::one($item);
            if ($need === null) {
                continue;
            }
            $byExpression[$need['expresion']] = $need;
        }

        return array_values($byExpression);
    }

    /**
     * @param list<array{expresion: string, estado: string}> $needs
     */
    public static function activeText(array $needs): string
    {
        $lines = [];
        foreach ($needs as $need) {
            if (($need['estado'] ?? '') !== self::ACTIVA) {
                continue;
            }
            $text = trim((string) ($need['expresion'] ?? ''));
            if ($text !== '') {
                $lines[] = $text;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<array{expresion: string, estado: string}> $needs
     */
    public static function formatForPreprocess(array $needs): string
    {
        $lines = [];
        foreach ($needs as $need) {
            $estado = trim((string) ($need['estado'] ?? ''));
            $text = trim((string) ($need['expresion'] ?? ''));
            if ($text === '' || !self::isValid($estado)) {
                continue;
            }
            $lines[] = '- ' . $estado . ': ' . $text;
        }

        return implode("\n", $lines);
    }

    /**
     * @param mixed $item
     * @return array{expresion: string, estado: string}|null
     */
    private static function one($item): ?array
    {
        if (is_string($item)) {
            $text = trim($item);

            return $text === '' ? null : ['expresion' => $text, 'estado' => self::ACTIVA];
        }
        if (!is_array($item)) {
            return null;
        }

        $text = trim((string) ($item['expresion'] ?? ''));
        $estado = strtolower(trim((string) ($item['estado'] ?? '')));
        if ($text === '' || !self::isValid($estado)) {
            return null;
        }

        return ['expresion' => $text, 'estado' => $estado];
    }
}
