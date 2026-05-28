# Fórmulas por tipo de consulta — drivers de costo del paciente

**Tipo:** modelo · casos  
**Última actualización:** 2026-05-28

Cada caso define:
- una fórmula de \(C_{total}\) (sin montos),
- qué variables suelen ser **dominantes** (las más costosas),
- y cómo afecta la **distancia** (D0/D1/D2).

## 0) Fórmula base y porcentajes

\( C_{total} = \sum C_i \)

\( \%C_i = \frac{C_i}{C_{total}} \times 100 \)

---

## 1) Consultas por urgencia / situación del paciente

### 1.1 Urgencia leve (triage bajo): cuadro agudo simple
**Ej:** resfrío, dermatitis leve, dolor leve.

\( C_{total} = C_{atencion} + C_{traslado} + C_{tiempo} + C_{tratamiento} \)

- **Driver dominante típico (D0):** \(C_{atencion}\) o \(C_{tiempo}\)
- **Driver dominante típico (D2):** \(C_{traslado} + C_{tiempo}\)
- **Etiqueta:** bajo (D0) → medio (D1) → alto (D2)

### 1.2 Urgencia moderada: posible estudio
**Ej:** dolor abdominal moderado, fiebre persistente, trauma menor.

\( C_{total} = C_{atencion} + C_{diagnostico} + C_{traslado} + C_{tiempo} \)

- **Driver dominante:** \(C_{diagnostico}\) (si hay imagen/lab) o \(C_{tiempo}\) (esperas)
- Distancia D2 amplifica traslados/tiempos; si requiere volver, aumenta \(C_{administrativo}\)
- **Etiqueta:** medio–alto

### 1.3 Urgencia alta / riesgo: guardia + procedimientos
**Ej:** dolor torácico, disnea, ACV sospecha.

\( C_{total} = C_{atencion} + C_{diagnostico} + C_{tratamiento} + C_{traslado} + C_{tiempo} + C_{oportunidad} \)

- **Driver dominante:** \(C_{diagnostico}\) y/o \(C_{tratamiento}\)
- **Penalidad \(C_{oportunidad}\):** crece con demoras y distancia (D2) si no hay derivación/traslado adecuado
- **Etiqueta:** alto–muy alto

### 1.4 Internación / procedimiento
**Ej:** apendicitis, fractura compleja, cirugía.

\( C_{total} = C_{atencion} + C_{diagnostico} + C_{tratamiento} + C_{estadia} + C_{acompanante} + C_{tiempo} + C_{administrativo} \)

- **Driver dominante:** \(C_{estadia}\) (si D2), \(C_{tratamiento}\) (insumos/meds), \(C_{tiempo}\) (días)
- **Etiqueta:** muy alto

### 1.4b Derivación a centro de mayor complejidad (D2): procedimientos + estadía
**Ej:** el paciente debe derivarse (programado o urgente) a una ciudad con centro de alta complejidad.

\( C_{total} = C_{atencion} + C_{diagnostico} + C_{tratamiento} + C_{traslado} + C_{tiempo} + C_{acompanante} + C_{estadia} + C_{administrativo} \)

- **Driver dominante (D2):** \(C_{traslado} + C_{tiempo}\) y \(C_{estadia}\) si hay pernocte.
- **Nota:** si hay múltiples “idas y vueltas” por autorizaciones o por estudios previos, aumenta fuerte \(C_{administrativo}\) y se multiplican traslados/tiempos.
- **Etiqueta:** alto → muy alto (si hay estadía o internación)

---

## 2) Consultas por especialidad (drivers típicos)

### 2.1 Clínica médica / generalista
Suele “resolver” sin grandes estudios.

\( C_{total} = C_{atencion} + C_{tratamiento} + C_{traslado} + C_{tiempo} \)

- Dominante: tiempo/traslado (D1/D2) o tratamiento (si Rx)
- Etiqueta: bajo–medio

### 2.2 Pediatría (con acompañante)
\( C_{total} = C_{atencion} + C_{tratamiento} + C_{traslado} + C_{tiempo} + C_{acompanante} \)

- Dominante: \(C_{acompanante} + C_{tiempo}\) (porque casi siempre hay 2 personas)
- Etiqueta: medio (D0) → alto (D2)

### 2.3 Obstetricia / embarazo (controles recurrentes)
\( C_{total} = \sum_k (C_{atencion,k} + C_{diagnostico,k} + C_{traslado,k} + C_{tiempo,k}) \)

