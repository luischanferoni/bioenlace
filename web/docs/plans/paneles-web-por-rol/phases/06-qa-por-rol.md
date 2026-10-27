# Fase 06 — QA por rol y compatibilidad de clientes

## Objetivo

Validar cada panel en web y confirmar que el contrato compartido con las apps no se rompe.

## Tareas

- Checklist por rol en `web/docs/qa/` (extender `qa/quien-puede-que.md` y abrir secciones en `qa/admin_efector/` y `qa/staff/`): panel esperado, secciones visibles, top menu, 403 esperados.
- Recorrido cruzado web ↔ app Personal de Salud con el mismo usuario/efector/servicio (como ya pide `qa/app-personalsalud/README.md`): el panel debe ser coherente.
- Compatibilidad móvil (los paneles de todos los roles se muestran también en la app; no hay requisito de retrocompatibilidad):
  - Verificar en dispositivo que cada panel nuevo renderiza en la app Personal de Salud, o que la app lo ignora sin romper si queda para una iteración siguiente.
  - `role` viaja en la respuesta; la app puede ignorarlo o usarlo en el encabezado.
- Prueba de acceso directo: pedir `/api/v1/home/panel` con cada rol y verificar que las secciones no expongan datos fuera del alcance del rol.

## Criterio de salida

- Checklist ejecutado por los 5 roles de la matriz, con evidencia (capturas o notas).
- App Personal de Salud abre el inicio sin errores con cada rol probado.
- Sin hallazgos abiertos de datos expuestos.

## Estado

Pendiente.
