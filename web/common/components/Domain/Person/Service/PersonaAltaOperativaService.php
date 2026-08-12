<?php

namespace common\components\Domain\Person\Service;

use common\models\Person\Persona;

/**
 * Alta operativa mínima de Persona (admisión / guardia): no KYC ni MPI.
 * Si el documento ya existe, reusa esa fila.
 */
final class PersonaAltaOperativaService
{
    /**
     * @param array<string, mixed> $input
     * @return array{
     *   apellido: string,
     *   nombre: string,
     *   documento: string,
     *   fecha_nacimiento: string,
     *   sexo_biologico: int
     * }
     */
    public function normalize(array $input): array
    {
        $apellido = $this->soloLetras(trim((string) ($input['apellido'] ?? '')));
        $nombre = $this->soloLetras(trim((string) ($input['nombre'] ?? '')));
        $documento = preg_replace('/\D+/', '', (string) ($input['documento'] ?? '')) ?? '';
        $fecha = $this->normalizeDate((string) ($input['fecha_nacimiento'] ?? ''));
        $sexo = $this->normalizeSexo($input['sexo_biologico'] ?? $input['sexo'] ?? null);

        if ($apellido === '' || $nombre === '' || $documento === '' || $fecha === '' || $sexo === null) {
            throw new \InvalidArgumentException(
                'Para registrar un paciente nuevo hace falta apellido, nombre, documento, fecha de nacimiento y sexo.'
            );
        }
        if (strlen($documento) > 8) {
            throw new \InvalidArgumentException('El documento no puede tener más de 8 dígitos.');
        }

        return [
            'apellido' => $apellido,
            'nombre' => $nombre,
            'documento' => $documento,
            'fecha_nacimiento' => $fecha,
            'sexo_biologico' => $sexo,
        ];
    }

    /**
     * True si el body trae datos suficientes para un alta (aunque falte algún campo:
     * normalize() valida el resto).
     *
     * @param array<string, mixed> $input
     */
    public function pareceAlta(array $input): bool
    {
        $apellido = trim((string) ($input['apellido'] ?? ''));
        $nombre = trim((string) ($input['nombre'] ?? ''));
        $documento = trim((string) ($input['documento'] ?? ''));

        return $apellido !== '' || $nombre !== '' || $documento !== '';
    }

    public function crearOReusar(array $input): Persona
    {
        $n = $this->normalize($input);
        $existente = Persona::findOne(['documento' => $n['documento']]);
        if ($existente !== null) {
            return $existente;
        }

        $persona = new Persona();
        $persona->scenario = Persona::SCENARIOCREATEUPDATE;
        $persona->apellido = $n['apellido'];
        $persona->nombre = $n['nombre'];
        $persona->documento = $n['documento'];
        $persona->fecha_nacimiento = $n['fecha_nacimiento'];
        $persona->sexo_biologico = $n['sexo_biologico'];
        $persona->genero = $n['sexo_biologico'];
        $persona->id_tipodoc = 1;
        $persona->id_estado_civil = 1;
        $persona->acredita_identidad = 0;

        if (!$persona->save()) {
            throw new \InvalidArgumentException(
                'No se pudo registrar la persona: ' . json_encode($persona->getErrors(), JSON_UNESCAPED_UNICODE)
            );
        }

        return $persona;
    }

    private function soloLetras(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return $value;
    }

    private function normalizeDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m) === 1) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        return '';
    }

    /**
     * 1 = Femenino, 2 = Masculino (mismo criterio que Persona::getSexoLetra / _set_minimo).
     */
    private function normalizeSexo(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_int($raw) || (is_string($raw) && ctype_digit($raw))) {
            $n = (int) $raw;
            if ($n === 1 || $n === 2) {
                return $n;
            }

            return null;
        }
        $s = strtoupper(trim((string) $raw));
        if ($s === 'F' || $s === 'FEMENINO') {
            return 1;
        }
        if ($s === 'M' || $s === 'MASCULINO') {
            return 2;
        }

        return null;
    }
}
