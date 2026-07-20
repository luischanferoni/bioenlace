<?php

namespace common\components\Domain\Scheduling\Service;

use Symfony\Component\Yaml\Yaml;

/**
 * Plantillas push async ({@see metadata/consulta_async_push.yaml}).
 */
final class ConsultaAsyncPushCatalogService
{
    private const CATALOG_FILE = 'consulta_async_push.yaml';

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @param array<string, string> $replace
     * @return array{title: string, body: string}
     */
    public function event(string $eventKey, array $replace = []): array
    {
        $events = self::load()['events'] ?? [];
        $tpl = is_array($events) && is_array($events[$eventKey] ?? null) ? $events[$eventKey] : [];
        $title = (string) ($tpl['title'] ?? '');
        $body = (string) ($tpl['body'] ?? '');

        return [
            'title' => str_replace(array_keys($replace), array_values($replace), $title),
            'body' => str_replace(array_keys($replace), array_values($replace), $body),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $path = __DIR__ . '/../metadata/' . self::CATALOG_FILE;
        if (!is_file($path)) {
            self::$cache = [];

            return self::$cache;
        }
        $parsed = Yaml::parseFile($path);
        self::$cache = is_array($parsed) ? $parsed : [];

        return self::$cache;
    }

    public static function resetCache(): void
    {
        self::$cache = null;
    }
}
