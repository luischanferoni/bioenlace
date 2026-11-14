<?php

namespace common\components\Domain\Clinical\Specialty\Application\Service;

use common\components\Domain\Clinical\Capture\Domain\RowContract\ClinicalPanelContract;
use common\components\Shared\Infrastructure\Persistence\ObservationPanelStore;
use common\models\Clinical\Encounter;

/**
 * Ciclo de vida de un panel clínico: escribir el control y leer los paneles del paciente.
 *
 * Genérico sobre {@see ClinicalPanelContract}: la especialidad aporta el contrato, no la
 * mecánica de persistencia ni el agrupado.
 *
 * @template T of ClinicalPanelContract
 */
final class ClinicalPanelService
{
    private ObservationPanelStore $store;

    public function __construct(?ObservationPanelStore $store = null)
    {
        $this->store = $store ?? new ObservationPanelStore();
    }

    /**
     * @param class-string<ClinicalPanelContract> $contract
     * @param mixed $payload
     * @return int cantidad de observaciones escritas
     */
    public function persist(string $contract, Encounter $encounter, $payload): int
    {
        if (!is_array($payload)) {
            return 0;
        }
        $values = $contract::normalize($payload);
        if ($values === []) {
            return 0;
        }

        $rows = [];
        foreach ($values as $field => $value) {
            $storage = $contract::storageFor($field);
            if ($storage === null) {
                continue;
            }
            $rows[] = [
                'field' => $field,
                'code' => $storage['code'],
                'system' => $storage['system'],
                'type' => $storage['type'],
                'unit' => $storage['unit'],
                'value' => $value,
            ];
        }

        $result = $this->store->write(
            $contract::panelKind(),
            $encounter,
            $rows,
            $contract::observationCategory()
        );

        return $result['created'];
    }

    /**
     * Paneles del paciente, del más reciente al más antiguo.
     *
     * @param class-string<ClinicalPanelContract> $contract
     * @return list<array{panelId: int, registradoEn: string, encounterId: int|null, values: array<string, array{label: string, value: string, unit: string|null}>}>
     */
    public function panelsForPerson(string $contract, int $personaId): array
    {
        $rows = $this->store->rowsForPerson(
            $personaId,
            $contract::panelKind(),
            $contract::observationCategory()
        );

        $panels = [];
        foreach ($rows as $row) {
            $panelId = (int) ($row['panel_id'] ?? 0);
            $field = (string) ($row['field_key'] ?? '');
            if ($panelId <= 0 || $field === '') {
                continue;
            }
            if (!isset($panels[$panelId])) {
                $panels[$panelId] = [
                    'panelId' => $panelId,
                    'registradoEn' => (string) ($row['effective_datetime'] ?? ''),
                    'encounterId' => isset($row['encounter_id']) ? (int) $row['encounter_id'] : null,
                    'values' => [],
                ];
            }
            $panels[$panelId]['values'][$field] = [
                'label' => $field,
                'value' => $this->store->valueFromRow($row),
                'unit' => isset($row['value_unit']) && $row['value_unit'] !== ''
                    ? (string) $row['value_unit']
                    : null,
            ];
        }

        $out = array_values($panels);
        usort($out, static fn (array $a, array $b): int => $b['panelId'] <=> $a['panelId']);

        $order = $contract::promptFields();
        foreach ($out as &$panel) {
            $panel['values'] = self::orderValues($panel['values'], $order);
        }
        unset($panel);

        return $out;
    }

    /**
     * Valores numéricos de un campo, en orden cronológico.
     *
     * @param class-string<ClinicalPanelContract> $contract
     * @return list<array{panelId: int, registradoEn: string, value: float}>
     */
    public function seriesForPerson(string $contract, int $personaId, string $field): array
    {
        $out = [];
        foreach ($this->store->rowsForPerson(
            $personaId,
            $contract::panelKind(),
            $contract::observationCategory()
        ) as $row) {
            if ((string) ($row['field_key'] ?? '') !== $field) {
                continue;
            }
            $value = $row['value_quantity'];
            if ($value === null || !is_numeric($value)) {
                continue;
            }
            $out[] = [
                'panelId' => (int) ($row['panel_id'] ?? 0),
                'registradoEn' => (string) ($row['effective_datetime'] ?? ''),
                'value' => (float) $value,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, array{label: string, value: string, unit: string|null}> $values
     * @param list<string> $order
     * @return array<string, array{label: string, value: string, unit: string|null}>
     */
    private static function orderValues(array $values, array $order): array
    {
        $out = [];
        foreach ($order as $field) {
            if (isset($values[$field])) {
                $out[$field] = $values[$field];
            }
        }
        foreach ($values as $field => $value) {
            if (!isset($out[$field])) {
                $out[$field] = $value;
            }
        }

        return $out;
    }
}
