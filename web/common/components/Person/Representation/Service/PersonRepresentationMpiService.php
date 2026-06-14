<?php

namespace common\components\Person\Representation\Service;

use common\models\Person\Persona;
use common\models\PersonaMpi;
use Yii;

/**
 * Resolución/alta de Persona sujeto vía local + RENAPER/MPI (patrón admin/MPI).
 */
final class PersonRepresentationMpiService
{
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

    public function syncMpiIfAvailable(Persona $persona): void
    {
        if (!Yii::$app->has('mpi')) {
            return;
        }

        try {
            $mpi = Yii::$app->mpi;
            $respuesta = $mpi->traerPaciente($persona->id_persona);
            $idMpi = $respuesta['data']['paciente']['set_minimo']['identificador']['mpi'] ?? null;
            if (!$idMpi) {
                return;
            }
            $personaMpi = PersonaMpi::findOne($persona->id_persona);
            if ($personaMpi === null) {
                $personaMpi = new PersonaMpi();
                $personaMpi->id_persona = (int) $persona->id_persona;
            }
            $personaMpi->id_mpi = $idMpi;
            $personaMpi->save(false);
        } catch (\Throwable $e) {
            Yii::error('MPI sync representación: ' . $e->getMessage(), 'person_representation');
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|null
     */
    private function fetchRenaper(string $documento, array $input): ?array
    {
        if (!Yii::$app->has('mpi')) {
            return null;
        }

        $sexo = trim((string) ($input['sexo'] ?? $input['sexo_biologico'] ?? ''));
        if ($sexo === '') {
            return null;
        }

        try {
            $mpi = Yii::$app->mpi;
            $respuesta = $mpi->caller('renaper?dni=' . rawurlencode($documento) . '&sexo=' . rawurlencode($sexo), '{}');
            if (!is_array($respuesta)) {
                return null;
            }
            $row = $respuesta['data'][0] ?? $respuesta['data'] ?? null;
            if (!is_array($row) || empty($row['apellido'])) {
                return null;
            }

            return $row;
        } catch (\Throwable $e) {
            Yii::error('RENAPER representación: ' . $e->getMessage(), 'person_representation');

            return null;
        }
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

        $this->syncMpiIfAvailable($persona);

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

        $this->syncMpiIfAvailable($persona);

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
