<?php 
use buttflattery\formwizard\FormWizard;
use kartik\select2\Select2;
use yii\helpers\ArrayHelper;

echo '<div class="card ">
        <div class="card-header "><h3>Creación de Formularios</h3></div>
        <div class="card-body">';

if($mensajeSuccess != "") echo '<div class="alert alert-success" role="alert">'.$mensajeSuccess.'</div>';
if($mensajeError != "") echo '<div class="alert alert-success" role="alert">'.$mensajeError.'</div>';

echo FormWizard::widget(
    [
        //'enablePersistence' => true,
        'theme'=> 'dots',
        'transitionEffect'=> 'fade',

        'formOptions' => [
            'id' => 'my_form_tabular'
        ],
        'labelPrev'=> 'Anterior',
        'labelNext'=> 'Siguiente',
        'labelFinish'=> 'Guardar',
        'steps' => [
            [
                'model' => $formulario,
                'title' => 'Nuevo formulario',
                'description' => 'Registra los datos del formulario',
                'formInfoText' => 'Especifica los siguientes datos',
                'fieldConfig' => [
                    'nombre' => [
                        'options' => [
                            'type' => 'text',
                            'class' => 'form-control',
                        ]
                    ],
                    'descripcion' => [
                        'options' => [
                            'type' => 'text',
                            'class' => 'form-control',
                        ]
                    ],
                    /*'form_tipo_id' => [
                        'widget' => Select2::class,
                        'containerOptions' => [
                            'class' => 'form-group'
                        ],
                        'options' => [
                            'data' => ArrayHelper::map($tipoForm, 'id', 'nombre'),
                            'options' => [
                                'class' => 'form-control'
                            ],
                            'theme' => Select2::THEME_BOOTSTRAP,
                            'pluginOptions' => [
                                'allowClear' => true,
                                'placeholder' => 'Select Tag'
                            ]
                        ]
                    ],*/
                    'form_tipo_id' => [
                        'containerOptions' => [
                            'class' => 'form-group'
                        ],
                        'labelOptions' => [
                            'label' => 'Tipo'
                        ],
                        'options' => [
                            'type' => 'dropdown',
                            'itemsList' => ArrayHelper::map($tipoForm, 'id', 'nombre'),
                            'prompt' => 'Seleccionar'
                        ]
                    ],
                    'form_estado_id' => [
                        'containerOptions' => [
                            'class' => 'form-group'
                        ],
                        'labelOptions' => [
                            'label' => 'Estado'
                        ],
                        'options' => [
                            'type' => 'dropdown',
                            'itemsList' => [1 => 'habilitado', 2 => 'finalizado'],//ArrayHelper::map(Shoots::find()->all(), 'id', 'name'),
                            'prompt' => 'Seleccionar'
                        ]
                    ],
                    'logo' => [
                        'containerOptions' => [
                            'class' => 'form-group'
                        ],
                        'options' => [
                            'type' => 'file'
                        ]
                    ]
                ]
            ],
            [
                'model' => [$regla],
                'title' => 'Reglas',
                'description' => 'Agrega las reglas',
                'formInfoText' => 'Especifica los siguientes datos',
                'type' => FormWizard::STEP_TYPE_TABULAR,
                'fieldConfig' => [
                    'campo' => [
                        'options' => [
                            'type' => 'text',
                            'class' => 'form-control',
                        ]
                    ],
                    'condicion' => [
                        'containerOptions' => [
                            'class' => 'form-group'
                        ],
                        'labelOptions' => [
                            'label' => 'Condición'
                        ],
                        'options' => [
                            'type' => 'dropdown',
                            'itemsList' => [
                                1=> 'SESSION menor que',
                                2=> 'SESSION mayor que',
                                3=> 'SESSION  igual a',
                                4=> 'SESSION  distinto de',
                                5=> 'ROL  igual a',
                            ],
                            'prompt' => 'Seleccionar'
                        ]
                    ],
                    'form_id' => [
                        'options' => [
                            'type' => 'hidden',                           
                        ]
                    ],
                ]
            ],            
            [
                'model' => [$seccion],
                'title' => 'Secciones',
                'description' => 'Agrega las secciones',
                'formInfoText' => 'Especifica los siguientes datos',
                'type' => FormWizard::STEP_TYPE_TABULAR,
                'fieldConfig' => [
                    'tituloseccion' => [
                        'options' => [
                            'type' => 'text',
                            'class' => 'form-control secciones',
                        ]
                    ],
                    'form_id' => [
                        'options' => [
                            'type' => 'hidden',                           
                        ]
                    ],
                    'mostrartitulo' => [
                        'labelOptions' => [
                            'label' => 'Mostrar Título'
                        ],
                        'options' => [
                            'type' => 'dropdown',
                            'itemsList' => [0 => 'No', 1 => 'Si'], // the radio inputs to be created for the radioList
                        ]
                    ],

                ]
            ],
            [
                //should be a single model or array of Activerecord model objects but for a single model only see wiki on github
                'model' => [$pregunta],
                 'name' => 'preguntas',
                'title' => 'Preguntas',
                'description' => 'Agrega las preguntas',
                'formInfoText' => 'Especifica los siguientes datos',
                //set step type to tabular
                'type' => FormWizard::STEP_TYPE_TABULAR,

                'fieldConfig' => [
                    'titulo' => [
                        'options' => [
                            'type' => 'text',
                            'class' => 'form-control',
                        ]
                    ],                    
                    'tipo_pregunta_id' => [
                        'containerOptions' => [
                            'class' => 'form-group'
                        ],
                        'labelOptions' => [
                            'label' => 'Tipo'
                        ],
                        'options' => [
                            'type' => 'dropdown',
                            'itemsList' => [
                                1=> 'texto',
                                2=> 'textoLargo',
                                3=> 'numero',
                                4=> 'checkbox',
                                5=> 'radioButton',
                                6=> 'fecha',
                                7=> 'N numeros',
                                8=> 'N textos',
                                9=> 'entidad',
                                10=> 'hora',
                                11=> 'select',
                                12=> 'selectMultiple'],//ArrayHelper::map(Shoots::find()->all(), 'id', 'name'),
                            'prompt' => 'Seleccionar'
                        ]
                    ],
                    'seccion_id' => [
                        'containerOptions' => [
                            'class' => 'form-group seccionPregunta'
                        ],
                        'labelOptions' => [
                            'label' => 'Sección'
                        ],
                        'options' => [
                            'type' => 'dropdown',
                            'itemsList' => [1=> '1', 2=> '2', 3=> '3',4=> '4',5=> '5',6=> '6',7=> '7'],//ArrayHelper::map(Shoots::find()->all(), 'id', 'name'),
                            'prompt' => 'Seleccionar'
                        ]
                        ],
                    'valores' => [
                        'options' => [
                            'type' => 'text',
                            'class' => 'form-control',
                        ],
                        'labelOptions' => [
                            'label' => 'Valores posibles(separados por coma) Si es endpoint attr indice, attr valor'
                        ]
                    ], 

                ]
            ]
                ]
            ]

);

echo '</div></div>';

$this->registerJs(
    "
    
        $(document).ready(function() {        
            // Esta funcion permite crear el select de secciones
            // en funcion de las secciones recientemente creadas
            $(document).on('change', '.secciones', function() {
                $('.seccionPregunta').find('option').remove().end();                
                var option = '';
                $('.secciones').each(function(index){                    
                    if($( this ).val() != ''){
                        option = '<option value=\''+index+'\'>'+$( this ).val()+'</option>';
                        $('.seccionPregunta').find('select').append(option);
                    }
                });
            }); 
        });
    "
);
