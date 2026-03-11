<?php

namespace console\controllers;

use Yii;
use yii\console\Controller;
use yii\helpers\Console;
use common\components\RegistroService;

/**
 * Comandos de utilidad relacionados con el flujo de registro API.
 *
 * Uso:
 *   php yii registro/simular-mercedes
 */
class RegistroController extends Controller
{
    /**
     * Simula la creación de una persona con datos fijos (Mercedes Diaz, DNI 29486884)
     * reutilizando el mismo servicio de registro que usa la API.
     *
     * Ejecuta el flujo completo (Verifik, Persona, MPI, REFEPS en caso de tipo=medico)
     * pero aquí se usa como paciente.
     *
     * @return int
     */
    public function actionSimularMercedes(): int
    {
        $this->stdout("Simulando registro de persona Mercedes Diaz (DNI 29486884)...\n", Console::FG_YELLOW);

        /** @var RegistroService $service */
        $service = Yii::$container->has(RegistroService::class)
            ? Yii::$container->get(RegistroService::class)
            : new RegistroService();

        $payload = [
            'tipo' => 'paciente',
            'dni' => '29486884',
            'nombre' => 'Mercedes',
            'apellido' => 'Diaz',
        ];

        try {
            $result = $service->registrar($payload);
        } catch (\RuntimeException $e) {
            $this->stderr("Error de negocio en el registro: {$e->getMessage()}\n", Console::FG_RED);
            return self::EXIT_CODE_ERROR;
        } catch (\Throwable $e) {
            $this->stderr("Error inesperado en el registro: {$e->getMessage()}\n", Console::FG_RED);
            return self::EXIT_CODE_ERROR;
        }

        $persona = $result['persona'] ?? [];
        $this->stdout("Registro simulado completado.\n", Console::FG_GREEN);
        $this->stdout("Persona ID: " . ($persona['id_persona'] ?? 'N/A') . "\n");
        $this->stdout("Nombre: " . ($persona['nombre'] ?? '') . " " . ($persona['apellido'] ?? '') . "\n");
        $this->stdout("DNI: " . ($persona['documento'] ?? '') . "\n");

        if (isset($result['verifik'])) {
            $this->stdout("Verifik status: " . ($result['verifik']['status'] ?? 'desconocido') . "\n");
        }
        if (isset($result['mpi'])) {
            $this->stdout("MPI empadronado: " . (!empty($result['mpi']['empadronado']) ? 'SI' : 'NO') . "\n");
        }

        return self::EXIT_CODE_NORMAL;
    }
}

