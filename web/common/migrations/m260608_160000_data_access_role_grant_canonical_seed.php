<?php

use yii\db\Migration;

/**
 * Grants canónicos DataAccess en BD (fuente única; sin role_grants en YAML).
 */
class m260608_160000_data_access_role_grant_canonical_seed extends Migration
{
    private const TABLE = '{{%data_access_role_grant}}';

    public function safeUp()
    {
        foreach ($this->canonicalGrants() as $grant) {
            $this->upsert(
                self::TABLE,
                [
                    'role_name' => $grant[0],
                    'entity_group_key' => $grant[1],
                    'operations_csv' => $grant[2],
                    'scope_checker' => $grant[3],
                    'active' => 1,
                    'notas' => $grant[4],
                ],
                [
                    'operations_csv' => $grant[2],
                    'scope_checker' => $grant[3],
                    'active' => 1,
                ]
            );
        }
    }

    public function safeDown()
    {
        echo "m260608_160000_data_access_role_grant_canonical_seed cannot be reverted safely.\n";

        return false;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string|null, 4: string|null}>
     */
    private function canonicalGrants(): array
    {
        return [
            ['AdminEfector', 'ProfesionalEfectorServicio.asignacion', 'aggregate,filter,read,write', 'efector_sesion', 'Catálogo canónico'],
            ['AdminEfector', 'Persona.sexo_genero', 'aggregate,filter,read', 'efector_sesion_via_pes', null],
            ['AdminEfector', 'Persona.identidad_basica', 'read,write', 'efector_sesion_via_pes', 'Catálogo canónico'],
            ['Medico', 'ProfesionalEfectorServicio.asignacion', 'aggregate,filter,read', 'efector_sesion', null],
            ['Medico', 'Persona.identidad_basica', 'read', 'efector_sesion_via_pes', null],
            ['Administrativo', 'ProfesionalEfectorServicio.asignacion', 'aggregate,filter', 'efector_sesion', null],
            ['Administrativo', 'Persona.sexo_genero', 'aggregate,filter,read', 'efector_sesion_via_pes', null],
            ['Administrativo', 'Persona.identidad_basica', 'read', 'efector_sesion_via_pes', null],
            ['paciente', 'Persona.edad_exacta', 'filter,read', 'permitir_para_si_mismo', null],
            ['paciente', 'Persona.sexo_genero', 'filter,read', 'permitir_para_si_mismo', null],
        ];
    }
}
