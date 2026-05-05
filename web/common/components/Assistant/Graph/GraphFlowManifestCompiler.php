<?php

namespace common\components\Assistant\Graph;

/**
 * Compila `flow_manifest` a partir de un grafo + operación (link).
 *
 * Deduce pasos desde `operation.requires` inferidos + resolvers (requires/provides) y orden topológico.
 * Browse: método con `browse: true` y keywords. Links pueden definir `provider_gates`.
 */
final class GraphFlowManifestCompiler
{
    /**
     * @param array<string, mixed> $draft
     * @return array{flow_manifest: array<string, mixed>, debug: array<string, mixed>}
     */
    public static function compileOperation(GraphRegistry $reg, string $linkId, array $draft = []): array
    {
        $lk = $reg->link($linkId);
        $required = $reg->inferredRequiresForLink($linkId);
        if ($required === []) {
            throw new \RuntimeException('Link sin campos requeridos inferidos: ' . $linkId);
        }
        $submitResolverId = $reg->submitResolverIdForLink($linkId);
        $providerGates = $reg->providerGatesForLink($linkId);

        $all = $reg->allResolvers();
        $byProvides = self::indexResolversByProvides($all);

        $initial = [$submitResolverId];
        foreach ($required as $k) {
            if (!self::draftHas($draft, $k)) {
                $cands = $byProvides[$k] ?? [];
                $cands = self::filterProvidersForDraftKey($cands, $all, $providerGates[$k] ?? null);
                if ($cands === []) {
                    throw new \RuntimeException(
                        'No hay método que provea ' . $k . ' cumpliendo provider_gates del link ' . $linkId
                    );
                }
                foreach ($cands as $rid) {
                    $initial[] = $rid;
                }
            }
        }
        $neededResolverIds = self::collectResolverTransitiveClosure(
            array_values(array_unique($initial)),
            $draft,
            $all,
            $byProvides,
            $providerGates
        );
        $steps = self::buildStepsFromResolvers($neededResolverIds, $all);
        $steps = self::topoSortSteps($steps);
        $steps = self::applyNextPointers($steps);
        $activeStep = self::pickActiveStep($steps, $draft);

        $manifest = [
            'version' => 1,
            'operation_id' => $linkId,
            'intent_kind' => 'operation',
            'action_name' => isset($lk['action_name']) ? (string) $lk['action_name'] : $linkId,
            'steps' => $steps,
            'active_step' => $activeStep,
            'active_subintent_id' => $activeStep['id'] ?? null,
        ];

        return [
            'flow_manifest' => $manifest,
            'debug' => [
                'draft' => $draft,
                'active_step_id' => $activeStep['id'] ?? null,
                'required' => $required,
                'submit_resolver' => $submitResolverId,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $draft
     * @return array{flow_manifest: array<string, mixed>, debug: array<string, mixed>}
     */
    public static function compileBrowse(GraphRegistry $reg, string $entryResolverId, array $draft = []): array
    {
        $entryResolverId = trim($entryResolverId);
        if ($entryResolverId === '') {
            throw new \InvalidArgumentException('entryResolverId vacío');
        }
        $all = $reg->allResolvers();
        if (!isset($all[$entryResolverId])) {
            throw new \InvalidArgumentException('Resolver browse no existe: ' . $entryResolverId);
        }
        $byProvides = self::indexResolversByProvides($all);
        $neededResolverIds = self::collectResolverTransitiveClosure(
            [$entryResolverId],
            $draft,
            $all,
            $byProvides,
            null
        );
        $steps = self::buildStepsFromResolvers($neededResolverIds, $all);
        $steps = self::topoSortSteps($steps);
        $steps = self::applyNextPointers($steps);
        $activeStep = self::pickActiveStep($steps, $draft);

        $r = $all[$entryResolverId];
        $step = isset($r['step']) && is_array($r['step']) ? $r['step'] : [];
        $title = isset($step['label']) ? (string) $step['label'] : $entryResolverId;

        $manifest = [
            'version' => 1,
            'operation_id' => 'browse:' . $entryResolverId,
            'intent_kind' => 'browse',
            'action_name' => $title,
            'entry_resolver_id' => $entryResolverId,
            'steps' => $steps,
            'active_step' => $activeStep,
            'active_subintent_id' => $activeStep['id'] ?? null,
        ];

        return [
            'flow_manifest' => $manifest,
            'debug' => [
                'draft' => $draft,
                'active_step_id' => $activeStep['id'] ?? null,
                'entry_resolver' => $entryResolverId,
            ],
        ];
    }

    /**
     * @param list<string> $initialRids
     * @param array<string, list<string>>|null $providerGates
     * @return list<string>
     */
    private static function collectResolverTransitiveClosure(
        array $initialRids,
        array $draft,
        array $all,
        array $byProvides,
        ?array $providerGates
    ): array {
        $need = [];
        $queue = [];
        foreach ($initialRids as $rid) {
            if (is_string($rid) && $rid !== '') {
                $queue[] = $rid;
            }
        }
        while ($queue !== []) {
            $rid = array_shift($queue);
            if (!is_string($rid) || $rid === '') {
                continue;
            }
            if (isset($need[$rid])) {
                continue;
            }
            if (!isset($all[$rid])) {
                throw new \RuntimeException('Resolver requerido no existe: ' . $rid);
            }
            $need[$rid] = true;
            $r = $all[$rid];
            $req = self::stringList($r['requires'] ?? null);
            foreach ($req as $k) {
                if (self::draftHas($draft, $k)) {
                    continue;
                }
                $providers = $byProvides[$k] ?? [];
                if ($providerGates !== null) {
                    $gate = $providerGates[$k] ?? null;
                    $providers = self::filterProvidersForDraftKey($providers, $all, $gate);
                }
                if ($providers === []) {
                    throw new \RuntimeException('No hay resolver que provea: ' . $k . ' (requerido por ' . $rid . ')');
                }
                foreach ($providers as $pRid) {
                    $queue[] = $pRid;
                }
            }
        }

        return array_keys($need);
    }

    /**
     * @param list<string> $candidateRids
     * @param list<string>|null $mustRequireSubset
     * @return list<string>
     */
    private static function filterProvidersForDraftKey(
        array $candidateRids,
        array $all,
        ?array $mustRequireSubset
    ): array {
        if ($mustRequireSubset === null || $mustRequireSubset === []) {
            return $candidateRids;
        }
        $out = [];
        foreach ($candidateRids as $rid) {
            if (!is_string($rid) || !isset($all[$rid])) {
                continue;
            }
            $req = self::stringList($all[$rid]['requires'] ?? null);
            $ok = true;
            foreach ($mustRequireSubset as $must) {
                if (!in_array($must, $req, true)) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $out[] = $rid;
            }
        }
        return $out;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function indexResolversByProvides(array $all): array
    {
        $out = [];
        foreach ($all as $rid => $r) {
            $prov = self::stringList($r['provides'] ?? null);
            foreach ($prov as $p) {
                $out[$p] ??= [];
                $out[$p][] = $rid;
            }
        }
        return $out;
    }

    /**
     * @param list<string> $resolverIds
     * @return list<array<string, mixed>>
     */
    private static function buildStepsFromResolvers(array $resolverIds, array $all): array
    {
        $groups = [];
        foreach ($resolverIds as $rid) {
            $r = $all[$rid];
            $step = isset($r['step']) && is_array($r['step']) ? $r['step'] : [];
            $sid = isset($step['id']) ? trim((string) $step['id']) : '';
            if ($sid === '') {
                throw new \RuntimeException('Método/resolver sin step.id: ' . $rid);
            }
            $groups[$sid] ??= [
                'id' => $sid,
                'assistant_text' => isset($step['label']) ? (string) $step['label'] : $sid,
                'requires' => [],
                'provides' => [],
                'ui' => ['default_tab' => 'default', 'tabs' => []],
            ];

            $groups[$sid]['requires'] = array_values(array_unique(array_merge(
                $groups[$sid]['requires'],
                self::stringList($r['requires'] ?? null)
            )));
            $groups[$sid]['provides'] = array_values(array_unique(array_merge(
                $groups[$sid]['provides'],
                self::stringList($r['provides'] ?? null)
            )));

            $tab = self::tabForResolver($rid, $r);
            $tabId = isset($step['tab_id']) ? trim((string) $step['tab_id']) : '';
            $tabLabel = isset($step['tab_label']) ? trim((string) $step['tab_label']) : '';
            if ($tabId !== '') {
                $tab['id'] = $tabId;
            }
            if ($tabLabel !== '') {
                $tab['label'] = $tabLabel;
            }

            $groups[$sid]['ui']['tabs'][] = $tab;
        }

        foreach ($groups as &$s) {
            $tabs = isset($s['ui']['tabs']) && is_array($s['ui']['tabs']) ? $s['ui']['tabs'] : [];
            $hasPorServicio = false;
            foreach ($tabs as $t) {
                if (is_array($t) && ($t['id'] ?? null) === 'por_servicio') {
                    $hasPorServicio = true;
                    break;
                }
            }
            $s['ui']['default_tab'] = $hasPorServicio ? 'por_servicio' : 'default';
        }
        unset($s);

        ksort($groups);
        return array_values($groups);
    }

    /**
     * @param list<array<string, mixed>> $steps
     * @return list<array<string, mixed>>
     */
    private static function topoSortSteps(array $steps): array
    {
        $byId = [];
        foreach ($steps as $s) {
            if (!is_array($s) || empty($s['id'])) {
                continue;
            }
            $byId[(string) $s['id']] = $s;
        }
        $deps = [];
        $in = [];
        foreach ($byId as $id => $s) {
            $deps[$id] = [];
            $in[$id] = 0;
        }
        foreach ($byId as $bId => $b) {
            $bReq = isset($b['requires']) && is_array($b['requires']) ? $b['requires'] : [];
            foreach ($byId as $aId => $a) {
                if ($aId === $bId) {
                    continue;
                }
                $aProv = isset($a['provides']) && is_array($a['provides']) ? $a['provides'] : [];
                if ($aProv === []) {
                    continue;
                }
                if (self::intersects($bReq, $aProv)) {
                    $deps[$aId][] = $bId;
                }
            }
        }
        foreach ($deps as $aId => $tos) {
            foreach ($tos as $bId) {
                $in[$bId] = ($in[$bId] ?? 0) + 1;
            }
        }

        $q = [];
        foreach ($in as $id => $n) {
            if ($n === 0) {
                $q[] = $id;
            }
        }
        sort($q);
        $out = [];
        while ($q !== []) {
            $id = array_shift($q);
            $out[] = $byId[$id];
            foreach (($deps[$id] ?? []) as $to) {
                $in[$to]--;
                if ($in[$to] === 0) {
                    $q[] = $to;
                    sort($q);
                }
            }
        }
        if (count($out) !== count($steps)) {
            return $steps;
        }
        return $out;
    }

    /**
     * @param list<array<string, mixed>> $steps
     * @return list<array<string, mixed>>
     */
    private static function applyNextPointers(array $steps): array
    {
        $out = $steps;
        for ($i = 0; $i < count($out); $i++) {
            $out[$i]['next'] = ($i + 1 < count($out)) ? (string) ($out[$i + 1]['id'] ?? '') : '';
        }
        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function pickActiveStep(array $steps, array $draft): array
    {
        foreach ($steps as $s) {
            if (!is_array($s)) {
                continue;
            }
            $missing = self::missingDraftFields($s, $draft);
            if ($missing !== []) {
                return $s;
            }
        }
        if ($steps === [] || !is_array($steps[0])) {
            throw new \RuntimeException('No hay steps para seleccionar active_step');
        }
        return $steps[0];
    }

    private static function draftHas(array $draft, string $draftKey): bool
    {
        $k = trim($draftKey);
        if ($k === '' || strncmp($k, 'draft.', 6) !== 0) {
            return true;
        }
        $field = substr($k, 6);
        if ($field === '') {
            return true;
        }
        return isset($draft[$field]) && $draft[$field] !== null && (string) $draft[$field] !== '';
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     */
    private static function intersects(array $a, array $b): bool
    {
        if ($a === [] || $b === []) {
            return false;
        }
        $set = [];
        foreach ($a as $x) {
            $set[(string) $x] = true;
        }
        foreach ($b as $y) {
            if (isset($set[(string) $y])) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private static function tabForResolver(string $resolverId, array $resolver): array
    {
        $ui = $resolver['ui'] ?? null;
        $actionId = is_array($ui) && isset($ui['action_id']) ? strtolower(trim((string) $ui['action_id'])) : '';
        $route = $actionId !== '' ? self::routeForActionId($actionId) : '';
        $params = is_array($ui) && isset($ui['params']) && is_array($ui['params']) ? $ui['params'] : [];
        $reqClient = isset($resolver['requires_client']) && is_array($resolver['requires_client'])
            ? array_values($resolver['requires_client'])
            : [];
        return [
            'id' => 'default',
            'label' => 'Elegir',
            'action_id' => $actionId,
            'route' => $route,
            'params' => $params === [] ? new \stdClass() : $params,
            'requires_client' => $reqClient,
        ];
    }

    private static function routeForActionId(string $actionId): string
    {
        $actionId = strtolower(trim($actionId));
        $p = strpos($actionId, '.');
        if ($p === false) {
            return '';
        }
        $entity = substr($actionId, 0, $p);
        $action = substr($actionId, $p + 1);
        if ($entity === '' || $action === '') {
            return '';
        }
        return '/api/v1/' . rawurlencode($entity) . '/' . rawurlencode($action);
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private static function stringList($raw): array
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

    /**
     * @return list<string>
     */
    private static function missingDraftFields(array $step, array $draft): array
    {
        $provides = isset($step['provides']) && is_array($step['provides']) ? $step['provides'] : [];
        $requires = isset($step['requires']) && is_array($step['requires']) ? $step['requires'] : [];
        $needs = array_merge($requires, $provides);
        $missing = [];
        foreach ($needs as $k) {
            $k = is_string($k) ? trim($k) : '';
            if ($k === '' || strncmp($k, 'draft.', 6) !== 0) {
                continue;
            }
            $field = substr($k, 6);
            if ($field === '') {
                continue;
            }
            if (!isset($draft[$field]) || $draft[$field] === null || (string) $draft[$field] === '') {
                $missing[] = $k;
            }
        }
        return array_values(array_unique($missing));
    }
}
