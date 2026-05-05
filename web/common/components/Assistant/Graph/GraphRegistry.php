<?php

namespace common\components\Assistant\Graph;

use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Carga el grafo conversacional desde YAML.
 *
 * Resolvers: se obtienen aplanando `entities.*.methods` como `Entity.metodo`.
 * Operaciones: `links.*` referencian un `entity` + `submit_method`.
 */
final class GraphRegistry
{
    public const BASE_DIR = '@common/components/Assistant/Graph/schemas';

    /** @var array<string, mixed> */
    private array $graph;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $flatResolvers = null;

    /**
     * @param array<string, mixed> $graph
     */
    private function __construct(array $graph)
    {
        $this->graph = $graph;
    }

    public static function loadTurnos(): self
    {
        $path = Yii::getAlias(self::BASE_DIR . '/turnos.graph.yaml');
        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            throw new \RuntimeException('No se pudo leer el grafo: ' . $path);
        }
        $parsed = Yaml::parse($raw);
        if (!is_array($parsed)) {
            throw new \RuntimeException('Grafo inválido (no es array YAML): ' . $path);
        }
        /** @var array<string, mixed> $graph */
        $graph = $parsed;
        return new self($graph);
    }

    /**
     * @return array<string, mixed>
     */
    public function link(string $linkId): array
    {
        $links = $this->graph['links'] ?? null;
        if (!is_array($links) || !isset($links[$linkId]) || !is_array($links[$linkId])) {
            throw new \InvalidArgumentException('Link no encontrado en el grafo: ' . $linkId);
        }
        return $links[$linkId];
    }

    /**
     * @return array<string, mixed>
     */
    public function operation(string $operationId): array
    {
        return $this->link($operationId);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function allResolvers(): array
    {
        if ($this->flatResolvers !== null) {
            return $this->flatResolvers;
        }
        $this->flatResolvers = $this->flattenEntityMethods();
        return $this->flatResolvers;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolver(string $resolverId): array
    {
        $all = $this->allResolvers();
        if (!isset($all[$resolverId])) {
            throw new \InvalidArgumentException('Resolver no encontrado: ' . $resolverId);
        }
        return $all[$resolverId];
    }

    /**
     * @return list<string> prefijo draft.*
     */
    public function inferredRequiresForLink(string $linkId): array
    {
        $lk = $this->link($linkId);
        $entityName = isset($lk['entity']) ? trim((string) $lk['entity']) : '';
        if ($entityName === '') {
            throw new \RuntimeException('Link sin entity: ' . $linkId);
        }
        $entities = $this->graph['entities'] ?? null;
        if (!is_array($entities) || !isset($entities[$entityName]) || !is_array($entities[$entityName])) {
            throw new \RuntimeException('Entity no encontrada para link: ' . $entityName);
        }
        $ent = $entities[$entityName];
        $fields = $ent['fields'] ?? null;
        if (!is_array($fields)) {
            return [];
        }
        $out = [];
        foreach ($fields as $name => $spec) {
            if (!is_string($name) || $name === '' || !is_array($spec)) {
                continue;
            }
            if (empty($spec['required'])) {
                continue;
            }
            $out[] = 'draft.' . $name;
        }
        return $out;
    }

    public function submitResolverIdForLink(string $linkId): string
    {
        $lk = $this->link($linkId);
        $entityName = isset($lk['entity']) ? trim((string) $lk['entity']) : '';
        $method = isset($lk['submit_method']) ? trim((string) $lk['submit_method']) : '';
        if ($entityName === '' || $method === '') {
            throw new \RuntimeException('Link sin entity o submit_method: ' . $linkId);
        }
        return $entityName . '.' . $method;
    }

    /**
     * @return array<string, list<string>>
     */
    public function providerGatesForLink(string $linkId): array
    {
        $lk = $this->link($linkId);
        $gates = $lk['provider_gates'] ?? null;
        if (!is_array($gates)) {
            return [];
        }
        $out = [];
        foreach ($gates as $providesKey => $spec) {
            if (!is_string($providesKey) || trim($providesKey) === '' || !is_array($spec)) {
                continue;
            }
            $must = self::stringListFromMixed($spec['require_resolver_requires'] ?? null);
            if ($must !== []) {
                $out[trim($providesKey)] = $must;
            }
        }
        return $out;
    }

    /**
     * @return array{type: 'operation'|'browse', operation_id: ?string, resolver_id: ?string, matched_keyword: ?string}
     */
    public function detectIntent(string $message): array
    {
        $msg = mb_strtolower(trim($message), 'UTF-8');
        if ($msg === '') {
            return ['type' => 'operation', 'operation_id' => null, 'resolver_id' => null, 'matched_keyword' => null];
        }
        $links = $this->graph['links'] ?? null;
        if (is_array($links)) {
            foreach ($links as $opId => $lk) {
                if (!is_string($opId) || !is_array($lk)) {
                    continue;
                }
                $keywords = $lk['keywords'] ?? null;
                if (!is_array($keywords) || $keywords === []) {
                    continue;
                }
                foreach ($keywords as $kw) {
                    $k = is_string($kw) ? trim(mb_strtolower($kw, 'UTF-8')) : '';
                    if ($k === '') {
                        continue;
                    }
                    if (mb_strpos($msg, $k, 0, 'UTF-8') !== false) {
                        return [
                            'type' => 'operation',
                            'operation_id' => $opId,
                            'resolver_id' => null,
                            'matched_keyword' => $k,
                        ];
                    }
                }
            }
        }

        foreach ($this->allResolvers() as $rid => $r) {
            if (!is_array($r) || empty($r['browse'])) {
                continue;
            }
            $keywords = $r['keywords'] ?? null;
            if (!is_array($keywords) || $keywords === []) {
                continue;
            }
            foreach ($keywords as $kw) {
                $k = is_string($kw) ? trim(mb_strtolower($kw, 'UTF-8')) : '';
                if ($k === '') {
                    continue;
                }
                if (mb_strpos($msg, $k, 0, 'UTF-8') !== false) {
                    return [
                        'type' => 'browse',
                        'operation_id' => null,
                        'resolver_id' => $rid,
                        'matched_keyword' => $k,
                    ];
                }
            }
        }

        return ['type' => 'operation', 'operation_id' => null, 'resolver_id' => null, 'matched_keyword' => null];
    }

    /**
     * @return array{operation_id: ?string, matched_keyword: ?string}
     */
    public function detectOperationId(string $message): array
    {
        $det = $this->detectIntent($message);
        if (($det['type'] ?? '') === 'operation' && !empty($det['operation_id'])) {
            return ['operation_id' => $det['operation_id'], 'matched_keyword' => $det['matched_keyword'] ?? null];
        }
        return ['operation_id' => null, 'matched_keyword' => null];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function flattenEntityMethods(): array
    {
        $entities = $this->graph['entities'] ?? null;
        if (!is_array($entities)) {
            return [];
        }
        $out = [];
        foreach ($entities as $entityId => $entity) {
            if (!is_string($entityId) || $entityId === '' || !is_array($entity)) {
                continue;
            }
            $methods = $entity['methods'] ?? null;
            if (!is_array($methods)) {
                continue;
            }
            foreach ($methods as $methodId => $method) {
                if (!is_string($methodId) || $methodId === '' || !is_array($method)) {
                    continue;
                }
                $rid = $entityId . '.' . $methodId;
                $out[$rid] = $this->normalizeMethodToResolver($rid, $entityId, $methodId, $method);
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $method
     * @return array<string, mixed>
     */
    private function normalizeMethodToResolver(string $resolverId, string $entityId, string $methodId, array $method): array
    {
        $ui = $method['ui'] ?? null;
        if (!is_array($ui)) {
            $ui = [];
        }
        if (!isset($ui['kind']) && isset($ui['action_id']) && trim((string) $ui['action_id']) !== '') {
            $ui['kind'] = 'ui_json';
        }
        $out = [
            '_resolver_id' => $resolverId,
            '_entity' => $entityId,
            '_method' => $methodId,
            'step' => isset($method['step']) && is_array($method['step']) ? $method['step'] : [],
            'provides' => isset($method['provides']) && is_array($method['provides']) ? array_values($method['provides']) : [],
            'requires' => isset($method['requires']) && is_array($method['requires']) ? array_values($method['requires']) : [],
            'requires_client' => isset($method['requires_client']) && is_array($method['requires_client'])
                ? array_values($method['requires_client'])
                : [],
            'ui' => $ui,
        ];
        if (!empty($method['browse'])) {
            $out['browse'] = true;
        }
        if (isset($method['keywords']) && is_array($method['keywords'])) {
            $out['keywords'] = array_values($method['keywords']);
        }
        return $out;
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private static function stringListFromMixed($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $v) {
            if (is_string($v) && trim($v) !== '') {
                $out[] = trim($v);
            }
        }
        return $out;
    }
}
