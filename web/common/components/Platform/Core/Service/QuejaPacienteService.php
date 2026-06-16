<?php

declare(strict_types=1);

namespace common\components\Platform\Core\Service;

use common\models\QuejaPaciente;

/**
 * Registro de quejas operativas enviadas por pacientes.
 */
final class QuejaPacienteService
{
    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function enviarComoPaciente(int $idPersona, array $input): array
    {
        if ($idPersona <= 0) {
            throw new \InvalidArgumentException('Sesión sin persona.');
        }

        $row = QuejaPaciente::crearDesdeInput($idPersona, $input);

        return [
            'success' => true,
            'data' => [
                'id' => (int) $row->id,
                'categoria' => (string) $row->categoria,
                'categoria_label' => QuejaPaciente::categoriaLabel((string) $row->categoria),
            ],
            'message' => 'Recibimos tu queja. Gracias por ayudarnos a mejorar.',
        ];
    }
}
