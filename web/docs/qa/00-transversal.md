# QA — Transversal (auth, sesión, personas, infra)

[← Índice](./README.md)

---

## CU-TR-001 — Login staff web

| Campo | Valor |
|-------|-------|
| **ID** | CU-TR-001 |
| **Prioridad** | P0 |
| **Actor** | Staff |
| **Superficie** | Web Yii |

### Pasos

1. Abrir URL frontend (ej. `/site/login`).
2. Ingresar usuario y contraseña válidos de staff.
3. Confirmar redirección al home / wizard post-login si aplica.

### Resultado esperado

- Sesión Yii activa; no queda en login.
- `Yii::$app->user->isGuest` = false.
- Token/cookie de sesión presente.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-TR-002 — Sesión operativa (`set-session`)

| Campo | Valor |
|-------|-------|
| **ID** | CU-TR-002 |
| **Prioridad** | P0 |
| **Actor** | Staff |
| **Superficie** | Web + API `sesion-operativa` |

### Precondiciones

- CU-TR-001 OK.
- Usuario con al menos un efector y servicio asignado.

### Pasos

1. Tras login, completar wizard o flujo de selección de efector/servicio/encounter class.
2. Verificar en UI que muestra nombre de efector y servicio actual.
3. (Opcional) `GET` o inspeccionar que API subsiguientes usan headers JWT + contexto.

### Resultado esperado

- `getIdEfector()`, `getServicioActual()`, `getEncounterClass()` poblados en sesión staff.
- Pantallas EMER/IMP/AMB coherentes con clase elegida.

### API

- `POST /api/v1/sesion-operativa/establecer` (o ruta equivalente del proyecto)

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-TR-003 — Menú principal sin rutas 404

| Campo | Valor |
|-------|-------|
| **ID** | CU-TR-003 |
| **Prioridad** | P0 |
| **Actor** | Staff |
| **Superficie** | Web |

### Pasos

1. Con sesión operativa AMB, recorrer ítems de menú visibles (Turnos, Personas, Nomenclador, Referencias, etc.).
2. Repetir con `encounter_class` EMER e IMP si el rol lo permite.
3. Intentar URL legacy conocida retirada: `/guardia/index`, `/internacion-diagnostico/create`, `/turnos/create`.

### Resultado esperado

- Enlaces del menú cargan sin 404/500.
- URLs legacy: 410 Gone, 403, o redirección — **no** 404 por vista inexistente sin mensaje.
- Lista de espera `/turnos/espera` **carga** (action restaurado fase 04).

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-TR-004 — Login paciente app (JWT)

| Campo | Valor |
|-------|-------|
| **ID** | CU-TR-004 |
| **Prioridad** | P0 |
| **Actor** | Paciente |
| **Superficie** | App Flutter / API |

### Pasos

1. Abrir app paciente; login con credenciales de persona paciente.
2. Verificar home con widgets (turnos, tratamientos, etc.).

### Resultado esperado

- JWT válido; `idPersona` en token.
- **No** exige `set-session` de efector para pantallas paciente.
- Endpoints paciente responden 200 (no 401).

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-TR-005 — Buscar persona (staff)

| Campo | Valor |
|-------|-------|
| **ID** | CU-TR-005 |
| **Prioridad** | P0 |
| **Actor** | Staff |
| **Superficie** | Web |

### Pasos

1. Ir a búsqueda de personas (flujo estándar del efector).
2. Buscar por documento o apellido de paciente de prueba.
3. Abrir ficha / vista de persona.

### Resultado esperado

- Resultados coherentes; ficha carga datos demográficos.
- Desde ficha se puede navegar a historia o turnos si permisos lo permiten.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-TR-006 — Alta / registro paciente

| Campo | Valor |
|-------|-------|
| **ID** | CU-TR-006 |
| **Prioridad** | P1 |
| **Actor** | Staff o Paciente |
| **Superficie** | Web / App |

### Pasos

1. Ejecutar flujo de registro según superficie ([registro-paciente](../producto/flows/registro-paciente.md)).
2. Validar documento; completar campos obligatorios.
3. Confirmar que la persona aparece en búsqueda.

### Resultado esperado

- Persona creada con `id_persona` usable en turnos y captura.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-TR-007 — Migraciones clean-legacy

| Campo | Valor |
|-------|-------|
| **ID** | CU-TR-007 |
| **Prioridad** | P0 |
| **Actor** | DevOps / QA en entorno preparado |
| **Superficie** | Consola |

### Pasos

1. Backup BD.
2. Desde `web/`: `php yii migrate --migrationPath=@common/migrations`
3. Verificar aplicación de (si pendientes):
   - `m260526_160002_service_request_referral_workflow`
   - `m260526_150002_clinical_fhir_drop_legacy_child_tables`
   - `m260526_170001_web_retired_mvc_rbac`
4. Ejecutar suite [08-post-clean-legacy-fhir](./08-post-clean-legacy-fhir.md).

### Resultado esperado

- Migraciones OK sin error SQL.
- Columnas `service_request.target_*`, `referral_status` existen.
- Tablas legacy ausentes en greenfield = omitidas sin fallo.

### Referencias

- [MIGRATIONS.md](../plans/clean-legacy/MIGRATIONS.md)

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-TR-008 — Rutas MVC retiradas

| Campo | Valor |
|-------|-------|
| **ID** | CU-TR-008 |
| **Prioridad** | P1 |
| **Actor** | Staff |
| **Superficie** | Web |

### Pasos

1. GET `/internacion-medicamento/create`, `/internacion-practica/create`, `/internacion-diagnostico/create`.
2. Verificar respuesta.

### Resultado esperado

- **410 Gone** (trait `RetiredInternacionClinicalMvcTrait`) o acceso denegado por RBAC tras migración 170001.
- Mensaje indica captura vía timeline / encounter IMP.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|
