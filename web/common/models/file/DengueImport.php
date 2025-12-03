<?php

namespace common\models\file;

use Yii;
 
class DengueImport extends \yii\db\ActiveRecord
{
    const DESCRIPCION = 'Dengue';
    const UNIQUE = 'codigo';
    const SUFIJO_NOMBRE_ARCHIVO = 'dengue_';

    /**
     * El listado de atributos en el orden que se lo espera recibir en el CSV
     * Importante mantener el orden exactamente igual a como proviene en el archivo
     */
    const ATTRIBUTES = [
        'codigo',
        'apellido', 
        'nombre',        
        'dni', 
        'edad',
        'establecimiento_notificador',
        'centro_derivador',
        'localidad',
        'departamento',
        'fecha_recepcion', 
        'fecha_procesamiento', 
        'fecha_inicio_fiebre', 
        'dias_evolucion', 
        'ns1_elisa', 
        'ns1_test_rapido',
        'ig_m_dengue_elisa',
        'ig_m_test_rapido',
        'igg_test_rapido',
        'rt_pcr_tiempo_real_dengue',
        'serotipo_virus_dengue',
        'rt_pcr_chik',
        'igm_chik',
        'rt_pcr_tiempo_real_chik',
        'rt_pcr_tiempo_real_zika',
        'rt_pcr_tiempo_real_yf',
        'resultado_laboratorio',
        'observaciones'
    ];
    
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'laboratorio_dengue';
    }

    public function behaviors()
    {
        return [
            'blames' => [
                'class' => 'yii\behaviors\AttributeBehavior',
                'attributes' => [
                    \yii\db\ActiveRecord::EVENT_BEFORE_INSERT => ['created_by'],
                    \yii\db\ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_by'],
                    \yii\db\ActiveRecord::EVENT_BEFORE_DELETE => ['deleted_by'],
                ],
                'value' => Yii::$app->user->id,                
            ],
            'fechas' => [
                'class' => 'yii\behaviors\AttributeBehavior',
                'attributes' => [
                    \yii\db\ActiveRecord::EVENT_AFTER_VALIDATE => ['fecha_recepcion', 'fecha_procesamiento', 'fecha_inicio_fiebre'],                    
                ],
                'preserveNonEmptyValues' => true,
                'value' => function ($event) {
                    if ($this->owner->fecha_recepcion != null) {
                        $fecha = date_create_from_format('d/m/Y', $this->owner->fecha_recepcion);                        
                        $this->owner->fecha_recepcion = $fecha ? date_format($fecha, 'Y-m-d') : '0000-00-00';
                    }
                    if ($this->owner->fecha_procesamiento != null) {
                        $fecha = date_create_from_format('d/m/Y', $this->owner->fecha_procesamiento);                        
                        $this->owner->fecha_procesamiento = $fecha ? date_format($fecha, 'Y-m-d') : '0000-00-00';
                    }
                    if ($this->owner->fecha_inicio_fiebre != null) {
                        $fecha = date_create_from_format('d/m/Y', $this->owner->fecha_inicio_fiebre);
                        $this->owner->fecha_inicio_fiebre = $fecha ? date_format($fecha, 'Y-m-d') : '0000-00-00';
                    }                    
                },
            ],            
        ];
    }
        
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['codigo', 'dni', 'fecha_recepcion', 'fecha_procesamiento', 
              'apellido', 'resultado_laboratorio'], 'required'],
              [['edad', 'localidad', 'departamento', 'ig_m_test_rapido', 'rt_pcr_chik',
              'igm_chik', 'rt_pcr_tiempo_real_dengue','serotipo_virus_dengue', 'rt_pcr_tiempo_real_chik',
              'rt_pcr_tiempo_real_zika', 'rt_pcr_tiempo_real_yf', 'igg_test_rapido','ns1_test_rapido', 'observaciones'], 'safe'],              
            [['codigo', 'edad', 'dni', 'dias_evolucion'], 'integer'],            
            [['centro_derivador','establecimiento_notificador', 'apellido', 'nombre', 'localidad', 
              'departamento', 'ns1_elisa', 'ig_m_dengue_elisa', 'ig_m_test_rapido', 'rt_pcr_chik', 'igm_chik', 'rt_pcr_tiempo_real_dengue',
              'serotipo_virus_dengue', 'rt_pcr_tiempo_real_chik', 'rt_pcr_tiempo_real_zika', 'rt_pcr_tiempo_real_yf', 'igg_test_rapido',
              'ns1_test_rapido', 'dias_evolucion', 'resultado_laboratorio', 'observaciones'], 'string'],
            //['fecha_inicio_fiebre', 'date', 'min' => strtotime(date("Y-m-d"). ' - 8 months'), 'tooSmall' => 'Solamente hasta 8 meses anteriores a la fecha actual', 'max' => time(), 'tooBig' => 'Fecha futura no esta permitida'],              
            ['fecha_recepcion', 'date', 'min' => strtotime(date("Y-m-d"). ' - 6 months'), 'tooSmall' => 'Solamente hasta 6 meses anteriores a la fecha actual', 'max' => time(), 'tooBig' => 'Fecha futura no esta permitida'],
            ['fecha_procesamiento', 'date', 'min' => strtotime(date("Y-m-d"). ' - 5 months'), 'tooSmall' => 'Solamente hasta 5 meses anteriores a la fecha actual', 'max' => time(), 'tooBig' => 'Fecha futura no esta permitida'],
            ['fecha_procesamiento', 'validarFechasDependientes'],

            // Esto quedaría para validar que no lleguen caracteres que no se pueden convertir
            /*[['centro_derivador', 'apellido', 'nombre', 'ns1_elisa', 'rt_pcr_tiempo_real_dengue', 'rt_pcr_tiempo_real_chik', 
            'rt_pcr_tiempo_real_yf', 'resultado_laboratorio', 'observaciones'] , 'validarCodificacion'],*/

            [['codigo'], 'unique'],

            // Este quedaría para validar que no se equivoquen con el codigo y procesen el mismo DNI para la misma fecha
            //[['dni', 'fecha_procesamiento'], 'unique', 'message' => 'El {attribute}: {value} ya fue utilizado para esta fecha de procesamiento'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'codigo' => 'Codigo de muestra',
            'apellido' => 'Apellido', 
            'nombre' => 'Nombre',
            'dni' => 'DNI',
            'edad' => 'Edad',
            'establecimiento_notificador' => 'Establecimiento Notificador',
            'centro_derivador' => 'Centro derivador',
            'localidad' => 'Localidad',
            'departamento' => 'Departamento',            
            'fecha_recepcion' => 'Fecha de recepción', 
            'fecha_procesamiento' => 'Fecha de procesamiento',
            'fecha_inicio_fiebre' => 'Fecha inicio fiebre', 
            'dias_evolucion' => 'Días de evolución', 
            'ns1_elisa' => 'NS1 Elisa',
            'ns1_test_rapido' => 'NS1 test rapido',
            'ig_m_dengue_elisa' => 'Ig M-dengue ELISA',
            'ig_m_test_rapido' => 'Ig M test rapido',            
            'igg_test_rapido' => 'Ig G test rapido',            
            'rt_pcr_tiempo_real_dengue' => 'RT-PCR tiempo real Dengue',
            'serotipo_virus_dengue' => 'Serotipo Virus Dengue',            
            'rt_pcr_chik' => 'RT-PCR CHIK',
            'igm_chik' => 'Ig M CHIK',
            'rt_pcr_tiempo_real_chik' => 'RT-PCR tiempo real CHIK',
            'rt_pcr_tiempo_real_zika' => 'RT-PCR tiempo real ZIKA',
            'rt_pcr_tiempo_real_yf' => 'RT-PCR tiempo real YF',
            'resultado_laboratorio' => 'Resultado de laboratorio',
            'observaciones' => 'Observaciones'
        ];
    }

    public function validarFechasDependientes()
    {
        $fecha_recepcion = date_create_from_format('d/m/Y', $this->fecha_recepcion);
        $fecha_procesamiento = date_create_from_format('d/m/Y', $this->fecha_procesamiento);

        // Fecha de inicio de fiebre sale de la validacion porque el CSV va a traer registros en blanco para esta columna
        if($fecha_recepcion > $fecha_procesamiento             
            //|| strtotime($this->fecha_recepcion) < strtotime($this->fecha_inicio_fiebre)
            ) {
            $this->addError('fecha_procesamiento', 'Fecha de Recepción debe ser menor o igual a Fecha de procesamiento');
            //$this->addError('fecha_recepcion', 'Fecha de inicio de fiebre debe ser menor a Fecha de Recepción y Fecha de procesamiento');
            //$this->addError('fecha_inicio_fiebre', 'Fecha de inicio de fiebre debe ser menor a Fecha de Recepción y Fecha de procesamiento');
        }
    }

    public function validarCodificacion($attribute, $params, $validator)
    {
        if (preg_match('^[A-ZÁÉÍÓÚÑa-záéíóúñ\s]+[-\(\)°]?[A-ZÁÉÍÓÚÑa-záéíóúñ\s]+', $this->$attribute)) {
            $this->addError($attribute, 'Caracteres erróneos en el campo "'.$this->getAttributeLabel("$attribute").'"');
        }
    }

}