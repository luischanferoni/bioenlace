<?php

namespace common\components\Domain\Clinical\CareCohort\Presentation;

/**
 * Convierte content_json de assistance_questions en ui_json (bloque fields).
 */
final class CarePackAssistancePresenter
{
    /**
     * @param array<string, mixed> $packContent
     * @param array<string, mixed> $prefill Respuestas previas (solo lectura si submitted)
     */
    public function buildUiJson(
        array $packContent,
        int $encounterId,
        int $packId,
        array $prefill = [],
        bool $readOnly = false
    ): array {
        $questions = $packContent['questions'] ?? [];
        if (!is_array($questions)) {
            $questions = [];
        }

        $fields = [];
        foreach ($questions as $q) {
            if (!is_array($q)) {
                continue;
            }
            $field = $this->questionToField($q, $prefill, $readOnly);
            if ($field !== null) {
                $fields[] = $field;
            }
        }

        $fields[] = [
            'name' => 'encounter_id',
            'type' => 'hidden',
            'value' => (string) $encounterId,
            'include_in_submit' => true,
        ];
        $fields[] = [
            'name' => 'pack_id',
            'type' => 'hidden',
            'value' => (string) $packId,
            'include_in_submit' => true,
        ];

        $notes = trim((string) ($packContent['notes_for_staff'] ?? ''));
        $blocks = [];
        if ($notes !== '') {
            $blocks[] = [
                'kind' => 'message',
                'id' => 'notas-cohorte',
                'title' => 'Orientación',
                'text' => $notes,
            ];
        }

        $blocks[] = [
            'kind' => 'fields',
            'id' => 'asistencia-preguntas',
            'title' => 'Antes de tu consulta',
            'fields' => $fields,
            'hide_submit' => $readOnly,
        ];

        return [
            'kind' => 'ui_definition',
            'ui_type' => 'ui_json',
            'title' => 'Asistencia pre-consulta',
            'ui_meta' => [
                'schema_version' => '1',
                'clients' => ['*' => ['min_app_version' => '1.0.0']],
                'care_pack' => [
                    'type' => 'assistance_questions',
                    'pack_id' => $packId,
                    'encounter_id' => $encounterId,
                ],
            ],
            'blocks' => $blocks,
        ];
    }

    public function buildPendingUi(int $encounterId): array
    {
        return [
            'kind' => 'ui_definition',
            'ui_type' => 'ui_json',
            'title' => 'Asistencia pre-consulta',
            'ui_meta' => [
                'schema_version' => '1',
                'care_pack' => [
                    'type' => 'assistance_questions',
                    'status' => 'pending',
                    'encounter_id' => $encounterId,
                    'retry_after_seconds' => 30,
                ],
            ],
            'blocks' => [
                [
                    'kind' => 'message',
                    'id' => 'pack-pending',
                    'title' => 'Un momento',
                    'text' => 'Estamos preparando las preguntas para tu consulta. Volvé a intentar en unos segundos.',
                ],
            ],
        ];
    }

    public function buildSubmittedUi(int $encounterId): array
    {
        return [
            'kind' => 'ui_definition',
            'ui_type' => 'ui_json',
            'title' => 'Asistencia pre-consulta',
            'ui_meta' => [
                'schema_version' => '1',
                'care_pack' => [
                    'type' => 'assistance_questions',
                    'status' => 'submitted',
                    'encounter_id' => $encounterId,
                ],
            ],
            'blocks' => [
                [
                    'kind' => 'message',
                    'id' => 'pack-done',
                    'title' => 'Gracias',
                    'text' => 'Ya registramos tus respuestas. El equipo las tendrá en cuenta antes de atenderte.',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $q
     * @param array<string, mixed> $prefill
     * @return array<string, mixed>|null
     */
    private function questionToField(array $q, array $prefill, bool $readOnly): ?array
    {
        $id = trim((string) ($q['id'] ?? ''));
        $text = trim((string) ($q['text'] ?? ''));
        if ($id === '' || $text === '') {
            return null;
        }

        $answerType = strtolower(trim((string) ($q['answer_type'] ?? 'text')));
        $required = !empty($q['required']);
        $options = $q['options'] ?? [];
        if (!is_array($options)) {
            $options = [];
        }

        $field = [
            'name' => $id,
            'label' => $text,
            'required' => $required,
            'include_in_submit' => true,
            'readonly' => $readOnly,
        ];

        if ($answerType === 'choice' || $answerType === 'scale') {
            $field['type'] = 'select';
            $field['options'] = $this->normalizeOptions($options, $answerType);
        } else {
            $field['type'] = 'textarea';
        }

        if (array_key_exists($id, $prefill)) {
            $field['value'] = (string) $prefill[$id];
        }

        return $field;
    }

    /**
     * @param list<mixed> $options
     * @return list<array{value: string, label: string}>
     */
    private function normalizeOptions(array $options, string $answerType): array
    {
        $out = [];
        if ($answerType === 'scale' && $options === []) {
            for ($i = 1; $i <= 10; $i++) {
                $out[] = ['value' => (string) $i, 'label' => (string) $i];
            }

            return $out;
        }

        foreach ($options as $opt) {
            if (is_array($opt)) {
                $value = (string) ($opt['value'] ?? $opt['id'] ?? $opt['label'] ?? '');
                $label = (string) ($opt['label'] ?? $value);
            } else {
                $value = (string) $opt;
                $label = $value;
            }
            if ($value === '') {
                continue;
            }
            $out[] = ['value' => $value, 'label' => $label];
        }

        return $out;
    }
}
