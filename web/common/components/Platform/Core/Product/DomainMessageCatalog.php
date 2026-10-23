<?php

namespace common\components\Platform\Core\Product;

use Symfony\Component\Yaml\Yaml;

/**
 * Mensajes de usuario de un caso de uso, cargados desde un YAML versionado.
 * Formato: `version: 1` y `messages: {clave: 'texto con {placeholders}'}`.
 */
final class DomainMessageCatalog
{
    /** @var array<string, array<string, string>> */
    private static array $cache = [];

    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * @param array<string, scalar> $params
     */
    public function text(string $key, array $params = []): string
    {
        $messages = $this->load();
        if (!isset($messages[$key])) {
            throw new \RuntimeException('Mensaje sin definir en ' . basename($this->path) . ': ' . $key);
        }

        $replacements = [];
        foreach ($params as $name => $value) {
            $replacements['{' . $name . '}'] = (string) $value;
        }

        return strtr($messages[$key], $replacements);
    }

    public static function resetCacheForTests(): void
    {
        self::$cache = [];
    }

    /**
     * @return array<string, string>
     */
    private function load(): array
    {
        if (isset(self::$cache[$this->path])) {
            return self::$cache[$this->path];
        }
        if (!is_file($this->path)) {
            throw new \RuntimeException('No se encontró el catálogo de mensajes: ' . $this->path);
        }

        $data = Yaml::parseFile($this->path);
        if (!is_array($data) || ($data['version'] ?? null) !== 1 || !is_array($data['messages'] ?? null)) {
            throw new \RuntimeException('Catálogo de mensajes inválido: ' . basename($this->path));
        }
        foreach ($data['messages'] as $key => $text) {
            if (!is_string($text) || trim($text) === '') {
                throw new \RuntimeException('Mensaje vacío en ' . basename($this->path) . ': ' . $key);
            }
        }

        self::$cache[$this->path] = $data['messages'];
        return self::$cache[$this->path];
    }
}
