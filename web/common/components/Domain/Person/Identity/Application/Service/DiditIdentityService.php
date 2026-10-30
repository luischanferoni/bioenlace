<?php

namespace common\components\Domain\Person\Identity\Application\Service;

use common\components\Domain\Person\FrontDesk\Infrastructure\External\Identity\Connector\DiditClient;
use common\models\Person\Persona;
use Yii;

/**
 * Resuelve la identidad verificada por Didit y sincroniza la Persona asociada.
 *
 * Paso compartido por los flujos de registro (paciente, médico, personal de salud):
 * consulta la verificación, normaliza los datos y crea/actualiza la Persona con
 * identidad acreditada. No crea usuario, rol ni contexto; eso queda en cada UseCase.
 */
final class DiditIdentityService
{
    /**
     * @param array<string, mixed> $extras campos opcionales de contacto a conservar (email, telefono)
     * @return array{persona: Persona, es_nueva: bool, didit: array<string, mixed>, verification_id: string}
     */
    public function resolveAndUpsertPersona(
        string $verificationId,
        ?string $fechaNacimientoFallback = null,
        array $extras = []
    ): array {
        /** @var DiditClient $didit */
        $didit = Yii::$container->has(DiditClient::class)
            ? Yii::$container->get(DiditClient::class)
            : new DiditClient();

        $diditResult = $didit->getIdentityVerification($verificationId);

        if ($diditResult['success'] === false || $diditResult['status'] === 'rejected') {
            $detalle = trim((string) ($diditResult['message'] ?? ''));
            $estado = trim((string) ($diditResult['status'] ?? 'unknown'));
            throw new \RuntimeException(
                'Verificación de identidad rechazada por Didit'
                . ($estado !== '' ? ' (estado: ' . $estado . ')' : '')
                . ($detalle !== '' ? ': ' . $detalle : '')
            );
        }

        $dni = $this->normalizeDocumento((string) $diditResult['documento']);
        $nombre = $this->normalizePersonName((string) $diditResult['nombre']);
        $apellido = $this->normalizePersonName((string) $diditResult['apellido']);

        if ($dni === '' || $nombre === '' || $apellido === '') {
            throw new \RuntimeException('La respuesta de Didit no contiene datos mínimos de identidad (dni, nombre, apellido).');
        }

        $fechaNacimiento = $this->normalizeBirthDate($diditResult['fecha_nacimiento'] ?? null)
            ?? $this->normalizeBirthDate($fechaNacimientoFallback);
        if ($fechaNacimiento === null || $fechaNacimiento === '') {
            throw new \RuntimeException('Didit no devolvió fecha de nacimiento; no se puede completar el registro.');
        }

        $sexoBiologico = (int) ($diditResult['sexo_biologico'] ?? 0);
        if (!in_array($sexoBiologico, [1, 2], true)) {
            throw new \RuntimeException('Didit no devolvió sexo biológico válido; no se puede completar el registro.');
        }

        // Crear o actualizar persona en base a los datos devueltos por Didit.
        $persona = Persona::findOne(['documento' => $dni]);
        $esNueva = false;

        if ($persona === null) {
            $persona = new Persona();
            $esNueva = true;
        }

        $persona->scenario = Persona::SCENARIOCREATEUPDATE;
        $persona->nombre = $nombre;
        $persona->apellido = $apellido;
        $persona->documento = $dni;
        $persona->documento_propio = 1;
        $persona->acredita_identidad = 1;
        $persona->fecha_nacimiento = $fechaNacimiento;
        $persona->id_tipodoc = $diditResult['id_tipodoc'] ?? $persona->id_tipodoc ?? 1;
        $persona->id_estado_civil = $diditResult['id_estado_civil'] ?? $persona->id_estado_civil ?? 1;
        $genero = (int) ($diditResult['genero'] ?? 0);
        $persona->genero = in_array($genero, [1, 2], true) ? $genero : $sexoBiologico;
        $persona->sexo_biologico = $sexoBiologico;
        $persona->sexo = $sexoBiologico === 2 ? 'M' : 'F';
        // Fuera del escenario de alta pero con validación global de letras: no arrastrar basura de BD.
        $persona->otro_nombre = null;
        $persona->otro_apellido = null;
        $persona->apellido_paterno = null;
        $persona->apellido_materno = null;

        if (property_exists($persona, 'email') && !empty($extras['email'])) {
            $persona->email = $extras['email'];
        }
        if (property_exists($persona, 'telefono') && !empty($extras['telefono'])) {
            $persona->telefono = $extras['telefono'];
        }

        if (property_exists($persona, 'didit_reference_id') && !empty($diditResult['didit_reference_id'])) {
            $persona->didit_reference_id = $diditResult['didit_reference_id'];
        }
        if (property_exists($persona, 'didit_last_kyc_verification_id')) {
            $persona->didit_last_kyc_verification_id = $diditResult['verification_id'] ?? $verificationId;
        }

        if (!$persona->save()) {
            Yii::warning('Registro Didit: validación Persona: ' . json_encode($persona->getErrors()), 'registro');
            throw new \RuntimeException(
                'Error guardando datos de la persona: ' . json_encode($persona->getErrors(), JSON_UNESCAPED_UNICODE)
            );
        }

        return [
            'persona' => $persona,
            'es_nueva' => $esNueva,
            'didit' => $diditResult,
            'verification_id' => $verificationId,
        ];
    }

    private function normalizeDocumento(string $documento): string
    {
        return preg_replace('/\D+/', '', $documento) ?? '';
    }

    private function normalizePersonName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($name === '') {
            return '';
        }

        return trim(preg_replace('/[^A-ZÁÉÍÓÚÑa-záéíóúñ\s]/u', '', $name) ?? '');
    }

    private function normalizeBirthDate($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches)) {
            return $matches[1];
        }

        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $value, $matches)) {
            return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }

        if (preg_match('/^\d{6}$/', $value)) {
            $yy = (int) substr($value, 0, 2);
            $mm = substr($value, 2, 2);
            $dd = substr($value, 4, 2);
            $year = $yy > 30 ? 1900 + $yy : 2000 + $yy;

            return sprintf('%04d-%s-%s', $year, $mm, $dd);
        }

        return $value;
    }
}
