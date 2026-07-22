<?php

namespace common\components\Domain\Clinical\Home\Sections;

use common\components\Domain\Clinical\Service\ConditionPresentationService;
use common\components\Domain\Person\Representation\Enum\RepresentationPermission;
use common\components\Domain\Person\Representation\Service\PersonRepresentationSubjectService;
use common\components\Domain\Scheduling\Service\ConsultaAsyncBandejaService;
use common\components\Platform\Ui\Home\Service\Sections\HomePanelSectionProviderInterface;

/**
 * Sección home: condiciones activas + consultas async anidadas (ui_group=condicion).
 */
final class PatientConditionsActiveSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $params = [];
        if (!empty($context['subject_persona_id'])) {
            $params['subject_persona_id'] = (int) $context['subject_persona_id'];
        }
        $subjectSvc = new PersonRepresentationSubjectService();
        $idPersona = $subjectSvc->resolveAndAuthorize(
            $params,
            RepresentationPermission::CLINICAL_CARE_PLAN
        );

        $summaries = (new ConditionPresentationService())->listPatientSummaries($idPersona);
        $bandeja = (new ConsultaAsyncBandejaService())->listForPaciente($idPersona, [
            'ui_group' => 'condicion',
        ]);
        $activasByCodigo = $this->indexByConditionCodigo($bandeja['items'] ?? []);

        $data = [];
        foreach ($summaries as $summary) {
            $codigo = strtoupper(trim((string) ($summary['codigo'] ?? '')));
            $activas = $codigo !== ''
                ? ($activasByCodigo[$codigo] ?? [])
                : [];
            // Sin código CIE: match por condition_ref = id de fila.
            if ($activas === [] && (int) ($summary['id'] ?? 0) > 0) {
                $activas = $activasByCodigo['id:' . (int) $summary['id']] ?? [];
            }
            $summary['solicitudes_activas'] = $activas;
            $summary['solicitudes_pendientes_count'] = count($activas);
            $data[] = $summary;
        }

        $pendientesTotal = 0;
        foreach ($data as $row) {
            $pendientesTotal += (int) ($row['solicitudes_pendientes_count'] ?? 0);
        }

        return [
            'title' => 'Tus condiciones',
            'items' => $data,
            'total' => count($data),
            'solicitudes_pendientes_count' => $pendientesTotal,
        ];
    }

    /**
     * @param list<mixed> $items
     * @return array<string, list<array<string, mixed>>>
     */
    private function indexByConditionCodigo(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $codigo = strtoupper(trim((string) ($item['condition_codigo'] ?? '')));
            $ref = trim((string) ($item['condition_ref'] ?? ''));
            $keys = [];
            if ($codigo !== '') {
                $keys[] = $codigo;
            }
            if ($ref !== '' && !ctype_digit($ref)) {
                $keys[] = strtoupper($ref);
            }
            if ($ref !== '' && ctype_digit($ref)) {
                $keys[] = 'id:' . (int) $ref;
            }
            if ($keys === []) {
                continue;
            }
            foreach (array_unique($keys) as $key) {
                $out[$key][] = $item;
            }
        }

        return $out;
    }
}
