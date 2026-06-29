<?php

namespace common\components\Domain\Integrations\Mpi;

/**
 * Normaliza respuestas del endpoint MPI domicilio a fila plana para persistencia local.
 */
final class MpiDomicilioNormalizer
{
    /**
     * @param array<string, mixed>|null $response
     * @return array<string, mixed>|null
     */
    public static function normalizeResponse(?array $response): ?array
    {
        if ($response === null || $response === []) {
            return null;
        }

        if (!self::isSuccessfulResponse($response)) {
            return null;
        }

        $data = $response['data'] ?? null;
        if ($data === null) {
            return null;
        }

        if (isset($data['paciente']) && is_array($data['paciente'])) {
            return self::fromPacienteNode($data['paciente']);
        }

        if (is_array($data) && array_is_list($data)) {
            foreach ($data as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $normalized = self::fromRow($row);
                if ($normalized !== null) {
                    return $normalized;
                }
            }

            return null;
        }

        if (is_array($data)) {
            return self::fromRow($data);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $response
     */
    private static function isSuccessfulResponse(array $response): bool
    {
        if (($response['successful'] ?? 0) == 1 && ($response['statusCode'] ?? 0) == 200) {
            return true;
        }

        return isset($response['data']) && is_array($response['data']);
    }

    /**
     * @param array<string, mixed> $paciente
     * @return array<string, mixed>|null
     */
    private static function fromPacienteNode(array $paciente): ?array
    {
        $residencia = $paciente['set_ampliado']['residencia'] ?? null;
        if (!is_array($residencia)) {
            return null;
        }

        return self::fromResidenciaNode($residencia);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private static function fromRow(array $row): ?array
    {
        if (isset($row['paciente']) && is_array($row['paciente'])) {
            return self::fromPacienteNode($row['paciente']);
        }

        if (isset($row['residencia']) && is_array($row['residencia'])) {
            return self::fromResidenciaNode($row['residencia']);
        }

        if (self::hasFlatDomicilioFields($row)) {
            return $row;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $residencia
     * @return array<string, mixed>|null
     */
    private static function fromResidenciaNode(array $residencia): ?array
    {
        $provincia = is_array($residencia['provincia'] ?? null) ? $residencia['provincia'] : [];
        $localidad = is_array($residencia['localidad'] ?? null) ? $residencia['localidad'] : [];
        $departamento = is_array($residencia['departamento'] ?? null) ? $residencia['departamento'] : [];

        $normalized = [
            'id_provincia' => trim((string) ($provincia['id'] ?? '')),
            'provincia' => trim((string) ($provincia['texto'] ?? '')),
            'localidad' => trim((string) ($localidad['texto'] ?? '')),
            'municipio' => trim((string) ($departamento['texto'] ?? '')),
            'calle' => trim((string) ($residencia['calle'] ?? '')),
            'numero' => trim((string) ($residencia['numero'] ?? '')),
            'barrio' => trim((string) ($residencia['barrio'] ?? '')),
        ];

        return self::hasFlatDomicilioFields($normalized) ? $normalized : null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hasFlatDomicilioFields(array $row): bool
    {
        $provincia = trim((string) ($row['id_provincia'] ?? $row['cod_provincia'] ?? $row['provincia'] ?? $row['provincia_nombre'] ?? ''));
        $calle = trim((string) ($row['calle'] ?? $row['direccion'] ?? ''));
        $localidad = trim((string) ($row['localidad'] ?? $row['ciudad'] ?? $row['municipio'] ?? ''));

        return $provincia !== '' || $calle !== '' || $localidad !== '';
    }
}
