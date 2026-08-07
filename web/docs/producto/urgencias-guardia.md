# Urgencias / guardia — producto

Programa operativo de **triage + tablero** en efectores con `encounterClass = EMER`. La fuente de verdad es la API v1 `clinical/emergency-guardia`; web y móvil consumen la misma capa.

## Roles y superficies

| Rol | Superficie | Comportamiento |
|-----|------------|----------------|
| Staff (enfermería, admisión) | Web inicio (`site/index` con EMER) | Tablero: triage / re-triage, solicitar o ingresar cama, indicadores. **Egreso administrativo** (fuga/abandono) si el paciente se retira sin atención. No sustituye la captura médica. |
| Médico guardia | App Personal de Salud (inicio EMER) | Tablero de cola: **Atender** (tap) abre captura. Sin triage ni “tomar caso” explícito. **Egreso clínico** solo con episodio en atención (o desde la HC). Derivación y signos vitales van en la **captura del encounter**. |
| Dirección / calidad | Web inicio + job nocturno | Resumen en vivo; histórico en `guardia_metrics_daily` |

No hay pantalla web dedicada `guardia/tablero`: el tablero vive en **inicio** según contexto operativo.

### Matriz de acciones (producto)

| Capacidad | Quién | Dónde |
|-----------|-------|--------|
| Triage / actualizar | Staff | Tablero web (+ intent asistente de triage) |
| Tomar caso | — (eliminado como acción) | `iniciar-atencion` asigna el PES de sesión si falta |
| Atender | Médico | Tap en card / botón Atender → captura clínica |
| Signos vitales | Staff en triage (opc.); médico en atención | Misma captura EMER; cards del timeline solo lectura |
| Derivar | Médico | Bloque Derivaciones de la captura (no atajo de tablero) |
| Egreso clínico | Médico | Episodio `en_atencion`: destino + confirmación de diag/epicrisis **heredados de la captura** (no segundo dictado); checklists; sin pedidos que retengan |
| Egreso administrativo | Staff | Sin atención médica: destino `FUGA` / abandono + fecha/hora (+ nota opcional). Sin diag/epicrisis clínicos |
| Cama | Staff / coordinación | Tablero web |

### Dos modos de egreso

| | Clínico | Administrativo |
|--|---------|----------------|
| Cuándo | `circuito_estado = en_atencion` | Cualquier estado abierto **salvo** `en_atencion` (p. ej. espera triage/médico) |
| Quién | Médico | Staff en tablero web (o HC) |
| Qué pide | Destino (alta, observación, internación, quirófano, derivación, defunción, fuga) + confirmación de diag/epicrisis + checklists | Solo fuga/abandono/retiro + fecha/hora |
| Idea | Cierra el **libro clínico** del episodio; la captura ya documentó el encounter | Cierra el **circuito** cuando no hubo médico |

## Circuito operativo

Estados canónicos (`circuito_estado`):

1. `ingresado` / `espera_triage` — pendiente de triage (staff)  
2. `espera_medico` — triage hecho, en cola para el médico  
3. `en_atencion` — médico atendiendo (consulta al abrir captura; asignación implícita)  
4. `derivado` / `finalizado` — cierre (derivación en captura o egreso estructurado)

Eventos auditable en `guardia_circuito_event` (incluye `re_triage`).

## API principal

Base: `/api/v1/clinical/emergency-guardia`

| Acción | Método | Notas |
|--------|--------|-------|
| Panel inicio (tablero) | `GET /api/v1/home/panel` | Sección `emergency_board` (+ `emergency_indicators`) |
| Triage | `POST …/{id}/registrar-triage` | Manchester 1–5 + motivo + vitales opcionales (staff) |
| Asignar | `POST …/{id}/asignar` | Uso interno / legado; el flujo médico usa `iniciar-atencion` |
| Atender | `POST …/{id}/iniciar-atencion` | Asigna PES de sesión si falta; devuelve `captura_url` |
| Derivar | `POST …/{id}/derivar` | Preferir salida vía captura; endpoint operativo permanece |
| Egreso | UI `egreso-formulario` (GET/POST) | Modo según circuito: clínico vs administrativo; `POST …/{id}/finalizar` queda como cierre operativo legacy |
| Indicadores | `GET …/indicadores-resumen` | Medianas y conteos del día |
| Efectores derivación | `GET …/listar-efectores-derivacion` | Select de destino |

