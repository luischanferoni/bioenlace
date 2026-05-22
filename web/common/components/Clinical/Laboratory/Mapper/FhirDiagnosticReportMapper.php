<?php

namespace common\components\Clinical\Laboratory\Mapper;

/**
 * Extrae recursos DiagnosticReport (y Observation embebidas) de una respuesta FHIR.
 */
final class FhirDiagnosticReportMapper
{
    /**
     * @return array<int, array{report: array<string, mixed>, observations: array<int, array<string, mixed>>}>
     */
    public function extractReportsFromBundle(array $fhirResponse): array
    {
        $out = [];
        $entries = $fhirResponse['entry'] ?? null;
        if (is_array($entries)) {
            foreach ($entries as $entry) {
                $this->collectFromEntry($entry, $out);
            }

            return array_values($out);
        }

        if (($fhirResponse['resourceType'] ?? '') === 'DiagnosticReport') {
            $out[] = [
                'report' => $fhirResponse,
                'observations' => $this->extractInlineObservations($fhirResponse),
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<int, array{report: array<string, mixed>, observations: array<int, array<string, mixed>>}> $out
     */
    private function collectFromEntry(array $entry, array &$out): void
    {
        $res = $entry['resource'] ?? null;
        if (!is_array($res)) {
            return;
        }
        $type = $res['resourceType'] ?? '';
        if ($type === 'DiagnosticReport') {
            $out[] = [
                'report' => $res,
                'observations' => $this->extractInlineObservations($res),
            ];
        }
    }

    /**
     * @param array<string, mixed> $report
     * @return array<int, array<string, mixed>>
     */
    private function extractInlineObservations(array $report): array
    {
        $obs = [];
        foreach ($report['contained'] ?? [] as $contained) {
            if (is_array($contained) && ($contained['resourceType'] ?? '') === 'Observation') {
                $obs[] = $contained;
            }
        }

        return $obs;
    }

    /**
     * @param array<string, mixed> $codingBlock code | coding
     * @return array{code: ?string, system: ?string, display: ?string}
     */
    public function firstCoding(array $codingBlock): array
    {
        if (isset($codingBlock['coding'][0]) && is_array($codingBlock['coding'][0])) {
            $c = $codingBlock['coding'][0];

            return [
                'code' => isset($c['code']) ? (string) $c['code'] : null,
                'system' => isset($c['system']) ? (string) $c['system'] : null,
                'display' => isset($c['display']) ? (string) $c['display'] : null,
            ];
        }
        if (isset($codingBlock['code'])) {
            return [
                'code' => (string) $codingBlock['code'],
                'system' => isset($codingBlock['system']) ? (string) $codingBlock['system'] : null,
                'display' => isset($codingBlock['display']) ? (string) $codingBlock['display'] : null,
            ];
        }

        return ['code' => null, 'system' => null, 'display' => null];
    }
}
