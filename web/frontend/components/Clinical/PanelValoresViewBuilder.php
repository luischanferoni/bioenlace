<?php

namespace frontend\components\Clinical;

/**
 * View model de un panel de valores con unidades (controles, evaluaciones, sesiones).
 */
final class PanelValoresViewBuilder
{
    /**
     * @param list<array<string, mixed>> $paneles salida de `panelsForPerson`
     * @return array{visible: bool, paneles: list<array{fecha: string, rows: list<array{label: string, value: string, unit: string}>}>}
     */
    public static function build(array $paneles): array
    {
        $out = [];
        foreach ($paneles as $panel) {
            if (!is_array($panel)) {
                continue;
            }
            $values = is_array($panel['values'] ?? null) ? $panel['values'] : [];
            $rows = [];
            foreach ($values as $value) {
                if (!is_array($value)) {
                    continue;
                }
                $text = trim((string) ($value['value'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $rows[] = [
                    'label' => (string) ($value['label'] ?? ''),
                    'value' => $text,
                    'unit' => trim((string) ($value['unit'] ?? '')),
                ];
            }
            if ($rows === []) {
                continue;
            }
            $registradoEn = trim((string) ($panel['registradoEn'] ?? ''));
            $out[] = [
                'fecha' => $registradoEn !== '' ? substr($registradoEn, 0, 10) : '',
                'rows' => $rows,
            ];
        }

        return ['visible' => $out !== [], 'paneles' => $out];
    }
}