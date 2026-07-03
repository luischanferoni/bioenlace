<?php

namespace common\components\Domain\Clinical\Service\EncounterJourney;

use Yii;

/**
 * Estado de ventana temporal por fase (metadata + anchor turno o encounter).
 */
final class EncounterPhaseWindowService
{
    private EncounterPhaseWindowsCatalogService $catalog;

    public function __construct(?EncounterPhaseWindowsCatalogService $catalog = null)
    {
        $this->catalog = $catalog ?? new EncounterPhaseWindowsCatalogService();
    }

    /**
     * @param array<string, mixed> $context
     * @return array{
     *   input_abierto: bool,
     *   ventana_abierta: bool,
     *   abre_en: string|null,
     *   cierra_en: string|null,
     *   minutos_antes_cierre: int|null,
     *   anchor_at: string|null
     * }
     */
    public function state(string $phaseId, array $context): array
    {
        $def = $this->catalog->phase($phaseId);
        if ($def === null) {
            return $this->emptyState();
        }

        $anchorTs = $this->resolveAnchorTimestamp($def, $context);
        if ($anchorTs === null) {
            return $this->emptyState();
        }

        $openOffset = trim((string) ($def['open_offset'] ?? ''));
        $closeOffset = trim((string) ($def['close_offset'] ?? ''));
        $openSec = $openOffset !== '' ? $this->catalog->offsetSeconds($openOffset) : null;
        $closeSec = $closeOffset !== '' ? $this->catalog->offsetSeconds($closeOffset) : null;

        $openAt = $openSec !== null ? $anchorTs + $openSec : $anchorTs;
        $closeAt = $closeSec !== null ? $anchorTs + $closeSec : null;

        $now = $this->nowTimestamp();
        $ventanaAbierta = $now >= $openAt && ($closeAt === null || $now < $closeAt);
        $inputAbierto = $ventanaAbierta;

        $minutosCierre = null;
        if ($closeSec !== null && $closeSec < 0) {
            $minutosCierre = (int) round(abs($closeSec) / 60);
        }

        return [
            'input_abierto' => $inputAbierto,
            'ventana_abierta' => $ventanaAbierta,
            'abre_en' => $now < $openAt ? date('c', $openAt) : null,
            'cierra_en' => $closeAt !== null ? date('c', $closeAt) : null,
            'minutos_antes_cierre' => $minutosCierre,
            'anchor_at' => date('c', $anchorTs),
        ];
    }

    public function minutesBeforeCloseForPhase(string $phaseId): int
    {
        $def = $this->catalog->phase($phaseId);
        if ($def === null) {
            return 2;
        }
        $closeOffset = trim((string) ($def['close_offset'] ?? ''));
        $sec = $closeOffset !== '' ? $this->catalog->offsetSeconds($closeOffset) : null;
        if ($sec === null || $sec >= 0) {
            return 0;
        }

        return max(0, (int) round(abs($sec) / 60));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function resolveAnchorTimestamp(array $def, array $context): ?int
    {
        $anchor = trim((string) ($def['anchor'] ?? 'turno_start'));
        if ($anchor === 'encounter_finished') {
            $raw = $context['encounter_finished_at'] ?? null;
            if (!is_string($raw) || trim($raw) === '') {
                return null;
            }
            $ts = strtotime($raw);

            return $ts !== false ? $ts : null;
        }

        $turnoAt = $context['turno_starts_at'] ?? null;

        return is_int($turnoAt) && $turnoAt > 0 ? $turnoAt : null;
    }

    /**
     * @return array{
     *   input_abierto: bool,
     *   ventana_abierta: bool,
     *   abre_en: null,
     *   cierra_en: null,
     *   minutos_antes_cierre: null,
     *   anchor_at: null
     * }
     */
    private function emptyState(): array
    {
        return [
            'input_abierto' => false,
            'ventana_abierta' => false,
            'abre_en' => null,
            'cierra_en' => null,
            'minutos_antes_cierre' => null,
            'anchor_at' => null,
        ];
    }

    private function nowTimestamp(): int
    {
        try {
            $tz = new \DateTimeZone(Yii::$app->timeZone ?: 'America/Argentina/Tucuman');
        } catch (\Exception $e) {
            $tz = new \DateTimeZone('America/Argentina/Tucuman');
        }

        return (new \DateTimeImmutable('now', $tz))->getTimestamp();
    }
}
