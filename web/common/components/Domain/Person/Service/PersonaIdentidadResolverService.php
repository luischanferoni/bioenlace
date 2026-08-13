<?php

namespace common\components\Domain\Person\Service;

/**
 * Identidad definitiva (conocido / DNI / Didit). Sin NN ni ficha tipeada.
 */
final class PersonaIdentidadResolverService
{
    /**
     * @param array<string, mixed> $body
     */
    public function resolver(array $body): int
    {
        $idPersona = (int) ($body['id_persona'] ?? 0);
        if ($idPersona > 0) {
            return $idPersona;
        }

        if (self::pareceIdentidadDidit($body)) {
            return $this->idPersonaDesdeRegistro([
                'modo' => 'didit',
                'verification_id' => trim((string) ($body['verification_id'] ?? '')),
            ], 'No se pudo resolver la persona desde Didit.');
        }

        if (self::pareceIdentidadDni($body)) {
            return $this->idPersonaDesdeRegistro([
                'modo' => 'dni_lector',
                'codigo_barras' => trim((string) ($body['codigo_barras'] ?? '')),
                'documento' => (string) ($body['documento'] ?? ''),
                'sexo_biologico' => (int) ($body['sexo_biologico'] ?? 0),
            ], 'No se pudo resolver la persona desde el DNI.');
        }

        throw new \InvalidArgumentException(
            'Elegí un paciente de la búsqueda o identificá uno con DNI (documento y sexo, o código de barras) o con foto del DNI (Didit).'
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function pareceIdentidadDidit(array $body): bool
    {
        return trim((string) ($body['verification_id'] ?? '')) !== '';
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function pareceIdentidadDni(array $body): bool
    {
        if (trim((string) ($body['codigo_barras'] ?? '')) !== '') {
            return true;
        }
        $documento = preg_replace('/\D+/', '', (string) ($body['documento'] ?? '')) ?? '';
        $sexo = (int) ($body['sexo_biologico'] ?? 0);

        return $documento !== '' && in_array($sexo, [1, 2], true);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function idPersonaDesdeRegistro(array $params, string $emptyMsg): int
    {
        try {
            $result = (new RegistroStaffPacienteService())->registrar($params);
        } catch (\RuntimeException $e) {
            throw new \InvalidArgumentException($e->getMessage(), 0, $e);
        }

        $id = (int) ($result['persona']['id_persona'] ?? 0);
        if ($id <= 0) {
            throw new \InvalidArgumentException($emptyMsg);
        }

        return $id;
    }
}
