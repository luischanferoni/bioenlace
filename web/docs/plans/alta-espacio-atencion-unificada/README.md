# Plan — Alta unificada de espacios de atención

| Campo | Valor |
|-------|--------|
| Slug | `alta-espacio-atencion-unificada` |
| Estado | Implementación iniciada; acceso móvil y recuperación de cuenta en curso |
| Dueño | Producto / Identidad / Organización / Billing / Mobile |
| Compatibilidad | Sin retrocompatibilidad funcional con los perfiles comerciales `CONSULTORIO` y `CLINICA`; preservar cuentas, licencias y pagos históricos |

## Contexto

El programa anterior de alta móvil se cerró como registro de lo construido y sus decisiones originales: [alta-movil-cuentas-efectores](../alta-movil-cuentas-efectores/README.md). Este plan toma el problema desde el flujo deseado y reemplaza la división comercial entre “Consultorio propio” y “Clínica o centro”.

## Índice

- [overview.md](./overview.md) — recorridos, estados y alcance
- [design.md](./design.md) — reglas de dominio, persistencia, permisos y recuperación
- [phases/00-decisiones-y-modelo.md](./phases/00-decisiones-y-modelo.md) — decisiones confirmadas y límites
- [phases/01-alta-unificada.md](./phases/01-alta-unificada.md) — UX móvil, sector y plan
- [phases/02-borrador-y-reanudacion.md](./phases/02-borrador-y-reanudacion.md) — alta idempotente y recuperación desde «Crear cuenta»
- [phases/03-pago-y-habilitacion.md](./phases/03-pago-y-habilitacion.md) — webhook, licencia, AdminEfector y asistente
- [phases/04-permisos-y-corte.md](./phases/04-permisos-y-corte.md) — acceso móvil, APIs y retiro de perfiles anteriores
- [phases/05-validacion-y-migracion.md](./phases/05-validacion-y-migracion.md) — fallos, transiciones y datos preexistentes

## Dependencias

- Reutilizar la verificación Didit, identidad Persona/User, precios centralizados, checkout mensual Mercado Pago y webhook ya implementados.
- Revisar la vigencia del catálogo SISA y la vinculación con ministerios para el alta pública.
- Asegurar la cotización USD/ARS vigente, URLs externas, credenciales y QA de producción de Mercado Pago antes de habilitar cobros reales.

## Criterio de cierre

La cuenta gratuita no entra en Personal de Salud; la cuenta comercial puede recuperar su borrador desde «Crear cuenta» sin iniciar sesión; ningún rol de administración o licencia se concede antes del pago confirmado; la asignación clínica es posterior e independiente; reintentos y webhooks son idempotentes; público/privado y las validaciones SISA/ministerio se cumplen; los perfiles anteriores dejan de ser contratos activos sin perder datos comerciales históricos.
