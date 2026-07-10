# Diseño — alta cuenta institucional

## Flujos

```mermaid
flowchart TD
  Inst[Institucional] -->|Registrar clínica| API1[POST licencia/registrar-efector]
  Inst -->|Soy ministerio| API2[POST licencia/solicitar-ministerio]
  API1 --> Pay[Pasarela simulada]
  Pay --> Acc[Cuenta EFECTOR + POOL + AdminEfector]
  API2 --> Pend[billing_signup_request PENDING]
  Pend --> Admin[Admin aprueba]
  Admin --> Min[Cuenta MINISTERIO + AdminMinisterio]
```

## Sector vs pago

| Sector | Afiliación | Pago (POOL) |
|--------|------------|-------------|
| PRIVADO | Opcional | Cuenta propia EFECTOR |
| PUBLICO | AFILIADO al ministerio elegido (obligatorio) | Propia por defecto; o solicitud de cobertura ministerial |

## Tablas nuevas

- `billing_payment` — cobros (SIMULATED|…), estado, monto, referencia.
- `billing_signup_request` — solicitudes ministerio (y log de altas efector).
- `billing_account.owner_user_id` — titular comercial.

## API

Públicas: `catalogo-ministerios`, `planes`, `registrar-efector`, `solicitar-ministerio`.  
Auth AdminEfector: `mi-licencia`, `desvincular-pago-ministerio`, `asociar-pago-ministerio` (solicitud o switch si hay invitación/aprobación admin).
