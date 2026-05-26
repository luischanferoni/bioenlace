# QA — Turnos y agenda

[← Índice](./README.md) · Producto: [turnos.md](../producto/turnos.md)

---

## CU-TUR-001 — Agenda staff `/turnos/index`

| Campo | Valor |
|-------|-------|
| **ID** | CU-TUR-001 |
| **Prioridad** | P0 |
| **Actor** | Staff |
| **Superficie** | Web |

### Precondiciones

- Paciente en sesión o `?id=` persona; CU-TR-002.

### Pasos

1. Menú → Turnos / Registrar → `/turnos/index`.
2. Verificar tarjetas por servicio, feriados, referencias activas si hay derivaciones.

### Resultado esperado

- Vista `index` (ex index2) carga sin error.
- Servicios del efector listados.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-TUR-002 — Lista de espera `/turnos/espera`

| Campo | Valor |
|-------|-------|
| **ID** | CU-TUR-002 |
| **Prioridad** | P0 |

### Pasos

1. Menú → Lista de espera o `/turnos/espera?pes=<id_pes>`.
2. Cambiar fecha anterior/siguiente.
3. Verificar listado de turnos del día.

### Resultado esperado

- **No** 404 (actionEspera restaurado).
- Turnos ordenados; datos paciente visibles.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-TUR-003 — Crear turno staff (API)

| Campo | Valor |
|-------|-------|
| **ID** | CU-TUR-003 |
| **Prioridad** | P0 |

### Pasos

1. Desde agenda o API, crear turno para paciente + PES + fecha/hora con cupo libre.
2. Verificar fila en `turnos` y estado inicial.

### Resultado esperado

- Turno persistido; visible en calendario y lista espera.

### API

- Endpoints en `frontend/modules/api/v1/controllers/TurnosController.php`

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-TUR-004 — Paciente crea turno

| Campo | Valor |
|-------|-------|
| **ID** | CU-TUR-004 |
| **Prioridad** | P0 |
| **Actor** | Paciente |
| **Superficie** | App / Asistente |

### Pasos

1. Flujo `turnos.crear-como-paciente` o pantalla nativa equivalente.
2. Elegir efector, servicio, slot disponible.
3. Confirmar.

### Resultado esperado

- Turno creado; aparece en home paciente.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-TUR-005 — Cancelar turno paciente (política)

| Campo | Valor |
|-------|-------|
| **ID** | CU-TUR-005 |
| **Prioridad** | P0 |

### Pasos

1. Turno futuro con anticipación suficiente → cancelar (app o intent).
2. Turno dentro de ventana prohibida → intentar cancelar.

### Resultado esperado

- Caso 1: cancelado.
- Caso 2: error claro con política (`turnos.consultar-politica-autogestion`).

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-TUR-006 — Reprogramar / reubicar

| Campo | Valor |
|-------|-------|
| **ID** | CU-TUR-006 |
| **Prioridad** | P1 |

### Pasos

1. `turnos.modificar-como-paciente-flow` o `turnos.reubicar-como-paciente-flow`.
2. Elegir nuevo horario disponible.

### Resultado esperado

- Fecha/hora actualizadas; notificación push opcional.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-TUR-007 — Referencias `/referencias`

| Campo | Valor |
|-------|-------|
| **ID** | CU-TUR-007 |
| **Prioridad** | P0 |

### Pasos

1. Crear derivación (CU-CAP-010) en otro servicio del mismo efector.
2. Abrir `/referencias/index` con servicio de destino en sesión.

### Resultado esperado

- Grid lista derivaciones `EN_ESPERA` sin SQL error.
- Datos paciente y encounter solicitante visibles.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-TUR-008 — Derivación → turno CON_TURNO

| Campo | Valor |
|-------|-------|
| **ID** | CU-TUR-008 |
| **Prioridad** | P0 |

### Pasos

1. Paciente con derivación pendiente al servicio X.
2. Crear turno en servicio X (CU-TUR-003 o 004).
3. Verificar `service_request.referral_status` y `turnos.parent_class` / `parent_id`.

### Resultado esperado

- Referral pasa a `CON_TURNO`.
- Turno referencia derivación como parent.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-TUR-009 — Sobreturno staff

| Campo | Valor |
|-------|-------|
| **ID** | CU-TUR-009 |
| **Prioridad** | P1 |

### Intent

- `turnos.crear-sobreturno-flow`

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-TUR-010 — No se presentó

| Campo | Valor |
|-------|-------|
| **ID** | CU-TUR-010 |
| **Prioridad** | P1 |

### Intent / API

- `turnos.no-se-presento-flow`, API `no-se-presento`

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-TUR-011 — Calendario profesional

| Campo | Valor |
|-------|-------|
| **ID** | CU-TUR-011 |
| **Prioridad** | P1 |

### Pasos

1. `/turnos/calendario`, `/turnos/eventos` con PES.
2. Verificar eventos JSON para FullCalendar o equivalente.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-TUR-012 — Indicadores agenda

| Campo | Valor |
|-------|-------|
| **ID** | CU-TUR-012 |
| **Prioridad** | P1 |

### API / Intent

- `GET /api/v1/turnos/indicadores-agenda`
- `turnos.indicadores-agenda-flow`

### Resultado esperado

- No-show, lead time, métricas del período sin error.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-TUR-013 — Conflicto agenda

| Campo | Valor |
|-------|-------|
| **ID** | CU-TUR-013 |
| **Prioridad** | P1 |

### Intent

- `turnos.conflicto-agenda-flow`, `agenda.resolver-conflictos-staff-flow`

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-TUR-014 — Confirmar asistencia

| Campo | Valor |
|-------|-------|
| **ID** | CU-TUR-014 |
| **Prioridad** | P2 |

### Intent

- `turnos.confirmar-asistencia-flow`

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-TUR-015 — Push cambio turno

| Campo | Valor |
|-------|-------|
| **ID** | CU-TUR-015 |
| **Prioridad** | P2 |

### Pasos

1. Staff cancela día o mueve turno; paciente con token FCM registrado.

### Resultado esperado

- Push recibido (entorno con Firebase configurado).

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|
