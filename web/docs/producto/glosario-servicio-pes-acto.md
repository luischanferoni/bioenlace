# Glosario: servicio de salud, PES y acto

Referencia canónica para producto y desarrollo. Evita confundir tres conceptos distintos que en el habla cotidiana se llaman “servicio” o “especialidad”.

## Frase canónica

> **`servicios`** = oferta de salud del **efector** (centro).  
> **PES** = profesional **asignado a** esa oferta en ese efector.  
> **Acto** (SNOMED/LOINC) = **qué** se indica o pide.  
> No modelar actos (ecografía, mamografía…) como filas de `servicios`.

## Definiciones

| Término | Significado en Bioenlace | Tabla / recurso |
|---------|--------------------------|-----------------|
| **Servicio de salud** (institucional) | Área u oferta que un **centro de salud (efector)** brinda: clínica, kinesiología, imágenes, laboratorio, etc. Aproximación FHIR: **HealthcareService**. | `servicios` |
| **PES** | Asignación operacional: **persona + efector + servicio institucional** (+ agenda). El profesional *trabaja en* el servicio del centro; el PES no “es” una especialidad portable. | `profesional_efector_servicio` |
| **Acto / práctica clínica** | Procedimiento, estudio o pedido tipado (ultrasonido, hemograma…). Dinámico vía terminología. | SNOMED/LOINC; `service_request.code`; caché `actos_clinicos` |
| **Especialidad profesional** | Título / matrícula de la persona (SISA, etc.). | Datos de persona / profesional — **no** es `servicios.id_servicio` |
| **Tipología de oferta** (`specialty_code`) | Código SNOMED/FHIR que tipifica la **oferta institucional** (p. ej. Radiology). No implica “el PES es radiólogo”. | Columnas en `servicios` |

Sinónimos aceptables en UI/docs: **área de atención**, **oferta del centro**, **línea asistencial** (= fila de `servicios`). Evitar usar solo “especialidad” para referirse a `servicios`.

## Diagrama

```text
Efector (centro)
  └── Servicio de salud (oferta del centro)     ← tabla servicios
        └── PES (persona asignada a esa oferta) ← agenda / sesión / atención
              └── puede ejecutar Actos (SNOMED) según capacidad del centro
```

## Si estás modelando X, usá Y

| Querés modelar… | Usá… | No usés… |
|-----------------|------|----------|
| Qué ofrece el centro / a qué área se agenda | `servicios` / `id_servicio` | Especialidad del título del médico |
| Quién atiende en qué centro y área | PES | Una fila nueva por cada estudio |
| “Necesito una ecografía” / “pido ultrasonido” | Acto SNOMED → resolver a un servicio del centro | Fila `ECOGRAFIA` como si fuera especialidad |
| Derivación / pedido clínico | `service_request` (`code` + `target_service_id`) | Solo nombre libre de “servicio” ambiguo |
| Matrícula oftalmólogo | Datos del profesional | `servicios.nombre = OFTALMOLOGIA` como identidad del PES |

## Anti-ejemplos

| Incorrecto | Correcto |
|------------|----------|
| Crear `servicios` = ECOGRAFIA, MAMOGRAFIA por cada estudio | Servicio **Imágenes** (o Radiología) + acto SNOMED ultrasonido / mamografía |
| Crear `servicios` = CORNEA, RETINA, DIABETES, VIH… | Contenedor institucional (Oftalmología / Endocrinología / Clínica) + programa o acto según el caso |
| Pensar que el PES “tiene” especialidad = `id_servicio` | El PES está **asignado** al servicio del efector; la matrícula es otro dato |
| Llamar “especialidad” a todo `id_servicio` en código/PRs | Decir **servicio del centro** / **área** / **línea** |
| Mapear texto “eco” → fila de `servicios` por nombre | Codificar acto (Snowstorm) → capacidad → servicio institucional |

## Relación con pedido línea × acto

El pedido une **acto** (qué) y **servicio institucional** (dónde/quién agenda). Capacidad: ECL por tipología de oferta + `linea_acto` para excepciones. No modelar actos (eco, ECG, PAP…) como filas de `servicios`. Los **canales** (nota staff / hub paciente) alimentan `PedidoAtencion`; la tipificación SNOMED del acto es **dominio**, no otro cerebro IA. Ver [../decisions/pedido-atencion-linea-acto.md](../decisions/pedido-atencion-linea-acto.md).

## Relacionado

- [turnos.md](./turnos.md) — el turno lleva `id_servicio` = oferta del centro  
- [solicitar-atencion.md](./solicitar-atencion.md) — paciente elige servicio institucional (y acto en camino estudio)  
- [medicina-clinica-hub-reserva.md](./medicina-clinica-hub-reserva.md) — hub = servicios con autogestión  
- [interoperabilidad-agendamiento-fhir.md](./interoperabilidad-agendamiento-fhir.md) — HealthcareService ↔ `id_servicio`  
- HIS: [../his-completo/07-servicios-y-especialidades.md](../his-completo/07-servicios-y-especialidades.md)
