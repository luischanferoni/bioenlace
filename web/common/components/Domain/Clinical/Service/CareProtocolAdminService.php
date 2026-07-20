<?php

namespace common\components\Domain\Clinical\Service;

use common\models\Clinical\CareProtocol;
use common\models\Provincia;
use Yii;

/**
 * ABM de protocolos de cuidado (solo superadmin).
 */
final class CareProtocolAdminService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listar(bool $incluirDeshabilitados = true, ?int $idProvincia = null): array
    {
        $this->assertSuperadmin();
        $q = CareProtocol::find()->orderBy(['orden' => SORT_ASC, 'id' => SORT_ASC]);
        if (!$incluirDeshabilitados) {
            $q->andWhere(['enabled' => true]);
        }
        if ($idProvincia !== null && $idProvincia > 0) {
            $q->andWhere([
                'or',
                ['scope_type' => CareProtocol::SCOPE_NATION],
                [
                    'and',
                    ['scope_type' => CareProtocol::SCOPE_PROVINCE],
                    ['id_provincia' => $idProvincia],
                ],
            ]);
        }
        $out = [];
        /** @var CareProtocol $row */
        foreach ($q->all() as $row) {
            $out[] = $this->toAdminArray($row);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function obtener(int $id): array
    {
        $this->assertSuperadmin();
        $model = $this->findModel($id);

        return $this->toAdminArray($model);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function crear(array $payload): array
    {
        $this->assertSuperadmin();
        $model = new CareProtocol();
        $model->created_at = date('Y-m-d H:i:s');
        $model->created_by = $this->currentUserId();
        $this->applyPayload($model, $payload, true);
        if (!$model->save()) {
            throw new \InvalidArgumentException($this->firstError($model));
        }
        CareProtocolCatalogService::clearCache();

        return $this->toAdminArray($model);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function actualizar(int $id, array $payload): array
    {
        $this->assertSuperadmin();
        $model = $this->findModel($id);
        $this->applyPayload($model, $payload, false);
        $model->updated_at = date('Y-m-d H:i:s');
        $model->updated_by = $this->currentUserId();
        if (!$model->save()) {
            throw new \InvalidArgumentException($this->firstError($model));
        }
        CareProtocolCatalogService::clearCache();

        return $this->toAdminArray($model);
    }

    public function desactivar(int $id): void
    {
        $this->setEnabled($id, false);
    }

    public function activar(int $id): void
    {
        $this->setEnabled($id, true);
    }

    private function setEnabled(int $id, bool $enabled): void
    {
        $this->assertSuperadmin();
        $model = $this->findModel($id);
        $model->enabled = $enabled;
        $model->updated_at = date('Y-m-d H:i:s');
        $model->updated_by = $this->currentUserId();
        if (!$model->save(false, ['enabled', 'updated_at', 'updated_by'])) {
            throw new \InvalidArgumentException($this->firstError($model));
        }
        CareProtocolCatalogService::clearCache();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyPayload(CareProtocol $model, array $payload, bool $isCreate): void
    {
        if ($isCreate || array_key_exists('protocol_key', $payload)) {
            $key = trim((string) ($payload['protocol_key'] ?? $model->protocol_key ?? ''));
            if ($key === '') {
                throw new \InvalidArgumentException('protocol_key es obligatorio.');
            }
            $model->protocol_key = $key;
        }
        if ($isCreate || array_key_exists('title', $payload)) {
            $title = trim((string) ($payload['title'] ?? ''));
            if ($title === '') {
                throw new \InvalidArgumentException('title es obligatorio.');
            }
            $model->title = $title;
        }
        if (array_key_exists('hub_label', $payload) || $isCreate) {
            $hub = trim((string) ($payload['hub_label'] ?? ''));
            $model->hub_label = $hub !== '' ? $hub : null;
        }
        if (array_key_exists('orden', $payload) || $isCreate) {
            $model->orden = (int) ($payload['orden'] ?? 100);
        }
        if (array_key_exists('enabled', $payload) || $isCreate) {
            $model->enabled = array_key_exists('enabled', $payload)
                ? (bool) $payload['enabled']
                : true;
        }

        $scope = strtoupper(trim((string) ($payload['scope_type'] ?? $model->scope_type ?? CareProtocol::SCOPE_NATION)));
        if (!in_array($scope, [CareProtocol::SCOPE_NATION, CareProtocol::SCOPE_PROVINCE], true)) {
            throw new \InvalidArgumentException('scope_type inválido (NATION|PROVINCE).');
        }
        $model->scope_type = $scope;

        $idProvincia = null;
        if (array_key_exists('id_provincia', $payload) || $isCreate || $scope === CareProtocol::SCOPE_PROVINCE) {
            $rawProv = $payload['id_provincia'] ?? $model->id_provincia;
            $idProvincia = $rawProv !== null && $rawProv !== '' ? (int) $rawProv : null;
        } else {
            $idProvincia = $model->id_provincia !== null ? (int) $model->id_provincia : null;
        }
        if ($scope === CareProtocol::SCOPE_NATION) {
            $model->id_provincia = null;
        } else {
            if ($idProvincia === null || $idProvincia <= 0) {
                throw new \InvalidArgumentException('id_provincia es obligatorio cuando scope_type=PROVINCE.');
            }
            if (Provincia::findOne($idProvincia) === null) {
                throw new \InvalidArgumentException('id_provincia no existe.');
            }
            $model->id_provincia = $idProvincia;
        }

        if (array_key_exists('age_min', $payload) || $isCreate) {
            $model->age_min = $this->nullableInt($payload['age_min'] ?? null);
        }
        if (array_key_exists('age_max', $payload) || $isCreate) {
            $model->age_max = $this->nullableInt($payload['age_max'] ?? null);
        }
        if (($model->age_min !== null && $model->age_max !== null) && $model->age_min > $model->age_max) {
            throw new \InvalidArgumentException('age_min no puede ser mayor que age_max.');
        }

        if (array_key_exists('sex', $payload) || array_key_exists('sex_json', $payload) || $isCreate) {
            $sex = $payload['sex'] ?? null;
            if ($sex === null && isset($payload['sex_json'])) {
                $decoded = is_string($payload['sex_json'])
                    ? json_decode((string) $payload['sex_json'], true)
                    : $payload['sex_json'];
                $sex = is_array($decoded) ? $decoded : [];
            }
            $model->sex_json = $this->encodeStringList(is_array($sex) ? $sex : [], true);
        }

        if (array_key_exists('condition_codes', $payload)
            || array_key_exists('condition_codes_json', $payload)
            || $isCreate
        ) {
            $codes = $payload['condition_codes'] ?? null;
            if ($codes === null && isset($payload['condition_codes_json'])) {
                $decoded = is_string($payload['condition_codes_json'])
                    ? json_decode((string) $payload['condition_codes_json'], true)
                    : $payload['condition_codes_json'];
                $codes = is_array($decoded) ? $decoded : [];
            }
            $model->condition_codes_json = $this->encodeStringList(is_array($codes) ? $codes : [], true);
        }

        $match = strtolower(trim((string) ($payload['condition_match'] ?? $model->condition_match ?? CareProtocol::MATCH_NONE)));
        $allowedMatch = [
            CareProtocol::MATCH_NONE,
            CareProtocol::MATCH_ACTIVE,
            CareProtocol::MATCH_CHRONIC,
            CareProtocol::MATCH_ACTIVE_OR_CHRONIC,
        ];
        if (!in_array($match, $allowedMatch, true)) {
            throw new \InvalidArgumentException('condition_match inválido.');
        }
        $model->condition_match = $match;

        if (array_key_exists('actions', $payload) || array_key_exists('actions_json', $payload) || $isCreate) {
            $actions = $payload['actions'] ?? null;
            if ($actions === null && isset($payload['actions_json'])) {
                $decoded = is_string($payload['actions_json'])
                    ? json_decode((string) $payload['actions_json'], true)
                    : $payload['actions_json'];
                $actions = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($actions) || $actions === []) {
                throw new \InvalidArgumentException('actions es obligatorio y debe ser una lista no vacía.');
            }
            $normalized = $this->normalizeActions($actions);
            if ($normalized === []) {
                throw new \InvalidArgumentException('actions no contiene ítems válidos.');
            }
            $model->actions_json = json_encode($normalized, JSON_UNESCAPED_UNICODE);
        }
        if ($model->actions_json === null || trim((string) $model->actions_json) === '') {
            throw new \InvalidArgumentException('actions es obligatorio.');
        }
    }

    /**
     * @param list<mixed> $actions
     * @return list<array<string, mixed>>
     */
    private function normalizeActions(array $actions): array
    {
        $out = [];
        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }
            $code = trim((string) ($action['code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $draft = [];
            foreach ($action['draft'] ?? [] as $k => $v) {
                $draft[trim((string) $k)] = trim((string) $v);
            }
            $out[] = [
                'code' => $code,
                'label' => trim((string) ($action['label'] ?? $code)),
                'description' => trim((string) ($action['description'] ?? '')),
                'outcome' => trim((string) ($action['outcome'] ?? 'captura_mensaje')) ?: 'captura_mensaje',
                'draft' => $draft,
            ];
        }

        return $out;
    }

    /**
     * @param list<mixed> $values
     */
    private function encodeStringList(array $values, bool $upper): ?string
    {
        $out = [];
        foreach ($values as $v) {
            $s = trim((string) $v);
            if ($s === '') {
                continue;
            }
            $out[] = $upper ? strtoupper($s) : $s;
        }
        if ($out === []) {
            return null;
        }

        return json_encode(array_values(array_unique($out)), JSON_UNESCAPED_UNICODE);
    }

    private function nullableInt(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }

        return (int) $v;
    }

    private function findModel(int $id): CareProtocol
    {
        $model = CareProtocol::findOne($id);
        if ($model === null) {
            throw new \InvalidArgumentException('Protocolo no encontrado.');
        }

        return $model;
    }

    /**
     * @return array<string, mixed>
     */
    private function toAdminArray(CareProtocol $model): array
    {
        $catalog = $model->toCatalogArray();

        return [
            'id' => (int) $model->id,
            'protocol_key' => (string) $model->protocol_key,
            'title' => (string) $model->title,
            'hub_label' => $model->hub_label !== null ? (string) $model->hub_label : null,
            'enabled' => (bool) $model->enabled,
            'orden' => (int) $model->orden,
            'scope_type' => (string) $model->scope_type,
            'id_provincia' => $model->id_provincia !== null ? (int) $model->id_provincia : null,
            'age_min' => $model->age_min !== null ? (int) $model->age_min : null,
            'age_max' => $model->age_max !== null ? (int) $model->age_max : null,
            'sex' => $model->sexList(),
            'condition_codes' => $model->conditionCodesList(),
            'condition_match' => (string) $model->condition_match,
            'actions' => $model->actionsList(),
            'catalog' => $catalog,
            'created_at' => $model->created_at,
            'updated_at' => $model->updated_at,
        ];
    }

    private function assertSuperadmin(): void
    {
        if (!(bool) (Yii::$app->user->isSuperadmin ?? false)) {
            throw new \RuntimeException('Solo superadmin puede administrar protocolos de cuidado.');
        }
    }

    private function currentUserId(): ?int
    {
        $id = Yii::$app->user->id ?? null;

        return $id !== null ? (int) $id : null;
    }

    private function firstError(CareProtocol $model): string
    {
        $errors = $model->getFirstErrors();
        $msg = reset($errors);

        return is_string($msg) && $msg !== '' ? $msg : 'No se pudo guardar el protocolo.';
    }
}
