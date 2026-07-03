<?php

namespace common\components\Domain\Clinical\Service\EncounterJourney;

use common\models\Clinical\Encounter;

/**
 * Respuestas de {@see Encounter::$motivos_intake_json} formateadas para staff (historia clínica).
 */
final class EncounterMotivosIntakeStaffViewService
{
    private EncounterMotivosIntakeCatalogService $catalog;

    public function __construct(?EncounterMotivosIntakeCatalogService $catalog = null)
    {
        $this->catalog = $catalog ?? new EncounterMotivosIntakeCatalogService();
    }

    /**
     * @return array<string, mixed>|null null si no hay nada que mostrar al equipo
     */
    public function buildForEncounter(Encounter $encounter): ?array
    {
        $answers = $this->decodeAnswers($encounter->motivos_intake_json ?? null);
        $catalogEnabled = $this->catalog->isEnabled();

        if ($answers === [] && !$catalogEnabled) {
            return null;
        }

        $notesForStaff = $this->notesForStaff();

        if ($answers === []) {
            return [
                'status' => 'pending',
                'title' => $this->catalog->title(),
                'notes_for_staff' => $notesForStaff,
                'answers' => [],
            ];
        }

        return [
            'status' => 'submitted',
            'title' => $this->catalog->title(),
            'notes_for_staff' => $notesForStaff,
            'answers' => $this->formatAnswersForStaff($answers),
        ];
    }

    private function notesForStaff(): string
    {
        $pack = $this->catalog->packContent();

        return trim((string) ($pack['notes_for_staff'] ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeAnswers(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $answers
     * @return list<array{id: string, question: string, answer: string}>
     */
    private function formatAnswersForStaff(array $answers): array
    {
        if ($answers === []) {
            return [];
        }

        $questionsById = [];
        foreach ($this->catalog->questions() as $q) {
            if (!is_array($q)) {
                continue;
            }
            $id = trim((string) ($q['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $questionsById[$id] = $q;
        }

        $out = [];
        foreach ($this->orderedQuestionIds($questionsById, $answers) as $id) {
            if (!array_key_exists($id, $answers)) {
                continue;
            }
            $value = $answers[$id];
            if ($value === null || (is_string($value) && trim($value) === '')) {
                continue;
            }
            $question = $questionsById[$id] ?? null;
            $label = is_array($question)
                ? trim((string) ($question['label'] ?? $id))
                : $id;
            $out[] = [
                'id' => $id,
                'question' => $label !== '' ? $label : $id,
                'answer' => $this->resolveAnswerLabel(is_array($question) ? $question : [], $value),
            ];
        }

        return $out;
    }

    /**
     * @param array<string, array<string, mixed>> $questionsById
     * @param array<string, mixed> $answers
     * @return list<string>
     */
    private function orderedQuestionIds(array $questionsById, array $answers): array
    {
        $ordered = [];
        foreach (array_keys($questionsById) as $id) {
            $ordered[] = $id;
        }
        foreach (array_keys($answers) as $id) {
            $key = trim((string) $id);
            if ($key !== '' && !in_array($key, $ordered, true)) {
                $ordered[] = $key;
            }
        }

        return $ordered;
    }

    /**
     * @param array<string, mixed> $question
     */
    private function resolveAnswerLabel(array $question, mixed $value): string
    {
        if (!is_scalar($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        $type = strtolower(trim((string) ($question['type'] ?? 'text')));
        if ($type !== 'select') {
            return $raw;
        }

        $options = $question['options'] ?? [];
        if (!is_array($options)) {
            return $raw;
        }

        foreach ($options as $opt) {
            if (!is_array($opt)) {
                continue;
            }
            if ((string) ($opt['value'] ?? '') === $raw) {
                $label = trim((string) ($opt['label'] ?? ''));

                return $label !== '' ? $label : $raw;
            }
        }

        return $raw;
    }
}
