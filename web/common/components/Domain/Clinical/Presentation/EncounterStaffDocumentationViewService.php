<?php

namespace common\components\Domain\Clinical\Presentation;

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

        $diagnosticos = [];
        foreach ($encounter->getDiagnosticos() as $condition) {
            if ($condition->deleted_at !== null) {
                continue;
            }
            $label = trim((string) ($condition->display ?? ''));
            if ($label === '') {
                $label = trim((string) ($condition->code ?? ''));
            }
            if ($label !== '') {
                $diagnosticos[] = $label;
            }
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
            if ($label !== '') {
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
            $category = mb_strtolower(trim((string) ($request->category ?? '')));
            if (in_array($category, ['counseling', 'follow-up'], true)) {
                $indicaciones[] = $label;
            } elseif ($category !== 'referral') {
                $practicas[] = $label;
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
}
