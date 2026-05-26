# QA — Captura clínica (encounter + IA)

[← Índice](./README.md) · Producto: [captura-clinica.md](../producto/captura-clinica.md)

---

## CU-CAP-001 — Timeline paciente carga historial

| Campo | Valor |
|-------|-------|
| **ID** | CU-CAP-001 |
| **Prioridad** | P0 |
| **Actor** | Staff |
| **Superficie** | Web `paciente/historia` |

### Precondiciones

- CU-TR-002; paciente con al menos un `encounter` previo.

### Pasos

1. Abrir timeline: `PacienteController::actionHistoria` o URL con `id_persona`.
2. Verificar listado de encuentros / eventos.
3. Expandir un encounter cerrado y uno en curso si existe.

### Resultado esperado

- Sin error PHP/SQL por tablas `consultas` inexistentes.
- Encounters muestran fecha, servicio, resumen según implementación.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-CAP-002 — Analizar texto libre → borrador FHIR

| Campo | Valor |
|-------|-------|
| **ID** | CU-CAP-002 |
| **Prioridad** | P0 |
| **Actor** | Staff |
| **Superficie** | API |

### Pasos

1. Abrir formulario captura con `id_configuracion` válido y encounter en curso o nuevo.
2. Ingresar texto de prueba: motivo, un diagnóstico CIE/SNOMED, una medicación, una práctica.
3. `POST /api/v1/clinical/encounter/analizar` (o ruta equivalente).

### Resultado esperado

- JSON con categorías extraídas alineadas a `workflow_json` del servicio.
- Sin persistencia aún; estructura revisable en UI.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-CAP-003 — Guardar encounter (dx + meds + prácticas)

| Campo | Valor |
|-------|-------|
| **ID** | CU-CAP-003 |
| **Prioridad** | P0 |
| **Actor** | Staff |
| **Superficie** | API + Web |

### Pasos

1. Tras analizar, confirmar guardado en UI.
2. `POST /api/v1/clinical/encounter/guardar`.
3. Consultar BD o timeline: `clinical_condition`, `medication_request`, `service_request`.

### Resultado esperado

- `encounter` actualizado; recursos FHIR creados.
- **No** filas nuevas en `consultas` (tabla no debe existir).

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-CAP-004 — Captura con audio / transcripción

| Campo | Valor |
|-------|-------|
| **ID** | CU-CAP-004 |
| **Prioridad** | P1 |

### Pasos

1. En formulario, grabar audio corto o subir archivo según UI.
2. Transcribir → analizar → guardar.

### Resultado esperado

- Texto transcrito visible; mismo pipeline que CU-CAP-002/003.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-CAP-005 — Permiso atención con turno (parent TURNO)

| Campo | Valor |
|-------|-------|
| **ID** | CU-CAP-005 |
| **Prioridad** | P0 |

### Precondiciones

- Turno del día para paciente en servicio del profesional.

### Pasos

1. Desde agenda turnos, abrir captura del turno (`parent=TURNO`, `parent_id=id_turno`).
2. Si turno de otro profesional sin permiso, intentar mismo flujo.

### Resultado esperado

- Propietario del turno: captura permitida.
- Otro profesional: mensaje de `validarPermisoAtencion` claro (403/flash).

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-CAP-006 — Captura parent INTERNACION

| Campo | Valor |
|-------|-------|
| **ID** | CU-CAP-006 |
| **Prioridad** | P0 |

### Pasos

1. Desde mapa internación, **Atender** paciente internado.
2. URL con `parent=INTERNACION`, `parent_id=<id_internacion>`.
3. Guardar evolución breve.

### Resultado esperado

- `encounter` con `parent_type` internación y `encounter_class` IMP.
- Visible en timeline del paciente.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-CAP-007 — Captura parent GUARDIA / EMER

| Campo | Valor |
|-------|-------|
| **ID** | CU-CAP-007 |
| **Prioridad** | P0 |

### Pasos

1. Desde tablero guardia, iniciar atención (CU-EMER-004).
2. Completar captura mínima y guardar.

### Resultado esperado

- Encounter vinculado al episodio de guardia; circuito pasa a `en_atencion` / coherente con API.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-CAP-008 — Workflow odontología

| Campo | Valor |
|-------|-------|
| **ID** | CU-CAP-008 |
| **Prioridad** | P1 |

### Pasos

1. Servicio odontológico con `EncounterDefinition` que incluya `ConsultaOdontologiaPracticas`, `ConsultaOdontologiaEstados`.
2. Analizar/guardar práctica con pieza + código.
3. Guardar estado pieza (C/P/O) si workflow lo incluye.

### Resultado esperado

- `procedure` + `procedure_odontology_ext`.
- Estados CPO en `clinical_condition` con nota `odontology_state:`.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-CAP-009 — Workflow oftalmología + lentes

| Campo | Valor |
|-------|-------|
| **ID** | CU-CAP-009 |
| **Prioridad** | P1 |

### Pasos

1. Captura con categorías oftalmología y `ConsultasRecetaLentes` si aplica.
2. Guardar y verificar `procedure` / `vision_prescription` según implementación.

### Resultado esperado

- Recursos FHIR oftalmológicos sin error.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-CAP-010 — Derivación en captura → referral

| Campo | Valor |
|-------|-------|
| **ID** | CU-CAP-010 |
| **Prioridad** | P0 |

### Pasos

1. En captura, incluir interconsulta/derivación a otro servicio/efector (texto IA o manual).
2. Guardar.
3. Verificar `service_request`: `category=referral`, `target_efector_id`, `target_service_id`, `referral_status=EN_ESPERA`.

### Resultado esperado

- Listado referencias muestra la derivación (CU-TUR-007).

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-CAP-011 — Balance / régimen / suministro (IMP)

| Campo | Valor |
|-------|-------|
| **ID** | CU-CAP-011 |
| **Prioridad** | P1 |

### Pasos

1. Captura IMP con categorías `ConsultaBalanceHidrico`, `ConsultaRegimen`, `ConsultaSuministroMedicamento` si el workflow las define.
2. Guardar filas de ejemplo.
3. `GET` bundle internación o repositorio: balance/régimen listados.

### Resultado esperado

- `observation` (fluid-balance), `nutrition_order`, `medication_administration` según fila.
- Sin lectura a `consultas_balancehidrico` / `consultas_regimen`.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-CAP-012 — API encounter 401 sin token

| Campo | Valor |
|-------|-------|
| **ID** | CU-CAP-012 |
| **Prioridad** | P1 |

### Pasos

1. Llamar `guardar` sin header Authorization.

### Resultado esperado

- HTTP 401 JSON; sin escritura en BD.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|
