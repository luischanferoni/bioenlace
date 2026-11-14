<?php

namespace frontend\components\Clinical;

/**
 * View model de las sesiones de salud mental para la historia clínica.
 */
final class PanelSaludMentalViewBuilder
{
    private const BADGE_CLASSES = [
        'success' => 'text-bg-success',
        'info' => 'text-bg-info',
        'warning' => 'text-bg-warning',
        'danger' => 'text-bg-danger',
    ];

    /**
     * @param list<array<string, mixed>> $sesiones salida de `sessionsForPerson`
     * @return array{visible: bool, sesiones: list<array<string, mixed>>}
     */
    public static function build(array $sesiones): array
    {
        $out = [];
        foreach ($sesiones as $sesion) {
            if (!is_array($sesion)) {
                continue;
            }

            $scores = [];
            foreach ((array) ($sesion['scores'] ?? []) as $score) {
                if (!is_array($score)) {
                    continue;
                }
                $severity = is_array($score['severity'] ?? null) ? $score['severity'] : null;
                $scores[] = [
                    'label' => (string) ($score['label'] ?? ''),
                    'value' => (string) ($score['value'] ?? ''),
                    'unit' => (string) ($score['unit'] ?? ''),
                    'severityLabel' => $severity !== null ? (string) ($severity['label'] ?? '') : '',
                    'severityClass' => $severity !== null
                        ? (self::BADGE_CLASSES[(string) ($severity['intent'] ?? '')] ?? 'text-bg-secondary')
                        : '',
                ];
            }

            $scoreLabels = array_column($scores, 'label');

            $texts = [];
            foreach ((array) ($sesion['values'] ?? []) as $field => $value) {
                if (!is_array($value)) {
                    continue;
                }
                if (in_array((string) ($value['label'] ?? $field), $scoreLabels, true)) {
                    continue;
                }
                $text = trim((string) ($value['value'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $texts[] = ['label' => (string) ($value['label'] ?? $field), 'value' => $text];
            }

            if ($scores === [] && $texts === []) {
                continue;
            }

            $registradoEn = trim((string) ($sesion['registradoEn'] ?? ''));
            $out[] = [
                'fecha' => $registradoEn !== '' ? substr($registradoEn, 0, 10) : '',
                'scores' => $scores,
                'texts' => $texts,
            ];
        }

        return ['visible' => $out !== [], 'sesiones' => $out];
    }
}
