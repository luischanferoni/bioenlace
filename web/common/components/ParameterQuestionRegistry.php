<?php

namespace common\components;

use Yii;
use common\models\Turno;
use common\models\Servicio;
use common\models\Efector;
use common\models\Rrhh;
use common\models\Medicamento;
use common\models\Practica;
use common\models\Localidad;
use common\models\ConsultaSintomas;

/**
 * Registry para mapear parámetros del chatbot a modelos de Yii2
 * 
 * Este registry centraliza el mapeo entre los nombres de parámetros
 * usados en el chatbot y los modelos que los contienen.
 * 
 * Cuando se agrega un nuevo parámetro o modelo, solo hay que actualizar
 * este archivo y agregar el método parameterQuestions() al modelo correspondiente.
 */
class ParameterQuestionRegistry
{
    /**
     * Mapeo de parámetros a modelos
     * 
     * La clave es el nombre del parámetro usado en el chatbot.
     * El valor es el nombre de la clase del modelo.
     * 
     * @var array
     */
    private static $parameterToModelMap = [
        // Parámetros de Turno
        'fecha' => Turno::class,
        'hora' => Turno::class,
        'horario' => Turno::class,
        'turno_id' => Turno::class,
        'id_turnos' => Turno::class,
        
        // Parámetros de Servicio
        'servicio' => Servicio::class,
        'id_servicio' => Servicio::class,
        'servicio_asignado' => Servicio::class,
        'servicio_actual' => Servicio::class,
        
        // Parámetros de Efector
        'efector' => Efector::class,
        'id_efector' => Efector::class,
        'centro_salud' => Efector::class,
        
        // Parámetros de Rrhh (Profesional)
        'profesional' => Rrhh::class,
        'id_rr_hh' => Rrhh::class,
        'id_rrhh' => Rrhh::class,
        'rrhh' => Rrhh::class,
        
        // Parámetros de Medicamento
        'medicamento' => Medicamento::class,
        'id_medicamento' => Medicamento::class,
        
        // Parámetros de Practica
        'tipo_practica' => Practica::class,
        'practica' => Practica::class,
        'id_practica' => Practica::class,
        
        // Parámetros de Localidad/Ubicación
        'ubicacion' => Localidad::class,
        'localidad' => Localidad::class,
        'id_localidad' => Localidad::class,
        'zona' => Localidad::class,
        
        // Parámetros de ConsultaSintomas
        'sintoma' => ConsultaSintomas::class,
        'sintomas' => ConsultaSintomas::class,
    ];
    
    /**
     * Obtener la pregunta para un parámetro desde su modelo correspondiente
     * 
     * @param string $parameter Nombre del parámetro
     * @return string|null La pregunta o null si no se encuentra
     */
    public static function getQuestion($parameter)
    {
        // Normalizar el nombre del parámetro
        $parameter = strtolower(trim($parameter));
        
        // Buscar el modelo asociado
        if (!isset(self::$parameterToModelMap[$parameter])) {
            return null;
        }
        
        $modelClass = self::$parameterToModelMap[$parameter];
        
        // Verificar que la clase existe
        if (!class_exists($modelClass)) {
            Yii::warning("Model class {$modelClass} not found for parameter {$parameter}", 'parameter-questions');
            return null;
        }
        
        // Crear una instancia temporal del modelo para obtener las preguntas
        // Usamos un modelo "nuevo" ya que solo necesitamos el método parameterQuestions()
        $model = new $modelClass();
        
        // Verificar que el modelo tiene el trait o método parameterQuestions
        if (!method_exists($model, 'parameterQuestions')) {
            Yii::warning("Model {$modelClass} does not implement parameterQuestions() for parameter {$parameter}", 'parameter-questions');
            return null;
        }
        
        return $model->getParameterQuestion($parameter);
    }
    
    /**
     * Obtener todas las preguntas para un array de parámetros
     * 
     * @param array $parameters Array de nombres de parámetros
     * @return array Array asociativo [param => question] con las preguntas encontradas
     */
    public static function getQuestions(array $parameters)
    {
        $questions = [];
        
        foreach ($parameters as $param) {
            $question = self::getQuestion($param);
            if ($question !== null) {
                $questions[$param] = $question;
            }
        }
        
        return $questions;
    }
    
    /**
     * Registrar un nuevo mapeo parámetro -> modelo
     * 
     * Útil para extensiones o módulos que quieran agregar sus propios parámetros
     * 
     * @param string $parameter Nombre del parámetro
     * @param string $modelClass Nombre completo de la clase del modelo
     */
    public static function register($parameter, $modelClass)
    {
        self::$parameterToModelMap[strtolower(trim($parameter))] = $modelClass;
    }
    
    /**
     * Obtener todos los mapeos registrados
     * 
     * @return array
     */
    public static function getAllMappings()
    {
        return self::$parameterToModelMap;
    }
    
    /**
     * Obtener el modelo asociado a un parámetro
     * 
     * @param string $parameter Nombre del parámetro
     * @return string|null Nombre de la clase del modelo o null si no se encuentra
     */
    public static function getModelClass($parameter)
    {
        // Normalizar el nombre del parámetro
        $parameter = strtolower(trim($parameter));
        
        // Buscar el modelo asociado
        if (!isset(self::$parameterToModelMap[$parameter])) {
            return null;
        }
        
        $modelClass = self::$parameterToModelMap[$parameter];
        
        // Verificar que la clase existe
        if (!class_exists($modelClass)) {
            Yii::warning("Model class {$modelClass} not found for parameter {$parameter}", 'parameter-questions');
            return null;
        }
        
        return $modelClass;
    }
}
