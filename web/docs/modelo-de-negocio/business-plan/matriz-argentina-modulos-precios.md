# Matriz Argentina — precio por volumen de atenciones × encounter_class

**Tipo:** business plan · go-to-market Argentina  
**Última actualización:** 2026-07-23  
**Alcance:** solo Argentina por ahora; otros países cuando haya validación local.

Precios **orientativos** en USD; no incluyen IVA. Impuestos: [impuestos-argentina.md](../../costos/impuestos-argentina.md).  
COGS de referencia: [costos-api.md](../../costos/costos-api.md) (**columna con context caching**).

**Modelo vigente:** licencia = **Σ (atenciones_mes[clase] × precio por atención)**.  
El precio por atención = **COGS_por_atención × (1 + margen sobre costo)**. El margen de lista es **233 %** (~70 % bruto ≈ ~49 % después de IIBB + ganancias). A más **atenciones totales** contratadas (suma ambulatorio + urgencia + internación), baja el margen según tramos. El cliente elige tipos de atención, el **volumen mensual** (escala del calculador) y, en ambulatorio, si suma **dictado** y/o **videollamada**. Motivos de consulta se presupuestan **siempre con audio**. El chat paciente (§1, **10 mensajes** alrededor del turno) entra solo en **ambulatorio**. Lo no contratado **se deshabilita** en sesión operativa y tableros.

Metadata producto: [`pricing-pes-by-encounter-class.yaml`](../../../common/metadata/bioenlace/organization/pricing-pes-by-encounter-class.yaml).  
Calculador público: sitio [`institucional/#precios`](../../../../institucional/index.html).  
Entitlements BD: `billing_account` + `billing_account_encounter_entitlement` (pool de profesionales derivados del volumen). Legacy: `efector_encounter_entitlement` (backfill).  
Admin: Licencias / Contratos.

**Fuera de este modelo (siguen cotizándose aparte):** pathway fees / PMPM al financiador, integraciones one-shot, autorización OS enterprise, proyectos farmacia/quirófano. Ver [modelos-pricing-diferenciados.md](./modelos-pricing-diferenciados.md).

---

## Fórmula

```
COGS_atención = motivos_audio + captura_ia
              + (AMB ? patient_chat_amb : 0)          # 10 msgs solo ambulatorio
              + ((dictado || videollamada) ? dictado_stt : 0)
              + (videollamada ? videollamada : 0)
atenciones_totales = Σ atenciones_mes[clase]
margen% = tramo(atenciones_totales).margin_on_cost_percent
precio_por_atención = COGS_atención × (1 + margen%/100)
USD/mes ≈ Σ_clase ( atenciones_mes[clase] × precio_por_atención )
```

| Componente COGS (USD / atención, **con context caching**) | USD | Notas |
|-----------------------------------------------------------|----:|-------|
| Chat paciente AMB (10 mensajes) | **0,0019** | Solo ambulatorio |
| Motivos con audio (batch + insights + ~4 min STT) | **0,0034** | Siempre |
| Captura IA (sin STT profesional) | **0,0006** | Siempre |
| Dictado / STT profesional (−30 % on-device) | **+0,0025** | Opcional AMB; incluido urgencia/internación y si hay videollamada |
| Videollamada | **+0,0088** | Solo ambulatorio |

**Margen sobre costo (lista):** **233 %**.

### Descuento por volumen (atenciones / mes)

| Tramo | Atenciones / mes | Margen sobre costo | Descuento vs lista | Margen después IIBB + ganancias (orientativo) |
|-------|------------------|--------------------|--------------------|-----------------------------------------------|
| Precio base | 1–4.999 | **233 %** | 0 % | ~49 % |
| Mediano | 5.000–14.999 | **163 %** | **−21 %** | ~43,5 % |
| Grande | 15.000–39.999 | **134 %** | **−30 %** | ~40 % |
| Enterprise | 40.000+ | **117 %** | **−35 %** | ~37,5 % |

Escala del calculador: 1.000 · 2.500 · 5.000 · 10.000 · 20.000 · 35.000 · 50.000 · 100.000.

### Precio por atención (lista, sin descuento de tramo)

| Configuración | COGS / atención | Precio lista / atención |
|---------------|----------------:|------------------------:|
| Ambulatorio (chat + motivos audio + captura IA) | 0,0059 | **~0,0196** |
| Ambulatorio + dictado | 0,0084 | **~0,0280** |
| Ambulatorio + videollamada | 0,0172 | **~0,0573** |
| Urgencia / internación (motivos audio + dictado) | 0,0065 | **~0,0216** |

Ejemplo: **20.000** atenciones ambulatorio + dictado → tramo Grande (−30 %) → **~USD 394 / mes**.

**Regla comercial:** videollamada **incluye** la transcripción de la llamada (= mismo STT de dictado una vez).

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
| Clínica ambulatoria | Ambulatorio × volumen mensual |
| Sanatorio con guardia | Ambulatorio + urgencia |
| Sanatorio con camas | Ambulatorio + urgencia + internación |
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
