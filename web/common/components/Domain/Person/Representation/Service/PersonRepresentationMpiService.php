<?php

namespace common\components\Domain\Person\Representation\Service;

use common\components\Domain\Integrations\Mpi\RenaperGatewayService;
use common\models\Person\Persona;

/**
 * Resolución/alta de Persona sujeto vía local + RENAPER.
 */
final class PersonRepresentationMpiService
{
    public function __construct(
        private readonly RenaperGatewayService $renaper = new RenaperGatewayService(),
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public function resolveOrCreateSubject(array $input): Persona
    {
        $idPersona = isset($input['id_persona']) ? (int) $input['id_persona'] : 0;
        if ($idPersona > 0) {
            $persona = Persona::findOne($idPersona);
            if ($persona === null) {
                throw new \InvalidArgumentException('No existe la persona indicada.');
            }

            return $persona;
        }

        $documento = trim((string) ($input['documento'] ?? ''));
        if ($documento === '') {
            throw new \InvalidArgumentException('Indicá id_persona o documento del menor.');
        }

        $persona = Persona::findOne(['documento' => $documento]);
        if ($persona !== null) {
            return $persona;
        }

        $renaper = $this->fetchRenaper($documento, $input);
        if ($renaper !== null) {
            return $this->createPersonaFromRenaper($documento, $renaper);
        }

        return $this->createPersonaFromManualInput($documento, $input);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|null
     */
    private function fetchRenaper(string $documento, array $input): ?array
    {
        $sexo = $this->resolveSexoParam($input);
        if ($sexo === '') {
            return null;
        }

        return $this->renaper->fetch($documento, $sexo);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function resolveSexoParam(array $input): string
    {
        $raw = $input['sexo'] ?? $input['sexo_biologico'] ?? '';
        if (is_int($raw) || (is_string($raw) && ctype_digit($raw))) {
            $n = (int) $raw;
            if ($n === 1) {
                return 'M';
            }
            if ($n === 2) {
                return 'F';
            }
        }
        $sexo = strtoupper(trim((string) $raw));
        if ($sexo === 'M' || $sexo === 'F') {
            return $sexo;
        }

        return '';
    }

    /**
     * @param array<string, mixed> $renaper
     */
    private function createPersonaFromRenaper(string $documento, array $renaper): Persona
    {
        $persona = new Persona();
        $persona->scenario = Persona::SCENARIOCREATEUPDATE;
        $persona->documento = $documento;
        $persona->nombre = $this->firstToken((string) ($renaper['nombres'] ?? ''));
        $persona->apellido = $this->firstToken((string) ($renaper['apellido'] ?? ''));
        $persona->fecha_nacimiento = $this->normalizeDate((string) ($renaper['fecha_nacimiento'] ?? ''));
        $persona->id_tipodoc = 1;
        $persona->id_estado_civil = 1;
        $persona->acredita_identidad = 1;
        $persona->sexo_biologico = $this->mapSexoBiologico($renaper);
        $persona->genero = $persona->sexo_biologico;

        if (!$persona->save()) {
            throw new \RuntimeException('No se pudo crear la persona: ' . json_encode($persona->getErrors()));
        }

        return $persona;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function createPersonaFromManualInput(string $documento, array $input): Persona
    {
        $nombre = trim((string) ($input['nombre'] ?? ''));
        $apellido = trim((string) ($input['apellido'] ?? ''));
        $fechaNac = $this->normalizeDate((string) ($input['fecha_nacimiento'] ?? ''));
        if ($nombre === '' || $apellido === '' || $fechaNac === '') {
            throw new \InvalidArgumentException(
                'Si RENAPER no está disponible, enviá nombre, apellido y fecha_nacimiento del menor.'
            );
        }

        $persona = new Persona();
        $persona->scenario = Persona::SCENARIOCREATEUPDATE;
        $persona->documento = $documento;
        $persona->nombre = $nombre;
        $persona->apellido = $apellido;
        $persona->fecha_nacimiento = $fechaNac;
        $persona->id_tipodoc = isset($input['id_tipodoc']) ? (int) $input['id_tipodoc'] : 1;
        $persona->id_estado_civil = 1;
        $persona->acredita_identidad = 0;
        if (isset($input['sexo_biologico'])) {
            $persona->sexo_biologico = (int) $input['sexo_biologico'];
            $persona->genero = (int) $input['sexo_biologico'];
        }

        if (!$persona->save()) {
            throw new \RuntimeException('No se pudo crear la persona: ' . json_encode($persona->getErrors()));
        }

        return $persona;
    }

    private function firstToken(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $parts = preg_split('/\s+/', $value);

        return trim((string) ($parts[0] ?? $value));
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

        return $value;
    }

    /**
     * @param array<string, mixed> $renaper
     */
    private function mapSexoBiologico(array $renaper): int
    {
        $sexo = strtoupper(trim((string) ($renaper['sexo'] ?? $renaper['genero'] ?? '')));
        if ($sexo === 'M' || $sexo === '1' || $sexo === 'MASCULINO') {
            return 1;
        }
        if ($sexo === 'F' || $sexo === '2' || $sexo === 'FEMENINO') {
            return 2;
        }

        return 0;
    }
}
