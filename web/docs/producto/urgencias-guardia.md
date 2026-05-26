# Urgencias / guardia — producto

Programa operativo de **triage + tablero** en efectores con `encounterClass = EMER`. La fuente de verdad es la API v1 `clinical/emergency-guardia`; web y móvil consumen la misma capa.

## Roles y superficies

| Rol | Superficie | Comportamiento |
|-----|------------|----------------|
| Staff (enfermería, admisión) | Web inicio (`site/pacientes` con EMER) | Tablero, triage modal, derivación, egreso, indicadores en cabecera |
| Médico guardia | App médico (inicio EMER) | Tablero, triage, atender → captura clínica, menú (tomar caso, derivar, egreso, re-triage) |
| Dirección / calidad | Web inicio + job nocturno | Resumen en vivo; histórico en `guardia_metrics_daily` |

No hay pantalla web dedicada `guardia/tablero`: el tablero vive en **inicio** según contexto operativo.

## Circuito operativo

Estados canónicos (`circuito_estado`):

1. `ingresado` / `espera_triage` — pendiente de triage  
2. `espera_medico` — triage hecho, en cola  
3. `en_atencion` — médico atendiendo (consulta al abrir captura)  
4. `derivado` / `finalizado` — cierre administrativo  

Eventos auditable en `guardia_circuito_event` (incluye `re_triage`).

## API principal

Base: `/api/v1/clinical/emergency-guardia`

| Acción | Método | Notas |
|--------|--------|-------|
| Tablero | `GET …/tablero` | Cola del efector en sesión |
| Triage | `POST …/{id}/registrar-triage` | Manchester 1–5 + motivo + vitales opcionales |
| Tomar caso | `POST …/{id}/asignar` | PES de sesión si no se envía body |
| Atender | `POST …/{id}/iniciar-atencion` | Devuelve `captura_url` |
| Derivar | `POST …/{id}/derivar` | `id_efector_derivacion`, condiciones |
| Egreso | `POST …/{id}/finalizar` | Libro de guardia |
| Indicadores | `GET …/indicadores-resumen` | Medianas y conteos del día |
| Efectores derivación | `GET …/listar-efectores-derivacion` | Select de destino |

## Notificaciones push

Backend envía:

- `EMERGENCY_ASSIGNED_TO_YOU` — al asignar médico  
- `EMERGENCY_PATIENT_CRITICAL` — triage nivel 1–2  

App **médico**: registro FCM vía `POST /devices/push-token` (`appClient: medico-flutter`). Requiere `google-services.json` / configuración Firebase (mismo patrón que paciente).

## Post-v1 (paquete A)

| Capacidad | API / UI |
|-----------|----------|
| Pedidos y lab en guardia | `GET …/resumen-clinico`, `POST …/crear-pedido`, modal web / menú móvil |
| Internación (cama) | `POST …/solicitar-internacion`, badge en tablero, ingreso web `internacion/create?id_guardia=` |
| SLA por efector | Tabla `efector_emergency_config`, flags `sla_violado` en tablero |
| Export CSV indicadores | `GET …/indicadores-export-csv` |

## Asistente

Intents YAML (sin hardcode de pantalla):

- `urgencias.ver-tablero-guardia` — navega a inicio EMER  
- `urgencias.triage-paciente-guardia` — flujo conversacional de triage (UI JSON `elegir-paciente-triage` → `registrar-triage-formulario`)  

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

## Referencias

- Plan de implementación: [`plans/urgencias-triage-tablero/`](../plans/urgencias-triage-tablero/)  
- HIS madurez: [`his-completo/02-urgencias.md`](../his-completo/02-urgencias.md)  
- Motores asistente: [`arquitectura/asistente-motores.md`](../arquitectura/asistente-motores.md)
