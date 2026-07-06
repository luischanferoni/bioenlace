<?php

namespace common\components\Domain\Person\Service;

use common\components\Domain\Person\Util\CuilValidator;
use common\models\Person\Persona;

/**
 * Alta y consulta de CUIL en persona (identificador nacional para matching FHIR Practitioner).
 */
final class PersonCuilService
{
    public static function normalize(string $cuil): string
    {
        return CuilValidator::normalize($cuil);
    }

    public static function isValid(string $cuil): bool
    {
        return CuilValidator::isValid($cuil);
    }

    public static function personaTieneCuil(?Persona $persona): bool
    {
        if ($persona === null) {
            return false;
        }

        return self::normalize((string) ($persona->cuil ?? '')) !== '';
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function ensureOnPersona(int $idPersona, string $cuil): string
    {
        if ($idPersona <= 0) {
            throw new \InvalidArgumentException('Persona inválida.');
        }

        $normalized = self::normalize($cuil);
        if ($normalized === '') {
            throw new \InvalidArgumentException('El CUIL es obligatorio.');
        }
        if (!self::isValid($normalized)) {
            throw new \InvalidArgumentException('CUIL inválido (verifique el número y el dígito verificador).');
        }

        /** @var Persona|null $persona */
        $persona = Persona::findOne(['id_persona' => $idPersona]);
        if ($persona === null) {
            throw new \InvalidArgumentException('Persona inexistente.');
        }

        if (self::normalize((string) ($persona->cuil ?? '')) === $normalized) {
            return $normalized;
        }

        $conflict = Persona::find()
            ->where(['cuil' => $normalized])
            ->andWhere(['<>', 'id_persona', $idPersona])
            ->exists();
        if ($conflict) {
            throw new \InvalidArgumentException('Ese CUIL ya está registrado para otra persona.');
        }

        $persona->cuil = $normalized;
        if (!$persona->save(false, ['cuil'])) {
            throw new \RuntimeException('No se pudo guardar el CUIL: ' . json_encode($persona->getErrors()));
        }

        return $normalized;
    }

  /**
   * @return Persona|null única coincidencia; null si 0 o >1
   */
    public static function findUniquePersonaByCuil(string $cuil): ?Persona
    {
        $normalized = self::normalize($cuil);
        if ($normalized === '' || !self::isValid($normalized)) {
            return null;
        }

        $rows = Persona::find()
            ->where(['cuil' => $normalized])
            ->limit(2)
            ->all();

        return count($rows) === 1 ? $rows[0] : null;
    }
}