- Dominante: **frecuencia** (repetición) + distancia (D1/D2)
- Etiqueta: medio–alto (por acumulación)

### 2.3b Obstetricia / embarazo (D2, posible estadía y acompañante)
**Ej:** controles o estudios programados que obligan a viajar a ciudad con mayor complejidad.

\( C_{total} = \sum_k (C_{atencion,k} + C_{diagnostico,k} + C_{traslado,k} + C_{tiempo,k}) + C_{acompanante} + C_{estadia} \)

- **Driver dominante (D2):** \(C_{traslado} + C_{tiempo}\) y, si aparece, \(C_{estadia}\)
- **Nota:** si los estudios no se coordinan en un solo viaje, crece el re-trabajo (múltiplos de traslado/tiempo).
- **Etiqueta:** alto (si hay estadía o alta frecuencia) → muy alto (si además hay urgencia o internación)

### 2.4 Salud mental (psicología/psiquiatría) — crónico
\( C_{total} = \sum_k (C_{atencion,k} + C_{traslado,k} + C_{tiempo,k}) + C_{tratamiento} \)

- Dominante: **número de sesiones** + tiempo; en psiquiatría aparece tratamiento crónico
- Distancia D2 penaliza muchísimo por recurrencia
- Etiqueta: medio–alto

### 2.4b Crónico de alta recurrencia (D2): control + medicación + múltiples viajes
**Ej:** paciente con enfermedad crónica que necesita controles frecuentes en centro de mayor complejidad.

\( C_{total} = \sum_k (C_{atencion,k} + C_{traslado,k} + C_{tiempo,k} + C_{administrativo,k}) + C_{tratamiento} + C_{estadia} + C_{acompanante} \)

- **Driver dominante (D2):** recurrencia de \(C_{traslado} + C_{tiempo}\); y \(C_{administrativo}\) si hay autorizaciones/derivaciones.
- **Etiqueta:** alto → muy alto si se suma estadía y/o acompañante de forma estable.

### 2.5 Odontología
A menudo “bolsillo” y procedimientos.

\( C_{total} = C_{atencion} + C_{procedimiento} + C_{insumos} + C_{traslado} + C_{tiempo} \)

- Dominante: procedimiento/insumos (cuando existe)
- Etiqueta: alto (procedimientos) / medio (controles)

### 2.6 Especialidad con alta tasa de estudios (trauma, neuro, cardio)
\( C_{total} = C_{atencion} + C_{diagnostico} + C_{tratamiento} + C_{traslado} + C_{tiempo} \)

- Dominante: diagnóstico + tratamiento
- Etiqueta: alto

---

## 3) Casos por “situación del paciente” (contexto social y geográfico)

### 3.1 Paciente sin cobertura formal (usa público, pero paga indirectos)
\( C_{total} = C_{traslado} + C_{tiempo} + C_{administrativo} + C_{tratamiento} \)

- Aunque \(C_{atencion}\) sea bajo/0, dominan **tiempo/traslado** y **tratamiento**
- D2 convierte casos “bajos” en “altos”

### 3.2 Paciente con OS/prepaga (reduce algunos componentes, introduce otros)
\( C_{total} = C_{copago} + C_{traslado} + C_{tiempo} + C_{administrativo} + C_{tratamiento} \)

- Driver dominante: administrativo (autorizaciones/derivaciones) si hay fricción; o tratamiento si crónico
- D2 amplifica el costo de fricción: si el circuito exige volver (papeles, autorizaciones), sube \(C_{administrativo}\) y se multiplican \(C_{traslado}\) y \(C_{tiempo}\)

---

## 4) Nota sobre “cerca / no tan cerca / lejos” (D0/D1/D2)

En casi todos los casos, la distancia no altera “qué” componentes existen, sino **cuánto pesan**:

- En D0 suele dominar \(C_{atencion}\) o \(C_{tratamiento}\) (según si hay Rx).
- En D1/D2 tienden a dominar \(C_{traslado} + C_{tiempo}\), y en D2 puede aparecer \(C_{estadia}\).

Un efecto frecuente es la **no linealidad por re-trabajo**: si el flujo requiere 2–3 visitas (por estudios o autorizaciones), el costo relativo de traslado/tiempo crece más que proporcionalmente.