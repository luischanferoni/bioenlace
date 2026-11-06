# Fase 01 — ACL residuales sobre el base

## Objetivo

Que ningún archivo del repo arme transporte, ni siquiera los ACL bien ubicados. Al terminar,
`new Client(` y `curl_init(` sólo existen en `Shared/Infrastructure/Http/` y en los tests.

## Tamaño estimado

**Medio (1 día).** Tres archivos con contratos ya definidos; el trabajo es migrar sin
cambiar comportamiento observable.

## Archivos

| Archivo | Hoy | Qué conservar |
|---------|-----|----------------|
| `DiditClient` | 4 requests con un factory de `Client` | Mapeo a resultado de dominio, headers de auth por tipo de verificación, el `404` tratado como "sin decisión" |
| `SnowstormClient` | `curl_*` | La excepción ante no-2xx, la URL base configurable, el `Accept` del servicio |
| `MpiApiClient` | `curl_*` con verificación TLS desactivada | Token JWT propio, verbo configurable, URL de producción vs. test, y la detección de fallas del proveedor envueltas en HTTP 200 |

## Tareas

- [ ] `DiditClient`: sustituir las 4 requests por el cliente base. Los headers de auth salen
      del mismo método que ya los arma; el cuerpo y el parseo no cambian.
- [ ] `SnowstormClient`: igual, manteniendo el mensaje de error que hoy ve el llamador.
- [ ] `MpiApiClient`: migrar **sin** arrastrar el `curl_*` implícito. Casos especiales:
      - verificación TLS desactivada: hoy es una decisión implícita; dejarla explícita y
        justificada en el mismo lugar (o corregirla si el host ya tiene certificado válido);
      - `CURLOPT_POST` + verbo custom: el base ya soporta verbo explícito, verificar que el
        request salga con el mismo verbo y cuerpo que hoy;
      - `responseHasRemoteFault`: es mapeo de error del proveedor → va en el ACL, no en el base.
- [ ] Tests de los tres con transporte falso: éxito, error de proveedor y timeout. Para el
      caso de TLS, al menos un test que fije la opción que se envía.
- [ ] Verificar que no cambie el tipo ni el mensaje de lo que cada ACL devuelve.

## Aceptación

- `git grep "new Client(" "curl_init("` sólo devuelve `Shared/Infrastructure/Http/` y tests.
- `ArchitectureHttpUsageTest` sigue verde sin allowlist.
- Los tres ACL mantienen su contrato público y sus llamadores no cambian.

## Riesgos

- **Identidad (KYC).** `DiditClient` está en el alta de cuentas: cualquier cambio de timeout
  o de reintento altera el comportamiento de una verificación en curso. Los `GET` pasan a
  reintentarse (idempotentes); confirmar que eso es aceptable para el proveedor.
- **TLS.** En `MpiApiClient` la verificación está apagada a propósito o por descuido: hay que
  decidirlo, no heredarlo. Si se deja apagada, que quede documentado en el ACL.
- **SEIPA responde 200 con errores adentro.** Un mapeo ingenuo a "ok" cambia el resultado de
  la consulta; el test debe cubrir ese caso.
