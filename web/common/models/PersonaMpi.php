<?php

namespace common\models;

use common\models\Estado_civil;
use common\models\Tipo_documento;
use Yii;

/**
 * This is the model class for table "personas".
 *
 * @property int $id_persona Codigo de la persona
 * @property int $acredita_identidad
 * @property string|null $apellido Apellido de la persona
 * @property string|null $otro_apellido
 * @property string|null $nombre Nombre de la persona
 * @property string|null $otro_nombre
 * @property string|null $apellido_materno
 * @property string|null $apellido_paterno
 * @property int $sexo_biologico
 * @property int $genero
 * @property int $id_tipodoc Codigo de tipo de documento de la persona
 * @property string|null $documento Numero de documento de la persona
 * @property int $documento_propio
 * @property string|null $sexo Sexo de la persona. M=masculino, F=femenino, I=indeterminado
 * @property string|null $fecha_nacimiento Fecha de nacimiento de la persona.
 * @property int $id_estado_civil Codigo del estado civil de la persona.
 * @property string|null $fecha_defuncion Fecha de defuncion.
 * @property string|null $usuario_alta Usuario que dio de alta a la persona.
 * @property string|null $fecha_alta Fecha que se dio de alta a la persona.
 * @property string|null $usuario_mod Usuario que realizo modificacion
 * @property string|null $fecha_mod Fecha en que se realizo la modificacion
 * @property int|null $id_user
 *
 * @property Alergias[] $alergias
 * @property Embarazos[] $embarazos
 * @property PersonaMails[] $personaMails
 * @property PersonaTelefono[] $personaTelefonos
 * @property EstadoCivil $estadoCivil
 * @property TiposDocumentos $tipodoc
 * @property PersonasDomicilios[] $personasDomicilios
 * @property Domicilios[] $domicilios
 * @property PersonasProgramas[] $personasProgramas
 * @property Programas[] $programas
 * @property TensionArterial[] $tensionArterials
 * @property Turnos[] $turnos
 * @property ValoracionNutricional[] $valoracionNutricionals
 */
