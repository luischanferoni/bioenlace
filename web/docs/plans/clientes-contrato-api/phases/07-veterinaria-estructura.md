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
- El ámbito lo informa el backend en la sesión y se deduce del servicio del profesional (rol Médico con servicio Veterinaria). Mismo alta de personal y mismos roles que el resto.
- Pantallas veterinarias particulares donde haga falta, con espacio para archivos propios a futuro.

## Backend

- Dominio nuevo bajo Domain para veterinaria con la gramática de carpetas vigente (Application, Domain, Infrastructure).
- Entidad Animal con vínculo a tutor y catálogos de especie y raza (fuente única, con migración y semilla).
- Sujeto del encounter como referencia genérica (tipo e identificador). Esto requiere refactorizar el motor de captura, hoy atado a `id_persona` (ver fase 4).
- Efectores: solo privados con servicio de veterinaria, reutilizando servicio, agenda y turnos.
- Definición de encounter veterinario sobre el mismo motor de captura, como una especialidad más en el registro de la fase 3. Prompts con sujeto variable y pocas instrucciones adicionales para animales.
- Sin controllers de encounter veterinario hasta definir el MVP. Documentar la frontera con el dominio humano.

## Fuera de esta fase

Endpoints de encounter veterinario, receta veterinaria, historia clínica completa, integración con turnos de efectores veterinarios.

## Criterio de salida

Esqueleto de dominio y de módulos revisado, glosario veterinario definido y lista de endpoints del MVP escrita (sin implementar).
