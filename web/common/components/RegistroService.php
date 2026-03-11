<?php

namespace common\components;

use Yii;
use common\models\Persona;
use common\models\PersonaMpi;

/**
 * Servicio de orquestación para el registro de pacientes y médicos.
 *
 * Se encarga de:
 *  - Verificar identidad con Verifik.
 *  - Crear o actualizar registros en la tabla personas.
 *  - Sincronizar información mínima con MPI (id_mpi).
 *  - Validar profesionales contra REFEPS/SISA cuando el tipo es "medico".
 */
class RegistroService
{
    /**
     * Ejecuta el flujo de registro unificado.
     *
     * @param array $bodyParams Datos recibidos desde la API (tipo, dni, nombre, apellido, etc.).
     * @return array Estructura:
     *               [
     *                 'persona' => [...],
     *                 'verifik' => [...],
     *                 'mpi' => [...],
     *                 'refeps' => [...|null],
     *               ]
     *
     * @throws \RuntimeException En caso de error bloqueante (por ejemplo, rechazo de Verifik o problemas de guardado).
     */
    public function registrar(array $bodyParams): array
    {
        $tipo = $bodyParams['tipo'] ?? null;
        $dni = $bodyParams['dni'] ?? null;
        $nombre = $bodyParams['nombre'] ?? null;
        $apellido = $bodyParams['apellido'] ?? null;

        if (!$tipo || !in_array($tipo, ['paciente', 'medico'], true)) {
            throw new \RuntimeException('El campo "tipo" es requerido y debe ser "paciente" o "medico".');
        }

        if (!$dni || !$nombre || !$apellido) {
            throw new \RuntimeException('Los campos "dni", "nombre" y "apellido" son requeridos.');
        }

        /** @var VerifikClient $verifik */
        $verifik = Yii::$container->has(VerifikClient::class)
            ? Yii::$container->get(VerifikClient::class)
            : new VerifikClient();

        $verifikResult = $verifik->verifyDni(
            $dni,
            $nombre,
            $apellido,
            [
                'context' => [
                    'tipo' => $tipo,
                    'source' => 'api_registro',
                ],
            ]
        );

        if ($verifikResult['success'] === false && $verifikResult['status'] === 'rechazado') {
            throw new \RuntimeException('Verificación de identidad rechazada por Verifik');
        }

        // Crear o actualizar persona.
        $persona = Persona::findOne(['documento' => $dni]);
        $esNueva = false;

        if ($persona === null) {
            $persona = new Persona();
            $esNueva = true;
        }

        $persona->nombre = $nombre;
        $persona->apellido = $apellido;
        $persona->documento = $dni;

        if (!empty($bodyParams['fecha_nacimiento'])) {
            $persona->fecha_nacimiento = $bodyParams['fecha_nacimiento'];
        }

        if (property_exists($persona, 'email') && !empty($bodyParams['email'])) {
            $persona->email = $bodyParams['email'];
        }
        if (property_exists($persona, 'telefono') && !empty($bodyParams['telefono'])) {
            $persona->telefono = $bodyParams['telefono'];
        }

        if (!$persona->save()) {
            throw new \RuntimeException('Error guardando datos de la persona: ' . json_encode($persona->getErrors()));
        }

        $mpiInfo = $this->syncMpiPersona($persona);

        $refepsInfo = null;
        if ($tipo === 'medico') {
            $refepsInfo = $this->verifyRefEpsMedico($persona);
            if ($refepsInfo['es_profesional'] !== true) {
                throw new \RuntimeException('El DNI no corresponde a un profesional de la salud registrado en REFEPS/SISA');
            }
        }

        $personaData = [
            'id_persona' => $persona->id_persona,
            'nombre' => $persona->nombre,
            'apellido' => $persona->apellido,
            'documento' => $persona->documento,
            'fecha_nacimiento' => $persona->fecha_nacimiento,
            'tipo' => $tipo,
            'es_nueva' => $esNueva,
        ];

        return [
            'persona' => $personaData,
            'verifik' => $verifikResult,
            'mpi' => $mpiInfo,
            'refeps' => $refepsInfo,
        ];
    }

    /**
     * Sincroniza información mínima de la persona con el MPI (id_mpi) si el componente está disponible.
     *
     * @param Persona $persona
     * @return array
     */
    protected function syncMpiPersona(Persona $persona): array
    {
        if (!Yii::$app->has('mpi')) {
            return [
                'empadronado' => false,
                'detalles' => [
                    'message' => 'Componente MPI no configurado en la aplicación',
                ],
            ];
        }

        try {
            /** @var \frontend\components\Mpi $mpi */
            $mpi = Yii::$app->mpi;

            $respuesta = $mpi->traerPaciente($persona->id_persona);
            $idMpi = $respuesta['data']['paciente']['set_minimo']['identificador']['mpi'] ?? null;

            $empadronado = false;

            if ($idMpi) {
                /** @var PersonaMpi|null $personaMpi */
                $personaMpi = PersonaMpi::findOne($persona->id_persona);
                if ($personaMpi) {
                    $personaMpi->id_mpi = $idMpi;
                    $personaMpi->save(false);
                    $empadronado = true;
                }
            }

            return [
                'empadronado' => $empadronado,
                'detalles' => $respuesta,
            ];
        } catch (\Throwable $e) {
            Yii::error('Error sincronizando persona con MPI: ' . $e->getMessage(), 'mpi');
            return [
                'empadronado' => false,
                'detalles' => [
                    'message' => 'Error al comunicarse con MPI',
                    'exception' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Verifica en REFEPS/SISA si la persona es un profesional de la salud registrado.
     *
     * @param Persona $persona
     * @return array
     */
    protected function verifyRefEpsMedico(Persona $persona): array
    {
        if (!Yii::$app->has('sisa')) {
            return [
                'es_profesional' => null,
                'detalles' => [
                    'message' => 'Componente SISA/REFEPS no configurado en la aplicación',
                ],
            ];
        }

        try {
            $dni = $persona->documento;

            $nombreCompleto = $persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON);
            $apellidoCompleto = $persona->apellido;
            $nombreSolo = $persona->nombre;

            if (strpos($nombreCompleto, ',') !== false) {
                [$apellidoCompletoFromFormat, $nombreSoloFromFormat] = explode(',', $nombreCompleto, 2);
                $apellidoCompleto = trim($apellidoCompletoFromFormat);
                $nombreSolo = trim($nombreSoloFromFormat);
            }

            /** @var \frontend\components\apis\Sisa $sisa */
            $sisa = Yii::$app->sisa;

            $rawResponse = $sisa->getProfesionalesDeSantiago($apellidoCompleto, $nombreSolo, '', $dni);

            $decoded = json_decode($rawResponse, true);

            $esProfesional = false;

            if (is_array($decoded)) {
                if (isset($decoded['ok']) && $decoded['ok'] === true) {
                    $esProfesional = true;
                } elseif (isset($decoded['total']) && (int) $decoded['total'] > 0) {
                    $esProfesional = true;
                } elseif (isset($decoded['profesionales']) && is_array($decoded['profesionales']) && count($decoded['profesionales']) > 0) {
                    $esProfesional = true;
                }
            }

            return [
                'es_profesional' => $esProfesional,
                'detalles' => $decoded ?? $rawResponse,
            ];
        } catch (\Throwable $e) {
            Yii::error('Error verificando profesional en REFEPS/SISA: ' . $e->getMessage(), 'refeps');
            return [
                'es_profesional' => false,
                'detalles' => [
                    'message' => 'Error al comunicarse con REFEPS/SISA',
                    'exception' => $e->getMessage(),
                ],
            ];
        }
    }
}

