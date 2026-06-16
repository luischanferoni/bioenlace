<?php

namespace common\components\Platform\Core\Permission;

use common\components\Platform\Assistant\Catalog\IntentSchemaPaths;
use Symfony\Component\Yaml\Yaml;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

/**
 * Resuelve intent_id activo en requests API (header de flow, body, default de ruta).
 */
final class IntentRequestContextService
{
    /**
     * @param array<string, mixed> $requestParams
     */
    public function resolveIntentId(
        array $requestParams,
        ?string $defaultIntentId = null,
        ?string $apiRbacRoute = null
    ): string {
        $candidates = [];

        if (Yii::$app->has('request')) {
            $header = Yii::$app->request->headers->get(FlowStepAccessService::HEADER_FLOW_INTENT_ID);
            if (is_string($header) && trim($header) !== '') {
                $candidates[] = trim($header);
            }
        }

        $fromBody = trim((string) ($requestParams['intent_id'] ?? ''));
        if ($fromBody !== '') {
            $candidates[] = $fromBody;
        }

        $defaultIntentId = trim((string) $defaultIntentId);
        if ($defaultIntentId !== '') {
            $candidates[] = $defaultIntentId;
        }

        foreach ($candidates as $intentId) {
            if (IntentManifestIndex::get($intentId) !== null) {
                return $intentId;
            }
        }

        $apiRbacRoute = trim((string) $apiRbacRoute);
        if ($apiRbacRoute !== '') {
            $fromRoute = $this->intentIdForRbacRoute($apiRbacRoute);
            if ($fromRoute !== null) {
                return $fromRoute;
            }
        }

        throw new BadRequestHttpException('No se pudo resolver el intent de la operación.');
    }

    public function assertUserCanIntent(int $userId, string $intentId): void
    {
        $intentId = trim($intentId);
        if ($intentId === '') {
            throw new BadRequestHttpException('intent_id inválido.');
        }
        if ($userId <= 0) {
            throw new ForbiddenHttpException('Usuario no autenticado.');
        }
        if (!BioenlaceAccessChecker::userCanPermissionKey($userId, $intentId)) {
            throw new ForbiddenHttpException('No tenés permiso para esta operación.');
        }
    }

    public function domainOperationForIntent(string $intentId): string
    {
        $intentId = trim($intentId);
        $meta = IntentManifestIndex::get($intentId);
        if ($meta === null) {
            throw new BadRequestHttpException('Intent desconocido: ' . $intentId);
        }

        $operation = trim((string) ($meta['domain_operation'] ?? ''));
        if ($operation === '') {
            throw new BadRequestHttpException('El intent no declara domain_operation.');
        }

        return $operation;
    }

    /**
     * @param array<string, mixed> $requestParams
     * @return array<string, mixed>|null
     */
    public function subjectResolutionConfig(string $intentId, array $requestParams = []): ?array
    {
        unset($requestParams);
        $path = IntentSchemaPaths::resolveFileForIntentId(trim($intentId));
        if ($path === null || !is_file($path)) {
            return null;
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        $sr = $data['subject_resolution'] ?? null;

        return is_array($sr) ? $sr : null;
    }

    private function intentIdForRbacRoute(string $rbacRoute): ?string
    {
        $rbacRoute = '/' . ltrim(trim($rbacRoute), '/');
        $matches = [];
        foreach (IntentManifestIndex::all() as $intentId => $meta) {
            $route = trim((string) ($meta['rbac_route'] ?? ''));
            if ($route === '') {
                continue;
            }
            $route = '/' . ltrim($route, '/');
            if ($route === $rbacRoute) {
                $matches[] = $intentId;
            }
        }

        return count($matches) === 1 ? $matches[0] : null;
    }
}
