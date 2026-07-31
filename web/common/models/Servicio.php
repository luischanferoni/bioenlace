<?php

namespace common\models;

use Yii;
use common\models\Efector;
use common\traits\ParameterQuestionsTrait;

/**
 * Servicio de salud **institucional**: oferta/área que un efector (centro) brinda.
 *
 * Tabla: `servicios` (FHIR ≈ HealthcareService).
 *
 * - Qué es: clínica, kinesiología, imágenes, laboratorio, etc. — catálogo de oferta del establecimiento.
 * - Qué no es: especialidad del título del profesional (matrícula).
 * - Qué no es: acto/práctica SNOMED (ecografía, hemograma…); eso es `actos_clinicos` / ServiceRequest.code.
 * - Relación: {@see ProfesionalEfectorServicio} asigna un profesional a **este** servicio en un efector.
 *
 * Glosario: `docs/producto/glosario-servicio-pes-acto.md`
 *
 * @property string $id_servicio
 * @property string $nombre
 * @property string|null $tipo Tipología de oferta: consulta|diagnostico|laboratorio|procedimiento|soporte
 * @property string|null $specialty_code Código SNOMED/FHIR de tipología de **esta oferta** (no “especialidad del PES”)
 * @property string|null $specialty_system
 * @property string|null $teleconsulta_politica NINGUNA|TODAS|ALGUNAS
 * @property string $reserva_autogestion_paciente SI|NO
 *
 * @property Referencia[] $referencias
 * @property ServiciosEfector[] $serviciosEfectors
 * @property-read Efector[] $efectores
 * @property Turnos[] $turnos
 */
class Servicio extends \yii\db\ActiveRecord
{
    use ParameterQuestionsTrait;

    public const TELECONSULTA_POLITICA_NINGUNA = 'NINGUNA';
    public const TELECONSULTA_POLITICA_TODAS = 'TODAS';
    public const TELECONSULTA_POLITICA_ALGUNAS = 'ALGUNAS';

    public const RESERVA_AUTOGESTION_PACIENTE_SI = 'SI';
    public const RESERVA_AUTOGESTION_PACIENTE_NO = 'NO';

    /** Línea asistencial: consulta / diagnóstico / laboratorio / procedimiento / soporte */
    public const TIPO_CONSULTA = 'consulta';
    public const TIPO_DIAGNOSTICO = 'diagnostico';
    public const TIPO_LABORATORIO = 'laboratorio';
    public const TIPO_PROCEDIMIENTO = 'procedimiento';
    public const TIPO_SOPORTE = 'soporte';

    /**
     * @return list<string>
     */
    public static function tipoValues(): array
    {
        return [
            self::TIPO_CONSULTA,
            self::TIPO_DIAGNOSTICO,
            self::TIPO_LABORATORIO,
            self::TIPO_PROCEDIMIENTO,
            self::TIPO_SOPORTE,
        ];
    }

    /**
     * @return list<string>
     */
    public static function teleconsultaPoliticaValues(): array
    {
        return [
            self::TELECONSULTA_POLITICA_NINGUNA,
            self::TELECONSULTA_POLITICA_TODAS,
            self::TELECONSULTA_POLITICA_ALGUNAS,
        ];
    }

