# Urgencias / guardia — producto

Programa operativo de **triage + tablero** en efectores con `encounterClass = EMER`. La fuente de verdad es la API v1 `clinical/emergency-guardia`; web y móvil consumen la misma capa.

## Roles y superficies

| Rol | Superficie | Comportamiento |
|-----|------------|----------------|
| Staff (enfermería, admisión) | Web inicio (`site/index` con EMER) y app Personal de Salud | Tablero: **primer triage** (web y app; `puede_triage` en `home/panel`), **Ingresar cama** (si hay pedido pendiente), indicadores. Puede registrar **Paciente se retiró** mientras el episodio sea operable. |
| Médico guardia | Web inicio EMER + app Personal de Salud | Tablero: **Atender** → captura del encounter (requiere triage). Conducta (alta, internación, derivación) **en la captura**. Tras documentar → **Ver consulta** (lectura). **Paciente se retiró** solo si aún está en atención (sin documentación de cierre). Triage del médico: solo en HC. |
| Dirección / calidad | Web inicio + job nocturno | Resumen en vivo; histórico en `guardia_metrics_daily` |

No hay pantalla web dedicada `guardia/tablero`: el tablero vive en **inicio** según contexto operativo.

### Matriz de acciones (producto)

| Capacidad | Quién | Dónde |
|-----------|-------|--------|
| Primer triage | Staff (`triage_roles` del manifiesto) | Tablero web y app Personal de Salud (`espera_triage`) |
| Editar / actualizar triage | Médico (y staff con HC abierta) | Historia clínica del episodio (`editar_triage` en banner). **No** en el tablero |
| Tomar caso | — (eliminado) | `iniciar-atencion` asigna el PES de sesión si falta |
| Atender | Médico | Tablero → captura clínica (requiere triage previo; episodio no `atendido`/`derivado`/`finalizado`) |
| Ver consulta | Médico / staff | Tablero cuando `circuito_estado = atendido` (o `derivado` con encounter): lectura como AMB (`/paciente/ver-consulta?encounter_id=…`) |
| Signos vitales | Staff en triage (opc.); médico en atención | Captura EMER; cards del timeline solo lectura |
| Derivar / alta / pedir internación | Médico | **Captura del encounter** (`EncounterDefinition` EMER). El sistema deduce y mueve el circuito |
| Paciente se retiró | Médico o staff | Solo tablero (⋮ móvil / CTA web), mientras el episodio sea operable. Cierra como `FUGA`. No aparece en la HC |
| Ingresar cama | Staff / administrativo | Tablero web cuando `internacion_pendiente` |

### Conducta clínica = captura, no segundo formulario

El médico **documenta encounters** según la configuración del efector (`encounter_definition.workflow_json`: secciones y campos requeridos por clase/servicio). En guardia (`emer_standard`) eso incluye motivos, SV, diagnóstico, medicación, prácticas, indicaciones y derivaciones.

Al guardar la captura, `GuardiaEncounterOutcomeService`:

- **Internación:** si queda explícito el pase a internación/UCI → pedido de cama; el staff completa **Ingresar cama**. El circuito **no** pasa a `atendido` mientras haya pedido pendiente / resuelto por internación.
- **Derivación institucional:** señales de derivación a otro efector → `circuito_estado = derivado`.
- **Alta / control / resto de documentación:** → `circuito_estado = atendido` (cierre clínico). Tablero: solo **Ver consulta**, sin Atender / se retiró / triage.
- **Retiro sin atención o abandono:** única acción explícita de cierre fuera de la captura = **Paciente se retiró** (`finalizado` / FUGA).

Detalle de captura: [captura-clinica.md](./captura-clinica.md). Plantilla: `EncounterDefinitionWorkflowCatalog::TEMPLATE_EMER_STANDARD`. Textos de ejemplo: [textos-ejemplo-captura-emer.md](../qa/escenarios/urgencia/textos-ejemplo-captura-emer.md).

## Circuito operativo

Estados canónicos (`circuito_estado`):

1. `ingresado` / `espera_triage` — pendiente de triage (staff)  
2. `espera_medico` — triage hecho, en cola para el médico  
3. `en_atencion` — médico atendiendo (consulta al abrir captura; asignación implícita)  
4. `atendido` — consulta documentada / cierre clínico (permanece en tablero del día; solo Ver consulta)  
5. `derivado` / `finalizado` — cierre (derivación en captura, internación ingresada, o paciente se retiró)

Eventos auditable en `guardia_circuito_event` (incluye `re_triage` solo desde HC / API de triage, no CTA de tablero).

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
- App móvil: primer triage nativo si `puede_triage`; ingreso a cama vía UI JSON. Ingreso a guardia (Administrativo) sigue pendiente de CTA.  

## Cobertura de plantel (agenda EMER)

Ver [agenda-por-encounter-class.md](./agenda-por-encounter-class.md).

## Referencias

- Madurez HIS: [his-completo/02-urgencias.md](../his-completo/02-urgencias.md)
- Captura clínica: [captura-clinica.md](./captura-clinica.md)
