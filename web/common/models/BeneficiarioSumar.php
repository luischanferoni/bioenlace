<?php

namespace common\models;

use Yii;

/**
 * BeneficiarioSumar is the model behind the contact form.
 */
class BeneficiarioSumar extends \yii\db\ActiveRecord
{
    public static function getDb()
    {
        return Yii::$app->get('dbpgsql');
    }
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'uad.beneficiarios';
    }

    /**
     * @return array customized attribute labels
     */
    public function attributeLabels()
    {
        return [
            'id_beneficiarios' => 'Id Beneficiario',
            'estado_envio' => 'estado_envio',
            'clave_beneficiario' => 'clave_beneficiario',
            'tipo_transaccion' => 'tipo_transaccion',
            'apellido_benef' => 'apellido_benef',
            'nombre_benef' => 'nombre_benef',
            'clase_documento_benef' => 'clase_documento_benef',
            'tipo_documento' => 'tipo_documento',
            'numero_doc' => 'numero_doc',
            'id_categoria' => 'id_categoria',
            'sexo' => 'sexo',
            'fecha_nacimiento_benef' => 'fecha_nacimiento_benef',
            'provincia_nac' => 'provincia_nac',
            'localidad_nac' => 'localidad_nac',
            'pais_nac' => 'pais_nac',
            'indigena' => 'indigena',
            'id_tribu' => 'id_tribu',
            'id_lengua' => 'id_lengua',
            'alfabeta' => 'alfabeta',
            'anio_mayor_nivel' => 'anio_mayor_nivel',
            'tipo_doc_madre' => 'tipo_doc_madre',
            'nro_doc_madre' => 'nro_doc_madre',
            'apellido_madre' => 'apellido_madre',
            'nombre_madre' => 'nombre_madre',
            'alfabeta_madre' => 'alfabeta_madre',
            'estudios_madre' => 'estudios_madre',
            'anio_mayor_nivel_madre' => 'anio_mayor_nivel_madre',
            'tipo_doc_padre' => 'tipo_doc_padre',
            'nro_doc_padre' => 'nro_doc_padre',
            'apellido_padre' => 'apellido_padre',
            'nombre_padre' => 'nombre_padre',
            'alfabeta_padre' => 'alfabeta_padre',
            'estudios_padre' => 'estudios_padre',
            'anio_mayor_nivel_padre' => 'anio_mayor_nivel_madre',
            'tipo_doc_tutor' => 'tipo_doc_tutor',
            'nro_doc_tutor' => 'nro_doc_tutor',
            'apellido_tutor' => 'apellido_tutor',
            'nombre_tutor' => 'nombre_tutor',
            'alfabeta_tutor' => 'alfabeta_tutor',
            'estudios_tutor' => 'estudios_tutor',
            'anio_mayor_nivel_tutor' => 'anio_mayor_nivel_tutor',
            'fecha_diagnostico_embarazo' => 'fecha_diagnostico_embarazo',
            'semanas_embarazo' => 'semanas_embarazo',
            'fecha_probable_parto' => 'fecha_probable_parto',
            'fecha_efectiva_parto' => 'fecha_efectiva_parto',
            'cuie_ea' => 'cuie_ea',
            'cuie_ah' => 'cuie_ah',
            'menor_convive_con_adulto' => 'menor_convive_con_adulto',
            'calle' => 'calle',
            'numero_calle' => 'numero_calle',
            'piso' => 'piso',
            'dpto' => 'dpto',
            'manzana' => 'manzana',
            'entre_calle_1' => 'entre_calle_1',
            'entre_calle_2' => 'entre_calle_2',
            'telefono' => 'telefono',
            'departamento' => 'departamento',
            'localidad' => 'localidad',
            'municipio' => 'municipio',
            'barrio' => 'barrio',
            'cod_pos' => 'cod_pos',
            'observaciones' => 'observaciones',
            'fecha_inscripcion' => 'fecha_inscripcion',
            'fecha_carga' => 'fecha_carga',
            'usuario_carga' => 'usuario_carga',
            'activo' => 'activo',
            'score_riesgo' => 'score_riesgo',
            'mail' => 'mail',
            'celular' => 'celular',
            'otrotel' => 'otrotel',
            'estadoest' => 'estadoest',
            'fum' => 'fum',
            'obsgenerales' => 'obsgenerales',
            'estadoest_madre' => 'estadoest_madre',
            'tipo_ficha' => 'tipo_ficha',
            'responsable' => 'responsable',
            'discv' => 'discv',
            'disca' => 'disca',
            'discmo' => 'discmo',
            'discme' => 'discme',
            'otradisc' => 'otradisc',
            'estadoest_padre' => 'estadoest_padre',
            'estadoest_tutor' => 'estadoest_tutor',
            'menor_embarazada' => 'menor_embarazada',
            'id_baja' => 'id_baja',
            'mensaje_baja' => 'mensaje_baja',
            'id_benef_offline' => 'id_benef_offline',
            'fecha_ingreso_me_entrada' => 'fecha_ingreso_me_entrada',
            'archivo_A' => 'archivo_A',
            'latitud' => 'latitud',
            'longitud' => 'longitud',
            'geo_cuie' => 'geo_cuie',
            'fecha_ultima_operacion' => 'fecha_ultima_operacion',
            'codigo_subarea' => 'codigo_subarea',
            'codigo_microarea' => 'codigo_microarea',
        ];
    }


    public static function buscarBeneficiario($clave_beneficiario){

        $beneficiario = self::find()
        ->where(['clave_beneficiario' => $clave_beneficiario])
        ->one();
    
        return isset($beneficiario) ? $beneficiario : false;
    
    }

}