    /**
     * @return list<string>
     */
    public static function reservaAutogestionPacienteValues(): array
    {
        return [
            self::RESERVA_AUTOGESTION_PACIENTE_SI,
            self::RESERVA_AUTOGESTION_PACIENTE_NO,
        ];
    }
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
            [['acepta_turnos', 'acepta_practicas', 'parametros', 'item_name', 'teleconsulta_politica', 'reserva_autogestion_paciente', 'tipo', 'specialty_code', 'specialty_system'], 'string'],
            [['tipo'], 'in', 'range' => self::tipoValues()],
            [['tipo'], 'default', 'value' => self::TIPO_CONSULTA],
            [['specialty_code'], 'string', 'max' => 64],
            [['specialty_system'], 'string', 'max' => 128],
            [['reserva_autogestion_paciente'], 'in', 'range' => self::reservaAutogestionPacienteValues()],
            [['reserva_autogestion_paciente'], 'default', 'value' => self::RESERVA_AUTOGESTION_PACIENTE_NO],
            [['teleconsulta_politica'], 'in', 'range' => self::teleconsultaPoliticaValues()],
            [['teleconsulta_politica'], 'default', 'value' => self::TELECONSULTA_POLITICA_NINGUNA],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_servicio' => 'Servicio del centro',
            'nombre' => 'Nombre del servicio (oferta del centro)',
            'tipo' => 'Tipo de oferta del centro',
            'specialty_code' => 'Tipología de oferta (código SNOMED/FHIR)',
            'specialty_system' => 'System tipología de oferta',
            'acepta_turnos' => 'Acepta Agenda',
            'acepta_practicas' => 'Acepta Practicas',
            'teleconsulta_politica' => 'Política de teleconsulta',
            'reserva_autogestion_paciente' => 'Reserva directa paciente (hub)',
            'item_name' => 'Rol'
        ];
    }

    /**
     * Tipología operativa de la oferta (consulta, laboratorio, …).
     */
    public function getTipoLinea(): string
    {
        $tipo = trim((string) ($this->tipo ?? self::TIPO_CONSULTA));

        return $tipo !== '' ? $tipo : self::TIPO_CONSULTA;
    }

    public function hasSpecialtyCoding(): bool
    {
        return trim((string) ($this->specialty_code ?? '')) !== ''
            && trim((string) ($this->specialty_system ?? '')) !== '';
    }

    /**
     * Tipología SNOMED/FHIR de esta **oferta institucional** (no especialidad del profesional).
     *
     * @return array{code: string, system: string}|null
     */
    public function specialtyCoding(): ?array
    {
        if (!$this->hasSpecialtyCoding()) {
            return null;
        }

        return [
            'code' => trim((string) $this->specialty_code),
            'system' => trim((string) $this->specialty_system),
        ];
    }

    public function esOfertaAsistencial(): bool
    {
        return $this->getTipoLinea() !== self::TIPO_SOPORTE;
    }

    public function permiteReservaAutogestionPaciente(): bool
    {
        if (!$this->esOfertaAsistencial()) {
            return false;
        }

        return strtoupper(trim((string) ($this->reserva_autogestion_paciente ?? self::RESERVA_AUTOGESTION_PACIENTE_NO)))
            === self::RESERVA_AUTOGESTION_PACIENTE_SI;
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
    
    /**
     * Filas {@see ProfesionalEfectorServicio} activas para este servicio.
     */
    public function getProfesionalEfectorServicios()
    {
        return $this->hasMany(ProfesionalEfectorServicio::className(), ['id_servicio' => 'id_servicio'])
            ->andWhere([ProfesionalEfectorServicio::tableName() . '.deleted_at' => null]);
    }

    /**
     * Servicios cuya agenda en site/index (IMP) debe listar cirugías en lugar de internados.
     * @see \Yii::$app->params['serviciosAgendaQuirurgicaIds']
     */
    public static function esServicioAgendaQuirurgica(int $idServicio): bool
    {
        $ids = Yii::$app->params['serviciosAgendaQuirurgicaIds'] ?? [];
        if (is_array($ids) && in_array($idServicio, $ids, true)) {
            return true;
        }
        $s = self::findOne($idServicio);
        if (!$s) {
            return false;
        }
        $n = mb_strtolower((string) $s->nombre, 'UTF-8');

        return (bool) preg_match('/cirug|quir[oó]fano|quirofano|\bqx\b/i', $n);
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
    public function getEfectores()
    {
        return $this->hasMany(Efector::className(), ['id_efector' => 'id_efector'])
            ->viaTable(ServiciosEfector::tableName(), ['id_servicio' => 'id_servicio']);
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
     * Ofertas con tipología SNOMED/FHIR dada. Si hay exactamente una (acepta turnos), la devuelve.
     * 0 o N → null (sin adivinar por nombre).
     */
    public static function findUniqueBySpecialtyCoding(string $specialtyCode, ?string $specialtySystem = null): ?self
    {
        $code = trim($specialtyCode);
        if ($code === '') {
            return null;
        }
        $system = trim((string) ($specialtySystem ?? \common\components\Domain\Clinical\Access\CodingSystems::SNOMED));
        $query = self::find()
            ->andWhere(['specialty_code' => $code])
            ->andWhere(['acepta_turnos' => 'SI']);
        if ($system !== '') {
            $query->andWhere(['specialty_system' => $system]);
        }
        /** @var self[] $rows */
        $rows = $query->orderBy(['id_servicio' => SORT_ASC])->all();
        $oferta = [];
        foreach ($rows as $row) {
            if ($row->esOfertaAsistencial()) {
                $oferta[] = $row;
            }
        }
        $pool = $oferta !== [] ? $oferta : $rows;
        if (count($pool) !== 1) {
            return null;
        }

        return $pool[0];
    }

    /**
     * Genera términos de búsqueda para matchear texto de usuario (ej. "cardiólogo", "cardiologo")
     * a partir del nombre en BD (ej. "CARDIOLOGIA"). Dinámico para cualquier servicio.
     *
     * No inventa sinónimos coloquiales (p. ej. "clínico" ↛ "MED CLINICA"): eso es tipología
     * (`specialty_code` + aliases NL) o selección del profesional.
     *
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
            $terms[] = $raiz . 'o';
            $terms[] = $raiz . 'a';
            $terms[] = $raiz;
        }
        $terms[] = mb_strtolower(self::quitarTildes($n), 'UTF-8');

        return array_values(array_unique(array_filter($terms, static fn ($t) => is_string($t) && $t !== '')));
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
     * Matchea nombre o variantes morfológicas del **nombre completo** (cardiólogo ↔ CARDIOLOGIA).
     * No resuelve apodos cortos contra nombres compuestos: si no hay match claro, null
     * (el cliente ofrece chips / el profesional confirma).
     *
     * @param string $nombre Nombre o mención del servicio (ej. "odontologo", "cardiología")
     * @return int|null ID del servicio encontrado
     */
    public static function findByName($nombre)
    {
        if (empty($nombre) || !is_string($nombre)) {
            return null;
        }
        $nombre = trim($nombre);
        $nombreNorm = strtoupper(self::quitarTildes($nombre));
        $nombreLower = mb_strtolower(self::quitarTildes($nombre), 'UTF-8');

        try {
            // 1) Búsqueda exacta en BD
            $servicio = self::find()->where(['nombre' => $nombreNorm])->one();
            if ($servicio) {
                return (int) $servicio->id_servicio;
            }

            // 2) LIKE en BD por si el nombre en BD tiene formato distinto
            $servicio = self::find()->where(['LIKE', 'nombre', $nombreNorm])->one();
            if ($servicio) {
                return (int) $servicio->id_servicio;
            }

            // 3) Términos del nombre completo: igualdad o el término aparece en el texto del usuario.
            //    No al revés (evita "clinico" ⊆ "med clinico").
            $bestId = null;
            $bestLen = 0;
            foreach (self::getServiciosConTurnos() as $s) {
                foreach (self::getSearchTermsForNombre($s->nombre) as $term) {
                    $termNorm = mb_strtolower(self::quitarTildes($term), 'UTF-8');
                    if ($termNorm === '' || mb_strlen($termNorm, 'UTF-8') < 3) {
                        continue;
                    }
                    $hit = $nombreLower === $termNorm
                        || mb_strpos($nombreLower, $termNorm) !== false;
                    if (!$hit) {
                        continue;
                    }
                    $len = mb_strlen($termNorm, 'UTF-8');
                    if ($len > $bestLen) {
                        $bestLen = $len;
                        $bestId = (int) $s->id_servicio;
                    }
                }
            }
            if ($bestId !== null) {
                return $bestId;
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
        $queryLower = mb_strtolower(self::quitarTildes(trim($userQuery)), 'UTF-8');

        $bestId = null;
        $bestLen = 0;

        foreach (self::getServiciosConTurnos() as $servicio) {
            $terms = self::getSearchTermsForNombre($servicio->nombre);
            foreach ($terms as $term) {
                $termSinTildes = mb_strtolower(self::quitarTildes($term), 'UTF-8');
                if ($termSinTildes === '' || mb_strlen($termSinTildes, 'UTF-8') < 3) {
                    continue;
                }
                if (mb_strpos($queryLower, $termSinTildes) !== false) {
                    $len = mb_strlen($termSinTildes, 'UTF-8');
                    if ($len > $bestLen) {
                        $bestLen = $len;
                        $bestId = (int) $servicio->id_servicio;
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