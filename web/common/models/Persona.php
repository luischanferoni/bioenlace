<?php

namespace common\models;

use Yii;
use common\models\Turno;

/**
 * This is the model class for table "personas".
 *
 * @property integer $id_persona
 * @property integer $acredita_identidad
 * @property string $apellido
 * @property string $otro_apellido
 * @property string $apellido_paterno
 * @property string $apellido_materno
 * @property string $nombre
 * @property string $otro_nombre
 * @property integer $id_tipodoc
 * @property string $documento
 * @property integer $documento_propio
 * @property string $sexo
 * @property integer $sexo_biologico
 * @property integer $genero
 * @property string $fecha_nacimiento
 * @property string $id_estado_civil
 * @property string $fecha_defuncion
 * @property string $usuario_alta
 * @property string $fecha_alta
 * @property string $usuario_mod
 * @property string $fecha_mod
 * @property integer $id_user
 * *
 * @property PersonaMails[] $personaMails
 * @property PersonaTelefono[] $personaTelefonos
 * @property TiposDocumentos $idTipodoc
 * @property EstadoCivil $idEstadoCivil
 * @property PersonasDomicilios[] $personasDomicilios
 * @property Domicilios[] $idDomicilios
 * @property RrHh[] $rrHhs
 * @property Usuarios[] $usuarios
 */
