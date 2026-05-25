# Fase 1 — Dominio triage + API

## Objetivo

Persistir triage estructurado y estados de circuito detrás de la API, sin depender aún del tablero visual ni del rediseño móvil completo.

## Checklist implementación

- [x] Migración: `guardia_triage`, `guardia_circuito_event`, columnas `circuito_estado` / `prioridad_triage` / `ingreso_at` en `guardia`
- [ ] Seed opcional: `emergency_triage_reason` (catálogo motivos)
- [x] Modelos AR + constantes (`CircuitoEstado`, `CircuitoEventType`, `TriageScale`)
- [x] `GuardiaTriageService`, `GuardiaCircuitoService`, `GuardiaIngresoService`, `GuardiaQueueService`
- [x] `EmergencyGuardiaController` + rutas en `main.php`
- [x] RBAC ApiGhost + migración `m260603_100001`
- [ ] Tests unitarios servicios (transiciones inválidas, 1 triage activo por guardia)
- [x] Endpoint tablero (`GET tablero`) + listado EMER vía `GuardiaQueueService`

## Reglas de negocio

1. Una guardia **activa** por `id_persona` + `id_efector` (mantener validación actual).
2. Triage obligatorio antes de pasar a `espera_medico` (configurable “triage diferido” por efector en Fase 2).
3. Actualizar triage permitido solo en estados `ingresado`, `espera_triage`, `espera_medico` (no tras `finalizado`).
4. Cada transición genera evento en `guardia_circuito_event`.
5. `id_efector` del request o sesión; médico móvil sin sesión operativa debe enviar `id_efector` en body/query donde aplique (regla paciente/staff API).

## API Fase 1 (detalle)

### POST `/api/v1/emergency/guardia/ingresar`

Body: paciente, ingreso (`ingresa_en`, `ingresa_con`), cobertura, fecha/hora, `id_efector`.

Respuesta: `{ id, circuito_estado, ingreso_at }`.

### POST `/api/v1/emergency/guardia/<id>/triage`

Body:

```json
{
  "scale": "manchester",
  "level": 3,
  "reason_code": "abdominal_pain",
  "reason_text": "Dolor abdominal intenso",
  "vitals": { "bp_sys": 120, "bp_dia": 80, "hr": 88, "rr": 18, "temp_c": 37.2 }
}
```

Efecto: upsert `guardia_triage`, evento `triage`, `circuito_estado = espera_medico`, `prioridad = level`.

### GET `/api/v1/emergency/guardia/tablero`

Query: `id_efector` (opcional si sesión staff), `estado` (filtro), `solo_sin_asignar`, paginación.

Respuesta: lista ordenada por `prioridad ASC`, `ingreso_at ASC` con campos: paciente resumido, nivel, minutos espera, PES asignado, `circuito_estado`.

### GET `/api/v1/emergency/guardia/<id>`

Detalle completo para móvil/web.

## Web (alcance Fase 1)

- Opcional: endpoint consumido por AJAX desde vista existente; **no** obligatorio rediseñar `guardia/_form.php` en PR1.
- Mantener `GuardiaController` operativo; marcar deprecación gradual en comentario de controlador.

## Criterio de aceptación

- Crear ingreso vía API → triage nivel 2 → aparece en `tablero` antes que nivel 4 ingresado después.
- Transición inválida (triage tras finalizado) → 400 con mensaje claro.
- Permisos: usuario sin ruta triage → 403.

## Próximo paso

Fase 2: UI tablero web + ampliar `PacientesController` para usar mismo servicio de cola.
