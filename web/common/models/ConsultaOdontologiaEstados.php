<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "consulta_odontologia_estados".
 *
 * @property int $id_consulta_odontologia_estados
 * @property int $id_consulta
 * @property int $pieza
 * @property string|null $caras
 * @property string $tipo
 */
class ConsultaOdontologiaEstados extends \yii\db\ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    // 4 sectores, el indice de este array indica el sector
    const CARAS = [
        ['IZQ' => 'DISTAL', 'DERE' => 'MESIAL', 'ARRIBA' => 'VESTIBULAR', 'ABAJO' => 'PALATINA'],
        ['IZQ' => 'MESIAL', 'DERE' => 'DISTAL', 'ARRIBA' => 'VESTIBULAR', 'ABAJO' => 'PALATINA'],
        ['IZQ' => 'MESIAL', 'DERE' => 'DISTAL', 'ARRIBA' => 'LINGUAL', 'ABAJO' => 'VESTIBULAR'],
        ['IZQ' => 'DISTAL', 'DERE' => 'MESIAL', 'ARRIBA' => 'LINGUAL', 'ABAJO' => 'VESTIBULAR'],
        // temporales
        ['IZQ' => 'DISTAL', 'DERE' => 'MESIAL', 'ARRIBA' => 'VESTIBULAR', 'ABAJO' => 'PALATINA'],
        ['IZQ' => 'MESIAL', 'DERE' => 'DISTAL', 'ARRIBA' => 'VESTIBULAR', 'ABAJO' => 'PALATINA'],
        ['IZQ' => 'MESIAL', 'DERE' => 'DISTAL', 'ARRIBA' => 'LINGUAL', 'ABAJO' => 'VESTIBULAR'],
        ['IZQ' => 'DISTAL', 'DERE' => 'MESIAL', 'ARRIBA' => 'LINGUAL', 'ABAJO' => 'VESTIBULAR'],        
    ];

    // OCLUSAL del 4 al 8 INCISAL del 1 al 3
    const CENTROS = ['INCISAL', 'OCLUSAL'];

    const CONDICION_ACTIVO = 'ACTIVO';
    const CONDICION_INACTIVO = 'INACTIVO';

    const estadosPiezasDiagnosticos = [
        '80967001' => [
            'nombre' => 'Caries',
            'diagnostico' => '80967001',
            'term' => 'caries dental (trastorno)',
            'path' => [
                'd' => "",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgb(8, 177, 186)",
                'style' => "",
                'transform' => ""
            ],
            'pathPiezaChica' => [
                'd' => "",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgb(8, 177, 186)",
                'style' => "",
                'transform' => ""
            ],
            'pathReferencia' => [
                'd' => "",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgb(8, 177, 186)",
                'style' => "",
                'transform' => ""
            ],
        ],
        'IE' => [
            'nombre' => 'Ind. Extracción',
            'diagnostico' => '234975001', // al elegir este estado esta eligiendo el siguiente diagnostico
            'diagnostico_term' => 'caries de raíz (trastorno)',
            'practica' => '173291009', // al elegir este estado esta eligiendo la siguiente practica
            'practica_term' => 'extracción simple de diente (procedimiento)',
            'path' => [
                'd' => "M 5.177 16.651 L 79.88 17.104 L 79.937 37.788 L 5.117 37.993 L 5.177 16.651 Z M 4.652 44.919 L 79.354 45.372 L 79.412 66.056 L 4.593 66.263 L 4.652 44.919 Z",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgb(8, 177, 186)",
                'style' => "",
                'transform' => ""
            ],
            'pathPiezaChica' => [
                'd' => "M 4.333 11 L 46.967 11.259 L 47 23.063 L 4.299 23.18 L 4.333 11 Z M 4.034 27.133 L 46.667 27.392 L 46.7 39.196 L 4 39.314 L 4.034 27.133 Z",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgb(8, 177, 186)",
                'style' => "",
                'transform' => ""
            ],            
            'pathReferencia' => [
                'd' => "M 0.116 0 L 14.989 0.086 L 15 3.98 L 0.104 4.019 L 0.116 0 Z M 0.012 5.323 L 14.884 5.408 L 14.896 9.302 L 0 9.341 L 0.012 5.323 Z",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgb(8, 177, 186)",
                'style' => "",
                'transform' => ""
            ]
        ],
    ];

    const estadosPiezasPracticas = [
       /* 'NE' => [
            'nombre' => 'No erupcionada', 
            'path' => [
                'd' => "M 38.501 -2 L 47.5 -2 L 47.5 38.5 L 88 38.5 L 88 47.5 L 47.5 47.5 L 47.5 88 L 38.501 88 L 38.501 47.5 L -2 47.5 L -2 38.5 L 38.501 38.5 L 38.501 -2 Z",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgb(73, 80, 87)",
                'style' => "transform-origin: 26.046px -13.031px",
                'transform' => ""
            ],
            'pathPiezaChica' => [
                'd' => "M 22.95 0 L 28.05 0 L 28.05 22.95 L 51 22.95 L 51 28.05 L 28.05 28.05 L 28.05 51 L 22.95 51 L 22.95 28.05 L 0 28.05 L 0 22.95 L 22.95 22.95 L 22.95 0 Z",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgb(73, 80, 87)",
                'style' => "transform-origin: 26.046px -13.031px",
                'transform' => ""
            ],
            'pathReferencia' => [
                'd' => "M 9.002 0 L 11 0 L 11 9 L 20 9 L 20 11 L 11 11 L 11 20 L 9.002 20 L 9.002 11 L 0 11 L 0 9 L 9.002 9 L 9.002 0 Z",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgb(73, 80, 87)",
                'style' => "transform-origin: 2px -5px;",
                'transform' => ""
            ]            
        ],*/
        'P' => [
            'nombre' => 'Ausente',
            'path' => [
                'd' => "M 72.658 -30.855 L 81.657 -30.855 L 81.657 9.645 L 122.157 9.645 L 122.157 18.645 L 81.657 18.645 L 81.657 59.145 L 72.658 59.145 L 72.658 18.645 L 32.157 18.645 L 32.157 9.645 L 72.658 9.645 L 72.658 -30.855 Z",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgb(192, 50, 33)",
                'style' => "transform-origin: 26.046px -13.031px",
                'transform' => "matrix(0.707107, 0.707106, -0.707106, 0.707107, 0.000001, 0)"
            ],
            'pathPiezaChica' => [
                'd' => "M 50.885 -10.741 L 55.985 -10.741 L 55.985 12.209 L 78.935 12.209 L 78.935 17.309 L 55.985 17.309 L 55.985 40.259 L 50.885 40.259 L 50.885 17.309 L 27.935 17.309 L 27.935 12.209 L 50.885 12.209 L 50.885 -10.741 Z",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgb(192, 50, 33)",
                'style' => "transform-origin: 26.046px -13.031px",
                'transform' => "matrix(0.707107, 0.707106, -0.707106, 0.707107, 0.000001, 0)"
            ],            
            'pathReferencia' => [
                'd' => "M 17.002 -12 L 19 -12 L 19 -3 L 28 -3 L 28 -1 L 19 -1 L 19 8 L 17.002 8 L 17.002 -1 L 8 -1 L 8 -3 L 17.002 -3 L 17.002 -12 Z",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgb(192, 50, 33)",
                'style' => "transform-origin: 2px -5px;",
                'transform' => "matrix(0.707107, 0.707106, -0.707106, 0.707107, 0, 0)"
            ]            
        ],
        /*
        'COR' => [
            'nombre' => 'Corona', 
            'path' => [
                'd' => "M 9 42.48 C 9 68.983 37.751 85.548 60.749 72.296 C 71.426 66.146 78 54.781 78 42.48 C 78 15.978 49.249 -0.585 26.248 12.665 C 15.575 18.818 9 30.178 9 42.48 Z M 19.695 42.48 C 19.695 24.193 39.53 12.765 55.399 21.911 C 62.767 26.152 67.305 33.995 67.305 42.48 C 67.305 60.766 47.47 72.195 31.6 63.054 C 24.233 58.807 19.695 50.967 19.695 42.48 Z",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgb(8, 177, 186)",
                'style' => "",
                'transform' => ""
            ],
            'pathPiezaChica' => [
                'd' => "M 7 25.989 C 7 40.584 22.836 49.705 35.499 42.408 C 41.381 39.023 45 32.761 45 25.989 C 45 11.393 29.164 2.272 16.498 9.568 C 10.619 12.956 7 19.21 7 25.989 Z M 12.89 25.989 C 12.89 15.918 23.811 9.623 32.551 14.66 C 36.61 16.995 39.11 21.315 39.11 25.989 C 39.11 36.059 28.189 42.349 19.449 37.317 C 15.39 34.977 12.89 30.663 12.89 25.989 Z",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgb(8, 177, 186)",
                'style' => "",
                'transform' => ""
            ],            
            'pathReferencia' => [
                'd' => "M 0 8.501 C 0 15.033 7.084 19.118 12.75 15.849 C 15.381 14.334 17 11.532 17 8.501 C 17 1.967 9.916 -2.118 4.25 1.151 C 1.62 2.667 0 5.469 0 8.501 Z M 2.635 8.501 C 2.635 3.993 7.521 1.175 11.431 3.43 C 13.247 4.475 14.365 6.409 14.365 8.501 C 14.365 13.008 9.479 15.826 5.566 13.571 C 3.754 12.526 2.635 10.592 2.635 8.501 Z",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgb(8, 177, 186)",
                'style' => "",
                'transform' => ""
            ]            
        ],
        'E' => [
            'nombre' => 'Extraida', 
            'path' => [
                'd' => "M 5.177 16.651 L 79.88 17.104 L 79.937 37.788 L 5.117 37.993 L 5.177 16.651 Z M 4.652 44.919 L 79.354 45.372 L 79.412 66.056 L 4.593 66.263 L 4.652 44.919 Z",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgb(73, 80, 87)",
                'style' => "",
                'transform' => ""
            ],
            'pathPiezaChica' => [
                'd' => "M 4.333 11 L 46.967 11.259 L 47 23.063 L 4.299 23.18 L 4.333 11 Z M 4.034 27.133 L 46.667 27.392 L 46.7 39.196 L 4 39.314 L 4.034 27.133 Z",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgb(73, 80, 87)",
                'style' => "",
                'transform' => ""
            ],            
            'pathReferencia' => [
                'd' => "M 0.116 0 L 14.989 0.086 L 15 3.98 L 0.104 4.019 L 0.116 0 Z M 0.012 5.323 L 14.884 5.408 L 14.896 9.302 L 0 9.341 L 0.012 5.323 Z",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgb(73, 80, 87)",
                'style' => "",
                'transform' => ""
            ]            
        ],
        */
        'RE' => [
            'nombre' => 'Restauración Existente', 
            'path' => [
                'd' => "",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgb(192, 50, 33)",
                'style' => "",
                'transform' => ""
            ],
            'pathPiezaChica' => [
                'd' => "",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgb(192, 50, 33)",
                'style' => "",
                'transform' => ""
            ],            
            'pathReferencia' => [
                'd' => "",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgb(192, 50, 33)",
                'style' => "",
                'transform' => ""
            ],            
        ],        
        'PF' => [
            'nombre' => 'Prótesis Fija', 
            'path' => [
                'd' => "M 8 12 L 78 12 L 78 71.071 L 8 71.071 L 8 12 Z M 19.67 23.359 L 19.67 59.713 L 66.33 59.713 L 66.33 23.359 L 19.67 23.359 Z",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgb(192, 50, 33)",
                'style' => "",
                'transform' => ""
            ],
            'pathPiezaChica' => [
                'd' => "M 6 8 L 46 8 L 46 41.755 L 6 41.755 L 6 8 Z M 12.668 14.491 L 12.668 35.265 L 39.332 35.265 L 39.332 14.491 L 12.668 14.491 Z",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgb(192, 50, 33)",
                'style' => "",
                'transform' => ""
            ],            
            'pathReferencia' => [
                'd' => "M 0 0 L 15 0 L 15 12.658 L 0 12.658 L 0 0 Z M 2.5 2.434 L 2.5 10.224 L 12.499 10.224 L 12.499 2.434 L 2.5 2.434 Z",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgb(192, 50, 33)",
                'style' => "",
                'transform' => ""
            ],            
        ],
        'PR' => [
            'nombre' => 'Prótesis Removible', 
            'path' => [
                'd' => "M 8 5.001 L 8.001 62.806 L 23.867 62.805 C 24.167 38.42 23.865 20.354 23.866 20.354 L 59.332 20.354 L 59.332 61.903 L 78 61.9 L 77.999 5 L 8 5.001 Z",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgb(192, 50, 33)",
                'style' => "",
                'transform' => ""
            ],
            'pathPiezaChica' => [
                'd' => "M 4 4.001 L 4.001 41.161 L 14.2 41.16 C 14.393 25.484 14.199 13.87 14.2 13.87 L 36.999 13.87 L 36.999 40.581 L 49 40.579 L 48.999 4 L 4 4.001 Z",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgb(192, 50, 33)",
                'style' => "",
                'transform' => ""
            ],            
            'pathReferencia' => [
                'd' => "M 0 0 L 0 12.8 L 3.4 12.8 C 3.464 7.4 3.399 3.4 3.4 3.4 L 11.8 3.4 L 11.6 12.6 L 15 12.599 L 15 0 L 0 0 Z",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgb(192, 50, 33)",
                'style' => "",
                'transform' => ""
            ],
        ],
       /* 'SP' => [
            'nombre' => 'Sin Problemas',
            'path' => [
                'd' => "M 0 29.977 L 15.272 48.077 L 53.169 6.223 L 47.51 0 L 15.837 34.502 L 6.787 24.321 L 0 29.977 Z",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgba(26, 160, 83, 0.6)",
                'style' => "",
                'transform' => ""
            ],
            'pathReferencia' => [
                'd' => "M 0 10.712 L 5.458 17.181 L 19 2.224 L 16.977 0 L 5.659 12.329 L 2.425 8.692 L 0 10.712 Z",
                'stroke' => "rgb(0, 0, 0)",
                'fill' => "rgba(26, 160, 83, 0.6)",
                'style' => "",
                'transform' => ""
            ],            
        ],
        'O' => [
            'nombre' => 'Obturado',
        ],*/
    ];
    
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'consultas_odontologia_estados';
    }

    public function behaviors()
    {
        return [
            'blames' => [
                'class' => 'yii\behaviors\AttributeBehavior',
                'attributes' => [
                    \yii\db\ActiveRecord::EVENT_BEFORE_INSERT => ['created_by'],
                ],
                'value' => Yii::$app->user->id,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_consulta', 'tipo', 'codigo'], 'required'],
            ['caras', 'required', 'when' => function($model) {
                return $model->tipo == 'CARAS';
            }],            
            [['id_consulta', 'pieza', 'id_consultas_odontologia_estados'], 'integer'],
            [['caras', 'codigo', 'tipo'], 'string'],
        ];
    }

    /**
     * Retorna los campos requeridos en lenguaje natural para prompts de IA
     * @return array
     */
            public function requeridosPrompt()
    {
        return [
            "Tipo",
            "Codigo",
        ];
    }


    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_consultas_odontologia_estados' => 'Id',
            'id_consulta' => 'Id Consulta',
            'pieza' => 'Pieza',
            'caras' => 'Caras',            
            'tipo' => 'Tipo',            
        ];
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getOdontoNomenclador()
    {
        return $this->hasOne(OdontoNomenclador::className(), ['codigo_faco' => 'codigo']);
    }

    public static function getPorPaciente($idPersona)
    {
        return self::find()
            ->innerJoin('consultas', 
                'consultas.id_consulta = consultas_odontologia_estados.id_consulta AND id_persona = '.$idPersona.
                ' AND consultas_odontologia_estados.condicion = "'.self::CONDICION_ACTIVO.'"'. ' AND consultas.deleted_at IS NULL')
            ->asArray()
            ->all();
    }

    public static function getPorPacienteHastaConsulta($idPersona, $idConsulta)
    {
        return self::find()
            ->innerJoin('consultas', 
                'consultas.id_consulta = consultas_odontologia_estados.id_consulta AND id_persona = '.$idPersona.
                ' AND consultas.id_consulta <= '.$idConsulta.
                ' AND consultas_odontologia_estados.condicion = "'.self::CONDICION_ACTIVO.'"'. ' AND consultas.deleted_at IS NULL')
            ->asArray()
            ->all();
    }

    public static function getCPOHastaConsulta($idPersona, $idConsulta)
    {
        $estados = self::getPorPacienteHastaConsulta($idPersona, $idConsulta);

        return self::getCPO($estados);
    }

    public static function getCPO($estados)
    {       
        $indicec = 0;
        $indicee = 0;
        $indiceo = 0;
        $indiceC = 0;
        $indiceP = 0;
        $indiceO = 0;
        
        foreach ($estados as $estado) {
            if ($estado['pieza'] >= 51) {
                if ($estado['codigo'] == "C" || $estado['codigo'] == 80967001 || $estado['codigo'] == "IE") {
                    $indicec++;
                }
                if ($estado['codigo'] == "P") {
                    $indicee++;
                }
                if ($estado['codigo'] == "O") {
                    $indiceo++;
                }
                if ($estado['codigo'] == "RE") {
                    $indiceo++;
                }
            }  else {
                if ($estado['codigo'] == "C" || $estado['codigo'] == 80967001 || $estado['codigo'] == "IE") {
                    $indiceC++;
                }
                if ($estado['codigo'] == "P") {
                    $indiceP++;
                }
                if ($estado['codigo'] == "O") {
                    $indiceO++;
                }
                if ($estado['codigo'] == "RE") {
                    $indiceO++;
                }
            }
        }

        return [
            'c' => $indicec,
            'e' => $indicee,
            'o' => $indiceo,
            'C' => $indiceC,
            'P' => $indiceP,
            'O' => $indiceO,
        ];
    }

    /**
     * Mientras la consulta no este finalizada (nueva o editando) el usuario
     * puede hacer un hard delete
     */
    public static function hardDeleteGrupo($id_consulta, $ids)
    {
        if (count($ids) > 0 && isset($id_consulta) && $id_consulta != "" && $id_consulta != 0) {
            self::hardDeleteAll([
                'AND',
                ['in', 'id_consultas_odontologia_estados', $ids],
                ['=', 'id_consulta', $id_consulta]
            ]);
        }
    }     
}