class PersonaMpi extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'personas';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['acredita_identidad', 'sexo_biologico', 'genero', 'id_tipodoc', 'id_estado_civil'], 'required'],
            [['acredita_identidad', 'sexo_biologico', 'genero', 'id_tipodoc', 'documento_propio', 'id_estado_civil', 'id_user'], 'integer'],
            [['sexo'], 'string'],
            [['fecha_nacimiento', 'fecha_defuncion', 'fecha_alta', 'fecha_mod'], 'safe'],
            [['apellido', 'nombre'], 'string', 'max' => 60],
            [['otro_apellido', 'otro_nombre', 'apellido_materno', 'apellido_paterno'], 'string', 'max' => 255],
            [['documento'], 'string', 'max' => 8],
            [['usuario_alta', 'usuario_mod'], 'string', 'max' => 40],
            [['id_estado_civil'], 'exist', 'skipOnError' => true, 'targetClass' => Estado_civil::className(), 'targetAttribute' => ['id_estado_civil' => 'id_estado_civil']],
            [['id_tipodoc'], 'exist', 'skipOnError' => true, 'targetClass' => Tipo_documento::className(), 'targetAttribute' => ['id_tipodoc' => 'id_tipodoc']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_persona' => 'Id Persona',
            'acredita_identidad' => 'Acredita Identidad',
            'apellido' => 'Apellido',
            'otro_apellido' => 'Otro Apellido',
            'nombre' => 'Nombre',
            'otro_nombre' => 'Otro Nombre',
            'apellido_materno' => 'Apellido Materno',
            'apellido_paterno' => 'Apellido Paterno',
            'sexo_biologico' => 'Sexo Biologico',
            'genero' => 'Genero',
            'id_tipodoc' => 'Id Tipodoc',
            'documento' => 'Documento',
            'documento_propio' => 'Documento Propio',
            'sexo' => 'Sexo',
            'fecha_nacimiento' => 'Fecha Nacimiento',
            'id_estado_civil' => 'Id Estado Civil',
            'fecha_defuncion' => 'Fecha Defuncion',
            'usuario_alta' => 'Usuario Alta',
            'fecha_alta' => 'Fecha Alta',
            'usuario_mod' => 'Usuario Mod',
            'fecha_mod' => 'Fecha Mod',
            'id_user' => 'Id User',
        ];
    }

    static function actualizarIdMpi($limit = 100, $offset = 0) {
        
        $personas = PersonaMpi::find()        
        ->where('id_mpi = 0')
        ->limit($limit)
        ->offset($offset)
        ->all();

        foreach ($personas as $persona) {
            $id_persona = $persona->id_persona;
            $persona_mpi = Yii::$app->mpi->traerPaciente($persona->id_persona);
            $id_mpi = $persona_mpi['data']['paciente']['set_minimo']['identificador']['mpi']??null;
            if(isset($id_mpi)){
                $persona->id_mpi = $id_mpi;
                $persona->save();
                echo "persona: $id_persona - mpi: $id_mpi <br>";
            } else {
                echo "persona: $id_persona - mpi: $id_mpi <br>";

            }
            
        }
    }

    /**
     * Gets query for [[Alergias]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getAlergias()
    {
        return $this->hasMany(Alergias::className(), ['id_persona' => 'id_persona']);
    }

    /**
     * Gets query for [[Embarazos]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getEmbarazos()
    {
        return $this->hasMany(Embarazos::className(), ['id_persona' => 'id_persona']);
    }

    /**
     * Gets query for [[PersonaMails]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPersonaMails()
    {
        return $this->hasMany(PersonaMails::className(), ['id_persona' => 'id_persona']);
    }

    /**
     * Gets query for [[PersonaTelefonos]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPersonaTelefonos()
    {
        return $this->hasMany(PersonaTelefono::className(), ['id_persona' => 'id_persona']);
    }

    /**
     * Gets query for [[EstadoCivil]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getEstadoCivil()
    {
        return $this->hasOne(EstadoCivil::className(), ['id_estado_civil' => 'id_estado_civil']);
    }

    /**
     * Gets query for [[Tipodoc]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTipodoc()
    {
        return $this->hasOne(TiposDocumentos::className(), ['id_tipodoc' => 'id_tipodoc']);
    }

    /**
     * Gets query for [[PersonasDomicilios]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPersonasDomicilios()
    {
        return $this->hasMany(PersonasDomicilios::className(), ['id_persona' => 'id_persona']);
    }

    /**
     * Gets query for [[Domicilios]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getDomicilios()
    {
        return $this->hasMany(Domicilios::className(), ['id_domicilio' => 'id_domicilio'])->viaTable('personas_domicilios', ['id_persona' => 'id_persona']);
    }

    /**
     * Gets query for [[PersonasProgramas]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPersonasProgramas()
    {
        return $this->hasMany(PersonasProgramas::className(), ['id_persona' => 'id_persona']);
    }

    /**
     * Gets query for [[Programas]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getProgramas()
    {
        return $this->hasMany(Programas::className(), ['id_programa' => 'id_programa'])->viaTable('personas_programas', ['id_persona' => 'id_persona']);
    }

    /**
     * Gets query for [[TensionArterials]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTensionArterials()
    {
        return $this->hasMany(TensionArterial::className(), ['id_persona' => 'id_persona']);
    }

    /**
     * Gets query for [[Turnos]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTurnos()
    {
        return $this->hasMany(Turnos::className(), ['id_persona' => 'id_persona']);
    }

    /**
     * Gets query for [[ValoracionNutricionals]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getValoracionNutricionals()
    {
        return $this->hasMany(ValoracionNutricional::className(), ['id_persona' => 'id_persona']);
    }
}
