# Servicios de salud (oferta del centro)

**Madurez orientativa:** 3/4

> **Servicio** aquí = oferta asistencial del **efector**, no la especialidad del profesional ni un acto SNOMED.  
> Glosario: [producto/glosario-servicio-pes-acto.md](../producto/glosario-servicio-pes-acto.md).

## Lo que tenemos

- [x] Catálogo de **servicios de salud institucionales** (`servicios`) por efector.
- [x] Profesional vinculado a efector y servicio (**PES**) para agenda y atención.
- [x] Turnos y encounters ambulatorios asociados al servicio del centro.
- [x] Sesión operativa (efector, servicio, clase de encounter) para staff.
- [x] Motivos de consulta y captura alineados al servicio del turno.
- [x] Tipología de oferta (`tipo`, `specialty_code`) y puente a actos (`actos_clinicos` / `linea_acto`).

## Lo que falta

- [ ] Reglas de cobertura y autorización por financiador en todos los flujos.
- [ ] Capacidad instalada y restricciones por recurso físico (salas, equipos).
- [ ] Reportes de producción por servicio unificados para dirección médica.
- [ ] Deprecar filas-acto históricas en `servicios` (p. ej. estudios como si fueran áreas) a favor de acto SNOMED + servicio contenedor.

## Relacionado

[producto/glosario-servicio-pes-acto.md](../producto/glosario-servicio-pes-acto.md) · [producto/turnos.md](../producto/turnos.md) · [producto/captura-clinica.md](../producto/captura-clinica.md) · [decisions/pedido-atencion-linea-acto.md](../decisions/pedido-atencion-linea-acto.md)
