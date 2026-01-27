<?php

namespace common\traits;

/**
 * Trait para definir preguntas de parámetros en modelos
 * 
 * Similar a attributeLabels(), permite definir preguntas que se hacen
 * al usuario cuando falta un parámetro en el chatbot.
 * 
 * @example
 * public function parameterQuestions()
 * {
 *     return [
 *         'fecha' => '¿Para qué día querés el turno?',
 *         'hora' => '¿En qué horario te gustaría?',
 *     ];
 * }
 */
trait ParameterQuestionsTrait
{
    /**
     * Retorna las preguntas asociadas a los parámetros de este modelo
     * 
     * @return array Array asociativo donde la clave es el nombre del parámetro
     *               y el valor es la pregunta a mostrar al usuario
     */
    public function parameterQuestions()
    {
        return [];
    }
    
    /**
     * Obtener pregunta para un parámetro específico
     * 
     * @param string $parameter Nombre del parámetro
     * @return string|null La pregunta o null si no existe
     */
    public function getParameterQuestion($parameter)
    {
        $questions = $this->parameterQuestions();
        return $questions[$parameter] ?? null;
    }
}
