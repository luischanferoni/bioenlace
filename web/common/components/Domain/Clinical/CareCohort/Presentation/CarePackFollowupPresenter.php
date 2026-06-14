<?php

namespace common\components\Domain\Clinical\CareCohort\Presentation;

/**
 * UI JSON para un touchpoint de seguimiento post-consulta.
 */
final class CarePackFollowupPresenter
{
    private CareEducationModuleResolver $educationResolver;

    public function __construct(?CareEducationModuleResolver $educationResolver = null)
    {
        $this->educationResolver = $educationResolver ?? new CareEducationModuleResolver();
    }

    /**
     * @param array<string, mixed> $touchpoint fila de cola o touchpoint del pack
     * @param list<array<string, mixed>> $educationModules
     * @param array<string, mixed> $prefill
     */
    public function buildUiJson(
        array $touchpoint,
        int $touchpointQueueId,
        int $encounterId,
        array $educationModules,
        array $prefill = [],
        bool $readOnly = false
    ): array {
        $title = trim((string) ($touchpoint['title'] ?? 'Seguimiento'));
        $formKind = strtolower(trim((string) ($touchpoint['form_kind'] ?? 'evolution_short')));
        $purpose = trim((string) ($touchpoint['purpose'] ?? 'evolution'));
        $touchpointKey = trim((string) ($touchpoint['touchpoint_key'] ?? ''));

        $blocks = [];
        foreach ($educationModules as $module) {
            $block = $this->moduleToMessageBlock($module);
            if ($block !== null) {
                $blocks[] = $block;
            }
        }

        if ($purpose === 'education' && $blocks === [] && $educationModules === []) {
            $blocks[] = [
                'kind' => 'message',
                'id' => 'edu-placeholder',
                'title' => $title,
                'text' => 'Revisá las recomendaciones de tu atención y consultá ante cualquier duda.',
            ];
        }

        $fields = $this->fieldsForFormKind($formKind, $prefill, $readOnly);
        $fields[] = [
            'name' => 'touchpoint_id',
            'type' => 'hidden',
            'value' => (string) $touchpointQueueId,
            'include_in_submit' => true,
        ];
        $fields[] = [
            'name' => 'encounter_id',
            'type' => 'hidden',
            'value' => (string) $encounterId,
            'include_in_submit' => true,
        ];
        if ($touchpointKey !== '') {
            $fields[] = [
                'name' => 'touchpoint_key',
                'type' => 'hidden',
                'value' => $touchpointKey,
                'include_in_submit' => true,
            ];
        }

        $blocks[] = [
            'kind' => 'fields',
            'id' => 'followup-evolucion',
            'title' => $title,
            'fields' => $fields,
            'hide_submit' => $readOnly,
        ];

        return [
            'kind' => 'ui_definition',
            'ui_type' => 'ui_json',
            'title' => $title,
            'ui_meta' => [
                'schema_version' => '1',
                'clients' => ['*' => ['min_app_version' => '1.0.0']],
                'care_pack' => [
                    'type' => 'followup_program',
                    'encounter_id' => $encounterId,
                    'touchpoint_id' => $touchpointQueueId,
                    'touchpoint_key' => $touchpointKey,
                    'form_kind' => $formKind,
                ],
            ],
            'blocks' => $blocks,
        ];
    }

    public function buildSubmittedUi(int $touchpointQueueId, int $encounterId, string $title): array
    {
        return [
            'kind' => 'ui_definition',
            'ui_type' => 'ui_json',
            'title' => $title,
            'ui_meta' => [
                'schema_version' => '1',
                'care_pack' => [
                    'type' => 'followup_program',
                    'status' => 'submitted',
                    'encounter_id' => $encounterId,
                    'touchpoint_id' => $touchpointQueueId,
                ],
            ],
            'blocks' => [
                [
                    'kind' => 'message',
                    'id' => 'followup-done',
                    'title' => 'Gracias',
                    'text' => 'Registramos tu evolución. Si empeorás o tenés dudas, contactá al efector.',
                ],
            ],
        ];
    }

