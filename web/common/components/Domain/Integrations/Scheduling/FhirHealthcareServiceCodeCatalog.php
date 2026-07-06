<?php

namespace common\components\Domain\Integrations\Scheduling;

use common\models\Integration\IntegrationFhirServiceCode;
use Yii;

/**
 * Resuelve HealthcareService FHIR → id_servicio (fail-closed si ambiguo).
 */
final class FhirHealthcareServiceCodeCatalog
{
    public const DEFAULT_SOURCE_SYSTEM = 'fhir-default';

    /**
     * @return int|null id_servicio único; null si 0 o >1 coincidencias
     */
    public function resolveIdServicio(
        string $codeSystem,
        string $codeValue,
        int $idEfector = 0,
        string $sourceSystem = self::DEFAULT_SOURCE_SYSTEM
    ): ?int {
        $codeSystem = trim($codeSystem);
        $codeValue = trim($codeValue);
        $sourceSystem = trim($sourceSystem) !== '' ? trim($sourceSystem) : self::DEFAULT_SOURCE_SYSTEM;

        if ($codeSystem === '' || $codeValue === '') {
            return null;
        }

        $scopes = $idEfector > 0
            ? [IntegrationFhirServiceCode::SCOPE_GLOBAL, $idEfector]
            : [IntegrationFhirServiceCode::SCOPE_GLOBAL];

        $rows = IntegrationFhirServiceCode::findActive()
            ->select(['id_servicio', 'id_efector_scope'])
            ->where([
                'source_system' => $sourceSystem,
                'code_system' => $codeSystem,
                'code_value' => $codeValue,
            ])
            ->andWhere(['id_efector_scope' => $scopes])
            ->orderBy(['id_efector_scope' => SORT_DESC])
            ->asArray()
            ->all();

        if ($rows === []) {
            return null;
        }

        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id_servicio'] ?? 0);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }

        return count($ids) === 1 ? (int) array_key_first($ids) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForEfector(int $idEfector, ?string $sourceSystem = null): array
    {
        $query = IntegrationFhirServiceCode::findActive()
            ->with(['servicio'])
            ->orderBy(['code_system' => SORT_ASC, 'code_value' => SORT_ASC]);

        if ($sourceSystem !== null && trim($sourceSystem) !== '') {
            $query->andWhere(['source_system' => trim($sourceSystem)]);
        }

        if ($idEfector > 0) {
            $query->andWhere([
                'or',
                ['id_efector_scope' => IntegrationFhirServiceCode::SCOPE_GLOBAL],
                ['id_efector_scope' => $idEfector],
            ]);
        }

        $out = [];
        foreach ($query->all() as $row) {
            /** @var IntegrationFhirServiceCode $row */
            $out[] = [
                'id' => (int) $row->id,
                'source_system' => (string) $row->source_system,
                'code_system' => (string) $row->code_system,
                'code_value' => (string) $row->code_value,
                'id_servicio' => (int) $row->id_servicio,
                'servicio_nombre' => $row->servicio !== null ? (string) $row->servicio->nombre : '',
                'id_efector_scope' => (int) $row->id_efector_scope,
                'label' => $row->label,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{id: int}
     * @throws \InvalidArgumentException
     */
    public function upsert(array $payload, int $idEfectorScope = 0): array
    {
        $sourceSystem = trim((string) ($payload['source_system'] ?? self::DEFAULT_SOURCE_SYSTEM));
        $codeSystem = trim((string) ($payload['code_system'] ?? ''));
        $codeValue = trim((string) ($payload['code_value'] ?? ''));
        $idServicio = (int) ($payload['id_servicio'] ?? 0);
        $scope = (int) ($payload['id_efector_scope'] ?? $idEfectorScope);
        $label = isset($payload['label']) ? trim((string) $payload['label']) : null;

        if ($codeSystem === '' || $codeValue === '' || $idServicio <= 0) {
            throw new \InvalidArgumentException('code_system, code_value e id_servicio son obligatorios.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $id = (int) ($payload['id'] ?? 0);

        if ($id > 0) {
            $row = IntegrationFhirServiceCode::findOne(['id' => $id, 'deleted_at' => null]);
            if ($row === null) {
                throw new \InvalidArgumentException('Código de catálogo inexistente.');
            }
        } else {
            $row = IntegrationFhirServiceCode::findActive()
                ->where([
                    'source_system' => $sourceSystem,
                    'code_system' => $codeSystem,
                    'code_value' => $codeValue,
                    'id_efector_scope' => $scope,
                ])
                ->one();
            if ($row === null) {
                $row = new IntegrationFhirServiceCode();
                $row->created_at = $now;
            }
        }

        $row->source_system = $sourceSystem;
        $row->code_system = $codeSystem;
        $row->code_value = $codeValue;
        $row->id_servicio = $idServicio;
        $row->id_efector_scope = $scope;
        $row->label = $label !== '' ? $label : null;
        $row->updated_at = $now;

        if (!$row->save()) {
            throw new \RuntimeException('No se pudo guardar el código FHIR: ' . json_encode($row->getErrors()));
        }

        return ['id' => (int) $row->id];
    }
}
