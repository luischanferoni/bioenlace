<?php

namespace common\components;

use Yii;
use common\models\Servicio;
use common\models\Efector;
use common\models\Rrhh;
use common\models\Practica;
use common\models\Localidad;
use common\models\Medicamento;
use common\models\ConsultaSintomas;

/**
 * Resuelve valores de la consulta por tipo de entidad y propaga el resultado
 * a todas las claves de la familia de parámetros (ej. "odontólogo" → servicio → id_servicio,
 * id_servicio_asignado, servicio_actual).
 *
 * Escalable: agregar un nuevo tipo = nueva entrada en los mapas + modelo con findAndValidate.
 */
class EntityParameterResolver
{
    /**
     * Por cada tipo de entidad: clave canónica (donde se escribe el id) y familia de parámetros.
     * @var array<string, array{canonical: string, family: string[]}>
     */
    private static $entityTypeConfig = [
        'servicio' => [
            'canonical' => 'id_servicio',
            'family' => ['id_servicio', 'id_servicio_asignado', 'servicio_actual', 'servicio', 'servicio_asignado'],
        ],
        'efector' => [
            'canonical' => 'id_efector',
            'family' => ['id_efector', 'efector', 'centro_salud'],
        ],
        'rrhh' => [
            'canonical' => 'id_rr_hh',
            'family' => ['id_rr_hh', 'id_rrhh', 'rrhh', 'profesional'],
        ],
        'practica' => [
            'canonical' => 'id_practica',
            'family' => ['id_practica', 'practica', 'tipo_practica'],
        ],
        'localidad' => [
            'canonical' => 'id_localidad',
            'family' => ['id_localidad', 'localidad', 'ubicacion', 'zona'],
        ],
        'medicamento' => [
            'canonical' => 'id_medicamento',
            'family' => ['id_medicamento', 'medicamento'],
        ],
        'sintoma' => [
            'canonical' => 'sintoma',
            'family' => ['sintoma', 'sintomas'],
        ],
    ];

    /**
     * Tipo de entidad => clase del modelo (debe tener findAndValidate).
     * @var array<string, string>
     */
    private static $entityTypeToModel = [
        'servicio' => Servicio::class,
        'efector' => Efector::class,
        'rrhh' => Rrhh::class,
        'practica' => Practica::class,
        'localidad' => Localidad::class,
        'medicamento' => Medicamento::class,
        'sintoma' => ConsultaSintomas::class,
    ];

    /** @var array<string, string> paramName (lowercase) => entityType */
    private static $paramToEntityType;

    /**
     * Construye el mapa paramName → entityType desde las familias.
     */
    private static function buildParamToEntityTypeMap()
    {
        if (self::$paramToEntityType !== null) {
            return;
        }
        self::$paramToEntityType = [];
        foreach (self::$entityTypeConfig as $entityType => $config) {
            foreach ($config['family'] as $paramName) {
                self::$paramToEntityType[strtolower(trim($paramName))] = $entityType;
            }
        }
    }

    /**
     * Devuelve el tipo de entidad para un nombre de parámetro, o null si no pertenece a ninguna familia.
     *
     * @param string $paramName
     * @return string|null
     */
    public static function getSemanticType($paramName)
    {
        self::buildParamToEntityTypeMap();
        $key = strtolower(trim($paramName));
        return self::$paramToEntityType[$key] ?? null;
    }

    /**
     * Dada una lista de nombres de parámetros (de las acciones), devuelve los tipos de entidad únicos necesarios.
     *
     * @param array $paramNames Lista de nombres de parámetros (ej. ['id_servicio_asignado', 'id_efector'])
     * @return array Lista de tipos (ej. ['servicio', 'efector'])
     */
    public static function getRequiredTypesFromParamNames(array $paramNames)
    {
        self::buildParamToEntityTypeMap();
        $types = [];
        foreach ($paramNames as $name) {
            $key = strtolower(trim($name));
            if (isset(self::$paramToEntityType[$key])) {
                $t = self::$paramToEntityType[$key];
                if (!in_array($t, $types, true)) {
                    $types[] = $t;
                }
            }
        }
        return $types;
    }