    public function buildNotAvailableUi(string $message): array
    {
        return [
            'kind' => 'ui_definition',
            'ui_type' => 'ui_json',
            'title' => 'Seguimiento',
            'blocks' => [
                [
                    'kind' => 'message',
                    'id' => 'followup-unavailable',
                    'title' => 'No disponible',
                    'text' => $message,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $module
     * @return array<string, mixed>|null
     */
    private function moduleToMessageBlock(array $module): ?array
    {
        $id = trim((string) ($module['id'] ?? 'edu'));
        $title = trim((string) ($module['title'] ?? ''));
        if ($title === '') {
            return null;
        }

        $parts = [];
        $summary = trim((string) ($module['summary'] ?? ''));
        if ($summary !== '') {
            $parts[] = $summary;
        }

        $bullets = $module['bullet_points'] ?? [];
        if (is_array($bullets) && $bullets !== []) {
            foreach ($bullets as $bullet) {
                $bullet = trim((string) $bullet);
                if ($bullet !== '') {
                    $parts[] = '• ' . $bullet;
                }
            }
        }

        $seek = trim((string) ($module['when_to_seek_care'] ?? ''));
        if ($seek !== '') {
            $parts[] = 'Cuándo consultar: ' . $seek;
        }

        if ($parts === []) {
            return null;
        }

        return [
            'kind' => 'message',
            'id' => 'edu-' . $id,
            'title' => $title,
            'text' => implode("\n\n", $parts),
        ];
    }

    /**
     * @param array<string, mixed> $prefill
     * @return list<array<string, mixed>>
     */
    private function fieldsForFormKind(string $formKind, array $prefill, bool $readOnly): array
    {
        switch ($formKind) {
            case 'adherence':
                return $this->withPrefill([
                    [
                        'name' => 'tomo_medicacion',
                        'label' => '¿Tomaste la medicación indicada?',
                        'type' => 'select',
                        'required' => true,
                        'include_in_submit' => true,
                        'readonly' => $readOnly,
                        'options' => [
                            ['value' => 'si', 'label' => 'Sí, según lo indicado'],
                            ['value' => 'parcial', 'label' => 'Parcialmente'],
                            ['value' => 'no', 'label' => 'No'],
                        ],
                    ],
                    [
                        'name' => 'observaciones',
                        'label' => 'Comentarios (opcional)',
                        'type' => 'textarea',
                        'required' => false,
                        'include_in_submit' => true,
                        'readonly' => $readOnly,
                    ],
                ], $prefill);

            case 'symptoms':
                return $this->withPrefill([
                    [
                        'name' => 'sintomas_actuales',
                        'label' => '¿Qué síntomas tenés ahora?',
                        'type' => 'textarea',
                        'required' => true,
                        'include_in_submit' => true,
                        'readonly' => $readOnly,
                    ],
                    [
                        'name' => 'intensidad',
                        'label' => 'Intensidad (1 = leve, 10 = muy intenso)',
                        'type' => 'select',
                        'required' => true,
                        'include_in_submit' => true,
                        'readonly' => $readOnly,
                        'options' => $this->scaleOptions(1, 10),
                    ],
                ], $prefill);

            case 'evolution_short':
            default:
                return $this->withPrefill([
                    [
                        'name' => 'sintomas_evolucion',
                        'label' => '¿Cómo evolucionaron tus síntomas?',
                        'type' => 'textarea',
                        'required' => true,
                        'include_in_submit' => true,
                        'readonly' => $readOnly,
                    ],
                    [
                        'name' => 'comparacion',
                        'label' => 'Comparado con después de la consulta',
                        'type' => 'select',
                        'required' => true,
                        'include_in_submit' => true,
                        'readonly' => $readOnly,
                        'options' => [
                            ['value' => 'mejor', 'label' => 'Mejor'],
                            ['value' => 'igual', 'label' => 'Igual'],
                            ['value' => 'peor', 'label' => 'Peor'],
                        ],
                    ],
                ], $prefill);
        }
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @param array<string, mixed> $prefill
     * @return list<array<string, mixed>>
     */
    private function withPrefill(array $fields, array $prefill): array
    {
        foreach ($fields as &$field) {
            $name = (string) ($field['name'] ?? '');
            if ($name !== '' && array_key_exists($name, $prefill)) {
                $field['value'] = (string) $prefill[$name];
            }
        }
        unset($field);

        return $fields;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function scaleOptions(int $min, int $max): array
    {
        $out = [];
        for ($i = $min; $i <= $max; $i++) {
            $out[] = ['value' => (string) $i, 'label' => (string) $i];
        }

        return $out;
    }
}
