# Fase 02 — Vista admin y navegación

## Objetivo

Exponer el panel en admin, con permisos, navegación y estados vacíos.

## Controlador

- Acción `actionPanel` en `EfectoresController`.
- Valida sesión, `BioenlaceAdminAccessControl` y alcance sobre el `id_efector`.
- Lee período y clase de la query string; aplica valores por defecto.
- Renderiza `admin/views/efectores/panel.php` con las secciones del servicio.

## Vista

- Bootstrap primero: tarjetas, tablas y badges; sin CSS custom salvo lo que Bootstrap no cubra.
- Encabezado con nombre del efector, estado y período.
- Secciones en el orden del [overview](../overview.md).
- Cada sección muestra la fuente y la fecha de corte.
- Estados vacíos explícitos («sin datos en el período») y errores acotados por sección.
- Enlaces a las pantallas existentes de billing, licencia, profesionales y servicios.

## Navegación

- Botón «Panel del efector» en `EfectoresController::actionView` y `actionLicencia`.
- Breadcrumb de vuelta a la ficha.
- El menú de admin no cambia: se entra desde el efector.

## Permisos

- Superadmin: cualquier efector.
- Administrador con alcance: solo los efectores habilitados por RBAC.
- Sin alcance: 403, no una vista vacía.

## Tareas

- [ ] Agregar `actionPanel` y la regla de alcance.
- [ ] Crear la vista con las secciones y los estados vacíos.
- [ ] Agregar los botones de navegación.
- [ ] Verificar que no haya datos personales en el HTML.
- [ ] Probar con un efector con datos y otro sin datos.

## Criterio de salida

Un usuario autorizado abre el panel desde la ficha del efector, ve las secciones del MVP y no puede acceder a un efector fuera de su alcance.
