# Fase 02 — Terminología «encounter»

## Objetivo

Que «encounter» sea el único término en código, rutas, clases y tablas. «Consulta» solo queda en copy español que decida el backend.

## Tareas

- Glosario en `web/docs/producto/` con los términos oficiales (encounter, episodio, captura, motivo) y su equivalente en copy. Frontera fijada: «consulta» solo en lo visible; «encounter» en todo lo interno; «atención» no se usa para este concepto.
- Backend: renombrar las 37 clases con «Consulta» en el nombre. Familias de rutas a renombrar: chat de consulta, motivos de consulta, consultas de seguimiento, ver consulta como staff, consulta async.
- Borrar las rutas viejas sin alias.
- Migraciones nuevas para tablas y columnas que lo exijan, sin vistas de compatibilidad.
- Móvil: clases, archivos, métodos y rutas de las dos apps.
- Web: JS, vistas, assets, claves de configuración.
- Textos visibles: pasan al catálogo de mensajes del backend.
- Prompts y YAML del asistente alineados al glosario.

## Riesgos

Despliegue coordinado de backend, web y apps. Las apps instaladas dejan de funcionar hasta actualizarse.

## Criterio de salida

El contador de «consulta» en código llega a cero y solo se permite en copy declarado por el backend.

## Estado

En curso. Glosario inicial en `web/docs/producto/glosario-encounter.md`. Primer corte en `PatientTimelineScreen`: `consultParent`/`consultParentId` y `resumenConsultaCargada` pasan a nombres de dominio `parentType`/`parentId` y `encounterSummaryLoaded`; los getters de captura y razones, y las acciones de análisis/envío, usan nombres internos basados en encounter. El servicio local de historia renombró `getConsultaComoStaff` y `fromStaffConsultaJson`; el modelo de razones ahora se llama `PatientEncounterReasons` y su campo interno `patientEncounterReasons`/`encounterId`. En `PacientesController`, el identificador local del modelo también se llama `$patientEncounterReasons`. Se eliminó `ConsultaGuardarService`, wrapper obsoleto sin consumidores funcionales, y su archivo. Los consumidores `home_screen.dart` y `appointments_screen.dart` se actualizaron en el mismo cambio.

Se renombró coordinadamente el endpoint `ver-consulta-como-staff` a `/clinical/encounter/staff-summary`, junto con la acción Yii, el servicio `StaffEncounterSummaryViewService`, los clientes web/móvil, la configuración de rutas y los permisos. La nueva migración RBAC copia roles/asignaciones existentes a las rutas nuevas y elimina las antiguas, sin alias HTTP. Las rutas históricas siguen mencionadas únicamente en la migración que las creó y en la migración de renombre.

Validación: hot reload de Personal Salud en Chrome pasó sin errores de runtime. `dart analyze` del servicio actualizado no reporta errores, pero conserva seis infos preexistentes sobre `print` y nombres locales. PHP lint de la migración, controlador, resolver de permisos, test y servicio pasó; una aserción runtime directa confirmó que el resolver genera los permisos público y de controlador esperados. El test Codeception no es ejecutable en este entorno: la suite carece de configuración inicializada y la ejecución PHPUnit directa requiere el servicio `di`; Composer además exige PHP >=8.1 y el entorno tiene PHP 8.0. El test smoke `test/widget_test.dart` no compila porque instancia `MyApp` sin el parámetro requerido `isLoggedIn`, ajeno a este cambio. La guarda de fase 00 baja `mobile_consulta_in_code` de 394 a 333, `mobile_consulta_file_names` de 10 a 9, `backend_consulta_file_names` de 60 a 59 y `backend_consulta_routes` de 25 a 23, sin exceder ningún tope.

En este corte coordinado, la clave wire `motivos_consulta_paciente` pasó a `patient_encounter_reasons` en historia clínica y resumen staff; se actualizaron el backend, el cliente de Personal Salud, los dos consumidores web MVC y la documentación de producto. No se mantiene alias de la clave anterior. Un test del decoder de Personal Salud verifica que la respuesta actual llena `PatientEncounterReasons`.

También se eliminó `consulta_id` del contrato de mensajes de motivos y chat, y de las respuestas de historia clínica: los clientes envían `encounter_id`, los endpoints dejan de aceptar el alias y las respuestas ya no duplican el identificador. Un test PHPUnit verifica que los resolvers reciben `encounter_id` y rechazan `consulta_id`. El endpoint de asistencia de care packs también requiere ahora `encounter_id` o `turno_id`. El modelo de cola offline usa `encounterId`, lee los índices locales previos con `consulta_id` para no perder mensajes pendientes y los reescribe con `encounter_id` en el siguiente guardado. Un test verifica esa migración local. Se retiraron las lecturas del alias en el decoder de mensajes y en el resolver de media.

Verificación del corte de mensajes: el test PHPUnit pasa (2 tests, 4 assertions), los PHP modificados pasan lint, el test de cola offline pasa y `dart analyze` no reporta problemas en los archivos Dart modificados. El hot reload de Paciente en Chrome también pasó y no se encontraron errores de runtime. En el contrato API de esos mensajes no aparece `consulta_id`; se conserva solo la lectura local necesaria para recuperar colas offline anteriores y las migraciones históricas.

En el flujo de captura, los clientes envían `encounter_id`, las respuestas de análisis/guardado ya no duplican `id_consulta` y el endpoint de órdenes activas rechaza ese alias en la query. Los servicios de caché y resolución ya no aceptan el campo antiguo como entrada. Para no perder capturas analizadas antes de este cambio, el presenter convierte `id_consulta` de snapshots persistidos antiguos a `encounter_id` al responder; no lo expone en el contrato HTTP. El test PHPUnit de contrato cubre esta conversión, y pasó con 3 tests y 6 assertions. PHP lint pasó para los servicios, el caso de uso y el controlador afectados; `dart analyze` y el chequeo de formato pasaron para el cliente compartido. El hot reload de Personal Salud en Chrome pasó sin errores de runtime.

Pendiente: migrar los demás campos `consulta`/`id_consulta`, las familias de rutas y el copy visible al catálogo backend. El contador global sigue por encima de cero.