    /**
     * Dada una lista de acciones (cada una con 'parameters' => [['name' => ...], ...]),
     * devuelve los tipos de entidad únicos necesarios.
     *
     * @param array $actions
     * @return array
     */
    public static function getRequiredTypesFromActions(array $actions)
    {
        $paramNames = [];
        foreach ($actions as $action) {
            $parameters = $action['parameters'] ?? [];
            foreach ($parameters as $param) {
                $name = $param['name'] ?? null;
                if ($name !== null) {
                    $paramNames[] = $name;
                }
            }
        }
        return self::getRequiredTypesFromParamNames($paramNames);
    }

    /**
     * Clave canónica para un tipo de entidad (donde se escribe el id resuelto).
     *
     * @param string $entityType
     * @return string|null
     */
    public static function getCanonicalKey($entityType)
    {
        return self::$entityTypeConfig[$entityType]['canonical'] ?? null;
    }

    /**
     * Resuelve por tipo de entidad y propaga a toda la familia en extractedData.
     * Por cada tipo en requiredTypes: si ya existe valor en la clave canónica válido, propaga a la familia;
     * si no, llama una vez al modelo findAndValidate y escribe en canónico + familia.
     *
     * @param array $extractedData Datos extraídos normalizados (se modifica por referencia)
     * @param string|null $userQuery Texto original de la consulta
     * @param array $requiredTypes Tipos necesarios (ej. ['servicio', 'efector'])
     * @return array extractedData actualizado
     */
    public static function resolve(array $extractedData, $userQuery, array $requiredTypes)
    {
        if (empty($requiredTypes)) {
            return $extractedData;
        }

        foreach ($requiredTypes as $entityType) {
            $config = self::$entityTypeConfig[$entityType] ?? null;
            $modelClass = self::$entityTypeToModel[$entityType] ?? null;
            if (!$config || !$modelClass || !class_exists($modelClass)) {
                continue;
            }
            if (!method_exists($modelClass, 'findAndValidate')) {
                continue;
            }

            $canonical = $config['canonical'];
            $family = $config['family'];

            // Si ya hay un valor válido en la clave canónica, solo propagar a la familia
            $existingId = null;
            if (isset($extractedData[$canonical]) && $extractedData[$canonical] !== null && $extractedData[$canonical] !== '') {
                $existingId = is_numeric($extractedData[$canonical]) ? (int) $extractedData[$canonical] : $extractedData[$canonical];
            }
            if ($existingId !== null) {
                self::propagateToFamily($extractedData, $family, $existingId);
                continue;
            }

            // Resolver una vez con la clave canónica
            try {
                $result = call_user_func([$modelClass, 'findAndValidate'], $extractedData, $userQuery, $canonical);
                if (!empty($result['found']) && !empty($result['is_valid']) && isset($result['id'])) {
                    $id = $result['id'];
                    $extractedData[$canonical] = $id;
                    self::propagateToFamily($extractedData, $family, $id);
                    if (YII_DEBUG) {
                        Yii::info("EntityParameterResolver: tipo '{$entityType}' resuelto a id {$id}, propagado a familia.", 'entity-parameter-resolver');
                    }
                }
            } catch (\Exception $e) {
                Yii::error("EntityParameterResolver: error resolviendo tipo '{$entityType}': " . $e->getMessage(), 'entity-parameter-resolver');
            }
        }

        return $extractedData;
    }

    /**
     * Escribe el mismo valor en todas las claves de la familia que aún no tengan valor.
     *
     * @param array $extractedData
     * @param array $family
     * @param mixed $value
     */
    private static function propagateToFamily(array &$extractedData, array $family, $value)
    {
        foreach ($family as $key) {
            if (!isset($extractedData[$key]) || $extractedData[$key] === null || $extractedData[$key] === '') {
                $extractedData[$key] = $value;
            }
        }
    }
}
