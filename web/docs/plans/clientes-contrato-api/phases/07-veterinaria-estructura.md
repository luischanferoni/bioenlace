# Fase 07 — Veterinaria (estructura MVP)

## Objetivo

Dejar la estructura lista para un MVP veterinario sin implementar todavía endpoints clínicos. Dos apps: Paciente y Personal de Salud.

## Principios

- Vertical separada del dominio humano; se acepta duplicar pantallas.
- Lo compartido es infraestructura neutra: contrato de respuesta, cliente HTTP, componentes y tokens de UI, captura de audio y STT, cola offline.
- Texto que debe vivir en cliente y se refiere a humano se ubica en el módulo humano; el veterinario tiene el suyo.

## App Paciente

- Nueva entidad Animal, vinculada a un tutor, en paralelo a la representación de una persona. No se reutiliza el modelo Persona.
- El formulario de representación ofrece crear un animal y pide los datos básicos (nombre, especie, raza, fecha de nacimiento aproximada, sexo, datos del tutor ya conocidos).
- Al elegir un animal, todo lo que haga el usuario queda asociado a él: solicitud de turnos y demás acciones. El sujeto activo (tipo e identificador) viaja en cada request.
- El backend responde qué acciones existen para ese tipo de sujeto; el cliente no lo decide.
- Los turnos se piden solo en efectores privados con servicio de veterinaria.

## App Personal de Salud

- Registro de módulos por ámbito de atención. Módulo humano (actual, reorganizado) y módulo veterinario (esqueleto con carpetas propias de pantallas, modelos y servicios).
- El ámbito lo informa el backend en la sesión (`ambito_atencion`) y se deduce del servicio del profesional: `Servicio.nombre = 'VETERINARIA'` es veterinario (decisión; ver fase 03). Mismo alta de personal y mismos roles que el resto.
- Pantallas veterinarias particulares donde haga falta, con espacio para archivos propios a futuro.

## Backend

- Dominio nuevo bajo Domain para veterinaria con la gramática de carpetas vigente (Application, Domain, Infrastructure).
- Entidad Animal con vínculo a tutor y catálogos de especie y raza (fuente única, con migración y semilla).
- Sujeto del encounter como referencia genérica (tipo e identificador). Esto requiere refactorizar el motor de captura, hoy atado a `id_persona` (ver fase 4).
- Hipótesis pendiente de validar: iniciar con efectores privados y reutilizar servicio, agenda y turnos. No implementar esta regla hasta acordarla. El ámbito del profesional ya se deriva de `Servicio.nombre = 'VETERINARIA'` (fase 03).
- Hipótesis pendiente de validar: definir encounter veterinario sobre el motor de captura existente y registrarlo como una especialidad más. Requiere resolver el sujeto genérico en fase 4 y auditar persistencia, autorización y prompts antes de afirmar que el motor es reutilizable.
- Sin controllers de encounter veterinario hasta definir el MVP. Documentar la frontera con el dominio humano.

## Fuera de esta fase

Endpoints de encounter veterinario, receta veterinaria, historia clínica completa, integración con turnos de efectores veterinarios.

## Criterio de salida

Esqueleto de dominio y de módulos revisado, glosario veterinario definido y lista de endpoints del MVP escrita (sin implementar).

## Avance

Se materializó la frontera del dominio bajo `common/components/Domain/Veterinary/Animal/` y la vertical móvil bajo `personalsalud/lib/veterinary/`, con carpetas Application, Domain, Infrastructure y pantallas, modelos y servicios. Sus README fijan la gramática módulo-primero, la separación respecto de Clinical humano y la prohibición temporal de rutas/pantallas clínicas. El glosario de Animal, tutor y sujeto genérico ya está en `design.md`.

### Alcance MVP propuesto — requiere confirmación manual

- `GET /api/v1/animals`: listar los animales asociados al tutor autenticado.
- `POST /api/v1/animals`: crear animal con nombre, especie, raza opcional, fecha de nacimiento aproximada y sexo.
- `GET /api/v1/animal-catalogs/species`: catálogo de especies.
- `GET /api/v1/animal-catalogs/breeds?species_id={id}`: razas filtradas por especie.

No se incluyen en esta propuesta turnos, encounter clínico, receta ni historia veterinaria. Antes de crear migraciones o contratos API hay que confirmar el vínculo persistente tutor-animal, los catálogos iniciales y el alcance de edición/listado. La fase sigue en curso por esa decisión manual y por el refactor genérico del sujeto de captura descrito en fase 04; pruebas pendientes no condicionan el estado.

### Análisis pendiente — no implementar todavía

La vertical veterinaria sigue en etapa exploratoria. La propuesta anterior no equivale a una decisión de producto ni a una autorización para adaptar el modelo humano por aproximación. Mantener el trabajo en el esqueleto aislado y no agregar migraciones, endpoints, filtros de servicio ni pantallas clínicas veterinarias hasta cerrar estos puntos:

- Ámbito de atención de un servicio/PES: resuelto por convención (`Servicio.nombre = 'VETERINARIA'`, ver fase 03). Pendiente solo decidir si esa convención se reemplaza luego por una clasificación explícita.
- Validar si el servicio, agenda, efector y turno actuales pueden representar una oferta veterinaria sin introducir ramas veterinarias en contratos clínicos humanos. Confirmar también la regla de efectores privados.
- Acordar el vínculo persistente tutor-animal, permisos de lectura/escritura y representación del sujeto activo en cada request.
- Elegir la fuente de verdad y el primer conjunto de especies/razas, y confirmar si el MVP requiere edición, baja o solo alta/listado.
- Resolver el sujeto genérico de captura de la fase 04 antes de conectar animales al motor de encounter.

Hasta que producto/dominio confirme estas decisiones, la lista de endpoints MVP permanece como borrador. La fase 07 puede continuar solo con documentación y estructura sin comportamiento.
