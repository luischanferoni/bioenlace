<?php

namespace common\components\Domain\Scheduling\Service;

use common\models\Scheduling\Turno;

/**
 * Reconstruye un draft de reserva/triage desde columnas persistidas en {@see Turno}.
 */
final class TurnoReservaTriageDraftBuilder
{
    /** @var list<string> */
    private const TRIAGE_FIELDS = [
        'triage_raiz',
        'triage_alarmas',
        'triage_zona',
        'triage_detalle',
        'triage_evolucion',
    ];

    /**
     * @return array<string, mixed>
     */
    public function buildFromTurno(Turno $turno): array
    {
        $draft = [
            'id_servicio_asignado' => (int) ($turno->id_servicio_asignado ?? 0) ?: null,
            'id_persona' => (int) ($turno->id_persona ?? 0) ?: null,
            'tipo_atencion' => (string) ($turno->tipo_atencion ?? Turno::TIPO_ATENCION_PRESENCIAL),
        ];

        foreach (self::TRIAGE_FIELDS as $field) {
            $v = trim((string) ($turno->$field ?? ''));
            if ($v !== '') {
                $draft[$field] = $v;
            }
        }

        $metaRaw = $turno->reserva_triage_meta_json ?? null;
        if ($metaRaw !== null && $metaRaw !== '') {
            $meta = is_array($metaRaw) ? $metaRaw : json_decode((string) $metaRaw, true);
            if (is_array($meta['path'] ?? null)) {
                foreach ($meta['path'] as $step) {
                    if (!is_array($step)) {
                        continue;
                    }
                    $field = trim((string) ($step['field'] ?? ''));
                    $code = trim((string) ($step['code'] ?? ''));
                    if ($field === '' || $code === '' || $code === '_free_text') {
                        continue;
                    }
                    $draft[$field] = $code;
                }
            }
        }

        return $this->enrichDraftFromLeafCode($turno, $draft);
    }

    /**
     * @param array<string, mixed> $draft
     * @return array<string, mixed>
     */
    private function enrichDraftFromLeafCode(Turno $turno, array $draft): array
    {
        $leaf = trim((string) ($turno->reserva_triage_code ?? ''));
        if ($leaf === '') {
            return $draft;
        }

        $catalog = new ReservaTurnoTriageCatalogService();
        $node = $catalog->findNode($leaf);
        if ($node === null) {
            return $draft;
        }

        $stepId = trim((string) ($node['step'] ?? ''));
        if ($stepId === '') {
            return $draft;
        }

        $step = $catalog->getStep($stepId);
        if ($step === null) {
            return $draft;
        }

        $field = trim((string) ($step['draft_field'] ?? ''));
        if ($field === '' || trim((string) ($draft[$field] ?? '')) !== '') {
            return $draft;
        }

        $draft[$field] = $leaf;

        return $draft;
    }

    /**
     * @return list<string>
     */
    public function codigosCasoDesdeTurno(Turno $turno): array
    {
        $draft = $this->buildFromTurno($turno);
        $ordered = ['triage_zona', 'triage_detalle', 'triage_evolucion', 'triage_alarmas', 'triage_raiz'];
        $out = [];
        foreach ($ordered as $key) {
            $v = trim((string) ($draft[$key] ?? ''));
            if ($v !== '') {
                $out[] = $v;
            }
        }
        $leaf = trim((string) ($turno->reserva_triage_code ?? ''));
        if ($leaf !== '' && !in_array($leaf, $out, true)) {
            $out[] = $leaf;
        }

        return array_values(array_unique($out));
    }

    public function tieneTriagePersistido(Turno $turno): bool
    {
        if (trim((string) ($turno->reserva_triage_code ?? '')) !== '') {
            return true;
        }

        return $this->codigosCasoDesdeTurno($turno) !== [];
    }
}
