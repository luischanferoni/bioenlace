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
        
        $abreviaturas = $query->orderBy(['frecuencia_uso' => SORT_DESC])->all();
        
        $textoProcesado = $texto;
        
        foreach ($abreviaturas as $abreviatura) {
            // Crear patrón para buscar la abreviatura como palabra completa
            $patron = '/\b' . preg_quote($abreviatura->abreviatura, '/') . '\b/i';
            
            // Reemplazar con expansión completa
            $textoProcesado = preg_replace(
                $patron, 
                $abreviatura->expansion_completa, 
                $textoProcesado
            );
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
        $abreviaturasEncontradas = [];
        $textoProcesado = $texto;
        
        // Obtener todas las abreviaturas activas con información de médicos
        $query = self::find()
            ->select([
                'abreviaturas_medicas.*',
                'GROUP_CONCAT(am.id_rr_hh) as medicos_ids',
                'MAX(am.frecuencia_uso) as max_frecuencia_medico'
            ])
            ->leftJoin('abreviaturas_medicos am', 'abreviaturas_medicas.id = am.abreviatura_id AND am.activo = 1')
            ->where(['abreviaturas_medicas.activo' => 1])
            ->groupBy('abreviaturas_medicas.id');
        
        if ($especialidad) {
            $query->andWhere(['or', 
                ['abreviaturas_medicas.especialidad' => $especialidad], 
                ['abreviaturas_medicas.especialidad' => null]
            ]);
        }
        
        $abreviaturas = $query->orderBy(['abreviaturas_medicas.frecuencia_uso' => SORT_DESC])->all();
        
        foreach ($abreviaturas as $abreviatura) {
            $patron = '/\b' . preg_quote($abreviatura->abreviatura, '/') . '\b/i';
            
            if (preg_match($patron, $textoProcesado)) {
                $expansionElegida = self::elegirExpansionPorMedico($abreviatura, $idRrHh);
                
                if ($expansionElegida) {
                    $textoProcesado = preg_replace(
                        $patron, 
                        $expansionElegida, 
                        $textoProcesado
                    );
                    
                    $abreviaturasEncontradas[] = [
                        'abreviatura' => $abreviatura->abreviatura,
                        'expansion' => $expansionElegida,
                        'categoria' => $abreviatura->categoria,
                        'contexto' => $abreviatura->contexto,
                        'metodo_seleccion' => $expansionElegida === $abreviatura->expansion_completa ? 'frecuencia_general' : 'medico_especifico'
                    ];
                }
            }
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
        
        if (count($abreviaturasSimilares) > 0) {
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
            
            if (is_numeric($respuesta) && $respuesta >= 1 && $respuesta <= count($opciones)) {
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
