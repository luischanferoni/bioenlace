# Fase 2 — Perfil materializado

**Estado:** implementada como base V1; requiere validación sobre una BD migrada y prueba de equivalencia incremental/rebuild en el runtime PHP compatible.

## Objetivo

Persistir snapshots factuales y reproducibles por persona, ventana y alcance.

## Trabajo

1. Crear `persona_turnos_perfil`.
2. Crear `persona_turnos_perfil_metrica`.
3. Implementar contrato de métricas versionado.
4. Implementar materialización completa e incremental.
5. Registrar watermark del último evento consumido.
6. Mantener generaciones inmutables y marcar snapshots superados.
7. Agregar reconstrucción por persona, rango y versión.
8. Publicar métricas de cobertura, muestra y confianza.

## Métricas V1

- turnos cerrados elegibles;
- atendidos;
- no-show atribuibles;
- cancelaciones por actor;
- cancelaciones tempranas y tardías;
- reprogramaciones;
- confirmaciones solicitadas, entregadas y respondidas;
- asistencia posterior a confirmación;
- cobertura de eventos nativos e inferidos.

## Invariantes

- Numerador y denominador se conservan.
- Denominador cero no produce tasa.
- Muestra insuficiente no produce nivel conductual.
- El mismo conjunto de eventos y versión genera el mismo perfil.
- Preferencias declaradas no se copian al perfil.
- No se persiste `risk_level`.

## Pruebas

- Ventanas 90, 180 y 365 días.
- Corte correcto por fecha de cita.
- Scopes global, efector, servicio y modalidad.
- Incremental equivalente a reconstrucción completa.
- Eventos corregidos.
- Perfil sin datos y muestra insuficiente.
- Cambio de versión sin mutar snapshots anteriores.

## Criterios de aceptación

- Rebuild e incremental producen resultados idénticos.
- Cada métrica puede explicarse desde sus eventos.
- El snapshot registra contrato, watermark, fecha y completitud.
- Existe proceso batch recuperable y observable.
- La materialización no cambia aún políticas ni turnos.
