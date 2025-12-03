<?php

namespace common\models;

use Yii;

class NomencladorSumar extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'public.nomenclador';
    }

    /**
     * @return \yii\db\Connection the database connection used by this AR class.
     */
    public static function getDb()
    {
        return Yii::$app->get('dbpgsql');
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_nomenclador'], 'required'],
            [['id_nomenclador', 'id_nomenclador_detalle', 'dias_uti', 'dias_sala', 'dias_total', 'dias_max'], 'default', 'value' => null],
            [['id_nomenclador', 'id_nomenclador_detalle', 'dias_uti', 'dias_sala', 'dias_total', 'dias_max'], 'integer'],
            [['codigo', 'grupo', 'subgrupo', 'descripcion', 'tipo_nomenclador', 'definicion', 'codigo_old', 'grupo_poblacional', 'nomenclador_detalle', 'tasa_uso_2018', 'tasa_uso_2020', 'tasa_uso_2021', 'tasa_uso_2022', 'tasa_uso_2023'], 'string'],
            [['precio', 'precio_2013', 'precio_ceb', 'precio_2014', 'precio_2012', 'precio_2015', 'precio_2016', 'precio_2018', 'precio_2017', 'precio_2018_2', 'precio_2019', 'precio_paces_19', 'precio_2020', 'precio_2021', 'precio_2022', 'precio_2023'], 'number'],
            [['prestacion_ceb', 'prestacion_priorizada', 'perstacion_trazadora', 'ruralidad'], 'boolean'],
            [['nivel'], 'string', 'max' => 5],
            [['originario', 'sexo'], 'string', 'max' => 1],
            [['id_nomenclador'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_nomenclador' => 'Id Nomenclador',
            'codigo' => 'Codigo',
            'grupo' => 'Grupo',
            'subgrupo' => 'Subgrupo',
            'descripcion' => 'Descripcion',
            'precio' => 'Precio',
            'tipo_nomenclador' => 'Tipo Nomenclador',
            'id_nomenclador_detalle' => 'Id Nomenclador Detalle',
            'definicion' => 'Definicion',
            'dias_uti' => 'Dias Uti',
            'dias_sala' => 'Dias Sala',
            'dias_total' => 'Dias Total',
            'dias_max' => 'Dias Max',
            'nivel' => 'Nivel',
            'codigo_old' => 'Codigo Old',
            'precio_2013' => 'Precio 2013',
            'originario' => 'Originario',
            'precio_ceb' => 'Precio Ceb',
            'precio_2014' => 'Precio 2014',
            'prestacion_ceb' => 'Prestacion Ceb',
            'prestacion_priorizada' => 'Prestacion Priorizada',
            'perstacion_trazadora' => 'Perstacion Trazadora',
            'sexo' => 'Sexo',
            'grupo_poblacional' => 'Grupo Poblacional',
            'precio_2012' => 'Precio 2012',
            'precio_2015' => 'Precio 2015',
            'nomenclador_detalle' => 'Nomenclador Detalle',
            'precio_2016' => 'Precio 2016',
            'precio_2018' => 'Precio 2018',
            'tasa_uso_2018' => 'Tasa Uso 2018',
            'precio_2017' => 'Precio 2017',
            'precio_2018_2' => 'Precio 2018 2',
            'precio_2019' => 'Precio 2019',
            'precio_paces_19' => 'Precio Paces 19',
            'ruralidad' => 'Ruralidad',
            'precio_2020' => 'Precio 2020',
            'tasa_uso_2020' => 'Tasa Uso 2020',
            'precio_2021' => 'Precio 2021',
            'tasa_uso_2021' => 'Tasa Uso 2021',
            'precio_2022' => 'Precio 2022',
            'tasa_uso_2022' => 'Tasa Uso 2022',
            'precio_2023' => 'Precio 2023',
            'tasa_uso_2023' => 'Tasa Uso 2023',
        ];
    }

    /* SELECT  codigo, descripcion, id_nomenclador_detalle, nivel, perstacion_trazadora, sexo, grupo_poblacional, nomenclador_detalle, ruralidad
	FROM public.nomenclador
	Where replace(trim(codigo),'-','') = trim('CTC001A97') and
	id_nomenclador_detalle IN (6,7,9,10); */

    public static function obtenerDetalleCodigo($codigo) {
        $codigos = self::find()
        ->select('codigo, descripcion, id_nomenclador_detalle, nivel, perstacion_trazadora, sexo, grupo_poblacional, nomenclador_detalle, ruralidad')
        ->where("replace(trim(codigo),'-','') = trim('$codigo')")
        ->andWhere('id_nomenclador_detalle IN (6,7,9,10)')
        ->asArray()
        ->all();
        $resultado = json_encode($codigos);
        var_dump($resultado); die;
    }

}
