<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "percentilos".
 *
 * @property int $id
 * @property string $nombre
 * @property int $edad
 * @property string $tipo_edad
 * @property string $sexo
 * @property float $percentilo_1
 * @property float $percentilo_2
 * @property float $percentilo_3
 * @property float $percentilo_4
 * @property float $percentilo_5
 * @property float $percentilo_6
 * @property float $percentilo_7
 */
class Percentilos extends \yii\db\ActiveRecord
{
    const ARRAY_PERCENTILOS_1 = [
        'P1' => '3',
        'P2' => '10',
        'P3' => '25',
        'P4' => '50',
        'P5' => '75',
        'P6' => '90',
        'P7' => '97',
    ];
    const ARRAY_PERCENTILOS_2 = [
        'P1' => '3',
        'P2' => '10',
        'P3' => '25',
        'P4' => '50',
        'P5' => '75',
        'P6' => '85',
        'P7' => '97',
    ];

    const CONFIGURACION_PERCENTILOS = [
        'peso' => [
            'nombre' => 'Peso',
            'labels' => self::ARRAY_PERCENTILOS_1,
            ],
        'talla'=> [
            'nombre' => 'Talla',
            'labels' => self::ARRAY_PERCENTILOS_1,
            ],
        'imc'=>[
            'nombre' => 'IMC',
            'labels' => self::ARRAY_PERCENTILOS_2,
            ],
        'pcefalico'=> [
            'nombre' => 'Perímetro Cefalico',
            'labels' => self::ARRAY_PERCENTILOS_1,
            ],
    ];

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'percentilos';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['nombre', 'edad', 'tipo_edad', 'sexo', 'percentilo_1', 'percentilo_2', 'percentilo_3', 'percentilo_4', 'percentilo_5', 'percentilo_6', 'percentilo_7'], 'required'],
            [['nombre', 'tipo_edad', 'sexo'], 'string'],
            [['edad'], 'integer'],
            [['percentilo_1', 'percentilo_2', 'percentilo_3', 'percentilo_4', 'percentilo_5', 'percentilo_6', 'percentilo_7'], 'number'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'nombre' => 'Nombre',
            'edad' => 'Edad',
            'tipo_edad' => 'Tipo Edad',
            'sexo' => 'Sexo',
            'percentilo_1' => 'Percentilo 1',
            'percentilo_2' => 'Percentilo 2',
            'percentilo_3' => 'Percentilo 3',
            'percentilo_4' => 'Percentilo 4',
            'percentilo_5' => 'Percentilo 5',
            'percentilo_6' => 'Percentilo 6',
            'percentilo_7' => 'Percentilo 7',
        ];
    }
}
