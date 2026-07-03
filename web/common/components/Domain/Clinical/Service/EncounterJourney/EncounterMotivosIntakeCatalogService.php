<?php

namespace common\components\Domain\Clinical\Service\EncounterJourney;

use Symfony\Component\Yaml\Yaml;

/**
 * Catálogo de guía del chat de motivos ({@see metadata/motivos_consulta_intake.yaml}).
 */
final class EncounterMotivosIntakeCatalogService
{
    private const CATALOG_FILE = 'motivos_consulta_intake.yaml';

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public function isEnabled(): bool
    {
        if (!((bool) (self::load()['enabled'] ?? false))) {
            return false;
        }

        return $this->hasChatGuide() || $this->questions() !== [];
    }

    public function presentation(): string
    {
        $mode = strtolower(trim((string) (self::load()['presentation'] ?? 'form')));

        return $mode !== '' ? $mode : 'form';
    }

    public function isChatPresentation(): bool
    {
        return $this->presentation() === 'chat';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function questions(): array
    {
        $raw = self::load()['questions'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    public function title(): string
    {
        $title = trim((string) (self::load()['title'] ?? ''));

        return $title !== '' ? $title : 'Motivos de consulta';
    }

    public function intro(): string
    {
        return trim((string) (self::load()['intro'] ?? ''));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buildChatGuide(?string $reservaTriageCode = null): ?array
    {
        if (!$this->isEnabled() || !$this->hasChatGuide()) {
            return null;
        }

        $message = $this->resolveChatMessage($reservaTriageCode);
        if ($message === '') {
            return null;
        }

        return [
            'enabled' => true,
            'title' => $this->title(),
            'message' => $message,
            'presentation' => 'chat',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function packContent(): array
    {
        return [
            'questions' => $this->questions(),
            'notes_for_staff' => trim((string) (self::load()['notes_for_staff'] ?? '')),
        ];
    }

    public static function resetCacheForTests(): void
    {
        self::$cache = null;
    }

    private function hasChatGuide(): bool
    {
        if ($this->isChatPresentation()) {
            return true;
        }

        $guide = self::load()['chat_guide'] ?? null;

        return is_array($guide) && $guide !== [];
    }

    private function resolveChatMessage(?string $reservaTriageCode): string
    {
        $guide = self::load()['chat_guide'] ?? null;
        if (!is_array($guide)) {
            return '';
        }

        $lines = [];
        $greeting = trim((string) ($guide['greeting'] ?? ''));
        if ($greeting !== '') {
            $lines[] = $greeting;
        }

        foreach ($this->questionLines($guide) as $line) {
            $lines[] = $line;
        }

        foreach ($this->extraQuestionsForTriage($reservaTriageCode) as $line) {
            $lines[] = $line;
        }

        $footer = trim((string) ($guide['footer'] ?? ''));
        if ($footer !== '') {
            if ($lines !== []) {
                $lines[] = '';
            }
            $lines[] = $footer;
        }

        return trim(implode("\n", $lines));
    }

    /**
     * @param array<string, mixed> $guide
     * @return list<string>
     */
    private function questionLines(array $guide): array
    {
        $raw = $guide['questions'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            $text = $this->normalizeQuestionText($item);
            if ($text === '') {
                continue;
            }
            $out[] = '• ' . $text;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function extraQuestionsForTriage(?string $reservaTriageCode): array
    {
        $code = strtolower(trim((string) $reservaTriageCode));
        if ($code === '') {
            return [];
        }

        $variants = self::load()['variants'] ?? [];
        if (!is_array($variants)) {
            return [];
        }

        $out = [];
        foreach ($variants as $variant) {
            if (!is_array($variant)) {
                continue;
            }
            $match = $variant['match'] ?? null;
            if (!is_array($match)) {
                continue;
            }
            $matchCode = strtolower(trim((string) ($match['reserva_triage_code'] ?? '')));
            if ($matchCode === '' || $matchCode !== $code) {
                continue;
            }
            $extra = $variant['extra_questions'] ?? [];
            if (!is_array($extra)) {
                continue;
            }
            foreach ($extra as $item) {
                $text = $this->normalizeQuestionText($item);
                if ($text === '') {
                    continue;
                }
                $out[] = '• ' . $text;
            }
        }

        return $out;
    }

    private function normalizeQuestionText(mixed $item): string
    {
        if (is_string($item)) {
            return trim($item);
        }
        if (!is_array($item)) {
            return '';
        }

        return trim((string) ($item['text'] ?? $item['label'] ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $path = dirname(__DIR__, 2) . '/metadata/' . self::CATALOG_FILE;
        if (!is_file($path)) {
            self::$cache = ['version' => 1, 'enabled' => false, 'questions' => []];

            return self::$cache;
        }
        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            throw new \RuntimeException('Catálogo motivos_consulta_intake inválido.');
        }
        self::$cache = $data;

        return self::$cache;
    }
}
