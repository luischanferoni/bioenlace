# Matriz Argentina — precio por profesional × encounter_class

**Tipo:** business plan · go-to-market Argentina  
**Última actualización:** 2026-07-17  
**Alcance:** solo Argentina por ahora; otros países cuando haya validación local.

Precios **orientativos** en USD; no incluyen IVA. Impuestos: [impuestos-argentina.md](../../costos/impuestos-argentina.md).  
COGS de referencia: [costos-api.md](../../costos/costos-api.md) (**columna con context caching**).

**Modelo vigente:** licencia = **Σ (profesionales contratados × precio unitario)**.  
El precio unitario = **COGS × (1 + margen sobre costo)**. El cliente elige qué `encounter_class` contrata (AMB / EMER / IMP), cuántos profesionales por cada una, y si suma **audio** y/o **videollamada**. Lo no contratado **se deshabilita** en sesión operativa y tableros.

Metadata producto: [`pricing-pes-by-encounter-class.yaml`](../../../common/metadata/bioenlace/organization/pricing-pes-by-encounter-class.yaml).  
Calculador público: sitio [`institucional/#precios`](../../../../institucional/index.html).  
Entitlements BD: `billing_account` + `billing_account_encounter_entitlement` (pool). Legacy: `efector_encounter_entitlement` (backfill).  
Admin: Licencias / Contratos.

**Fuera de este modelo (siguen cotizándose aparte):** pathway fees / PMPM al financiador, integraciones one-shot, autorización OS enterprise, proyectos farmacia/quirófano. Ver [modelos-pricing-diferenciados.md](./modelos-pricing-diferenciados.md).

---

## Fórmula

```
COGS_ref = base
         + ((audio || videollamada) ? stt_profesional : 0)   # STT una sola vez
         + (videollamada ? cogs_video : 0)
COGS_clase = COGS_ref × (encounters_clase / 400)
precio_unitario_clase = COGS_clase × (1 + margin_on_cost_percent/100)
USD/mes ≈ Σ_clase ( cantidad_profesionales[clase] × precio_unitario_clase )
```

| Componente COGS (a 400 encounters/mes, **con context caching**) | USD / profesional / mes | Fuente |
|----------------------------------------------------------------|-------------------------|--------|
| **Base** (IA + captura texto; motivos paciente texto; **sin** STT del profesional) | **0,95** | Apartado 1 motivos texto con caché − STT §4 bruto (~1,40) |
| **+ Audio / STT** (profesional ~5 min, **−30 % on-device**) | **+0,98** | costos-api § STT; **incluido** si hay videollamada |
| **+ Videollamada** (self-host Track Egress + storage; solo AMB) | **+3,50** | costos-api §6 (**sin** STT duplicado) |

**Margen sobre costo:** **233 %** ≈ margen bruto ~70 % (objetivo software; ver [impuestos-argentina.md](../../costos/impuestos-argentina.md)).

**Regla comercial:** videollamada **incluye** la transcripción de la llamada (= mismo COGS `audio` una vez). No se suma dictado + video como dos STT.


### Volumen por clase (por profesional / mes, época normal)

| Clase | Volumen lista | Rango de estimación (orientativo) | Dictado | Videollamada |
|-------|---------------|-----------------------------------|---------|--------------|
| **AMB** | **400** | costos-api (20×20) | Opcional (auto si hay video) | Opcional |
| **EMER** | **350** | Normal ~195–455; pico ~455–780+ | **Obligatorio** (incluido) | **No** |
| **IMP** | **300** | Típico chico–mediano ~100–960; grandes 960–2400+ | **Obligatorio** (incluido) | **No** |

No hay SKU chico/mediano/grande: el tamaño se refleja en **cantidad de profesionales**. Picos y outliers → más N o cotización.

| Configuración | COGS (a vol. de la clase) | Precio lista / profesional / mes |
|---------------|---------------------------|----------------------------------|
| AMB solo base | 0,95 | **~3,16** |
| AMB + audio (sin video) | 1,93 | **~6,43** |
| AMB + videollamada (incluye STT) | 5,43 | **~18,08** |
| AMB + audio + videollamada | **5,43** (igual; STT no se duplica) | **~18,08** |
| EMER (audio incluido, vol 350) | 1,689 | **~5,62** |
| IMP (audio incluido, vol 300) | 1,448 | **~4,82** |

Ejemplo: 10 AMB + 4 EMER, sin add-ons AMB → `10×3,16 + 4×5,62 = **USD 54,08/mes**`.
Ejemplo tele: 10 AMB con videollamada → `10×18,08 = **USD 180,80/mes**`.

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
| Wizard / opciones sesión | Solo clases contratadas en la **cuenta** del efector (`billing_account_encounter_entitlement`; si no hay cuenta/filas: `default_when_empty: allow_all`) |
| `set-session` | Rechaza `encounter_class` no contratada |
| Agenda AMB / cobertura EMER·IMP | Operan solo si la clase está contratada (vía sesión) |
| Alta PES | Tope `max_pes` del **pool de la cuenta de facturación** (personas distintas en efectores con rol **POOL**; excluye AdminEfector) |
| Baja PES | Operativa inmediata; el mes en curso se cobra con el `max_pes` vigente. Pending a nivel cuenta, efectivo el **1º del mes siguiente** (`php yii entitlement/apply-pending-downgrades`). |
| Admin | [`/billing-account`](../../../../admin/) — cuentas Ministerio/Red/Efector, miembros con rol, editar `max_pes` / dictado / videollamada por clase. Tab **Licencia** en ficha de efector. |

### Cuentas y pool

```
billing_account (MINISTERIO | RED | EFECTOR)
  ├── billing_account_efector (rol_membresia: POOL | AFILIADO)
  └── billing_account_encounter_entitlement (AMB/EMER/IMP + max_pes + pending + flags)
```

| Rol | Significado |
|-----|-------------|
| **POOL** | Consume `max_pes` de esa cuenta (facturación). Máx. **una** membresía POOL por efector. |
| **AFILIADO** | Solo jerarquía (ministerio/red). No consume cupo. Compatible con POOL en otra cuenta (efector autárquico). |

Un efector sin membresía POOL: sin tope (compat). Cupo compartido: el uso suma profesionales billable solo de miembros **POOL**.

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
2. Cargar cuentas (`billing_account`) y entitlements de pool al firmar; asociar efectores como **POOL** o **AFILIADO** (autárquicos: afiliados al ministerio + pool en cuenta propia).  
3. Enforce alta PES + downgrade diferido (`entitlement/apply-pending-downgrades`).  
4. Admin Licencias / Contratos para editar `max_pes` y miembros.  
5. **Self-service institucional** (AdminEfector + pasarela **simulada**) y solicitud asistida AdminMinisterio — ver [alta-cuenta-licencia.md](../../producto/alta-cuenta-licencia.md).  
6. Usage report para renovación; pasarela real (Mercado Pago / Stripe) sustituyendo el gateway simulado.

---

## Referencias

- [costos-api.md](../../costos/costos-api.md)
- [impuestos-argentina.md](../../costos/impuestos-argentina.md)
- [modelos-pricing-diferenciados.md](./modelos-pricing-diferenciados.md)