class Persona extends \yii\db\ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;
    
    protected $oldAttributes;
    public $fecha_nacimiento_1;
    public $motivo_acredita;

    // El estado actual del paciente, se corresponde con las constantes de abajo
    public $estadoPaciente = [
        'estado' => Persona::ESTADO_SIN_ESTADO,
        'id' => 0,
        'turnos' => []
    ];

    const ESTADO_SIN_ESTADO = 'SIN_ESTADO';
    const ESTADO_ESPERANDO_TURNO = 'ESPERANDO_TURNO';
    const ESTADO_INTERNADA = 'INTERNADA';
    const ESTADO_EN_GUARDIA = 'EN_GUARDIA';
    /*
    *   Para establecer los campos obligatorios de la persona, excluyendo id_user
    */
    const SCENARIOCREATEUPDATE = 'scenarioregistrar';
    const SCENARIOSEARCH = 'scenariobuscar';
    /*
    *   Para establecer id_user, asociando asi a una persona con un usuario del sistema
    */
    const SCENARIOUSERUPDATE = 'scenarioactualizaruser';

    const FORMATO_NOMBRE_A_OA_N_ON = 'apellido_otroapellido_nombre_otronombre';
    const FORMATO_NOMBRE_A_N = 'apellido_nombre';
    const FORMATO_NOMBRE_A_N_D = 'apellido_nombre_dni';

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'personas';
    }

    // scenarios encapsulated
    public function getCustomScenarios()
    {
        return [
            self::SCENARIOCREATEUPDATE      =>  ['id_tipodoc', 'fecha_nacimiento', 'apellido', 'apellido_materno', 'apellido_paterno', 'otro_apellido', 'otro_nombre', 'nombre', 'documento', 'sexo_biologico', 'genero', 'sexo_biologico', 'id_estado_civil', 'acredita_identidad'],
            self::SCENARIOSEARCH      =>  ['id_tipodoc', 'fecha_nacimiento', 'apellido', 'apellido_materno', 'apellido_paterno', 'otro_apellido', 'otro_nombre', 'nombre', 'documento', 'genero', 'sexo_biologico', 'fecha_nacimiento_1', 'motivo_acredita', 'acredita_identidad'],
            self::SCENARIOUSERUPDATE   =>  ['id_user'],
        ];
    }
    // get scenarios
    public function scenarios()
    {
        $scenarios = $this->getCustomScenarios();
        return $scenarios;
    }

    // modify items required for rules
    public function ModifyRequired()
    {
        $allscenarios = $this->getCustomScenarios();
        // published not required
        $allscenarios[self::SCENARIOSEARCH] = array_diff($allscenarios[self::SCENARIOSEARCH], ['apellido_paterno', 'apellido_materno', 'otro_apellido', 'otro_nombre', 'motivo_acredita']);
        $allscenarios[self::SCENARIOCREATEUPDATE] = array_diff($allscenarios[self::SCENARIOCREATEUPDATE], ['apellido_paterno', 'apellido_materno', 'otro_apellido', 'otro_nombre']);
        $allscenarios[self::SCENARIOUSERUPDATE] = $allscenarios[self::SCENARIOUSERUPDATE];
        return $allscenarios;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        // get scenarios
        $allscenarios = $this->ModifyRequired();
        return [
            [$allscenarios[self::SCENARIOCREATEUPDATE], 'required', 'on' => self::SCENARIOCREATEUPDATE],
            [$allscenarios[self::SCENARIOSEARCH], 'required', 'on' => self::SCENARIOSEARCH],
            [$allscenarios[self::SCENARIOUSERUPDATE], 'required', 'on' => self::SCENARIOUSERUPDATE],
            [['acredita_identidad', 'id_tipodoc', 'documento_propio', 'genero', 'sexo_biologico', 'id_estado_civil', 'id_user'], 'integer'],
            ['motivo_acredita', 'motivoCondicional', 'on' => self::SCENARIOSEARCH, 'skipOnEmpty' => false],
            ['id_user', 'unique', 'on' => self::SCENARIOUSERUPDATE],
            ['documento', 'unique', 'targetAttribute' => ['documento', 'fecha_nacimiento'], 'on' => self::SCENARIOCREATEUPDATE],
            [['fecha_nacimiento', 'fecha_defuncion', 'fecha_alta', 'fecha_mod'], 'safe'],
            [['apellido', 'nombre'], 'string', 'max' => 60],
            /*[
                ['apellido', 'nombre'], 'match',
                'pattern' => '/^[a-zA-Z\s]+$/',
                'message' => 'El campo solo debe contener letras'
            ],*/
            //['apellido', 'match', 'pattern' => '/^[a-zA-Z\d]+$/', 'on' => self::SCENARIOCREATEUPDATE],
            [['otro_apellido', 'apellido_paterno', 'apellido_materno', 'otro_nombre'], 'string', 'max' => 255],
            [['apellido', 'nombre', 'otro_apellido', 'apellido_paterno', 'apellido_materno', 'otro_nombre'], 'match', 'pattern' => '/^[A-ZÁÉÍÓÚÑa-záéíóúñ\s]+$/', 'message' => 'El campo solo debe contener letras'],
            [['documento'], 'string', 'max' => 8],
            [['documento'], 'documentoUnico'],
            [['usuario_alta', 'usuario_mod'], 'string', 'max' => 40],
            [['fecha_nacimiento_1'], 'validarFechaNacimiento']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_persona' => 'Nro de Persona',
            'acredita_identidad' => 'Acredita Identidad',
            'apellido' => 'Apellido',
            'otro_apellido' => 'Otro Apellido',
            'apellido_paterno' => 'Apellido Paterno',
            'apellido_materno' => 'Apellido Materno',
            'nombre' => 'Nombre',
            'otro_nombre' => 'Otro Nombre',
            'id_tipodoc' => 'Tipo documento',
            'documento' => 'Nro de Documento',
            'documento_propio' => 'Documento Propio',
            'sexo' => 'Sexo Biologico',
            'genero' => 'Género Legal',
            'fecha_nacimiento' => 'Fecha de Nacimiento',
            'id_estado_civil' => 'Estado Civil',
            'fecha_defuncion' => 'Fecha Defunción',
            'usuario_alta' => 'Usuario Alta',
            'fecha_alta' => 'Fecha Alta',
            'usuario_mod' => 'Usuario Modificacion',
            'fecha_mod' => 'Fecha Modificacion',
            'id_user' => 'Usuario',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getMails()
    {
        return $this->hasMany(Persona_mails::className(), ['id_persona' => 'id_persona']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTelefonos()
    {
        return $this->hasMany(PersonaTelefono::className(), ['id_persona' => 'id_persona']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAtencionesEnfermeria()
    {
        return $this->hasMany(ConsultaAtencionesEnfermeria::className(), ['id_persona' => 'id_persona'])->orderBy(['fecha_creacion' => SORT_DESC]);
    }
   /**
     * @return \yii\db\ActiveQuery
     */
    //TODO: borrar este metodo y la vista de atenciones de enfermeria sisse v1 del menu personas (despues de la migracion)
    public function getAtencionesEnfermeriaV1()
    {
        return $this->hasMany(ConsultaAtencionesEnfermeria::className(), ['id_persona' => 'id_persona'])->where("fecha_creacion < '2024-03-28'")->andWhere("deleted_at IS NULL")->orderBy(['fecha_creacion' => SORT_DESC]);
    }
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTipoDocumento()
    {
        return $this->hasOne(Tipo_documento::className(), ['id_tipodoc' => 'id_tipodoc']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getEstadoCivil()
    {
        return $this->hasOne(EstadoCivil::className(), ['id_estado_civil' => 'id_estado_civil']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDomicilios()
    {
        return $this->hasMany(Persona_domicilio::className(), ['id_persona' => 'id_persona']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getProfesionalSalud()
    {
        return $this->hasMany(ProfesionalSalud::className(), ['id_persona' => 'id_persona']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAntecedentes()
    {
        return $this->hasMany(PersonasAntecedente::className(), ['id_persona' => 'id_persona']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(\webvimark\modules\UserManagement\models\User::className(), ['id' => 'id_user']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getInternaciones()
    {
        return $this->hasMany(SegNivelInternacion::className(), ['id_persona' => 'id_persona']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getEncuentrosGuardia()
    {
        return $this->hasMany(Guardia::className(), ['id_persona' => 'id_persona']);
    }

    public function getRrhhEfector()
    {
        return $this->hasMany(RrhhEfector::className(), ['id_persona' => 'id_persona']);
    }

    public function getTurnos()
    {
        return $this->hasMany(Turno::className(), ['id_persona' => 'id_persona'])->orderBy(['fecha' => SORT_DESC]);;
    }

    public function getTurnosActivos($idServicio, $idRrhh)
    {
        $query = Turno::find();
        $turnos = $query->andWhere(['id_persona' => $this->id_persona])
            ->andWhere(['estado' => 'PENDIENTE'])
            ->andWhere([
                'or',
                ['id_servicio_asignado' => $idServicio],
                ['id_rrhh_servicio_asignado' => $idRrhh]
            ])
            ->one();
    }

    public function getUltimaEncuestaParchesMamarios()
    {
        $query = EncuestaParchesMamarios::find();
        $encuesta = $query->andWhere(['id_persona' => $this->id_persona])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['id' => SORT_DESC])
            ->limit(1)
            ->one();

        return $encuesta;
    }

    public function getNombreCompleto($formato)
    {
        switch ($formato) {
            case self::FORMATO_NOMBRE_A_OA_N_ON:
                $otroApellido = ($this->otro_apellido != null && $this->otro_apellido != '') ? ' ' . $this->otro_apellido : '';
                $otroNombre = ($this->otro_nombre != null && $this->otro_nombre != '') ? ' ' . $this->otro_nombre : '';

                return $this->apellido . $otroApellido . ', ' . $this->nombre . $otroNombre;
                break;

            case self::FORMATO_NOMBRE_A_N:
                return $this->apellido . ', ' . $this->nombre;
                break;

            case self::FORMATO_NOMBRE_A_N_D:
                return $this->apellido . ', ' . $this->nombre . ' - ' . $this->documento;
                break;

            default:
                return $this->apellido . ', ' . $this->nombre;
                break;
        }
    }

    //el parametro $fecha_prestacion se utiliza para calcular la edad de la persona al momento del que se realizo una consulta.
    public function getEdad($fecha_prestacion = "")
    {
        list($Y, $m, $d) = explode("-", $this->fecha_nacimiento);

        if ($fecha_prestacion == "") {

            return (date("md") < $m . $d ? date("Y") - $Y - 1 : date("Y") - $Y);
        } else {

            $fecha = strtotime($fecha_prestacion);
            return (date("md", $fecha) < $m . $d ? date("Y", $fecha) - $Y - 1 : date("Y", $fecha) - $Y);
        }
    }


    //Calcula la edad en años, meses y días
    public function getEdadBebe()
    {
        //Calculo fecha de hoy
        $expression = new \yii\db\Expression('NOW()');
        $fecha_hora = (new \yii\db\Query)->select($expression)->scalar();
        list($fecha_hoy, $hora) = explode(" ", $fecha_hora);
        list($Y1, $m1, $d1) = explode("-", $fecha_hoy);
        list($Y, $m, $d) = explode("-", $this->fecha_nacimiento);


        $anios = $Y1 - $Y; //Calculo años
        $meses = $m1 - $m; //Calculo meses
        $dias = $d1 - $d; //Calculo días

        if ($dias < 0) {
            --$meses;

            //sumo a $dias los dias que tiene el mes anterior a la fecha de hoy 
            switch ($m1) {
                case 1:
                    $dias_mes_anterior = 31;
                    break;
                case 2:
                    $dias_mes_anterior = 31;
                    break;
                case 3:
                    if (self::bisiesto($Y1)) {
                        $dias_mes_anterior = 29;
                        break;
                    } else {
                        $dias_mes_anterior = 28;
                        break;
                    }
                case 4:
                    $dias_mes_anterior = 31;
                    break;
                case 5:
                    $dias_mes_anterior = 30;
                    break;
                case 6:
                    $dias_mes_anterior = 31;
                    break;
                case 7:
                    $dias_mes_anterior = 30;
                    break;
                case 8:
                    $dias_mes_anterior = 31;
                    break;
                case 9:
                    $dias_mes_anterior = 31;
                    break;
                case 10:
                    $dias_mes_anterior = 30;
                    break;
                case 11:
                    $dias_mes_anterior = 31;
                    break;
                case 12:
                    $dias_mes_anterior = 30;
                    break;
            }

            $dias = $dias + $dias_mes_anterior;
        }

        //Si el numero de meses es negativo
        if ($meses < 0) {
            --$anios;
            $meses = $meses + 12;
        }
        if ($anios !== 0) {
            $edad_bebe = $anios . '  años,  ' . $meses . '  meses,  ' . $dias . '  días  ';
        } else {
            $edad_bebe =  $meses . '  meses,  ' . $dias . '  días  ';
        }
        return $edad_bebe;
    }

    /**
     *  Pasa la fecha al formato ISO "$y-$m-$d"
     *  @return  "$y-$m-$d"
     */
    public function pasarFechaFormatISO($date)
    {
        list($d, $m, $y) = explode("/", $date);
        return "$y-$m-$d";
    }

    public function afterFind()
    {
        $this->oldAttributes = $this->attributes;
        return parent::afterFind();
    }

    // funcion para consultar los departamentos segun un id de provincia
    public function getDepartamentoxidprovincia($id)
    {
        //$departamentos=Departamento::find()->asArray()->select('id_departamento, nombre')->from('departamentos')->where(['id_provincia' => $id])->all();
        $departamentos = Departamento::find()->asArray()->select(['id' => 'id_departamento', 'name' => 'nombre'])
            ->from('departamentos')
            ->where(['id_provincia' => $id])
            ->orderBy('nombre')->all();
        return $departamentos;
    }

    // funcion para consultar las localidades segun el id de departamento
    public function getLocalidadxiddepartamento($idd)
    {
        $localidades = Localidad::find()->asArray()->select(['id' => 'id_localidad', 'name' => 'nombre'])
            ->from('localidades')
            ->where(['id_departamento' => $idd])
            ->orderBy('nombre')->all();
        return $localidades;
    }

    public function getDatosPersonaXDni($dni, $nombre)
    {
        $personas = Persona::find()->asArray()->select(['nombre', 'documento', 'apellido'])
            ->from('personas')
            ->where(['documento' => $dni])
            //                        ->andWhere(['nombre' => $nombre])
            ->orderBy('nombre')->all();
        return $personas;
    }

    public static function getDatosPersonaXId_usuario($id_user)
    {
        $personas = Persona::findOne(['id_user' => $id_user]);
        return $personas;
    }

    public static function Autocomplete($q)
    {
        $out = ['id' => '', 'text' => ''];

        $query = new yii\db\Query;
        $query->select([
            "CONCAT(CONCAT(apellido, ', ', nombre), ' - ', documento) AS text",
            '`personas`.id_persona AS id',
            'IF (numero_hc IS NULL, "--", numero_hc) AS hc'
        ])
            ->from('personas')
            ->where(['like', 'CONCAT(apellido," ",nombre)', '%' . $q . '%', false])
            ->orwhere(['like', 'nombre', '%' . $q . '%', false])
            ->orwhere(['like', 'apellido', $q . '%', false])
            ->orWhere(['like', 'documento', $q . '%', false])
            ->leftJoin('personas_hc', '`personas_hc`.`id_persona` = `personas`.`id_persona` AND `personas_hc`.id_efector = ' . Yii::$app->user->idEfector)
            ->limit(20);
        $command = $query->createCommand();
        $data = $command->queryAll();

        $out = array_values($data);
        if (count($out) > 0) {
            $out[] = ['text' => "<a href=\"" . \yii\helpers\Url::toRoute('personas/create') . "\">Crear Persona</a>"];
        }
        return $out;
    }

    /*
     * Usado para los Select2
     */
    public static function liveSearch($q)
    {
        $out = ['id' => '', 'text' => ''];

        $query = new yii\db\Query;

        $query->select([
            "CONCAT(CONCAT(apellido, ', ', nombre), ' - ', documento) AS text",
            '`personas`.id_persona AS id'
        ])
            ->from('personas')
            ->where(['like', 'CONCAT(apellido," ",nombre)', '%' . $q . '%', false])
            ->orwhere(['like', 'nombre', '%' . $q . '%', false])
            ->orwhere(['like', 'apellido', $q . '%', false])
            ->orWhere(['like', 'documento', $q . '%', false])
            ->limit(20);

        $command = $query->createCommand();

        $data = $command->queryAll();

        $out = array_values($data);

        return $out;
    }

    public function obtenerUltimoNHistoriaClinica()
    {
        $query = new yii\db\Query;
        $query->select("numero_hc")
            ->from('personas_hc')
            ->where("id_efector = " . Yii::$app->user->getIdEfector())
            ->orderBy('numero_hc DESC')
            ->limit(1);

        $command = $query->createCommand();
        $data = $command->queryOne();
        if ($data) {
            return $data['numero_hc'];
        }

        return null;
    }

    public function obtenerNHistoriaClinica($id_efector)
    {
        $query = new yii\db\Query;
        $query->select(['numero_hc'])
            ->from('personas_hc')
            ->where("id_efector = " . $id_efector)
            ->andWhere("id_persona = " . $this->id_persona)
            ->limit(1);

        $command = $query->createCommand();
        $data = $command->queryOne();

        return $data ? $data['numero_hc'] : '--';
    }

    public function generarNHistoriaClinica($id_persona, $nro_hc)
    {
        $sql = 'INSERT INTO personas_hc (id_persona, id_efector, numero_hc) VALUES (' . $id_persona . ', ' . Yii::$app->user->getIdEfector() . ',' . $nro_hc . ')';

        \Yii::$app->db->createCommand($sql)->execute();
    }

    public function obtenerUltimoControl()
    {
        $query = new yii\db\Query;
        $query->select("datos, fecha_creacion")
            ->from('atenciones_enfermeria')
            ->where("id_persona = " . $this->id_persona)
            ->andWhere("DATE(fecha_creacion) = " . date("Y-m-d"))
            ->limit(1);

        $command = $query->createCommand();
        $data = $command->queryOne();

        return $data;
    }

    public function revisarSiExiste($numero_hc)
    {
        $query = new yii\db\Query;
        $query->select(['COUNT(*) AS cnt'])
            ->from('personas_hc')
            ->where("id_efector = " . Yii::$app->user->idEfector)
            ->andWhere("numero_hc = " . $numero_hc)
            ->limit(1);

        $command = $query->createCommand();
        $data = $command->queryOne();

        return $data['cnt'];
    }

    public function motivoCondicional($attribute, $params)
    {
        if ($this->acredita_identidad == 0) {
            if (isset($this->$attribute) && $this->$attribute != '') {
                return false;
            } else {
                $this->addError($attribute, 'Debe indicar por que la persona no presenta DNI.');
            }
        } else {
            return false;
        }
    }

    public function documentoUnico($attribute, $params)
    {
        $sql = 'SELECT COUNT(*) AS cnt FROM personas WHERE documento = ' . $this->$attribute;

        $data = \Yii::$app->db->createCommand($sql)->queryOne();

        if ($this->documento_propio == 1) {
            if ($data['cnt'] == 1) {
                if ($this->isNewRecord) {
                    $this->addError($attribute, 'El documento ya existe en el sistema.');
                } else {
                    if ($this->$attribute != $this->oldAttributes['documento']) {
                        $this->addError($attribute, 'El documento ya existe en el sistema.');
                    }
                }
            } else {
                return false;
            }
        } else {
            if ($data['cnt'] == 3) {
                if ($this->isNewRecord) {
                    $this->addError($attribute, 'Límite alcanzado como documento ajeno (2).');
                } else {
                    if ($this->$attribute != $this->oldAttributes['documento']) {
                        $this->addError($attribute, 'Límite alcanzado como documento ajeno (2).');
                    }
                }
            } else {
                return false;
            }
        }
    }

    public static function existe_en_puco($documento)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => 'https://esalud.msaludsgo.gov.ar/seipa/web/api/coberturas?dni=' . $documento . '&sexo=1',
        ));
        $resp = curl_exec($curl);
        curl_close($curl);

        $arreglo_puco = json_decode($resp);
        if (count($arreglo_puco) > 0) {
            $row_puco = (array) $arreglo_puco[0];
            return $row_puco;
        } else {
            return false;
        }
    }

    public function validarFechaNacimiento($attribute, $params)
    {
        $fecha_1 = strtotime($this->$attribute);
        $fecha = strtotime($this->fecha_nacimiento);
        /*var_dump($fecha_1);echo "<br>";
        var_dump($fecha); die();*/
        if ($fecha != $fecha_1) {
            $this->addError($attribute, "Las fechas de nacimiento no son las mismas");
        } else {
            return false;
        }
    }

    public function listadoCandidatos($parametros)
    {
        $connection = Yii::$app->getDb();
        $apellido = $parametros['apellido'];
        $nombre = $parametros['nombre'];
        $documento = $parametros['documento'];
        $fecha_nacimiento = $parametros['fecha_nacimiento'];

        /*
        $command = $connection->createCommand("SELECT id_persona as id, apellido, p.nombre,td.nombre as tipo_doc, documento, sexo_biologico as sexo, fecha_nacimiento
            FROM personas p
            LEFT JOIN tipos_documentos td USING (id_tipodoc)
            WHERE (soundex(apellido) = soundex('$apellido') AND soundex(p.nombre) = soundex('$nombre')) 
            ");
            */
            
        $command = $connection->createCommand(
            "SELECT id_persona as id, apellido, p.nombre,td.nombre as tipo_doc, documento, sexo_biologico as sexo, fecha_nacimiento
                FROM personas p
                LEFT JOIN tipos_documentos td USING (id_tipodoc)
                WHERE (p.apellido LIKE '%$apellido%' AND p.nombre LIKE '%$nombre%') 
                   OR (p.apellido LIKE '%$apellido%' AND p.otro_nombre LIKE '%$nombre%')
                   OR (p.otro_apellido LIKE '%$apellido%' AND p.nombre LIKE '%$nombre%')
                   OR (p.otro_apellido LIKE '%$apellido%' AND p.otro_nombre LIKE '%$nombre%')
                   OR (soundex(apellido) = soundex('$apellido') AND soundex(p.nombre) = soundex('$nombre'))

            ");

        //AND (LEVENSHTEIN(CAST(documento as CHAR), '$documento') <= 2 AND  LEVENSHTEIN(CAST(fecha_nacimiento as CHAR), '$fecha_nacimiento') <= 2)
//        echo $command->sql;
        $candidatos = $command->queryAll();
        //var_dump($candidatos); die();
        $total= 0;
        $candidatos_lev = [];


        foreach ($candidatos as $key => $candidato) {
            $lev_documento = levenshtein($documento, $candidato['documento']);
            $lev_fecha_nac = levenshtein($fecha_nacimiento, $candidato['fecha_nacimiento']);
            if($lev_documento <= 2 || $lev_fecha_nac <= 2) {
                $peso_candidato = $this->calcularPesos($candidato, $parametros);
                $candidato['score'] = $peso_candidato;
                $candidatos_lev[] = $candidato;                
                $total++;
            } 

        }
        return $candidatos_lev;
    }

    protected function calcularPesos ($parametros_candidato, $parametros_ingreso)
    {
        if (!isset($parametros_ingreso['sexo'])) {
            return 0;
        }

        if (!is_integer($parametros_ingreso['sexo'])) {
            $parametros_ingreso['sexo'] = $parametros_ingreso['sexo'] === 'M' ? 2 : ($parametros_ingreso['sexo'] === 'F' ? 1 : 3);
        }
        
        $peso_absoluto_tipo_documento = 10;
        $peso_absoluto_sexo = 10;
        $peso_absoluto_apellido = 20;
        $peso_absoluto_nombre = 10;
        $peso_absoluto_nro_documento = 30;
        $peso_absoluto_fecha_nacimiento = 20;
        
        $peso_tipo_doc = ($parametros_candidato['tipo_doc'] == Tipo_documento::getTipoDocumento($parametros_ingreso['tipo_doc'])->nombre)? $peso_absoluto_tipo_documento : 0;
        $peso_sexo = ($parametros_candidato['sexo'] == $parametros_ingreso['sexo'])? $peso_absoluto_sexo : 0;

        //--- calculos relativos
        
        $apellidos_candidato=self::separarApellidos($parametros_candidato['apellido']);
        $apellidos_ingreso=self::separarApellidos($parametros_ingreso['apellido']);
        $distancia_apellido = levenshtein(mb_strtolower($apellidos_candidato[0]), mb_strtolower($apellidos_ingreso[0])); 

        $longitud_apellido = strlen ($apellidos_candidato[0]);
        $coeficiente_apellido = ($longitud_apellido - $distancia_apellido) / $longitud_apellido;
        $peso_relativo_apellido = $coeficiente_apellido * $peso_absoluto_apellido;

        $nombres_candidato=self::separarNombres($parametros_candidato['nombre']);
        $nombres_ingreso=self::separarNombres($parametros_ingreso['nombre']);

        $distancia_nombre = levenshtein(mb_strtolower($nombres_candidato[0]), mb_strtolower($nombres_ingreso[0]));        
        $longitud_nombre = strlen ($nombres_candidato[0]);
        $coeficiente_nombre = ($longitud_nombre - $distancia_nombre) / $longitud_nombre;
        $peso_relativo_nombre = $coeficiente_nombre * $peso_absoluto_nombre;

        $distancia_nro_documento = levenshtein($parametros_candidato['documento'], $parametros_ingreso['documento']);  
        $longitud_nro_documento = strlen ($parametros_candidato['documento']);
        $coeficiente_nro_documento = ($longitud_nro_documento - $distancia_nro_documento) / $longitud_nro_documento;
        $peso_relativo_nro_documento = $coeficiente_nro_documento * $peso_absoluto_nro_documento;
        
        $distancia_fecha_nacimiento = levenshtein($parametros_candidato['fecha_nacimiento'], $parametros_ingreso['fecha_nacimiento']);        
        $longitud_fecha_nacimiento = strlen ($parametros_candidato['fecha_nacimiento']);
        $coeficiente_fecha_nacimiento = ($longitud_fecha_nacimiento - $distancia_fecha_nacimiento) / $longitud_fecha_nacimiento;
        $peso_relativo_fecha_nacimiento = $coeficiente_fecha_nacimiento * $peso_absoluto_fecha_nacimiento;
        //suma de pesos relativos para el score final
        $peso_candidato = $peso_tipo_doc + $peso_sexo + $peso_relativo_nombre + $peso_relativo_apellido + $peso_relativo_nro_documento + $peso_relativo_fecha_nacimiento;
        return $peso_candidato;
    }

    public function getDomicilioActivo()
    {
        foreach ($this->domicilios as $domicilio) {
            if ($domicilio->activo == 'SI') {
                return $domicilio->domicilio;
            }
        }
    }

    public function getTelefonoContacto()
    {
        foreach ($this->telefonos as $telefono) {
            return $telefono->tipoTelefono->nombre . ': ' . $telefono->numero;
        }
    }


    public function establecerEstadoPaciente()
    {
        $idSegNivelInternacion = SegNivelInternacion::personaInternada($this->id_persona);
        if ($idSegNivelInternacion) {
            $this->estadoPaciente = [
                'estado' => Persona::ESTADO_INTERNADA,
                'id' => $idSegNivelInternacion
            ];
        }
    }


    /**
     *  Antes de guardar los datos llama a la funcion para convertir 
     *  los datos tipo fecha a formato ISO. 
     *  Tambien le asigna la fecha actual al campo fecha_alta
     *  Y se asigna el usuario_alta y usuario_mod
     *  @return  true
     */
    public function beforeSave($insert)
    {

        if ($this->fecha_nacimiento != "" && $this->fecha_nacimiento != NULL) {
            if (strpos($this->fecha_nacimiento, "/") !== false) {
                $this->fecha_nacimiento = $this->pasarFechaFormatISO($this->fecha_nacimiento);
            }
        }
        if ($this->fecha_defuncion != "" && $this->fecha_defuncion != NULL) {
            if (strpos($this->fecha_defuncion, "/") !== false) {
                $this->fecha_defuncion = $this->pasarFechaFormatISO($this->fecha_defuncion);
            }
        }

        if ($insert) {
            $this->fecha_alta = date("Y-m-d");
            $this->usuario_alta = Yii::$app->user->userName;
        } else {
            $this->fecha_mod = date("Y-m-d");
            $this->usuario_mod =  Yii::$app->user->userName;
        }

        return parent::beforeSave($insert);
    }
    /*
* Métodos creados para obtener el evento activo para el paciente
* así poder generar la url correspondiente
*/
    public function InternacionActiva()
    {
        foreach ($this->internaciones as $key => $internacion) {
            # busco una internacion activa
            if ($internacion->fecha_fin == null) {
                if ($internacion->cama->sala->piso->id_efector == Yii::$app->user->getIdEfector()) {
                    return $internacion; // el paciente esta internado en el efector en sesion
                } else {
                    return true; //El paciente esta internado en otro efector, mostrar mensaje para realizar externacion en el efector de origen e internacion en el efector actual
                }
            }
        }
        return false; //no hay internaciones activas
    }

    public function turnoHoy($idServicio, $idRrhh, $idEfector)
    {

        $turnos = Turno::pacienteEsperandoTurno($this->id_persona);

        foreach ($turnos as $key => $turno) {
            if ($turno->fecha == date('Y-m-d') && $turno->atendido != 'SI') {

                if (($turno->id_servicio_asignado == $idServicio || $turno->id_rrhh_servicio_asignado == $idRrhh)
                    && $turno->id_efector == $idEfector
                ) {
                    return $turno; // el paciente tiene un turno en el efector, en el servicio o con el rrhh en sesion
                } else {
                    return false; //El paciente tiene turno en otro efector
                }
            }
        }
        return false; //no tiene turnos hoy
    }

    public function GuardiaActiva()
    {
        foreach ($this->encuentrosGuardia as $key => $guardia) {
            # busco un encuentro de guardia activo
            if ($guardia->fecha_fin == null) {
                if ($guardia->id_efector == Yii::$app->user->getIdEfector()) {
                    return $guardia; // el paciente esta en la guardia en el efector en sesion
                } else {
                    return true; //El paciente esta en la guardia en otro efector, mostrar mensaje para realizar traslado en el efector de origen e ingreso en el efector actual
                }
            }
        }
    }

    public function getSexoCrecimiento()
    {
        $sexo = $this->sexo;
        if (null == $sexo) {
            $sexo = 'F';
            if ($this->sexo_biologico == 2) {
                $sexo = 'M';
            }
        }
        return $sexo;
    }

    public function getEdadCrecimiento()
    {
        $edad = $this->edad;
        if ($edad == 0) {
            $edad = $this->EdadBebe;
        }
        return $edad;
    }

    public function getSexoLetra()
    {

        $sexo = [1 => 'F', 2 => 'M'];

        return $sexo[$this->sexo_biologico];
    }

    public function getSexoTexto()
    {

        $sexo = [1 => 'Femenino', 2 => 'Masculino'];

        return $sexo[$this->sexo_biologico];
    }

    public function getGeneroTexto()
    {

        $genero = [1 => 'Femenino', 2 => 'Masculino', 3 => 'Otro'];

        return $genero[$this->genero];
    }

    public function getGrupoEtareoSumar($fecha_prestacion)
    {

        $edad = $this->getEdad($fecha_prestacion);
        $sexo = $this->getSexoLetra();

        switch ($edad) {

            case $edad < 6:
                if (!$edad < 1) {
                    $grupoEtareo = 'NINO';
                } else {
                    $grupoEtareo = 'NEONATO';
                }
                break;

            case $edad < 10:
                $grupoEtareo = 'NINOS 6-9';
                break;

            case $edad < 20:
                $grupoEtareo = 'ADOLESCENTES';
                break;

            case $edad < 64:
                if ($sexo == 'M') {
                    $grupoEtareo = 'HOMBRES';
                } else {
                    $grupoEtareo = 'MUJERES';
                }
                break;

            case $edad < 110:
                $grupoEtareo = 'ADULTOS';
                break;
        }

        return $grupoEtareo;
    }

    private static function separarApellidos($apellido)
    {
        /* separar el apellido en espacios */
        $tokens = explode(' ', trim($apellido));
        /* arreglo donde se guardan las "palabras" del apellido */
        $apellidos = [];
        /* palabras de apellidos compuestos */
        $tokens_especiales = array('da','das', 'de', 'del','d', 'dell', 'di', 'do', 'dos', 'du', 'la', 'las', 'le', 'li', 'lo', 'lu', 'los', 'mac', 'mc', 'van', 'vd','ver','von', 'y', 'i', 'san', 'santa','ten');
    
        $prev = "";
        foreach($tokens as $token) {
            $_token = strtolower($token);
            if(in_array($_token, $tokens_especiales)) {
                $prev .= "$token ";
            } else {
                $apellidos[] = $prev. $token;
                $prev = "";
            }
        }
        if (count($apellidos) > 2) {
            $primer_apellido = $apellidos[0];
            unset($apellidos[0]);
            $otros_apellidos = implode(' ', $apellidos);
            $apellidos[0] = $primer_apellido;
            $apellidos[1] = $otros_apellidos;
        }

        return $apellidos;
    }    


    private static function separarNombres($nombre)
    {
        /* separar el apellido en espacios */
        $tokens = explode(' ', trim($nombre));
        /* arreglo donde se guardan las "palabras" del apellido */
        $apellidos = [];
        /* palabras de apellidos compuestos */
        $tokens_especiales = array('da','das', 'de', 'del','d', 'dell', 'di', 'do', 'dos', 'du', 'la', 'las', 'le', 'li', 'lo', 'lu', 'los', 'mac', 'mc', 'van', 'vd','ver','von', 'y', 'i', 'san','ten');
    
        $prev = "";
        foreach($tokens as $token) {
            $_token = strtolower($token);
            if(in_array($_token, $tokens_especiales)) {
                $prev .= "$token ";
            } else {
                $apellidos[] = $prev. $token;
                $prev = "";
            }
        }
        if (count($apellidos) > 2) {
            $primer_apellido = $apellidos[0];
            unset($apellidos[0]);
            $otros_apellidos = implode(' ', $apellidos);
            $apellidos[0] = $primer_apellido;
            $apellidos[1] = $otros_apellidos;
        }

        return $apellidos;
    }   


    public static function situacionPaciente(){

        $estado = '';

        



    } 


}
