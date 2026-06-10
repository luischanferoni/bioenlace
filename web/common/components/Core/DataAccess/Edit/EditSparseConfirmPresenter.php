<?php

namespace common\components\Core\DataAccess\Edit;

/**
 * Preview de cambios (dry-run) antes de persistir.
 */
final class EditSparseConfirmPresenter
{
    private const FIELD_LABELS = [
        'nombre' => 'Nombre',
        'apellido' => 'Apellido',
        'otro_nombre' => 'Otro nombre',
        'otro_apellido' => 'Otro apellido',
    ];

    /**
     * @param array<string, array<string, string>> $baseline
     * @param array<string, string> $proposed valores planos enviados en el formulario
     * @param list<string> $aspectIds
     * @return array{
     *   lines: list<string>,
     *   changes: list<array{field: string, label: string, before: string, after: string}>,
     *   has_changes: bool
     * }
     */
    public function buildDiff(
        array $baseline,
        array $proposed,
        array $aspectIds
    ): array {
        $changes = [];
        $lines = [];

        foreach ($aspectIds as $aspectId) {
            $beforeAspect = $baseline[$aspectId] ?? [];
            if (!is_array($beforeAspect)) {
                continue;
            }
            foreach ($beforeAspect as $field => $beforeValue) {
                if (!is_string($field)) {
                    continue;
                }
                if (!array_key_exists($field, $proposed)) {
                    continue;
                }
                $afterValue = trim((string) $proposed[$field]);
                $beforeNorm = trim((string) $beforeValue);
                if ($afterValue === $beforeNorm) {
                    continue;
                }
                $label = self::FIELD_LABELS[$field] ?? ucfirst(str_replace('_', ' ', $field));
                $changes[] = [
                    'field' => $field,
                    'label' => $label,
                    'before' => $beforeNorm,
                    'after' => $afterValue,
                ];
                $lines[] = $label . ': «' . $beforeNorm . '» → «' . $afterValue . '»';
            }
        }

        return [
            'lines' => $lines,
            'changes' => $changes,
            'has_changes' => $changes !== [],
        ];
    }

    public function formatPreviewText(string $subjectLabel, array $diff, array $openUiAspects = []): string
    {
        $parts = ['Registro: ' . $subjectLabel];

        if ($diff['has_changes'] ?? false) {
            $parts[] = '';
            $parts[] = 'Cambios propuestos:';
            foreach ($diff['lines'] as $line) {
                $parts[] = '• ' . $line;
            }
        } elseif (($diff['changes'] ?? []) === [] && $openUiAspects === []) {
            $parts[] = '';
            $parts[] = 'No hay cambios respecto a los valores actuales.';
        }

        if ($openUiAspects !== []) {
            $parts[] = '';
            $parts[] = 'Aspectos con pantalla dedicada (sin cambios en este paso):';
            foreach ($openUiAspects as $aspect) {
                if (!is_array($aspect)) {
                    continue;
                }
                $label = trim((string) ($aspect['label'] ?? ''));
                if ($label !== '') {
                    $parts[] = '• ' . $label;
                }
            }
        }

        $parts[] = '';
        $parts[] = 'Vista previa (sin guardar aún). En la próxima fase se aplicarán los cambios al confirmar.';

        return implode("\n", $parts);
    }
}
