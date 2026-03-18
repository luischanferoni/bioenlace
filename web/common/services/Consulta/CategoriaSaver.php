<?php

namespace common\services\Consulta;

final class CategoriaSaver
{
    public static function guardarDatosCategoria($modelConsulta, $nombreModelo, $datosCategoria, $tituloCategoria, $pasoConfig = null)
    {
        $claseModelo = "\\common\\models\\{$nombreModelo}";

        if (!class_exists($claseModelo)) {
            throw new \Exception("Modelo {$nombreModelo} no existe");
        }

        $relacion = self::obtenerRelacionConsulta($nombreModelo);
        $modelosExistentes = [];
        if ($relacion && method_exists($modelConsulta, $relacion)) {
            $modelosExistentes = $modelConsulta->$relacion;
            if (!is_array($modelosExistentes)) {
                $modelosExistentes = $modelosExistentes ? [$modelosExistentes] : [];
            }
        }

        $idsGuardados = [];
        foreach ($modelosExistentes as $modelo) {
            if (isset($modelo->id)) {
                $idsGuardados[] = $modelo->id;
            }
        }

        $nuevosIds = [];

        switch ($nombreModelo) {
            case 'ConsultaMedicamentos':
                $nuevosIds = self::guardarMedicamentos($modelConsulta, $datosCategoria, $modelosExistentes, $pasoConfig);
                break;
            case 'ConsultaSintomas':
            case 'ConsultaMotivos':
                $nuevosIds = self::guardarSintomasOMotivos($modelConsulta, $datosCategoria, $nombreModelo, $modelosExistentes, $pasoConfig);
                break;
            case 'ConsultaPracticas':
            case 'ConsultaPracticasOftalmologia':
                $nuevosIds = self::guardarPracticas($modelConsulta, $datosCategoria, $nombreModelo, $modelosExistentes, $pasoConfig);
                break;
            case 'ConsultaDiagnosticos':
                $nuevosIds = self::guardarDiagnosticos($modelConsulta, $datosCategoria, $modelosExistentes, $pasoConfig);
                break;
            default:
                $nuevosIds = self::guardarGenerico($modelConsulta, $claseModelo, $datosCategoria, $modelosExistentes, $pasoConfig);
                break;
        }

        $idsAEliminar = array_diff($idsGuardados, $nuevosIds);
        if (!empty($idsAEliminar) && method_exists($claseModelo, 'hardDeleteGrupo')) {
            $claseModelo::hardDeleteGrupo($modelConsulta->id_consulta, $idsAEliminar);
        }
    }

    public static function obtenerRelacionConsulta($nombreModelo)
    {
        $mapa = [
            'ConsultaMedicamentos' => 'consultaMedicamentos',
            'ConsultaSintomas' => 'consultaSintomas',
            'ConsultaMotivos' => 'motivoConsulta',
            'ConsultaPracticas' => 'practicasPersonaConsultas',
            'ConsultaPracticasOftalmologia' => 'oftalmologiasDP',
            'ConsultaDiagnosticos' => 'diagnosticoConsultas',
        ];

        return $mapa[$nombreModelo] ?? null;
    }

    public static function guardarMedicamentos($modelConsulta, $datosCategoria, $modelosExistentes, $pasoConfig = null)
    {
        $nuevosIds = [];

        if (!is_array($datosCategoria)) {
            return $nuevosIds;
        }

        foreach ($datosCategoria as $medicamentoData) {
            $modelo = new \common\models\ConsultaMedicamentos();

            if (is_array($medicamentoData)) {
                self::mapearDatosAModelo($modelo, $medicamentoData, $pasoConfig, [
                    'id_snomed_medicamento' => ['id_snomed_medicamento', 'snomed_code', 'codigo_snomed', 'conceptId'],
                    'cantidad' => ['Cantidad del medicamento', 'cantidad', 'quantity'],
                    'frecuencia' => ['Frecuencia de administracion', 'frecuencia', 'frequency'],
                    'durante' => ['Duracion del tratamiento', 'durante', 'duration', 'duracion'],
                    'indicaciones' => ['indicaciones', 'indicacion', 'instructions'],
                ]);

                $termino = $medicamentoData['Nombre del medicamento'] ?? $medicamentoData['termino'] ?? $medicamentoData['medicamento'] ?? null;
                $codigoSnomed = $modelo->id_snomed_medicamento;

                if ($termino && $codigoSnomed) {
                    \common\models\snomed\SnomedMedicamentos::crearSiNoExiste($codigoSnomed, $termino);
                }
            }

            $modelo->id_consulta = $modelConsulta->id_consulta;
            $modelo->estado = \common\models\ConsultaMedicamentos::ESTADO_ACTIVO;

            if ($modelo->save()) {
                $nuevosIds[] = $modelo->id;
            }
        }

        return $nuevosIds;
    }

