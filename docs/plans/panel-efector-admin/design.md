# Diseño — Panel de efector en admin

## Arquitectura

Controlador delgado → servicio de aplicación → consultas de lectura. La vista solo presenta datos; no calcula costos ni consulta la base.

| Pieza | Responsabilidad | Ubicación propuesta |
|-------|-----------------|---------------------|
| Controlador | Permiso, período, render | `EfectoresController::actionPanel` |
| Servicio | Componer secciones y aplicar filtros | `Domain/Organization/Efector/Application/Service/EfectorPanelService` |
| Consultas | Agregados por sección | Objetos de consulta en el mismo servicio o en `.../Query` |
| Vista | Tablas y tarjetas Bootstrap | `admin/views/efectores/panel.php` |
| Permiso | Alcance por efector | `BioenlaceAdminAccessControl` + RBAC existente |

El servicio devuelve un arreglo tipado por sección, con valores ya formateados para mostrar y con la fuente indicada. No usa Service Locator: recibe colaboradores por constructor cuando necesite servicios de dominio.

## Fuentes de datos por sección

| Sección | Tabla o servicio | Notas |
|---------|------------------|-------|
| Ficha | `Efector`, `EfectorResumenMetricsService` | Reusar el resumen existente. |
| Contrato | `EfectorEncounterEntitlementService::contractSummary` | Ya devuelve clase, tope, usado, dictado, videollamada y WhatsApp. |
| Uso | `Turno` y `Encounter` | `Encounter` no tiene `id_efector`: unir por `id_profesional_efector_servicio` → `id_efector`. |
| Guardia | `EmergencyIndicatorsService::resumen` | KPIs en vivo; para período histórico, extender con consultas por fecha. |
| Internación | `InpatientIndicatorsService::resumen` | Camas y estada; revisar si el resumen actual es solo del presente. |
| WhatsApp | `AsistenteWhatsappMensajeEstado` | Tiene `wa_id`, estado, `pricing_category`, `pricing_type` y `billable`, pero **no** `id_efector`. |
| Didit | `AsistenteWhatsappVerificacion` | Tiene `id_persona`; el efector se resuelve por los turnos o la pertenencia del paciente. |
| IA | `AICostTracker` | Hoy acumula en memoria y log; no hay histórico por efector. |

## Brecha de IA

`AICostTracker` no persiste tokens por efector. Para mostrar costo de IA en el panel hay que agregar una tabla de uso (tokens de entrada, cacheados, salida, modelo, contexto, `id_efector`, `id_persona` cuando aplique y fecha) y escribir en ella desde el punto donde hoy se registra el uso. Sin esa persistencia, el panel no debe intentar reconstruir costos desde logs.

## Brecha de WhatsApp

`AsistenteWhatsappMensajeEstado` guarda el `wa_id`, no el efector. Opciones:

1. **Agregar `id_efector` al registrar el estado**, resuelto en el envío. Es la opción preferida: el costo queda atribuido desde el origen.
2. Resolver por `wa_id` → `AsistenteWhatsappVinculo.id_persona` → turnos del paciente. Es una aproximación: un paciente puede tener turnos en varios efectores y el mensaje puede no estar atado a ninguno.

Para el MVP, el panel muestra el total del canal y, cuando la atribución es posible, el desagregado por efector; el resto se informa como «sin asignar». No se inventa una asignación.

## Cálculo de costos

- **Precios y COGS:** salen de `PricingPesByEncounterClassCatalog`. No se hardcodean USD en la vista ni en el servicio.
- **Estimado vs facturado:** el panel muestra el COGS estimado (catálogo × volumen) y, cuando existe, el costo medido (Meta, Didit, IA). Son columnas distintas; no se suman sin aclarar.
- **Moneda:** USD para proveedores; ARS solo con cotización explícita y fecha.
- **Período:** todos los agregados usan el mismo rango; el costo de IA y WhatsApp se corta por fecha de evento, no por fecha de alta.

## Privacidad y agregación

- Solo agregados y conteos. No se muestran nombres, documentos, teléfonos ni identificadores de pacientes.
- Si una celda tiene menos de un umbral (por ejemplo, 5 casos), se muestra como «—» para evitar reidentificación.
- Los conteos de profesionales y servicios son agregados; los enlaces llevan a las pantallas existentes, que aplican su propio RBAC.

## Performance

- Consultas con índices por `id_efector` y fecha.
- Cache corto (5 minutos) por efector y período para las secciones costosas.
- El panel no bloquea la ficha: si una sección falla, muestra el error acotado y el resto sigue.
- Paginación o límite en listas (mensajes, verificaciones) con totales agregados.

## Permisos y auditoría

- El controlador valida sesión y alcance antes de resolver datos.
- El `id_efector` se valida contra el alcance del usuario; no se acepta un id arbitrario.
- Las consultas son de solo lectura; no hay acciones de escritura en el panel.

## Alternativas descartadas

- **Reporte SQL manual:** no escala y no tiene permisos ni presentación.
- **Reutilizar `CostosController`:** es de simulación de IA y solo superadmin; no es un panel operativo por efector.
- **Leer logs para costos:** frágil y no auditable.
- **Panel en el frontend del staff:** mezcla backoffice con la operación diaria y duplica permisos.
