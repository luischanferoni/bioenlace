<?php

namespace common\components\Domain\Person\Service;

/**
 * Parsea el string del código de barras PDF417 del DNI argentino (salida del lector hardware).
 */
final class DniBarcodeParserService
{
    private const REG_ES = '/^[0-9]{11}@[A-ZÁÉÍÓÚÑ ]+@[A-ZÁÉÍÓÚÑ ]+@[MF]@([MF]|[0-9])?[0-9]{7}@[A-Z]{1}@[0-9]{2}\/[0-9]{2}\/[0-9]{4}@[0-9]{2}\/[0-9]{2}\/[0-9]{4}(@[0-9]{3})?$/u';
    private const REG_EN = '/^[0-9]{11}"[A-ZÁÉÍÓÚÑ ]+"[A-ZÁÉÍÓÚÑ ]+"[MF]"([MF]|[0-9])?[0-9]{7}"[A-Z]{1}"[0-9]{2}[0-9]{2}[0-9]{4}"[0-9]{2}[0-9]{2}[0-9]{4}("[0-9]{3})?$/u';
    private const REG_LIBRETA = '/^"[0-9]?[0-9]{7}"[A-Z]{1}"[0-9]{1}"[A-ZÁÉÍÓÚÑ ]+"[A-ZÁÉÍÓÚÑ ]+"[A-ZÁÉÍÓÚÑ ]+"[0-9]{2}[0-9]{2}[0-9]{4}"[MF]"[0-9]{2}[0-9]{2}[0-9]{4}/u';

    /**
     * @return array{
     *   documento: string,
     *   sexo_biologico: int,
     *   sexo_letra: string,
     *   apellido?: string,
     *   nombre?: string,
     *   fecha_nacimiento?: string
     * }|null
     */
    public function parse(string $rawCode): ?array
    {
        $code = trim($rawCode);
        if ($code === '') {
            return null;
        }

        if (!preg_match(self::REG_ES, $code)
            && !preg_match(self::REG_EN, $code)
            && !preg_match(self::REG_LIBRETA, $code)) {
            return null;
        }

        $parts = str_contains($code, '"') ? explode('"', $code) : explode('@', $code);

        $sexoLetra = null;
        $documento = null;

        if (isset($parts[8]) && ($parts[8] === 'M' || $parts[8] === 'F')) {
            $sexoLetra = $parts[8];
            $documento = trim((string) ($parts[1] ?? ''));
        } elseif (isset($parts[3]) && ($parts[3] === 'M' || $parts[3] === 'F')) {
            $sexoLetra = $parts[3];
            $documento = trim((string) ($parts[4] ?? ''));
        }

        if ($documento === null || $documento === '' || $sexoLetra === null) {
            return null;
        }

        $sexoBiologico = $sexoLetra === 'F' ? 1 : 2;
        $out = [
            'documento' => $documento,
            'sexo_biologico' => $sexoBiologico,
            'sexo_letra' => $sexoLetra,
        ];

        if (isset($parts[8]) && ($parts[8] === 'M' || $parts[8] === 'F')) {
            $out['apellido'] = trim((string) ($parts[2] ?? ''));
            $out['nombre'] = trim((string) ($parts[3] ?? ''));
            $out['fecha_nacimiento'] = $this->parseFechaDni($parts[6] ?? '');
        } elseif (isset($parts[3]) && ($parts[3] === 'M' || $parts[3] === 'F')) {
            $out['apellido'] = trim((string) ($parts[6] ?? ''));
            $out['nombre'] = trim((string) ($parts[5] ?? ''));
            $out['fecha_nacimiento'] = $this->parseFechaDniCompacta($parts[7] ?? '');
        }

        return $out;
    }

    private function parseFechaDni(string $value): ?string
    {
        $value = trim($value);
        if (!preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m)) {
            return null;
        }

        return sprintf('%s-%s-%s', $m[3], $m[2], $m[1]);
    }

    private function parseFechaDniCompacta(string $value): ?string
    {
        $value = preg_replace('/\D/', '', $value) ?? '';
        if (strlen($value) !== 8) {
            return null;
        }

        return sprintf('%s-%s-%s', substr($value, 4, 4), substr($value, 2, 2), substr($value, 0, 2));
    }
}
