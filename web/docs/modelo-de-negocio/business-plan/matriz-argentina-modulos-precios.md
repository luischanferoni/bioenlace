# Matriz Argentina — precio por profesional × encounter_class

**Tipo:** business plan · go-to-market Argentina  
**Última actualización:** 2026-07-10  
**Alcance:** solo Argentina por ahora; otros países cuando haya validación local.

Precios **orientativos** en USD; no incluyen IVA. Impuestos: [impuestos-argentina.md](../../costos/impuestos-argentina.md).  
COGS de referencia: [costos-api.md](../../costos/costos-api.md) (sin context caching).

**Modelo vigente:** licencia = **Σ (profesionales contratados × precio unitario)**.  
El precio unitario = **COGS × (1 + margen sobre costo)**. El cliente elige qué `encounter_class` contrata (AMB / EMER / IMP), cuántos profesionales por cada una, y si suma **audio** y/o **videollamada**. Lo no contratado **se deshabilita** en sesión operativa y tableros.

Metadata producto: [`pricing-pes-by-encounter-class.yaml`](../../../common/metadata/bioenlace/organization/pricing-pes-by-encounter-class.yaml).  
Calculador público: sitio [`institucional/#precios`](../../../../institucional/index.html).  
Entitlements BD: `efector_encounter_entitlement`.

**Fuera de este modelo (siguen cotizándose aparte):** pathway fees / PMPM al financiador, integraciones one-shot, autorización OS enterprise, proyectos farmacia/quirófano. Ver [modelos-pricing-diferenciados.md](./modelos-pricing-diferenciados.md).

---

## Fórmula

```
COGS = base + (audio ? delta_audio : 0) + (videollamada ? cogs_video : 0)
precio_unitario = COGS × (1 + margin_on_cost_percent/100)
USD/mes ≈ Σ_clase ( cantidad_profesionales[clase] × precio_unitario )
```

| Componente COGS | USD / profesional / mes | Fuente |
|-----------------|-------------------------|--------|
| **Base** (IA + STT captura; motivos paciente solo texto) | **1,24** | costos-api Apartado 1 motivos texto |
| **+ Audio** (motivos paciente con voz) | **+0,31** | delta → Apartado 1 ~1,55 |
| **+ Videollamada** (Twilio, 30 % × 12 min × 2) | **+11,52** | costos-api §6 |

**Margen sobre costo:** **233 %** ≈ margen bruto ~70 % (objetivo software; ver [impuestos-argentina.md](../../costos/impuestos-argentina.md)).

| Configuración | COGS | Precio lista / profesional / mes |
|---------------|------|----------------------------------|
| Solo base | 1,24 | **~4,13** |
| Base + audio | 1,55 | **~5,16** |
| Base + videollamada | 12,76 | **~42,49** |
| Base + audio + videollamada | 13,07 | **~43,52** |

El precio unitario es **igual** en AMB, EMER e IMP: lo que cambia al elegir clase es qué módulos de producto se habilitan.

Ejemplo: 10 profesionales AMB + 4 EMER, sin add-ons → `14 × 4,13 = **USD 57,82/mes**`.  
Mismo plantel con videollamada → `14 × 42,49 = **USD 594,86/mes**`.

---

## Clases vendibles (gates de producto)

| Clase | Label | Qué habilita |
|-------|-------|--------------|
| **AMB** | Ambulatorio | Agenda cupos, turnos paciente, captura AMB |
| **EMER** | Urgencia / guardia | Cobertura roster, tablero guardia, captura EMER |
| **IMP** | Internación | Cobertura piso, mapa camas, captura IMP |

---

## Deshabilitado de lo no contratado

| Capa | Comportamiento |
|------|----------------|
| Wizard / opciones sesión | Solo clases en `efector_encounter_entitlement` (si no hay filas: `default_when_empty: allow_all`) |
| `set-session` | Rechaza `encounter_class` no contratada |
| Agenda AMB / cobertura EMER·IMP | Operan solo si la clase está contratada (vía sesión) |

Tope `max_pes` por clase: declarado en contrato; enforcement estricto de conteo = fase siguiente.

---

## Compradores típicos (lectura rápida)

| Comprador | Selección típica en el calculador |
|-----------|-----------------------------------|
| Clínica ambulatoria | Solo AMB × N profesionales |
| Sanatorio con guardia | AMB + EMER |
| Sanatorio con camas | AMB + EMER + IMP |
| Teleconsulta | Misma selección + add-on videollamada |

---

## Add-ons / fuera de la fórmula

| Ítem | Notas | Precio orientativo |
|------|-------|-------------------|
| Audio / videollamada | En la fórmula (COGS + margen) | Ver tabla arriba |
| Implementación + capacitación | One-shot | USD 3–40k según tamaño |
| Integración LIS | One-shot + mant. | USD 2–8k + 200–800/mes |
| Autorización OS / prepaga | Otro ciclo | USD 2–15k/mes |
| API / white-label | Volumen API | USD 10–40k/mes |

---

## Priorización comercial

1. Publicar calculador institucional (COGS + margen + add-ons).  
2. Cargar entitlements por efector al firmar.  
3. Usage report (encounters / profesionales activos) para renovación.  
4. Pasarela / seña (fase posterior).

---

## Referencias

- [costos-api.md](../../costos/costos-api.md)
- [impuestos-argentina.md](../../costos/impuestos-argentina.md)
- [modelos-pricing-diferenciados.md](./modelos-pricing-diferenciados.md)
