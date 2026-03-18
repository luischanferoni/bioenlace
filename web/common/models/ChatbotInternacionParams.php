<?php

namespace common\models;

use common\traits\ParameterQuestionsTrait;

/**
 * Contenedor liviano de preguntas para parámetros de internación.
 *
 * No es ActiveRecord: se usa únicamente por `ParameterQuestionRegistry`
 * para construir preguntas cuando faltan parámetros en intents operativos.
 */
final class ChatbotInternacionParams
{
    use ParameterQuestionsTrait;

    public function parameterQuestions()
    {
        return [
            'id_persona' => '¿De qué paciente (ID) se trata?',
            'id_internacion' => '¿Cuál es el ID de la internación?',
            'id_cama' => '¿A qué cama querés asignarlo/a? (ID de cama)',

            'motivo' => '¿Cuál es el motivo/observación?',
            'tipo_alta' => '¿Qué tipo de alta corresponde?',

            'diagnostico' => '¿Qué diagnóstico querés registrar?',
            'practica' => '¿Qué práctica/procedimiento querés registrar?',

            'medicamento' => '¿Qué medicación querés indicar?',
            'dosis' => '¿Qué dosis?',
            'frecuencia' => '¿Con qué frecuencia?',
            'via' => '¿Por qué vía?',
            'observaciones' => '¿Alguna observación?',
        ];
    }
}

