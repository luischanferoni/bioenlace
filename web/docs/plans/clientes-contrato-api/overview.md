# Overview — Clientes delgados y contrato de API

## Objetivo

Que el backend sea la única fuente de verdad de textos, estados, vocabulario clínico, reglas y acciones permitidas, y que las apps móviles (Paciente y Personal de Salud) y la web solo rendericen, naveguen y accedan al hardware. La UX actual se mantiene; cambia la arquitectura, hoy rígida y acoplada a un caso de uso.

## Por qué

Los mensajes de error, las etiquetas de estado, las claves clínicas (peso, talla, tensión) y los tipos de episodio (guardia, internación) están escritos en los clientes. Cuando el backend cambia, los clientes quedan desincronizados. Agregar una especialidad o un tipo de sujeto obliga a tocar tres bases de código. La palabra «consulta» convive con «encounter» en código, rutas y tablas.

## Alcance

Todo el sistema: backend (API, dominio, catálogos), web (Yii, JS, vistas), app Paciente, app Personal de Salud, asistente, admin, jobs y documentación. Sin retrocompatibilidad: se borra lo viejo. Un solo idioma (español).

## Actores

- Backend: API v1, servicios de dominio, catálogos YAML.
- App Personal de Salud: médicos de distintas especialidades, enfermería y, a futuro, veterinaria.
- App Paciente: representación de terceros; a futuro, representación de una mascota.
- Web: operación clínica (Camino A), asistente SPA, admin.

## Línea base medida

| Área | Hallazgo | Cantidad aproximada |
|------|----------|---------------------|
| Móvil | Textos de usuario literales | 325 en 37 archivos |
| Móvil | Vocabulario clínico fijo (guardia, internación, signos vitales, triage) | 908 en 72 archivos |
| Móvil | «consulta» en identificadores, rutas y textos | 521 en 39 archivos |
| Móvil | Archivos duplicados entre apps | 6 o más |
| Web JS | HTML concatenado | 237 |
| Web JS | Textos de UI literales | 66 |
| Web vistas PHP | Lógica de request, rol o URL dentro de la vista | 40 |
| Backend API | Mensajes literales en controllers | 68 |
| Backend dominio | Mensajes literales en servicios | 659 |
| Backend | Archivos con «Consulta» en el nombre | 37 |

Estas cifras salen de búsquedas textuales. La fase 0 las convierte en contadores oficiales.

## Fuera de este plan

- Multi-idioma.
- Personalización de vocabulario por institución.
- Implementar endpoints clínicos veterinarios (solo se deja estructura y contrato).
- Atributos avanzados del animal más allá del alta básica.
- Rediseñar la UX de las apps.
- Reescribir la web en un framework SPA (se mantiene Camino A).

## Fases

| Fase | Qué |
|------|-----|
| [00](./phases/00-linea-base-y-guardas.md) | Contadores base y guardas de CI que impiden regresiones |
| [01](./phases/01-contrato-api.md) | Formato único de respuesta, errores con código, catálogo de mensajes |
| [02](./phases/02-terminologia-encounter.md) | Glosario y renombrado de «consulta» a «encounter» sin alias |
| [03](./phases/03-metadata-clinica-humana.md) | Secciones clínicas, tipos de episodio y triage desde el backend |
| [04](./phases/04-captura-y-stt.md) | Decisiones de audio y transcripción en el servidor |
| [05](./phases/05-movil.md) | Shared, cliente HTTP único y reescritura de pantallas |
| [06](./phases/06-web.md) | Plantillas en lugar de HTML en JS, vistas delgadas, cliente HTTP común |
| [07](./phases/07-veterinaria-estructura.md) | Estructura de la vertical veterinaria (sin endpoints clínicos) |
| [08](./phases/08-asistente-admin-jobs-docs.md) | Asistente, admin, notificaciones y documentación |
| [09](./phases/09-cierre.md) | Pruebas, cierre y volcado a documentación estable |

## Orden

La fase 0 va primero. La fase 1 bloquea al resto. Las fases 2 y 3 van antes que los clientes para no renombrar dos veces. Las fases 5 y 6 pueden ir en paralelo. La fase 7 puede empezar cuando el contrato de la fase 1 esté cerrado.

## Piloto

Captura y timeline del encounter, en la app Personal de Salud y en la web. Valida contrato, estados, secciones y STT antes de extender al resto de las pantallas.
