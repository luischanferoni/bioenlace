<?php

namespace common\components\Domain\Clinical\Access;

use common\models\Servicio;
use yii\db\Query;

/**
 * Capacidad acto → servicios institucionales por reglas ECL (metadata).
 *
 * No usa nombres de fila; tipología specialty_* / tipo. Excluye tipo=soporte.
 */
final class EclCapacityCatalog
{
    private ActoEclMembershipInterface $membership;

    /** @var list<array{id: int, label: string, tipo: string, specialty_code: ?string, specialty_system: ?string}>|null */
    private ?array $serviciosOverride;

    /**
     * @param list<array{id: int, label: string, tipo?: string, specialty_code?: ?string, specialty_system?: ?string}>|null $serviciosOverride
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
            if (($svc['tipo'] ?? '') === Servicio::TIPO_SOPORTE) {
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
     * @return list<array{code: string, system: string, display: string, preferente: bool}>
     */
    public function actosForLinea(int $lineaId, ?int $efectorId): array
    {
        return [];
    }

    /**
     * @return list<array{id: int, label: string, tipo: string, specialty_code: ?string, specialty_system: ?string}>
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
                ];
            }

            return $out;
        }

        if (Servicio::getTableSchema() === null) {
            return [];
        }

        $rows = (new Query())
            ->from(Servicio::tableName())
            ->select(['id_servicio', 'nombre', 'tipo', 'specialty_code', 'specialty_system'])
            ->all();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row['id_servicio'],
                'label' => (string) $row['nombre'],
                'tipo' => strtolower(trim((string) ($row['tipo'] ?? Servicio::TIPO_CONSULTA))),
                'specialty_code' => trim((string) ($row['specialty_code'] ?? '')) ?: null,
                'specialty_system' => trim((string) ($row['specialty_system'] ?? '')) ?: null,
            ];
        }

        return $out;
    }

    /**
     * @param array{id: int, label: string, tipo: string, specialty_code: ?string, specialty_system: ?string} $svc
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
}
