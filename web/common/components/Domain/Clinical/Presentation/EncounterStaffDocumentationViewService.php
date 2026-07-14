<?php

namespace common\components\Domain\Clinical\Presentation;

use common\components\Platform\Core\Product\ClinicalTextIaMetadata;
use common\models\Clinical\Encounter;

/**
 * Vista declarativa de lo registrado por el médico en un encounter (app / JSON).
 */
final class EncounterStaffDocumentationViewService
{
    /**
     * @return array{
     *   encounter_id: int,
     *   tiene_datos: bool,
     *   secciones: list<array{titulo: string, items: list<string>}>
     * }
     */
    public function buildForEncounter(Encounter $encounter): array
    {
        $secciones = [];

        $nota = trim((string) ($encounter->note ?? ''));
        if ($nota !== '') {
            $secciones[] = [
                'titulo' => 'Evolución',
                'items' => [$nota],
            ];
        }

        $motivos = [];
        $reason = trim((string) ($encounter->reason_text ?? ''));
        if ($reason !== '') {
            foreach (preg_split('/\n|;/u', $reason) ?: [] as $part) {
                $part = trim((string) $part);
                if ($part !== '' && !$this->isDuplicateLabel($motivos, $part)) {
                    $motivos[] = $part;
                }
            }
        }

        $diagnosticos = [];
        foreach ($encounter->getDiagnosticos() as $condition) {
            if ($condition->deleted_at !== null) {
                continue;
            }
            $label = trim((string) ($condition->display ?? ''));
            if ($label === '') {
                $label = trim((string) ($condition->code ?? ''));
            }
            if ($label === '') {
                continue;
            }

            // Secundarios auto-codificados que son queja/síntoma → Motivos (no Diagnósticos).
            $role = mb_strtolower(trim((string) ($condition->diagnosis_role ?? '')));
            if (
                $role === 'secondary'
                && ClinicalTextIaMetadata::textMatchesClinicalLexiconPattern($label, 'subjective_complaint')
            ) {
                if (!$this->isDuplicateLabel($motivos, $label)) {
                    $motivos[] = $label;
                }
                continue;
            }

            if (!$this->isDuplicateLabel($diagnosticos, $label)) {
                $diagnosticos[] = $label;
            }
        }
        if ($motivos !== []) {
            $secciones[] = [
                'titulo' => 'Motivos de consulta',
                'items' => $motivos,
            ];
        }
        if ($diagnosticos !== []) {
            $secciones[] = [
                'titulo' => 'Diagnósticos',
                'items' => $diagnosticos,
            ];
        }

        $medicacion = [];
        foreach ($encounter->getMedicamentosActivos() as $medication) {
            $label = trim((string) ($medication->medication_display ?? ''));
            if ($label === '') {
                $label = trim((string) ($medication->medication_code ?? ''));
            }
            $dosis = trim((string) ($medication->dosage_text ?? ''));
            if ($dosis !== '' && $label !== '') {
                $label .= ' · ' . $dosis;
            }
            if ($label !== '' && !$this->isDuplicateLabel($medicacion, $label)) {
                $medicacion[] = $label;
            }
        }
        if ($medicacion !== []) {
            $secciones[] = [
                'titulo' => 'Medicación',
                'items' => $medicacion,
            ];
        }

        $practicas = [];
        $indicaciones = [];
        foreach ($encounter->getServiceRequests()->andWhere(['deleted_at' => null])->all() as $request) {
            $label = trim((string) ($request->display ?? ''));
            if ($label === '') {
                $label = trim((string) ($request->code ?? ''));
            }
            if ($label === '') {
                continue;
            }
            $note = trim((string) ($request->note ?? ''));
            if ($note !== '' && mb_stripos($label, $note) === false) {
                $label .= ' · ' . $note;
            }
            $category = mb_strtolower(trim((string) ($request->category ?? '')));
            if (in_array($category, ['counseling', 'follow-up'], true)) {
                if (!$this->isDuplicateLabel($indicaciones, $label)) {
                    $indicaciones[] = $label;
                }
            } elseif ($category !== 'referral') {
                if (!$this->isDuplicateLabel($practicas, $label)) {
                    $practicas[] = $label;
                }
            }
        }
        if ($practicas !== []) {
            $secciones[] = [
                'titulo' => 'Prácticas realizadas',
                'items' => $practicas,
            ];
        }
        if ($indicaciones !== []) {
            $secciones[] = [
                'titulo' => 'Indicaciones',
                'items' => $indicaciones,
            ];
        }

        return [
            'encounter_id' => (int) $encounter->id,
            'tiene_datos' => $secciones !== [],
            'secciones' => $secciones,
        ];
    }

    /**
     * @param list<string> $existing
     */
    private function isDuplicateLabel(array $existing, string $label): bool
    {
        $folded = $this->foldLabel($label);
        if ($folded === '') {
            return true;
        }
        foreach ($existing as $item) {
            if ($this->foldLabel($item) === $folded) {
                return true;
            }
        }

        return false;
    }

    private function foldLabel(string $text): string
    {
        $folded = strtr(mb_strtolower(trim($text), 'UTF-8'), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return preg_replace('/\s+/u', ' ', $folded) ?? $folded;
    }
}
