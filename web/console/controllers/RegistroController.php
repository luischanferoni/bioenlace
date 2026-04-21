<?php

namespace console\controllers;

use common\components\CrearUsuarioDePruebaHelper;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Comandos relacionados con el registro de personas (consola).
 *
 * Uso:
 *   php yii registro/crear-usuario-de-prueba
 */
class RegistroController extends Controller
{
    /**
     * Crea persona + usuario de prueba una sola vez (falla si el documento o usuario ya existen).
     */
    public function actionCrearUsuarioDePrueba(): int
    {
        $this->stdout(
            'Creando usuario de prueba (documento ' . CrearUsuarioDePruebaHelper::DOCUMENTO . ")...\n",
            Console::FG_YELLOW
        );

        $result = CrearUsuarioDePruebaHelper::crear();

        if (!$result['ok']) {
            $this->stderr($result['message'] . "\n", Console::FG_RED);
            if (!empty($result['errors'])) {
                $this->stderr(print_r($result['errors'], true) . "\n", Console::FG_RED);
            }
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout($result['message'] . "\n", Console::FG_GREEN);
        $this->stdout('Persona: ' . json_encode($result['persona'], JSON_UNESCAPED_UNICODE) . "\n");
        $this->stdout('Usuario: ' . json_encode($result['user'], JSON_UNESCAPED_UNICODE) . "\n");

        return ExitCode::OK;
    }
}
