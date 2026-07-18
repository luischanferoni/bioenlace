# Fase 4 — API, UI y transparencia

**Estado:** V1 completa a nivel API/UI JSON (propio, representado, explicación, agregado, solicitud y resolución de corrección). La entrega/apertura de push queda explícitamente fuera hasta evidencia de canal.

## Objetivo

Permitir consulta segura y explicación de hechos y decisiones sin exponer etiquetas estigmatizantes.

## API

### Persona o representante

- Consulta del historial resumido y métricas propias.
- Fecha de actualización, ventanas, muestra y completitud.
- Distinción entre datos nativos e inferidos.
- Acceso a explicación de una acción anti no-show.
- Inicio de solicitud de corrección.

### Staff

- Explicación de una decisión sobre un turno cuando tiene autorización sobre el recurso.
- Hechos pertinentes para resolver una corrección.
- Sin acceso general a una categoría reputacional.

### Dirección

- Agregados por efector, servicio, período y modalidad.
- Supresión de cohortes pequeñas.
- Sin identificación individual.

## UI

El lenguaje para la persona utiliza “historial de turnos” y explica:

- qué datos se observaron;
- durante qué período;
- qué fuente y calidad tienen;
- cómo afectaron una acción;
- cómo pedir una corrección.

Web y móvil consumen el mismo contrato JSON. Las variaciones se declaran mediante metadata y capacidades genéricas.

## Seguridad

- Acciones API explícitas para propio/representado, recurso staff y agregado de efector.
- Autorización en servicios de dominio.
- Auditoría de consultas individuales.
- No exponer motivos libres ni datos clínicos.
- No devolver métricas de otras personas por identificadores manipulables.

## Pruebas

- Persona propia y representación autorizada.
- Acceso staff permitido y denegado.
- Supresión de cohortes pequeñas.
- Explicación de snapshot superado.
- Corrección pendiente y aplicada.
- Paridad contractual entre web y móvil.

## Criterios de aceptación

- La persona puede comprender y cuestionar sus datos.
- Toda acción automática presenta evidencia y versión.
- No existen endpoints genéricos ambiguos para sujetos con permisos diferentes.
- Los agregados no permiten reidentificación razonable.
- Accesibilidad y lenguaje neutral aprobados.
