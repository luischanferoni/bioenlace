<?php

namespace common\models\file;

use Yii;
 
class VirusRespiratoriosImport extends \yii\db\ActiveRecord
{
    const DESCRIPCION = 'Virus Respiratorios';
    const UNIQUE = 'caso';
    const SUFIJO_NOMBRE_ARCHIVO = 'virusrespiratorio_';    
    /**
     * El listado de atributos en el orden que se lo espera recibir en el CSV
     * Importante mantener el orden exactamente igual a como proviene en el archivo
     */
    const ATTRIBUTES = [
        'caso',
        'apellido', 
        'nombre',        
        'dni',
        'establecimiento_notificador',
        'localidad',
        'edad',
        'situacion_paciente',
        'fecha_procesamiento', 
        'observaciones',
        'resultado_genoma_viral_sars_cov_2',
        'resultado_rt_pcr_virus_influenza_a',
        'resultado_rt_pcr_virus_influenza_b',
        'resultado_genoma_viral_rsv',
        'tipo_muestra',
    ];
    
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'laboratorio_virus_respiratorios';
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
                    \yii\db\ActiveRecord::EVENT_AFTER_VALIDATE => ['fecha_procesamiento'],
                ],
                'preserveNonEmptyValues' => true,
                'value' => function ($event) {
                    if ($this->owner->fecha_procesamiento != null) {
                        $fecha = date_create_from_format('d/m/Y', $this->owner->fecha_procesamiento);                        
                        $this->owner->fecha_procesamiento = $fecha ? date_format($fecha, 'Y-m-d') : '0000-00-00';
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
            [['caso', 'dni', 'fecha_procesamiento', 'apellido', 'resultado_genoma_viral_sars_cov_2'], 'required'],            
            [['caso', 'edad', 'dni'], 'integer'],            
            [['apellido', 'nombre', 'establecimiento_notificador', 'localidad', 'situacion_paciente', 'observaciones',
              'resultado_genoma_viral_sars_cov_2', 'resultado_rt_pcr_virus_influenza_a',
              'resultado_rt_pcr_virus_influenza_b', 'resultado_genoma_viral_rsv', 'tipo_muestra'], 'string'],            

            ['fecha_procesamiento', 'date', 'min' => strtotime(date("Y-m-d"). ' - 10 months'), 'tooSmall' => 'Solamente hasta 5 meses anteriores a la fecha actual', 'max' => time(), 'tooBig' => 'Fecha futura no esta permitida'],            

            // Esto quedaría para validar que no lleguen caracteres que no se pueden convertir
            /*[['apellido', 'nombre', 'resultado_genoma_viral_sars_cov_2', 'resultado_rt_pcr_virus_influenza_a', 'resultado_rt_pcr_virus_influenza_b', 
            'resultado_rt_pcr_virus_influenza_b', 'observaciones'] , 'validarCodificacion'],*/

            [['caso'], 'unique'],

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
            'caso' => 'Número de Caso',
            'apellido' => 'Apellido', 
            'nombre' => 'Nombre',
            'dni' => 'DNI',
            'edad' => 'Edad',
            'establecimiento_notificador' => 'Establecimiento Notificador',
            'localidad' => 'Localidad',
            'situacion_paciente' => 'Situación del Paciente', 
            'fecha_procesamiento' => 'Fecha de procesamiento',
            'observaciones' => 'Observaciones de la muestra',

            'resultado_genoma_viral_sars_cov_2' => 'Genoma Viral de SARS-Cov-2',
            'resultado_rt_pcr_virus_influenza_a' => 'RT-PCR Virus Influenza A',
            'resultado_rt_pcr_virus_influenza_b' => 'RT-PCR Virus Influenza B',
            'resultado_genoma_viral_rsv' => 'Genoma Viral RSV',

            'tipo_muestra' => 'Tipo de Muestra',
            
        ];
    }

    public function validarCodificacion($attribute, $params, $validator)
    {
        if (preg_match('^[A-ZÁÉÍÓÚÑa-záéíóúñ\s]+[-\(\)°]?[A-ZÁÉÍÓÚÑa-záéíóúñ\s]+', $this->$attribute)) {
            $this->addError($attribute, 'Caracteres erróneos en el campo "'.$this->getAttributeLabel("$attribute").'"');
        }
    }

}