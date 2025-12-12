<?php

namespace common\models;

use Yii;

/**
 * Modelo para manejar abreviaturas médicas y su expansión semántica
 * 
 * @property int $id
 * @property string $abreviatura
 * @property string $expansion_completa
 * @property string $categoria
 * @property string $especialidad
 * @property string $contexto
 * @property string $sinonimos
 * @property int $frecuencia_uso
 * @property string $origen
 * @property int $activo
 * @property string $fecha_creacion
 * @property string $fecha_actualizacion
 */
class AbreviaturasMedicas extends \yii\db\ActiveRecord
{
    const ORIGEN_USUARIO = 'USUARIO';
    const ORIGEN_LLM = 'LLM';
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'abreviaturas_medicas';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['abreviatura', 'expansion_completa'], 'required'],
            [['abreviatura'], 'string', 'max' => 50],
            [['expansion_completa'], 'string', 'max' => 255],
            [['categoria', 'especialidad'], 'string', 'max' => 100],
            [['contexto'], 'string', 'max' => 500],
            [['sinonimos'], 'string', 'max' => 1000],
            [['frecuencia_uso', 'activo'], 'integer'],
            [['origen'], 'in', 'range' => [self::ORIGEN_USUARIO, self::ORIGEN_LLM]],
            [['fecha_creacion', 'fecha_actualizacion'], 'safe'],
            [['abreviatura'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'abreviatura' => 'Abreviatura',
            'expansion_completa' => 'Expansión Completa',
            'categoria' => 'Categoría',
            'especialidad' => 'Especialidad',
            'contexto' => 'Contexto',
            'sinonimos' => 'Sinónimos',
            'frecuencia_uso' => 'Frecuencia de Uso',
            'origen' => 'Origen',
            'activo' => 'Activo',
            'fecha_creacion' => 'Fecha Creación',
            'fecha_actualizacion' => 'Fecha Actualización',
        ];
    }

    /**
     * Procesar texto expandiendo abreviaturas médicas
     * @param string $texto
     * @param string $especialidad Opcional: filtrar por especialidad
     * @return string
     */
    public static function expandirAbreviaturas($texto, $especialidad = null)
    {
        // Obtener abreviaturas activas
        $query = self::find()->where(['activo' => 1]);
        
        if ($especialidad) {
            $query->andWhere(['or', 
                ['especialidad' => $especialidad], 
                ['especialidad' => null]
            ]);
        }
        
        // Ordenar por longitud descendente para evitar que abreviaturas cortas
        // se reemplacen dentro de abreviaturas más largas
        try {
            $abreviaturas = $query->orderBy([
                new \yii\db\Expression('LENGTH(abreviatura) DESC'),
                'frecuencia_uso' => SORT_DESC
            ])->all();
        } catch (\Exception $e) {
            \Yii::error("Error cargando abreviaturas: " . $e->getMessage(), 'abreviaturas');
            $abreviaturas = [];
        }
        
        // Asegurar que $abreviaturas sea siempre un array
        if (!is_array($abreviaturas) && !($abreviaturas instanceof \Countable)) {
            $abreviaturas = [];
        }
        
        // Crear mapa de abreviaturas para búsqueda rápida (case-insensitive)
        $mapaAbreviaturas = [];
        foreach ($abreviaturas as $abreviatura) {
            $clave = mb_strtolower($abreviatura->abreviatura, 'UTF-8');
            $mapaAbreviaturas[$clave] = $abreviatura;
        }
        
        // Logging usando ConsultaLogger si está disponible
        $logger = \common\components\ConsultaLogger::obtenerInstancia();
        
        // Listar abreviaturas cargadas para debugging
        $listaAbreviaturas = [];
        foreach ($abreviaturas as $abrev) {
            $listaAbreviaturas[] = $abrev->abreviatura . ' → ' . $abrev->expansion_completa;
        }
        
        $totalAbreviaturas = is_countable($abreviaturas) ? count($abreviaturas) : 0;
        
        if ($logger) {
            $logger->registrar(
                'PROCESAMIENTO',
                'Carga de abreviaturas',
                'Total abreviaturas cargadas: ' . $totalAbreviaturas,
                [
                    'metodo' => 'AbreviaturasMedicas::expandirAbreviaturas',
                    'total_abreviaturas' => $totalAbreviaturas,
                    'texto_preview' => substr($texto, 0, 100),
                    'abreviaturas' => $listaAbreviaturas
                ]
            );
        } else {
            // Fallback a Yii::info si no hay logger
            \Yii::info("Abreviaturas cargadas ({$totalAbreviaturas}): " . implode(', ', $listaAbreviaturas), 'abreviaturas');
        }
        
        $textoProcesado = $texto;
        $abreviaturasAplicadas = [];
        
        // Ordenar abreviaturas por longitud descendente para evitar reemplazos parciales
        // Asegurar que $abreviaturas sea un array antes de ordenar
        if (is_array($abreviaturas) && is_countable($abreviaturas) && count($abreviaturas) > 0) {
            usort($abreviaturas, function($a, $b) {
                return strlen($b->abreviatura) - strlen($a->abreviatura);
            });
        }
        
        // Asegurar que $abreviaturas sea iterable antes del foreach
        if (!is_array($abreviaturas) && !($abreviaturas instanceof \Traversable)) {
            $abreviaturas = [];
        }
        
        // Procesar cada abreviatura
        foreach ($abreviaturas as $abreviatura) {
            $abrevOriginal = $abreviatura->abreviatura;
            
            // Buscar todas las ocurrencias de la abreviatura en el texto (case-insensitive)
            // Patrón que permite puntuación después (:, ., etc.) pero no dentro de otra palabra
            // Usar lookahead negativo para asegurar que no sea parte de otra palabra
            $abrevEscapada = preg_quote($abrevOriginal, '/');
            // Permitir: inicio de línea/palabra, la abreviatura, y luego espacio/puntuación/fin
            $patron = '/(?<=^|\s|[:.,;!?])' . $abrevEscapada . '(?=\s|[:.,;!?]|$)/iu';
            
            if (preg_match_all($patron, $textoProcesado, $matches, PREG_OFFSET_CAPTURE)) {
                // Validar que $matches[0] sea un array antes de contar
                $ocurrencias = 0;
                if (isset($matches[0]) && (is_array($matches[0]) || ($matches[0] instanceof \Countable))) {
                    $ocurrencias = is_countable($matches[0]) ? count($matches[0]) : 0;
                }
                
                // Reemplazar todas las ocurrencias encontradas
                $textoProcesado = preg_replace($patron, $abreviatura->expansion_completa, $textoProcesado);
                
                $abreviaturasAplicadas[] = [
                    'abreviatura' => $abrevOriginal,
                    'expansion' => $abreviatura->expansion_completa,
                    'ocurrencias' => $ocurrencias
                ];
                
                if ($logger) {
                    $logger->registrar(
                        'PROCESAMIENTO',
                        "Abreviatura encontrada: {$abrevOriginal}",
                        "Expandida a: {$abreviatura->expansion_completa}",
                        [
                            'metodo' => 'AbreviaturasMedicas::expandirAbreviaturas',
                            'abreviatura' => $abrevOriginal,
                            'expansion' => $abreviatura->expansion_completa,
                            'ocurrencias' => $ocurrencias
                        ]
                    );
                }
            }
        }
        
        // Log para debugging
        if (!empty($abreviaturasAplicadas)) {
            \Yii::info("Abreviaturas expandidas: " . json_encode($abreviaturasAplicadas), 'abreviaturas');
        }
        
        return $textoProcesado;
    }

    /**
     * Procesar texto expandiendo abreviaturas médicas con lógica de médico
     * @param string $texto
     * @param string $especialidad Opcional: filtrar por especialidad
     * @param int $idRrHh Opcional: ID del médico para priorizar sus abreviaturas
     * @return array
     */
    public static function expandirAbreviaturasConMedico($texto, $especialidad = null, $idRrHh = null)
    {
        // Inicializar variables asegurando que sean arrays
        $abreviaturasEncontradas = [];
        $textoProcesado = $texto;
        
        // Obtener todas las abreviaturas activas con información de médicos
        // La tabla de relación se llama 'abreviaturas_rrhh', no 'abreviaturas_medicos'
        $query = self::find()
            ->select([
                'abreviaturas_medicas.*',
                'GROUP_CONCAT(am.id_rr_hh) as medicos_ids',
                'MAX(am.frecuencia_uso) as max_frecuencia_medico'
            ])
            ->leftJoin('abreviaturas_rrhh am', 'abreviaturas_medicas.id = am.abreviatura_id AND am.activo = 1')
            ->where(['abreviaturas_medicas.activo' => 1])
            ->groupBy('abreviaturas_medicas.id');
        
        if ($especialidad) {
            $query->andWhere(['or', 
                ['abreviaturas_medicas.especialidad' => $especialidad], 
                ['abreviaturas_medicas.especialidad' => null]
            ]);
        }
        
        // Ordenar por longitud descendente para evitar que abreviaturas cortas
        // se reemplacen dentro de abreviaturas más largas
        try {
            $abreviaturas = $query->orderBy([
                new \yii\db\Expression('LENGTH(abreviaturas_medicas.abreviatura) DESC'),
                'abreviaturas_medicas.frecuencia_uso' => SORT_DESC
            ])->all();
        } catch (\Exception $e) {
            // Si hay error en la consulta, usar método simple sin JOIN
            \Yii::error("Error en consulta con JOIN, usando método simple: " . $e->getMessage(), 'abreviaturas');
            $abreviaturas = self::find()
                ->where(['activo' => 1])
                ->orderBy([
                    new \yii\db\Expression('LENGTH(abreviatura) DESC'),
                    'frecuencia_uso' => SORT_DESC
                ])
                ->all();
        }
        
        // Asegurar que $abreviaturas sea siempre un array
        if (!is_array($abreviaturas) && !($abreviaturas instanceof \Countable)) {
            $abreviaturas = [];
        }
        
        // Logging usando ConsultaLogger si está disponible
        $logger = \common\components\ConsultaLogger::obtenerInstancia();
        
        // Listar abreviaturas cargadas para debugging
        $listaAbreviaturas = [];
        foreach ($abreviaturas as $abrev) {
            $listaAbreviaturas[] = $abrev->abreviatura . ' → ' . $abrev->expansion_completa;
        }
        
        $totalAbreviaturas = is_countable($abreviaturas) ? count($abreviaturas) : 0;
        
        if ($logger) {
            $logger->registrar(
                'PROCESAMIENTO',
                'Carga de abreviaturas (con médico)',
                'Total abreviaturas cargadas: ' . $totalAbreviaturas,
                [
                    'metodo' => 'AbreviaturasMedicas::expandirAbreviaturasConMedico',
                    'total_abreviaturas' => $totalAbreviaturas,
                    'id_rr_hh' => $idRrHh,
                    'especialidad' => $especialidad,
                    'abreviaturas' => $listaAbreviaturas
                ]
            );
        } else {
            // Fallback a Yii::info si no hay logger
            \Yii::info("Abreviaturas cargadas (con médico) ({$totalAbreviaturas}): " . implode(', ', $listaAbreviaturas), 'abreviaturas');
        }
        
        // Ordenar abreviaturas por longitud descendente para evitar reemplazos parciales
        // Asegurar que $abreviaturas sea un array antes de ordenar
        if (is_array($abreviaturas) && is_countable($abreviaturas) && count($abreviaturas) > 0) {
            usort($abreviaturas, function($a, $b) {
                return strlen($b->abreviatura) - strlen($a->abreviatura);
            });
        }
        
        // Asegurar que $abreviaturas sea iterable antes del foreach
        if (!is_array($abreviaturas) && !($abreviaturas instanceof \Traversable)) {
            $abreviaturas = [];
        }
        
        // Procesar cada abreviatura
        foreach ($abreviaturas as $abreviatura) {
            $abrevOriginal = $abreviatura->abreviatura;
            
            // Buscar todas las ocurrencias de la abreviatura en el texto (case-insensitive)
            // Patrón que permite puntuación después (:, ., etc.) pero no dentro de otra palabra
            $abrevEscapada = preg_quote($abrevOriginal, '/');
            // Permitir: inicio de línea/palabra, la abreviatura, y luego espacio/puntuación/fin
            $patron = '/(?<=^|\s|[:.,;!?])' . $abrevEscapada . '(?=\s|[:.,;!?]|$)/iu';
            
            if (preg_match_all($patron, $textoProcesado, $matches, PREG_OFFSET_CAPTURE)) {
                // Validar que $matches[0] sea un array antes de contar
                $ocurrencias = 0;
                if (isset($matches[0]) && (is_array($matches[0]) || ($matches[0] instanceof \Countable))) {
                    $ocurrencias = is_countable($matches[0]) ? count($matches[0]) : 0;
                }
                
                $expansionElegida = self::elegirExpansionPorMedico($abreviatura, $idRrHh);
                
                if ($expansionElegida) {
                    // Reemplazar todas las ocurrencias encontradas
                    $textoProcesado = preg_replace($patron, $expansionElegida, $textoProcesado);
                    
                    $abreviaturasEncontradas[] = [
                        'abreviatura' => $abrevOriginal,
                        'expansion' => $expansionElegida,
                        'categoria' => $abreviatura->categoria,
                        'contexto' => $abreviatura->contexto,
                        'metodo_seleccion' => $expansionElegida === $abreviatura->expansion_completa ? 'frecuencia_general' : 'medico_especifico',
                        'ocurrencias' => $ocurrencias
                    ];
                    
                    if ($logger) {
                        $logger->registrar(
                            'PROCESAMIENTO',
                            "Abreviatura encontrada: {$abrevOriginal}",
                            "Expandida a: {$expansionElegida}",
                            [
                                'metodo' => 'AbreviaturasMedicas::expandirAbreviaturasConMedico',
                                'abreviatura' => $abrevOriginal,
                                'expansion' => $expansionElegida,
                                'ocurrencias' => $ocurrencias
                            ]
                        );
                    }
                }
            }
        }
        
        // Asegurar que $abreviaturasEncontradas sea siempre un array
        if (!is_array($abreviaturasEncontradas)) {
            $abreviaturasEncontradas = [];
        }
        
        return [
            'texto_procesado' => $textoProcesado,
            'abreviaturas_encontradas' => $abreviaturasEncontradas
        ];
    }

    /**
     * Elegir expansión basada en el médico y frecuencia
     * @param AbreviaturasMedicas $abreviatura
     * @param int $idRrHh
     * @param string $contexto
     * @return string|null
     */
    private static function elegirExpansionPorMedico($abreviatura, $idRrHh = null, $contexto = null)
    {
        if (!$idRrHh) {
            return $abreviatura->expansion_completa;
        }
        
        // Verificar si el médico tiene una preferencia específica
        $relacionMedico = \common\models\AbreviaturasMedicos::find()
            ->where(['abreviatura_id' => $abreviatura->id, 'id_rr_hh' => $idRrHh, 'activo' => 1])
            ->one();
        
        if ($relacionMedico) {
            // El médico tiene historial con esta abreviatura, usar su preferencia
            return $abreviatura->expansion_completa;
        }
        
        // Verificar si hay empate de frecuencia o ambigüedad
        $abreviaturasSimilares = self::find()
            ->where(['abreviatura' => $abreviatura->abreviatura, 'activo' => 1])
            ->andWhere(['!=', 'id', $abreviatura->id])
            ->all();
        
        // Asegurar que sea un array antes de contar
        if (!is_array($abreviaturasSimilares) && !($abreviaturasSimilares instanceof \Countable)) {
            $abreviaturasSimilares = [];
        }
        
        if (is_countable($abreviaturasSimilares) && count($abreviaturasSimilares) > 0) {
            // Hay ambigüedad, usar LLM para desambiguar
            return self::desambiguarConLLM($abreviatura, $abreviaturasSimilares, $contexto, $idRrHh);
        }
        
        // Si no hay preferencia del médico, usar la más frecuente
        return $abreviatura->expansion_completa;
    }

    /**
     * Desambiguar abreviatura usando LLM
     * @param AbreviaturasMedicas $abreviatura
     * @param array $abreviaturasSimilares
     * @param string $contexto
     * @param int $idRrHh
     * @return string
     */
    private static function desambiguarConLLM($abreviatura, $abreviaturasSimilares, $contexto, $idRrHh)
    {
        // Asegurar que $abreviaturasSimilares sea un array
        if (!is_array($abreviaturasSimilares)) {
            $abreviaturasSimilares = [];
        }
        try {
            // Crear prompt para el LLM
            $opciones = [$abreviatura->expansion_completa];
            foreach ($abreviaturasSimilares as $simil) {
                $opciones[] = $simil->expansion_completa;
            }
            
            $prompt = "Eres un especialista médico. Dada la abreviatura '{$abreviatura->abreviatura}' en el siguiente contexto, elige la expansión más apropiada:\n\n";
            $prompt .= "Contexto: {$contexto}\n\n";
            $prompt .= "Opciones:\n";
            foreach ($opciones as $i => $opcion) {
                $prompt .= ($i + 1) . ". {$opcion}\n";
            }
            $prompt .= "\nResponde SOLO con el número de la opción correcta:";
            
            // Usar IAManager para consultar LLM
            $respuesta = \Yii::$app->iamanager->consultarLLM($prompt);
            
            $opcionesCount = is_countable($opciones) ? count($opciones) : 0;
            if (is_numeric($respuesta) && $respuesta >= 1 && $respuesta <= $opcionesCount) {
                $expansionElegida = $opciones[$respuesta - 1];
                
                // Guardar la elección del LLM para futuras referencias
                self::guardarEleccionLLM($abreviatura->abreviatura, $expansionElegida, $contexto, $idRrHh);
                
                return $expansionElegida;
            }
            
        } catch (\Exception $e) {
            \Yii::error("Error en desambiguación LLM: " . $e->getMessage(), 'abreviaturas');
        }
        
        // Fallback a la expansión original si LLM falla
        return $abreviatura->expansion_completa;
    }

    /**
     * Guardar elección del LLM para futuras referencias
     * @param string $abreviatura
     * @param string $expansionElegida
     * @param string $contexto
     * @param int $idRrHh
     */
    private static function guardarEleccionLLM($abreviatura, $expansionElegida, $contexto, $idRrHh)
    {
        try {
            // Crear o actualizar registro en tabla principal con origen LLM
            $eleccion = self::find()
                ->where(['abreviatura' => $abreviatura, 'origen' => self::ORIGEN_LLM])
                ->one();
            
            if ($eleccion) {
                $eleccion->expansion_completa = $expansionElegida;
                $eleccion->frecuencia_uso++;
                $eleccion->fecha_actualizacion = date('Y-m-d H:i:s');
            } else {
                $eleccion = new self();
                $eleccion->abreviatura = $abreviatura;
                $eleccion->expansion_completa = $expansionElegida;
                $eleccion->contexto = $contexto;
                $eleccion->origen = self::ORIGEN_LLM;
                $eleccion->frecuencia_uso = 1;
                $eleccion->activo = 1;
            }
            
            $eleccion->save();
            
        } catch (\Exception $e) {
            \Yii::error("Error guardando elección LLM: " . $e->getMessage(), 'abreviaturas');
        }
    }

    /**
     * Buscar abreviatura por texto
     * @param string $abreviatura
     * @return AbreviaturasMedicas|null
     */
    public static function buscarAbreviatura($abreviatura)
    {
        return self::find()
            ->where(['abreviatura' => $abreviatura, 'activo' => 1])
            ->one();
    }

    /**
     * Obtener abreviaturas por especialidad
     * @param string $especialidad
     * @return array
     */
    public static function getAbreviaturasPorEspecialidad($especialidad)
    {
        return self::find()
            ->where(['activo' => 1])
            ->andWhere(['or', 
                ['especialidad' => $especialidad], 
                ['especialidad' => null]
            ])
            ->orderBy(['frecuencia_uso' => SORT_DESC])
            ->all();
    }

    /**
     * Incrementar frecuencia de uso de una abreviatura
     * @param string $abreviatura
     */
    public static function incrementarFrecuencia($abreviatura)
    {
        $model = self::findOne(['abreviatura' => $abreviatura]);
        if ($model) {
            $model->frecuencia_uso++;
            $model->save(false);
        }
    }

    /**
     * Agregar nueva abreviatura
     * @param array $datos
     * @return bool
     */
    public static function agregarAbreviatura($datos)
    {
        $model = new self();
        $model->attributes = $datos;
        $model->fecha_creacion = date('Y-m-d H:i:s');
        $model->fecha_actualizacion = date('Y-m-d H:i:s');
        $model->activo = 1;
        $model->frecuencia_uso = 0;
        
        return $model->save();
    }

    /**
     * Obtener estadísticas de uso
     * @return array
     */
    public static function getEstadisticas()
    {
        return [
            'total_abreviaturas' => self::find()->where(['activo' => 1])->count(),
            'por_especialidad' => self::find()
                ->select(['especialidad', 'COUNT(*) as total'])
                ->where(['activo' => 1])
                ->groupBy('especialidad')
                ->asArray()
                ->all(),
            'mas_usadas' => self::find()
                ->where(['activo' => 1])
                ->orderBy(['frecuencia_uso' => SORT_DESC])
                ->limit(10)
                ->asArray()
                ->all()
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($insert) {
                $this->fecha_creacion = date('Y-m-d H:i:s');
            }
            $this->fecha_actualizacion = date('Y-m-d H:i:s');
            return true;
        }
        return false;
    }
}
