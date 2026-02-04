<?php

namespace common\models;

use Yii;
use common\models\Efector;
use common\models\RrhhEfector;
use common\traits\ParameterQuestionsTrait;

/**
 * This is the model class for table "servicios".
 *
 * @property string $id_servicio
 * @property string $nombre
 *
 * @property Referencia[] $referencias
 * @property ServiciosEfector[] $serviciosEfectors
 * @property Efectores[] $idEfectors
 * @property Turnos[] $turnos
 */
class Servicio extends \yii\db\ActiveRecord
{
    use ParameterQuestionsTrait;
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'servicios';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['nombre'], 'required'],
            [['nombre'], 'string', 'max' => 40],
            [['acepta_turnos', 'acepta_practicas', 'parametros', 'item_name'], 'string']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_servicio' => 'Codigo de servicio',
            'nombre' => 'Nombre del serivicio',
            'acepta_turnos' => 'Acepta Agenda',
            'acepta_practicas' => 'Acepta Practicas',
            'item_name' => 'Rol'
        ];
    }
    
    /**
     * Preguntas para parámetros del chatbot
     * @return array
     */
    public function parameterQuestions()
    {
        return [
            'servicio' => '¿Qué servicio necesitás?',
            'id_servicio' => '¿Qué servicio necesitás?',
            'servicio_asignado' => '¿Qué servicio necesitás?',
        ];
    }
    
    public function getRrhhs()
    {
        return $this->hasMany(RrhhEfector::className(), ['id_rr_hh' => 'id_rr_hh'])
                ->viaTable('rrhh_servicio', ['id_servicio' => 'id_servicio']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getReferencias()
    {
        return $this->hasMany(Referencia::className(), ['id_servicio' => 'id_servicio']);
    }
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getServiciosEfectors()
    {
        return $this->hasMany(ServiciosEfector::className(), ['id_servicio' => 'id_servicio']);
}

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdEfectors()
    {
        return $this->hasMany(Efectores::className(), ['id_efector' => 'id_efector'])->viaTable('ServiciosEfector', ['id_servicio' => 'id_servicio']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTurnos()
    {
        return $this->hasMany(Turnos::className(), ['id_servicio' => 'id_servicio']);
    }
    
    public function getServiciosPorEfector($id) 
    {
        $servicios=Departamento::find()->asArray()
                ->select(['id' => 's.id_servicio', 'name' => 's.nombre'])
                ->from('servicios s')
                ->innerJoin('ServiciosEfector se', 's.id_servicio = se.id_servicio')
                ->where(['se.id_efector' => $id])->all();
        return $servicios;
    }

    public function getEfector()
    {
        return $this->hasMany(Efector::className(), ['id_efector' => 'id_efector'])
            ->viaTable('ServiciosEfector', ['id_servicio' => 'id_servicio']);
    }

    public static function searchServicio($q)
    {
        $results = Servicio::find()
                ->select(['id_servicio AS id', 'nombre AS text'])
                ->where(['like', 'nombre', '%'.$q.'%', false])
                ->asArray()
                ->all();

        return $results;
    }

    public static function puedeAtender($id_servicio){

        $servicio = self::find()->where(['id_servicio'=>$id_servicio])->one();

        if($servicio->item_name == 'Medico' || $servicio->item_name == 'enfermeria'){
            return true;
        }

        return false;

    }

    /**
     * Validar si un id_servicio existe en la base de datos
     * @param int $idServicio
     * @return bool
     */
    public static function validateId($idServicio)
    {
        try {
            $servicio = self::findOne($idServicio);
            return $servicio !== null;
        } catch (\Exception $e) {
            Yii::error("Error validando id_servicio {$idServicio}: " . $e->getMessage(), 'servicio-model');
            return false;
        }
    }

    /**
     * Servicios que aceptan turnos (cache por request)
     * @return Servicio[]
     */
    public static function getServiciosConTurnos()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $cache = self::find()
                ->where(['acepta_turnos' => 'SI'])
                ->orderBy(['nombre' => SORT_ASC])
                ->all();
        } catch (\Exception $e) {
            Yii::error("Error getServiciosConTurnos: " . $e->getMessage(), 'servicio-model');
            $cache = [];
        }
        return $cache;
    }

    /**
     * Genera términos de búsqueda para matchear texto de usuario (ej. "cardiólogo", "cardiologo")
     * a partir del nombre en BD (ej. "CARDIOLOGIA"). Dinámico para cualquier servicio.
     * @param string $nombreServicio Nombre del servicio en BD (ej. "CARDIOLOGIA", "ODONTOLOGIA")
     * @return string[]
     */
    public static function getSearchTermsForNombre($nombreServicio)
    {
        $n = trim($nombreServicio);
        if ($n === '') {
            return [];
        }
        $sinTildes = self::quitarTildes($n);
        $lower = mb_strtolower($sinTildes, 'UTF-8');
        $terms = [$lower];
        // Raíz sin -ia: CARDIOLOGIA -> cardiolog (para matchear cardiólogo, cardiologo, cardiología)
        if (preg_match('/^(.+)(ia|ía)$/u', $lower, $m)) {
            $raiz = $m[1];
            $terms[] = $raiz . 'o';   // cardiologo
            $terms[] = $raiz . 'a';   // cardiologa
            $terms[] = $raiz;         // cardiolog
        }
        // Variantes con tildes comunes
        $terms[] = mb_strtolower($n, 'UTF-8');
        return array_unique($terms);
    }

    /**
     * Quitar tildes para búsqueda insensible a acentos
     */
    private static function quitarTildes($s)
    {
        $map = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N',
        ];
        return strtr($s, $map);
    }

    /**
     * Buscar servicio por nombre de forma dinámica desde la base de datos.
     * Matchea nombre o variantes (cardiólogo, cardiologo, cardiología) contra servicios existentes.
     *
     * @param string $nombre Nombre o mención del servicio (ej. "odontologo", "cardiología", "el oftalmologo")
     * @return int|null ID del servicio encontrado
     */
    public static function findByName($nombre)
    {
        if (empty($nombre) || !is_string($nombre)) {
            return null;
        }
        $nombre = trim($nombre);
        $nombreNorm = strtoupper(self::quitarTildes($nombre));
        $nombreLower = mb_strtolower($nombre, 'UTF-8');

        try {
            // 1) Búsqueda exacta en BD
            $servicio = self::find()->where(['nombre' => $nombreNorm])->one();
            if ($servicio) {
                return (int)$servicio->id_servicio;
            }

            // 2) LIKE en BD por si el nombre en BD tiene formato distinto
            $servicio = self::find()->where(['LIKE', 'nombre', $nombreNorm])->one();
            if ($servicio) {
                return (int)$servicio->id_servicio;
            }

            // 3) Matchear contra términos generados desde todos los servicios (dinámico)
            $servicios = self::getServiciosConTurnos();
            foreach ($servicios as $s) {
                $terms = self::getSearchTermsForNombre($s->nombre);
                foreach ($terms as $term) {
                    if ($term === '' || strlen($term) < 3) {
                        continue;
                    }
                    // El usuario puede decir "el cardiologo" o "cardiologo"
                    if ($nombreLower === $term || strpos($nombreLower, $term) !== false || strpos($term, $nombreLower) !== false) {
                        return (int)$s->id_servicio;
                    }
                }
            }
        } catch (\Exception $e) {
            Yii::error("Error buscando servicio por nombre '{$nombre}': " . $e->getMessage(), 'servicio-model');
        }
        return null;
    }

    /**
     * Extraer servicio desde el texto de la consulta del usuario.
     * Dinámico: usa todos los servicios que aceptan turnos en la BD y sus variantes (Xólogo, Xología).
     * Devuelve el servicio cuyo término matchee con la longitud más larga (más específico).
     *
     * @param string $userQuery Texto de la consulta del usuario
     * @return int|null ID del servicio encontrado
     */
    public static function extractFromQuery($userQuery)
    {
        if (empty($userQuery) || !is_string($userQuery)) {
            return null;
        }
        $queryLower = mb_strtolower(trim($userQuery), 'UTF-8');
        $querySinTildes = self::quitarTildes($queryLower);

        $bestId = null;
        $bestLen = 0;

        foreach (self::getServiciosConTurnos() as $servicio) {
            $terms = self::getSearchTermsForNombre($servicio->nombre);
            foreach ($terms as $term) {
                if ($term === '' || strlen($term) < 3) {
                    continue;
                }
                $termSinTildes = self::quitarTildes($term);
                if (strpos($queryLower, $term) !== false || strpos($querySinTildes, $termSinTildes) !== false) {
                    if (strlen($term) > $bestLen) {
                        $bestLen = strlen($term);
                        $bestId = (int)$servicio->id_servicio;
                    }
                }
            }
        }
        return $bestId;
    }

    /**
     * Buscar y validar servicio desde datos extraídos y userQuery
     * Busca en extractedData primero, luego en userQuery si no se encuentra
     * 
     * @param array $extractedData Datos extraídos por la IA
     * @param string|null $userQuery Texto original de la consulta (opcional)
     * @param string|null $paramName Nombre del parámetro específico a buscar (ej: 'id_servicio', 'servicio_actual')
     * @return array ['found' => bool, 'id' => int|null, 'name' => string|null, 'is_valid' => bool]
     */
    public static function findAndValidate($extractedData, $userQuery = null, $paramName = null)
    {
        $result = [
            'found' => false,
            'id' => null,
            'name' => null,
            'is_valid' => false,
        ];

        // Buscar id_servicio directamente en extracted_data
        if (isset($extractedData['id_servicio'])) {
            $idServicio = $extractedData['id_servicio'];
            if (is_numeric($idServicio)) {
                $result['found'] = true;
                $result['id'] = (int)$idServicio;
                $result['is_valid'] = self::validateId($result['id']);
                if ($result['is_valid']) {
                    $servicio = self::findOne($result['id']);
                    if ($servicio) {
                        $result['name'] = $servicio->nombre;
                    }
                }
                return $result;
            }
        }
        
        // Buscar servicio por nombre en extracted_data
        $servicioName = null;
        $searchKeys = ['servicio', 'servicio_actual'];
        if ($paramName) {
            array_unshift($searchKeys, $paramName);
        }
        
        foreach ($searchKeys as $key) {
            if (isset($extractedData[$key])) {
                $servicioName = $extractedData[$key];
                break;
            }
        }
        
        // Buscar en raw data
        if ($servicioName === null && isset($extractedData['raw'])) {
            if (isset($extractedData['raw']['servicio'])) {
                $servicioName = $extractedData['raw']['servicio'];
            } elseif (isset($extractedData['raw']['names'])) {
                // Buscar nombres que puedan ser servicios
                foreach ($extractedData['raw']['names'] as $name) {
                    $servicioId = self::findByName($name);
                    if ($servicioId !== null) {
                        $result['found'] = true;
                        $result['id'] = $servicioId;
                        $result['is_valid'] = true;
                        $servicio = self::findOne($servicioId);
                        if ($servicio) {
                            $result['name'] = $servicio->nombre;
                        }
                        return $result;
                    }
                }
            }
        }
        
        // Si encontramos un nombre de servicio, buscar su ID
        if ($servicioName !== null) {
            if (is_numeric($servicioName)) {
                $result['found'] = true;
                $result['id'] = (int)$servicioName;
                $result['is_valid'] = self::validateId($result['id']);
            } else {
                $servicioId = self::findByName($servicioName);
                if ($servicioId !== null) {
                    $result['found'] = true;
                    $result['id'] = $servicioId;
                    $result['is_valid'] = true;
                    $servicio = self::findOne($servicioId);
                    if ($servicio) {
                        $result['name'] = $servicio->nombre;
                    }
                }
            }
        }
        
        // Si aún no se encontró, buscar directamente en el texto de la consulta
        if (!$result['found'] && $userQuery !== null) {
            $servicioId = self::extractFromQuery($userQuery);
            if ($servicioId !== null) {
                $result['found'] = true;
                $result['id'] = $servicioId;
                $result['is_valid'] = true;
                $servicio = self::findOne($servicioId);
                if ($servicio) {
                    $result['name'] = $servicio->nombre;
                }
            }
        }

        return $result;
    }


}