    public static function guardarSintomasOMotivos($modelConsulta, $datosCategoria, $nombreModelo, $modelosExistentes, $pasoConfig = null)
    {
        $nuevosIds = [];

        if (!is_array($datosCategoria)) {
            return $nuevosIds;
        }

        $claseModelo = "\\common\\models\\{$nombreModelo}";

        foreach ($datosCategoria as $item) {
            $modelo = new $claseModelo();

            if (is_string($item)) {
                $modelo->codigo = null;
            } elseif (is_array($item)) {
                self::mapearDatosAModelo($modelo, $item, $pasoConfig, [
                    'codigo' => ['codigo', 'id_snomed', 'snomed_code', 'conceptId', 'codigo_snomed'],
                ]);

                $termino = $item['termino'] ?? $item['texto'] ?? $item['nombre'] ?? null;
                $codigoSnomed = $modelo->codigo;

                if ($termino && $codigoSnomed && $nombreModelo === 'ConsultaSintomas') {
                    \common\models\snomed\SnomedProblemas::crearSiNoExiste($codigoSnomed, $termino);
                }
            }

            $modelo->id_consulta = $modelConsulta->id_consulta;
            if ($nombreModelo === 'ConsultaMotivos') {
                $modelo->origen = \common\models\ConsultaMotivos::ORIGEN_MEDICO;
            }

            if ($modelo->save()) {
                $nuevosIds[] = $modelo->id;
            }
        }

        return $nuevosIds;
    }

    public static function guardarPracticas($modelConsulta, $datosCategoria, $nombreModelo, $modelosExistentes, $pasoConfig = null)
    {
        $nuevosIds = [];

        if (!is_array($datosCategoria)) {
            return $nuevosIds;
        }

        $claseModelo = "\\common\\models\\{$nombreModelo}";

        foreach ($datosCategoria as $practicaData) {
            $modelo = new $claseModelo();

            if (is_string($practicaData)) {
                $modelo->codigo = null;
            } elseif (is_array($practicaData)) {
                self::mapearDatosAModelo($modelo, $practicaData, $pasoConfig, [
                    'codigo' => ['codigo', 'id_snomed', 'snomed_code', 'conceptId', 'codigo_snomed'],
                ]);
            }

            $modelo->id_consulta = $modelConsulta->id_consulta;

            if ($modelo->save()) {
                $nuevosIds[] = $modelo->id;
            }
        }

        return $nuevosIds;
    }

    public static function guardarDiagnosticos($modelConsulta, $datosCategoria, $modelosExistentes, $pasoConfig = null)
    {
        $nuevosIds = [];

        if (!is_array($datosCategoria)) {
            return $nuevosIds;
        }

        foreach ($datosCategoria as $diagnosticoData) {
            $modelo = new \common\models\DiagnosticoConsulta();

            if (is_string($diagnosticoData)) {
                $modelo->codigo = null;
            } elseif (is_array($diagnosticoData)) {
                self::mapearDatosAModelo($modelo, $diagnosticoData, $pasoConfig, [
                    'codigo' => ['codigo', 'codigo_cie10', 'cie10', 'id_cie10'],
                ]);
            }

            $modelo->id_consulta = $modelConsulta->id_consulta;

            if ($modelo->save()) {
                $nuevosIds[] = $modelo->id;
            }
        }

        return $nuevosIds;
    }

    public static function guardarGenerico($modelConsulta, $claseModelo, $datosCategoria, $modelosExistentes, $pasoConfig = null)
    {
        $nuevosIds = [];

        if (!is_array($datosCategoria)) {
            return $nuevosIds;
        }

        foreach ($datosCategoria as $item) {
            $modelo = new $claseModelo();

            if (is_array($item)) {
                if ($pasoConfig) {
                    self::mapearDatosAModelo($modelo, $item, $pasoConfig);
                } else {
                    foreach ($item as $key => $value) {
                        if ($modelo->hasAttribute($key)) {
                            $modelo->$key = $value;
                        }
                    }
                }
            }

            if ($modelo->hasAttribute('id_consulta')) {
                $modelo->id_consulta = $modelConsulta->id_consulta;
            }

            if ($modelo->save()) {
                $nuevosIds[] = $modelo->id ?? $modelo->primaryKey;
            }
        }

        return $nuevosIds;
    }

    public static function mapearDatosAModelo($modelo, $datos, $pasoConfig = null, $mapaCampos = null)
    {
        if ($pasoConfig && isset($pasoConfig['campos'])) {
            foreach ($pasoConfig['campos'] as $campoConfig) {
                $nombreCampo = $campoConfig['nombre'] ?? null;
                $fuentesDatos = $campoConfig['fuentes'] ?? [];

                if ($nombreCampo && $modelo->hasAttribute($nombreCampo)) {
                    foreach ($fuentesDatos as $fuente) {
                        if (isset($datos[$fuente])) {
                            $modelo->$nombreCampo = $datos[$fuente];
                            break;
                        }
                    }
                }
            }
        }

        if ($mapaCampos) {
            foreach ($mapaCampos as $campoModelo => $fuentesPosibles) {
                if ($modelo->hasAttribute($campoModelo)) {
                    foreach ($fuentesPosibles as $fuente) {
                        if (isset($datos[$fuente])) {
                            $modelo->$campoModelo = $datos[$fuente];
                            break;
                        }
                    }
                }
            }
        }

        foreach ($datos as $key => $value) {
            if ($modelo->hasAttribute($key) && !isset($modelo->$key)) {
                $modelo->$key = $value;
            }
        }
    }
}

