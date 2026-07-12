# Diseño — alta cuenta institucional

## Flujos

```mermaid
flowchart TD
  Inst[Institucional] -->|Clínica o consultorio| API1[POST licencia/registrar-efector]
  Inst -->|Soy ministerio| API2[POST licencia/solicitar-ministerio]
  API1 --> Pay[Pasarela simulada]
  Pay --> Acc[Cuenta EFECTOR + POOL + AdminEfector]
  Acc --> Guide[next_steps: asignarse a servicio clínico]
  API2 --> Pend[billing_signup_request PENDING]
  Pend --> Admin[Admin aprueba]
  Admin --> Min[Cuenta MINISTERIO + AdminMinisterio]
```

## Perfiles de alta efector

| Perfil | Defaults | Post-alta |
|--------|----------|-----------|
| `CLINICA` | AMB / EMER / IMP opcionales (mín. 1 clase) | Invitar staff / habilitar servicios |
| `CONSULTORIO` | Solo AMB, `max_pes` = 1; tipología consultorio; sin EMER/IMP ni sector público | Guiar autoasignación a servicio clínico |

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

Públicas: `catalogo-ministerios`, `planes`, `registrar-efector` (body `perfil`), `solicitar-ministerio`.  
Auth AdminEfector: `mi-licencia`, `desvincular-pago-ministerio`, `asociar-pago-ministerio` (solicitud o switch si hay invitación/aprobación admin).
