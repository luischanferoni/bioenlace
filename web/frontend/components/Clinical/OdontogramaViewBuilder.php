<?php

namespace frontend\components\Clinical;

use common\components\Domain\Clinical\Capture\Domain\RowContract\OdontologiaItemRowContract;

/**
 * View model del odontograma para la historia clínica.
 *
 * Traduce los estados dentales agrupados por pieza a filas listas para pintar,
 * de modo que la vista no resuelva etiquetas, clases ni agrupaciones.
 */
final class OdontogramaViewBuilder
{
    private const TIEMPO_LABELS = [
        OdontologiaItemRowContract::TIME_QUALIFIERS[0] => 'Pasada',
        OdontologiaItemRowContract::TIME_QUALIFIERS[1] => 'Presente',
        OdontologiaItemRowContract::TIME_QUALIFIERS[2] => 'Futura',
    ];

    private const TIEMPO_CLASSES = [
        OdontologiaItemRowContract::TIME_QUALIFIERS[0] => 'text-bg-secondary',
        OdontologiaItemRowContract::TIME_QUALIFIERS[1] => 'text-bg-primary',
        OdontologiaItemRowContract::TIME_QUALIFIERS[2] => 'text-bg-info',
    ];

    private const DENTITION_TITLES = [
        OdontologiaItemRowContract::DENTITION_PERMANENT => 'Dentición permanente',
        OdontologiaItemRowContract::DENTITION_TEMPORARY => 'Dentición temporaria',
    ];

    /**
     * @param array<string, mixed>|null $odontograma salida de `buildOdontogram`
     * @return array{visible: bool, rows: list<array{title: string, teeth: list<array<string, mixed>>}>, totals: array<string, int>}
     */
    public static function build(?array $odontograma): array
    {
        $teeth = is_array($odontograma['teeth'] ?? null) ? $odontograma['teeth'] : [];
        $totals = is_array($odontograma['totals'] ?? null)
            ? $odontograma['totals']
            : ['teeth' => 0, 'states' => 0, 'unassigned' => 0];

        $grouped = [
            OdontologiaItemRowContract::DENTITION_PERMANENT => [],
            OdontologiaItemRowContract::DENTITION_TEMPORARY => [],
        ];
        $unclassified = [];

        foreach ($teeth as $tooth) {
            if (!is_array($tooth)) {
                continue;
            }
            $viewTooth = self::tooth($tooth);
            if ($viewTooth === null) {
                continue;
            }
            $dentition = (string) ($tooth['dentition'] ?? '');
            if (isset($grouped[$dentition])) {
                $grouped[$dentition][] = $viewTooth;
            } else {
                $unclassified[] = $viewTooth;
            }
        }

        $rows = [];
        foreach ($grouped as $dentition => $list) {
            if ($list !== []) {
                $rows[] = ['title' => self::DENTITION_TITLES[$dentition], 'teeth' => $list];
            }
        }
        if ($unclassified !== []) {
            $rows[] = ['title' => 'Sin dentición clasificada', 'teeth' => $unclassified];
        }

        return [
            'visible' => $rows !== [],
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    /**
     * @param array<string, mixed> $tooth
     * @return array<string, mixed>|null
     */
    private static function tooth(array $tooth): ?array
    {
        $number = trim((string) ($tooth['tooth'] ?? ''));
        if ($number === '') {
            return null;
        }
        $current = is_array($tooth['current'] ?? null) ? $tooth['current'] : [];
        $history = is_array($tooth['history'] ?? null) ? $tooth['history'] : [];

        return [
            'number' => $number,
            'target' => 'odonto-' . preg_replace('/[^0-9a-zA-Z]/', '', $number),
            'label' => self::label($current),
            'badgeClass' => self::badgeClass($current),
            'badgeLabel' => self::badgeLabel($current),
            'history' => self::history($history),
        ];
    }

    /**
     * @param list<mixed> $history
     * @return list<array{when: string, label: string, surfaces: string, badgeClass: string, badgeLabel: string}>
     */
    private static function history(array $history): array
    {
        $out = [];
        foreach ($history as $state) {
            if (!is_array($state)) {
                continue;
            }
            $when = trim((string) ($state['registradoEn'] ?? ''));
            $out[] = [
                'when' => $when !== '' ? substr($when, 0, 16) : '',
                'label' => self::label($state),
                'surfaces' => trim((string) ($state['caras'] ?? '')),
                'badgeClass' => self::badgeClass($state),
                'badgeLabel' => self::badgeLabel($state),
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function label(array $state): string
    {
        foreach (['display', 'codigo', 'tipo'] as $key) {
            $value = trim((string) ($state[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return 'sin detalle';
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function badgeLabel(array $state): string
    {
        return self::TIEMPO_LABELS[(string) ($state['tiempo'] ?? '')] ?? 'Sin momento';
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function badgeClass(array $state): string
    {
        return self::TIEMPO_CLASSES[(string) ($state['tiempo'] ?? '')] ?? 'text-bg-light';
    }
}
