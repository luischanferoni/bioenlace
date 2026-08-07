# Urgencias / guardia — producto

Programa operativo de **triage + tablero** en efectores con `encounterClass = EMER`. La fuente de verdad es la API v1 `clinical/emergency-guardia`; web y móvil consumen la misma capa.

## Roles y superficies

| Rol | Superficie | Comportamiento |
|-----|------------|----------------|
| Staff (enfermería, admisión) | Web inicio (`site/index` con EMER) | Tablero: triage / re-triage, **Ingresar cama** (si hay pedido pendiente), indicadores. Puede registrar **Paciente se retiró**. |
| Médico guardia | App Personal de Salud (inicio EMER) | Tablero: tap → captura del encounter. Conducta (alta, internación, derivación) **en la captura**. **Paciente se retiró** solo en menú ⋮ del tablero (no en la HC). |
| Dirección / calidad | Web inicio + job nocturno | Resumen en vivo; histórico en `guardia_metrics_daily` |

No hay pantalla web dedicada `guardia/tablero`: el tablero vive en **inicio** según contexto operativo.

### Matriz de acciones (producto)

| Capacidad | Quién | Dónde |
|-----------|-------|--------|
| Triage / actualizar | Staff | Tablero web |
| Editar triage | Médico (y staff con HC abierta) | Historia clínica del episodio (`editar_triage` en banner); no en el tablero para el médico |
| Tomar caso | — (eliminado) | `iniciar-atencion` asigna el PES de sesión si falta |
| Atender | Médico | Tap en card → captura clínica |
| Signos vitales | Staff en triage (opc.); médico en atención | Captura EMER; cards del timeline solo lectura |
| Derivar / alta / pedir internación | Médico | **Captura del encounter** (`EncounterDefinition` EMER: secciones Derivaciones / Indicaciones). El sistema deduce y marca pedidos (p. ej. cama pendiente) |
| Paciente se retiró | Médico o staff | Solo tablero (⋮ móvil / CTA web). Cierra circuito como `FUGA`. No aparece en la HC |
| Ingresar cama | Staff / administrativo | Tablero web cuando `internacion_pendiente` |

### Conducta clínica = captura, no segundo formulario

El médico **documenta encounters** según la configuración del efector (`encounter_definition.workflow_json`: secciones y campos requeridos por clase/servicio). En guardia (`emer_standard`) eso incluye motivos, SV, diagnóstico, medicación, prácticas, indicaciones y derivaciones.

- **Internación:** si en la captura queda explícito el pase a internación/UCI, `GuardiaEncounterOutcomeService` marca pedido de cama; el staff completa **Ingresar cama**.
- **Alta / control:** se documenta en Indicaciones (y diagnóstico) de la captura; no hay formulario de “egreso clínico”.
- **Retiro sin atención o abandono:** única acción explícita de cierre de circuito fuera de la captura = **Paciente se retiró**.

Detalle de captura: [captura-clinica.md](./captura-clinica.md). Plantilla: `EncounterDefinitionWorkflowCatalog::TEMPLATE_EMER_STANDARD`.

## Circuito operativo

Estados canónicos (`circuito_estado`):

1. `ingresado` / `espera_triage` — pendiente de triage (staff)  
2. `espera_medico` — triage hecho, en cola para el médico  
3. `en_atencion` — médico atendiendo (consulta al abrir captura; asignación implícita)  
4. `derivado` / `finalizado` — cierre (derivación en captura, internación ingresada, o paciente se retiró)

Eventos auditable en `guardia_circuito_event` (incluye `re_triage`).

## API principal

Base: `/api/v1/clinical/emergency-guardia`

| Acción | Método | Notas |
|--------|--------|-------|
| Panel inicio (tablero) | `GET /api/v1/home/panel` | Sección `emergency_board` (+ `emergency_indicators`) |
| Triage | `POST …/{id}/registrar-triage` | Manchester 1–5 + motivo + vitales opcionales (staff) |
| Asignar | `POST …/{id}/asignar` | Uso interno / legado; el flujo médico usa `iniciar-atencion` |
| Atender | `POST …/{id}/iniciar-atencion` | Asigna PES de sesión si falta; devuelve `captura_url` |
| Derivar | Preferir captura | Endpoint operativo permanece por compatibilidad |
| Paciente se retiró | UI `egreso-formulario` | Solo retiro/fuga; destino fijo `FUGA` |
| Ingresar cama | UI ingreso internación | Staff; requiere `internacion_pendiente` (pedido desde captura) |
| Indicadores | `GET …/indicadores-resumen` | Medianas y conteos del día |

## Notificaciones push

Backend envía:

- `EMERGENCY_ASSIGNED_TO_YOU` — al asignar médico  
- `EMERGENCY_PATIENT_CRITICAL` — triage nivel 1–2  

App **Personal de Salud**: registro FCM vía `POST /devices/push-token` (`appClient: personalsalud-flutter`).

## Captura clínica en guardia

Al atender, la HC se abre con `parent=GUARDIA` (banner + timeline + captura). El médico puede **Editar triage** desde el banner; **Paciente se retiró** no aparece en la HC (solo tablero). Ver [hcd-episodio-emergencia-internacion.md](./hcd-episodio-emergencia-internacion.md).

## Post-v1 / evolución de configuración

Hoy la deducción de internación usa señales en Derivaciones/Indicaciones + texto. El camino deseado es **declarar en `EncounterDefinition`** (secciones/campos requeridos por efector) la conducta de guardia de forma estructurada, para no depender de keywords. Mientras tanto, IA (`clinical-text-ia.yaml`) orienta a dejar explícita internación/alta en esas categorías.

## Asistente

- `urgencias.ver-tablero-guardia` — navega a inicio EMER  
- `urgencias.triage-paciente-guardia` — triage (staff)  
- `urgencias.egreso-estructurado-flow` — paciente se retiró  

## Operación

Migraciones (orden): ver historial `m260603_*` emergency_guardia + egreso estructurado.

Job métricas:

```bash
php yii emergency-guardia/materialize-metrics
```

## Fuera de alcance actual

- Vista web solo de indicadores  
- App móvil staff completa (triage/cama nativos); triage/cama en web  

## Cobertura de plantel (agenda EMER)

Ver [agenda-por-encounter-class.md](./agenda-por-encounter-class.md).

## Referencias

- Madurez HIS: [his-completo/02-urgencias.md](../his-completo/02-urgencias.md)
- Captura clínica: [captura-clinica.md](./captura-clinica.md)
