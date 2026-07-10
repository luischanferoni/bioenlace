# Alta de cuenta y licencia

## De qué se trata

Desde el sitio institucional se puede **crear una clínica/efector** (rol AdminEfector) con licencia y **pago simulado**, o **solicitar** una cuenta de ministerio (activación asistida). Un efector público declara ministerio (afiliación) y puede pagar por su cuenta o pedir cobertura del pool ministerial.

## Actores

| Actor | Cómo entra |
|-------|------------|
| AdminEfector (clínica / efector) | Self-service en institucional → API licencia |
| AdminMinisterio | Solicitud → verificación → aprobación en admin plataforma |
| Ops Bioenlace | Aprueba solicitudes y movimientos de pool |

## Cómo funciona

```mermaid
flowchart LR
  Inst[Institucional alta.html] --> API[API licencia]
  API -->|efector| Pay[Pago simulado]
  Pay --> Acc[billing_account EFECTOR + POOL + PES AdminEfector]
  API -->|ministerio| Req[billing_signup_request]
  Pend --> Admin[Admin aprueba]
  Admin --> Rol[AdminMinisterio + cuenta MINISTERIO]
```

1. **Privado:** cuenta propia, cobro simulado, entitlements según plan.
2. **Público:** elige ministerio (AFILIADO). Por defecto paga en cuenta propia; si marca cobertura ministerial, queda pendiente hasta que ops mueva el POOL.
3. **Desvincular pago ministerio / asociar:** AdminEfector autenticado vía API `desvincular-pago-ministerio` / `asociar-pago-ministerio` (esta última genera solicitud).

## Relación con el resto

- Modelo de pool: [matriz-argentina-modulos-precios.md](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md)
- Decisión: [onboarding-comercial-self-service.md](../decisions/onboarding-comercial-self-service.md)
- Admin: Licencias / Contratos + Solicitudes alta licencia
