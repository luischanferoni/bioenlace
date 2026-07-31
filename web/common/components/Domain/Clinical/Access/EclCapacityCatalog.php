<?php

namespace common\components\Domain\Clinical\Access;

use common\models\Servicio;
use yii\db\Query;

/**
 * Capacidad acto → servicios institucionales por reglas ECL (metadata).
 *
 * No usa nombres de fila; tipología specialty_* / tipo. Excluye legacy_acto.
 */
final class EclCapacityCatalog
{
    private ActoEclMembershipInterface $membership;

    /** @var list<array{id: int, label: string, tipo: string, specialty_code: ?string, specialty_system: ?string, oferta_modelo: string}>|null */
    private ?array $serviciosOverride;

    /**
     * @param list<array{id: int, label: string, tipo?: string, specialty_code?: ?string, specialty_system?: ?string, oferta_modelo?: string}>|null $serviciosOverride
     */
    public function __construct(
        ?ActoEclMembershipInterface $membership = null,
        ?array $serviciosOverride = null
    ) {
        $this->membership = $membership ?? new SnowstormActoEclMembership();
        $this->serviciosOverride = $serviciosOverride;
    }

    /**
     * @return list<array{id: int, label: string, preferente: bool}>
     */
    public function lineasForActo(string $code, string $system, ?int $efectorId): array
    {
        $code = trim($code);
        $system = trim($system);
        if ($code === '') {
            return [];
        }

        $rules = PedidoAtencionMetadata::capacityRules();
        if ($rules === []) {
            return [];
        }

        $matchedEcls = [];
        foreach ($rules as $rule) {
            if ($this->membership->matches($code, $system, $rule['act_ecl'])) {
                $matchedEcls[] = $rule;
            }
        }
        if ($matchedEcls === []) {
            return [];
        }

        $byId = [];
        foreach ($this->candidateServicios() as $svc) {
            if (!$this->isInstitucional($svc)) {
                continue;
            }
            foreach ($matchedEcls as $rule) {
                if (!$this->servicioMatchesRule($svc, $rule)) {
                    continue;
                }
                $id = (int) $svc['id'];
                if (!isset($byId[$id])) {
                    $byId[$id] = [
                        'id' => $id,
                        'label' => (string) $svc['label'],
                        'preferente' => false,
                    ];
                }
            }
        }

        return array_values($byId);
    }

    /**
     * ECL no expande el value set completo aquí; actos vienen de puente explícito / caché.
     *
     * @return list<array{code: string, system: string, display: string, preferente: bool}>
     */
    public function actosForLinea(int $lineaId, ?int $efectorId): array
    {
        return [];
    }

    /**
     * @return list<array{id: int, label: string, tipo: string, specialty_code: ?string, specialty_system: ?string, oferta_modelo: string}>
     */
    private function candidateServicios(): array
    {
        if ($this->serviciosOverride !== null) {
            $out = [];
            foreach ($this->serviciosOverride as $row) {
                $out[] = [
                    'id' => (int) $row['id'],
                    'label' => (string) ($row['label'] ?? ''),
                    'tipo' => strtolower(trim((string) ($row['tipo'] ?? Servicio::TIPO_CONSULTA))),
                    'specialty_code' => isset($row['specialty_code']) && $row['specialty_code'] !== null
                        ? trim((string) $row['specialty_code'])
                        : null,
                    'specialty_system' => isset($row['specialty_system']) && $row['specialty_system'] !== null
                        ? trim((string) $row['specialty_system'])
                        : null,
                    'oferta_modelo' => trim((string) ($row['oferta_modelo'] ?? Servicio::OFERTA_MODELO_INSTITUCIONAL)),
                ];
            }

            return $out;
        }

        $schema = Servicio::getTableSchema();
        if ($schema === null) {
            return [];
        }

        $select = ['id_servicio', 'nombre', 'tipo', 'specialty_code', 'specialty_system'];
        $hasModelo = isset($schema->columns['oferta_modelo']);
        if ($hasModelo) {
            $select[] = 'oferta_modelo';
        }

        $rows = (new Query())
            ->from(Servicio::tableName())
            ->select($select)
            ->all();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row['id_servicio'],
                'label' => (string) $row['nombre'],
                'tipo' => strtolower(trim((string) ($row['tipo'] ?? Servicio::TIPO_CONSULTA))),
                'specialty_code' => trim((string) ($row['specialty_code'] ?? '')) ?: null,
                'specialty_system' => trim((string) ($row['specialty_system'] ?? '')) ?: null,
                'oferta_modelo' => $hasModelo
                    ? trim((string) ($row['oferta_modelo'] ?? Servicio::OFERTA_MODELO_INSTITUCIONAL))
                    : Servicio::OFERTA_MODELO_INSTITUCIONAL,
            ];
        }

        return $out;
    }

    /**
     * @param array{id: int, label: string, tipo: string, specialty_code: ?string, specialty_system: ?string, oferta_modelo: string} $svc
     * @param array{specialty_system: string|null, specialty_code: string|null, match_tipo: string|null, act_ecl: string} $rule
     */
    private function servicioMatchesRule(array $svc, array $rule): bool
    {
        if ($rule['specialty_code'] !== null) {
            $code = $svc['specialty_code'] ?? '';
            $system = $svc['specialty_system'] ?? '';
            if ($code === '' || $code !== $rule['specialty_code']) {
                return false;
            }
            $expectedSystem = $rule['specialty_system'] ?? CodingSystems::SNOMED;
            if ($expectedSystem !== null && $expectedSystem !== '' && $system !== $expectedSystem) {
                return false;
            }

            return true;
        }

        if ($rule['match_tipo'] !== null) {
            return ($svc['tipo'] ?? '') === $rule['match_tipo'];
        }

        return false;
    }

    /**
     * @param array{oferta_modelo: string, label: string} $svc
     */
    private function isInstitucional(array $svc): bool
    {
        $modelo = $svc['oferta_modelo'] ?? Servicio::OFERTA_MODELO_INSTITUCIONAL;
        if ($modelo === Servicio::OFERTA_MODELO_LEGACY_ACTO) {
            return false;
        }
        if (PedidoAtencionMetadata::isLegacyActoServicioNombre((string) ($svc['label'] ?? ''))) {
            return false;
        }

        return true;
    }
}
