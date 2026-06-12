<?php

namespace common\components\Core\DataAccess\Edit;

use common\components\Core\DataAccess\AttributeGroupCatalog;
use common\components\Core\DataAccess\EditSurfaceAuthorizationService;
use common\components\Core\DataAccess\PermissionContext;
use common\components\Organization\Service\ProfesionalEfectorServicio\ProfesionalEfectorServicioAgendaUiService;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

/**
 * Orquesta mutaciones autorizadas de edición dispersa.
 */
final class MutationExecutor
{
    private AttributeGroupCatalog $catalog;
    private EditSurfaceAuthorizationService $surfaceAuth;
    private EditMutationAuthorizationService $mutationAuth;
    private EditMutationRegistry $registry;
    private OpenUiEditMutationDelegate $openUiDelegate;

    public function __construct(
        ?AttributeGroupCatalog $catalog = null,
        ?EditSurfaceAuthorizationService $surfaceAuth = null,
        ?EditMutationAuthorizationService $mutationAuth = null,
        ?EditMutationRegistry $registry = null,
        ?OpenUiEditMutationDelegate $openUiDelegate = null
    ) {
        $this->catalog = $catalog ?? new AttributeGroupCatalog();
        $this->surfaceAuth = $surfaceAuth ?? new EditSurfaceAuthorizationService($this->catalog);
        $this->mutationAuth = $mutationAuth ?? new EditMutationAuthorizationService($this->catalog);
        $this->registry = $registry ?? new EditMutationRegistry();
        $this->openUiDelegate = $openUiDelegate ?? new OpenUiEditMutationDelegate();
    }

    /**
     * @param list<string> $aspectIds
     * @param array<string, array<string, string>> $baseline
     * @param array<string, string> $proposed
     * @param array<string, int|string> $subjectContext
     * @param array<string, mixed> $params
     */
    public function apply(
        string $surfaceId,
        array $aspectIds,
        array $baseline,
        array $proposed,
        array $subjectContext,
        array $params,
        PermissionContext $ctx
    ): EditMutationResult {
        $surface = $this->catalog->getEditSurface($surfaceId);
        if ($surface === null) {
            throw new \InvalidArgumentException('Superficie de edición desconocida.');
        }

        if (!$this->surfaceAuth->userCanAccessEditSurface($ctx, $surfaceId, $params)) {
            EditMutationAuditLogger::logDenied($surfaceId, $aspectIds, $subjectContext, $ctx, 'surface_forbidden');
            throw new ForbiddenHttpException('No tenés permiso para editar esa categoría de datos.');
        }

        $aspects = $surface['aspects'] ?? [];
        if (!is_array($aspects)) {
            $aspects = [];
        }

        $scalarJobs = [];
        $openUiActions = [];

        foreach ($aspectIds as $aspectId) {
            if (!$this->surfaceAuth->userCanAccessAspect($ctx, $surfaceId, $aspectId, $params)) {
                EditMutationAuditLogger::logDenied($surfaceId, $aspectIds, $subjectContext, $ctx, 'aspect_forbidden:' . $aspectId);
                throw new ForbiddenHttpException('No tenés permiso para modificar el aspecto ' . $aspectId . '.');
            }

            $def = $aspects[$aspectId] ?? null;
            if (!is_array($def)) {
                continue;
            }

            $kind = trim((string) ($def['kind'] ?? 'scalar_group'));
            if ($kind === 'open_ui') {
                $persisted = $this->applyOpenUiAspect(
                    $surfaceId,
                    $aspectId,
                    $def,
                    $subjectContext,
                    $params,
                    $ctx
                );
                if ($persisted !== []) {
                    $appliedChanges = array_merge($appliedChanges, $persisted);
                } else {
                    $openUiActions[] = $this->openUiDelegate->buildAction($aspectId, $def, $subjectContext);
                }
                continue;
            }

            if ($kind !== 'scalar_group') {
                continue;
            }

            $aspectBaseline = $baseline[$aspectId] ?? [];
            if (!is_array($aspectBaseline)) {
                continue;
            }

            $changes = [];
            foreach ($aspectBaseline as $field => $beforeValue) {
                if (!is_string($field) || !array_key_exists($field, $proposed)) {
                    continue;
                }
                $after = trim((string) $proposed[$field]);
                $before = trim((string) $beforeValue);
                if ($after !== $before) {
                    $changes[$field] = $after;
                }
            }

            if ($changes === []) {
                continue;
            }

            $group = trim((string) ($def['attribute_group'] ?? ''));
            $this->mutationAuth->assertCanApplyScalarChanges(
                $ctx,
                $surfaceId,
                $aspectId,
                $def,
                $params,
                $changes
            );

            $handler = $this->registry->getHandler($group);
            if ($handler === null) {
                throw new \RuntimeException('Sin handler de mutación para ' . $group);
            }

            $scalarJobs[] = [
                'aspect_id' => $aspectId,
                'attribute_group' => $group,
                'changes' => $changes,
                'handler' => $handler,
            ];
        }

        if ($scalarJobs === [] && $openUiActions === []) {
            throw new \InvalidArgumentException('No hay cambios para aplicar.');
        }

        $appliedChanges = [];
        if ($scalarJobs !== []) {
            $tx = Yii::$app->db->beginTransaction();
            try {
                foreach ($scalarJobs as $job) {
                    $appliedChanges = array_merge(
                        $appliedChanges,
                        $job['handler']->apply(
                            $job['attribute_group'],
                            $job['changes'],
                            $subjectContext,
                            $ctx
                        )
                    );
                }
                $tx->commit();
            } catch (\Throwable $e) {
                if ($tx->isActive) {
                    $tx->rollBack();
                }
                throw $e;
            }
        }

        if ($appliedChanges !== []) {
            EditMutationAuditLogger::logApplied($surfaceId, $aspectIds, $subjectContext, $appliedChanges, $ctx);
        }

        return new EditMutationResult($appliedChanges, $openUiActions, $subjectContext);
    }

    /**
     * Persiste aspectos open_ui conocidos (p. ej. agenda) bajo permiso write del attribute_group.
     *
     * @param array<string, mixed> $def
     * @param array<string, int|string> $subjectContext
     * @param array<string, mixed> $params
     * @return list<array{field: string, label: string, before: string, after: string}>
     */
    private function applyOpenUiAspect(
        string $surfaceId,
        string $aspectId,
        array $def,
        array $subjectContext,
        array $params,
        PermissionContext $ctx
    ): array {
        $uiAction = trim((string) ($def['ui_action'] ?? ''));
        if ($uiAction !== 'profesional-agenda.configurar-agenda') {
            return [];
        }

        $this->mutationAuth->assertCanApplyOpenUiAspect($ctx, $surfaceId, $aspectId, $def, $params);

        $idEfector = (int) ($subjectContext['id_efector'] ?? $params['id_efector'] ?? 0);
        if ($idEfector <= 0) {
            $idEfector = (int) Yii::$app->user->getIdEfector();
        }
        if ($idEfector <= 0) {
            throw new BadRequestHttpException('Se requiere efector en sesión.');
        }

        $post = [];
        foreach ($params as $key => $value) {
            if (is_scalar($value)) {
                $post[(string) $key] = $value;
            }
        }

        $result = ProfesionalEfectorServicioAgendaUiService::submitAgendaConfig($idEfector, $post);
        $label = trim((string) ($def['label'] ?? $aspectId)) ?: $aspectId;

        return [[
            'field' => $aspectId,
            'label' => $label,
            'before' => '',
            'after' => (string) ($result['message'] ?? 'Guardado'),
        ]];
    }
}
