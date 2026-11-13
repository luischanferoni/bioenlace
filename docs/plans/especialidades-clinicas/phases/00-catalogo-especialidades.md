# Fase 00 — Catálogo de especialidades y contratos de campos

**Estado:** cerrada.

## Objetivo

Que exista un solo lugar donde se declare qué especialidades atiende el sistema, en
qué estado está cada una y qué campos se interpretan. Y que las claves de esos campos
no puedan desfasarse entre el prompt y la interfaz.

## Entregado

- [x] Catálogo de especialidades con estado `specific`, `standard` o `planned`.
      Cubre odontología, oftalmología, enfermería, clínica médica y medicina familiar,
      pediatría, neurología, nutrición, kinesiología, más una veintena de
      especialidades declaradas como pendientes con su descripción pública.
- [x] Las categorías del workflow estándar se derivan de la plantilla del workflow, no
      se transcriben.
- [x] El registro de especialidades por definición de encuentro consume el catálogo y
      dejó de mantener mapas propios.
- [x] La lista de claves por fila vive en el contrato de fila; el modelo de entrada y
      el catálogo la consumen.
- [x] Cada campo declara si ya se extrae o si sólo está previsto para la interfaz.
- [x] Tests: claves únicas, estado coherente con las secciones, el catálogo nunca omite
      un campo que el prompt pide, lo marcado como extraído siempre se pide, y la
      metadata que publica la API sale del catálogo.

## Verificación

Test de catálogo en verde y registry metadata idéntica a la del catálogo. Los gates de
arquitectura, valores derivados y reglas de agente siguen en verde.

## Fuera de esta fase

- Elegir la plantilla de captura por código de especialidad en lugar de por nombre de
  servicio. Requiere datos cargados en los servicios existentes.
- Publicar el catálogo en la web institucional.
