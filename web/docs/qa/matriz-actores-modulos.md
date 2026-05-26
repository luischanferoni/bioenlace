# Matriz actores × módulos × superficie

Leyenda: **●** = casos documentados en QA · **○** = parcial / vía API solo · **—** = no aplica · **?** = pendiente documentar

## Por módulo HIS

| Módulo | % madurez | Paciente app | Staff web | Asistente | API directa |
|--------|-----------|--------------|-----------|-----------|-------------|
| [01 Quirófanos](../his-completo/01-quirofanos.md) | ~50% | — | ○ | — | ○ |
| [02 Urgencias](../his-completo/02-urgencias.md) | ~95% | — | ● | ● | ● |
| [03 Internación](../his-completo/03-internacion.md) | ~82% | — | ● | ● | ● |
| [04 LIS](../his-completo/04-lis.md) | ~63% | ● | ○ | ● | ● |
| [05 Farmacia](../his-completo/05-farmacia.md) | ~38% | — | ○ | — | ○ |
| [06 Receta electrónica](../his-completo/06-receta-electronica.md) | ~75% | ● | ● | ● | ● |
| [07 Servicios](../his-completo/07-servicios-y-especialidades.md) | ~75% | — | ○ | — | ● |
| [08 Logística](../his-completo/08-materiales-y-logistica.md) | ~38% | — | — | — | — |
| [09 Facturación](../his-completo/09-facturacion-y-contabilidad.md) | ~38% | — | ○ | — | ○ |
| [10 Ambulatorio](../his-completo/10-atencion-ambulatoria.md) | ~75% | ● | ● | ● | ● |
| [11 Turnos](../his-completo/11-agenda-turnos.md) | ~81% | ● | ● | ● | ● |
| [12 Planes tratamiento](../his-completo/12-planes-tratamiento.md) | ~75% | ● | ● | ● | ● |

## Por superficie UI

| Superficie | Descripción | Archivo QA principal |
|------------|-------------|----------------------|
| **Inicio / panel** | Tablero EMER, mapa IMP, agenda AMB | [03-urgencias](./03-urgencias-guardia.md), [04-internacion](./04-internacion.md), [02-turnos](./02-turnos-agenda.md) |
| **Captura encounter** | Timeline + formulario IA | [01-captura-clinica](./01-captura-clinica.md) |
| **Flow asistente** | Wizard UI JSON | [07-asistente-intents](./07-asistente-intents.md) |
| **Admin / ABM** | Backend, plantillas, RBAC | [06-reportes-nomenclador](./06-reportes-nomenclador.md), [00-transversal](./00-transversal.md) |

## Por `encounter_class`

| Código | Uso en pruebas | Casos clave |
|--------|----------------|-------------|
| `AMB` | Consultorio / ambulatorio | Captura, turnos, resumen paciente |
| `EMER` | Guardia | Tablero, triage, atender, derivar |
| `IMP` | Internación | Mapa camas, captura piso, alta |
| `OBSENC` | Observación | Si el efector lo usa en sesión |
| `VR` / `HH` | Tele / domicilio | P2 según despliegue |

## Sesión operativa (staff)

Antes de bloques IMP/EMER/AMB en web, validar siempre:

| Paso | Caso |
|------|------|
| Login | [CU-TR-001](./00-transversal.md#cu-tr-001) |
| `set-session` efector + servicio + encounter class | [CU-TR-002](./00-transversal.md#cu-tr-002) |
| Permisos menú sin 404 | [CU-TR-003](./00-transversal.md#cu-tr-003) |