## Notificaciones push

Backend envía:

- `EMERGENCY_ASSIGNED_TO_YOU` — al asignar médico  
- `EMERGENCY_PATIENT_CRITICAL` — triage nivel 1–2  

App **Personal de Salud**: registro FCM vía `POST /devices/push-token` (`appClient: personalsalud-flutter`). Requiere `google-services.json` / configuración Firebase (mismo patrón que paciente).

## Captura clínica en guardia

Al atender, la HC se abre con `parent=GUARDIA` y muestra el **banner de episodio** (triaje, circuito, médico, motivo), el **registro cronológico**, signos vitales en **solo lectura** y el formulario de captura EMER (motivos, diagnóstico, medicación, prácticas, indicaciones, **signos vitales**, **derivaciones**). El egreso estructurado cierra el episodio. Detalle: [hcd-episodio-emergencia-internacion.md](./hcd-episodio-emergencia-internacion.md) y [captura-clinica.md](./captura-clinica.md).

Intent: `urgencias.egreso-estructurado-flow` → UI JSON `egreso-formulario`. El descriptor se **filtra por modo** (`modo_egreso`): clínico (destino + confirmación diag/epicrisis + checklists) o administrativo (FUGA + nota).

## Post-v1 (paquete A)

| Capacidad | API / UI |
|-----------|----------|
| Pedidos y lab | Alta y seguimiento en la **captura clínica del encounter** (no hay atajo de pedidos en el tablero). El tablero solo muestra badges informativos de pedidos/lab pendientes. |
| Internación (cama) | `POST …/solicitar-internacion`, badge en tablero, ingreso web `internacion/create?id_guardia=` |
| SLA por efector | Tabla `efector_emergency_config`, flags `sla_violado` en tablero |
| Export CSV indicadores | `GET …/indicadores-export-csv` |

## Asistente

Intents YAML (sin hardcode de pantalla):

- `urgencias.ver-tablero-guardia` — navega a inicio EMER  
- `urgencias.triage-paciente-guardia` — flujo conversacional de triage (UI JSON `elegir-paciente-triage` → `registrar-triage-formulario`); orientado a staff  

Catálogo: `ClinicalUiActionCatalog` + categoría en `CommonActionsService`.

## Operación

Migraciones (orden):

1. `m260603_100000_emergency_guardia_circuito`  
2. `m260603_100001_api_emergency_guardia_rbac`  
3. `m260603_100002_api_emergency_guardia_operaciones_rbac`  
4. `m260603_100003_guardia_metrics_daily`  
5. `m260603_100005_efector_emergency_config` (+ `seg_nivel_internacion.id_guardia`)  
6. `m260603_100007_api_emergency_guardia_post_v1_rbac`  

Job métricas (cron nocturno sugerido):

```bash
php yii emergency-guardia/materialize-metrics
# opcional fecha: php yii emergency-guardia/materialize-metrics 2026-05-19
```

## Fuera de alcance actual

- Vista web dedicada solo de indicadores (el resumen en inicio basta)  
- Sonido automático en tablero al violar SLA (solo alerta visual por ahora)  
- App móvil staff completa (triage/cama nativos); mientras tanto triage/cama en web  

## Cobertura de plantel (agenda EMER)

El tablero clínico no es agenda de cupos. La disponibilidad del personal de guardia se declara como **cobertura** (`profesional_cobertura` materializada desde plantilla semanal `profesional_cobertura_plantilla`, `encounter_class = EMER`). El panel de inicio muestra la sección `staff_cobertura_activa` (`session.tiene_cobertura`, `session.mensaje_sin_cobertura` accionable). **Sin cobertura vigente** el tablero no lista pacientes (ni en API ni en UI); el mensaje orienta a «Configurar mis horarios» en el Asistente o a coordinación/administración del centro. **Iniciar atención / asignar** exige cobertura vigente (metadata `emer_assign_requires_cobertura`). Ver [agenda-por-encounter-class.md](./agenda-por-encounter-class.md).

## Referencias

- Madurez HIS: [his-completo/02-urgencias.md](../his-completo/02-urgencias.md)
- Motores asistente: [arquitectura/asistente-motores.md](../arquitectura/asistente-motores.md)
- Captura clínica en guardia: [captura-clinica.md](./captura-clinica.md)
- Agenda tipada: [agenda-por-encounter-class.md](./agenda-por-encounter-class.md)
