<?php

namespace common\components\Domain\Person\Service;

use common\components\Domain\Integrations\Identity\DiditClient;
use common\components\Domain\Integrations\Mpi\RenaperGatewayService;
use common\models\Person\Persona;
use Yii;

/**
 * Alta de paciente por personal (admin web): lector DNI o Didit, sin MPI.
 */
final class RegistroStaffPacienteService
{
    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function registrar(array $params): array
    {
        $modo = strtolower(trim((string) ($params['modo'] ?? '')));
        if ($modo === 'didit') {
            return $this->registrarDesdeDidit($params);
        }
        if ($modo === 'dni_lector') {
            return $this->registrarDesdeDniLector($params);
        }

        throw new \InvalidArgumentException('modo inválido: use didit o dni_lector.');
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function previewRenaper(array $params): array
    {
        $identity = $this->resolveIdentityInput($params);
        if (!empty($params['codigo_barras'])) {
            $identity['codigo_barras'] = trim((string) $params['codigo_barras']);
        }
        $row = (new RenaperGatewayService())->fetch(
            $identity['documento'],
            $identity['sexo_letra']
        );
        if ($row === null) {
            return [
                'encontrado' => false,
                'mensaje' => 'No se encontró la persona en RENAPER.',
            ];
        }

        return [
            'encontrado' => true,
            'renaper' => $row,
            'identity' => $identity,
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function registrarDesdeDidit(array $params): array
    {
        $verificationId = trim((string) ($params['verification_id'] ?? ''));
        if ($verificationId === '') {
            throw new \InvalidArgumentException('verification_id es requerido para modo didit.');
        }

        $registro = new RegistroService();
        $result = $registro->registrar(array_merge($params, [
            'tipo' => 'paciente',
            'verification_id' => $verificationId,
        ]));
        unset($result['token']);

        return $result;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function registrarDesdeDniLector(array $params): array
    {
        $identity = $this->resolveIdentityInput($params);
        $renaper = (new RenaperGatewayService())->fetch(
            $identity['documento'],
            $identity['sexo_letra']
        );

        $persona = Persona::findOne(['documento' => $identity['documento']]);
        $esNueva = $persona === null;
        if ($persona === null) {
            $persona = new Persona();
        }

        $persona->scenario = Persona::SCENARIOCREATEUPDATE;
        $persona->documento = $identity['documento'];
        $persona->sexo_biologico = $identity['sexo_biologico'];
        $persona->sexo = $identity['sexo_letra'];
        $persona->acredita_identidad = 1;
        $persona->id_tipodoc = (int) ($params['id_tipodoc'] ?? 1);
        $persona->id_estado_civil = (int) ($params['id_estado_civil'] ?? 1);
        $persona->genero = (int) ($params['genero'] ?? $identity['sexo_biologico']);

        $this->aplicarDatosRenaper($persona, $renaper, $identity);

        if (!$persona->save()) {
            throw new \RuntimeException('Error guardando persona: ' . json_encode($persona->getErrors()));
        }

        $result = (new RegistroService())->finalizarAltaPaciente(
            $persona,
            $params,
            'paciente',
            $esNueva,
            null,
            false
        );
        $result['renaper'] = $renaper;
        $result['modo'] = 'dni_lector';

        return $result;
    }

    /**
     * @param array<string, mixed> $params
     * @return array{documento: string, sexo_biologico: int, sexo_letra: string, apellido?: string, nombre?: string, fecha_nacimiento?: string}
     */
    private function resolveIdentityInput(array $params): array
    {
        $codigo = trim((string) ($params['codigo_barras'] ?? ''));
        if ($codigo !== '') {
            $parsed = (new DniBarcodeParserService())->parse($codigo);
            if ($parsed === null) {
                throw new \InvalidArgumentException('Código de barras del DNI no reconocido.');
            }

            return $parsed;
        }

        $documento = preg_replace('/\D/', '', (string) ($params['documento'] ?? '')) ?? '';
        $sexoBiologico = (int) ($params['sexo_biologico'] ?? 0);
        if ($documento === '' || !in_array($sexoBiologico, [1, 2], true)) {
            throw new \InvalidArgumentException('documento y sexo_biologico (1=F, 2=M) son requeridos.');
        }

        return [
            'documento' => $documento,
            'sexo_biologico' => $sexoBiologico,
            'sexo_letra' => $sexoBiologico === 1 ? 'F' : 'M',
            'apellido' => trim((string) ($params['apellido'] ?? '')) ?: null,
            'nombre' => trim((string) ($params['nombre'] ?? '')) ?: null,
            'fecha_nacimiento' => trim((string) ($params['fecha_nacimiento'] ?? '')) ?: null,
        ];
    }

    /**
     * @param array<string, mixed>|null $renaper
     * @param array<string, mixed> $identity
     */
    private function aplicarDatosRenaper(Persona $persona, ?array $renaper, array $identity): void
    {
        if (is_array($renaper)) {
            if (!empty($renaper['nombres'])) {
                $persona->nombre = is_array($renaper['nombres'])
                    ? trim((string) ($renaper['nombres'][0] ?? ''))
                    : trim((string) $renaper['nombres']);
            }
            if (!empty($renaper['apellido'])) {
                $apellidos = is_array($renaper['apellido']) ? $renaper['apellido'] : [$renaper['apellido']];
                $persona->apellido = trim((string) ($apellidos[0] ?? ''));
                if (isset($apellidos[1]) && trim((string) $apellidos[1]) !== '') {
                    $persona->otro_apellido = trim((string) $apellidos[1]);
                }
            }
            if (!empty($renaper['fechaNacimiento'])) {
                $persona->fecha_nacimiento = (string) $renaper['fechaNacimiento'];
            } elseif (!empty($renaper['fecha_nacimiento'])) {
                $persona->fecha_nacimiento = (string) $renaper['fecha_nacimiento'];
            }
            if (!empty($renaper['numeroDocumento'])) {
                $persona->documento = (string) $renaper['numeroDocumento'];
            }
        }

        if (empty($persona->nombre) && !empty($identity['nombre'])) {
            $persona->nombre = (string) $identity['nombre'];
        }
        if (empty($persona->apellido) && !empty($identity['apellido'])) {
            $persona->apellido = (string) $identity['apellido'];
        }
        if (empty($persona->fecha_nacimiento) && !empty($identity['fecha_nacimiento'])) {
            $persona->fecha_nacimiento = (string) $identity['fecha_nacimiento'];
        }

        if (empty($persona->nombre) || empty($persona->apellido)) {
            throw new \RuntimeException('No se pudieron obtener nombre y apellido desde el DNI ni RENAPER.');
        }
    }
}
