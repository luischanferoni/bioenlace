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

En curso. Glosario inicial en `web/docs/producto/glosario-encounter.md`. Primer corte en `PatientTimelineScreen`: `consultParent`/`consultParentId` y `resumenConsultaCargada` pasan a nombres de dominio `parentType`/`parentId` y `encounterSummaryLoaded`; los getters de captura y razones, y las acciones de análisis/envío, usan nombres internos basados en encounter. El servicio local de historia renombró `getConsultaComoStaff` y `fromStaffConsultaJson`; el modelo de motivos ahora se llama `PatientEncounterReasons` y su campo interno `patientEncounterReasons`/`encounterId`. En `PacientesController`, el identificador local del modelo también se llama `$patientEncounterReasons`; la clave de respuesta wire `motivos_consulta_paciente` se conserva hasta una migración coordinada. Se eliminó `ConsultaGuardarService`, wrapper obsoleto sin consumidores funcionales, y su archivo. Los consumidores `home_screen.dart` y `appointments_screen.dart` se actualizaron en el mismo cambio.

Se renombró coordinadamente el endpoint `ver-consulta-como-staff` a `/clinical/encounter/staff-summary`, junto con la acción Yii, el servicio `StaffEncounterSummaryViewService`, los clientes web/móvil, la configuración de rutas y los permisos. La nueva migración RBAC copia roles/asignaciones existentes a las rutas nuevas y elimina las antiguas, sin alias HTTP. Las rutas históricas siguen mencionadas únicamente en la migración que las creó y en la migración de renombre.

Validación: hot reload de Personal Salud en Chrome pasó sin errores de runtime. `dart analyze` del servicio actualizado no reporta errores, pero conserva seis infos preexistentes sobre `print` y nombres locales. PHP lint de la migración, controlador, resolver de permisos, test y servicio pasó; una aserción runtime directa confirmó que el resolver genera los permisos público y de controlador esperados. El test Codeception no es ejecutable en este entorno: la suite carece de configuración inicializada y la ejecución PHPUnit directa requiere el servicio `di`; Composer además exige PHP >=8.1 y el entorno tiene PHP 8.0. El test smoke `test/widget_test.dart` no compila porque instancia `MyApp` sin el parámetro requerido `isLoggedIn`, ajeno a este cambio. La guarda de fase 00 baja `mobile_consulta_in_code` de 394 a 333, `mobile_consulta_file_names` de 10 a 9, `backend_consulta_file_names` de 60 a 59 y `backend_consulta_routes` de 25 a 23, sin exceder ningún tope.

Pendiente: migrar en un cambio coordinado las claves wire `motivos_consulta_paciente`, `consulta`/`id_consulta`, las demás familias de rutas y el copy visible al catálogo backend. El contador global sigue por encima de cero.
