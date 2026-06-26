# Admin: efectores, geografía y seeds de desarrollo

[← Índice](./README.md) · Checklist: [10-checklist-ejecutable.md](./10-checklist-ejecutable.md)

---

## Listado de efectores (`/admin/efectores`)

### Filtros disponibles

| Columna / filtro | Qué hace |
|------------------|----------|
| Nombre | Búsqueda parcial por nombre del efector |
| Localidad | Texto sobre `localidades.nombre` |
| Departamento | Dropdown de departamentos |
| **Provincia** | Dropdown de provincias (`provincias.id_provincia`) |
| **Financiamiento** | Texto sobre `origen_financiamiento` |
| **Sector** | Público / Privado (mismas reglas que offering paciente en `paciente-contexto-offering.yaml`) |

### Casos a probar

| ID | Acción | Resultado esperado |
|----|--------|-------------------|
| ADM-01 | Sin filtros | Lista todos los efectores accesibles al admin |
| ADM-02 | Filtro provincia = Santa Fe (o la del CAP demo) | Solo efectores cuya localidad pertenece a esa provincia |
| ADM-03 | Filtro sector = **Público** | Efectores con financiamiento Nacional/Provincial/Municipal; **sin** `Privado` en origen |
| ADM-04 | Filtro sector = **Privado** | Solo efectores con `Privado` en `origen_financiamiento` |
| ADM-05 | Provincia + sector combinados | Intersección de ambos criterios |
| ADM-06 | Ordenar por columna Provincia | Orden alfabético estable |
| ADM-07 | Abrir detalle desde el nombre | Navega a `efectores/view` sin error |

---

## Seeds de efectores demo (consola)

### Comandos

```bash
php yii clinical-seed/efector-demo-contexto
php yii clinical-seed/efector-demo-contexto-info
php yii clinical-seed/efector-demo-contexto-remove
php yii clinical-seed/medico-med-general --efector=<id>
```

### Casos a probar

| ID | Comando | Resultado esperado |
|----|---------|-------------------|
| SEED-01 | `efector-demo-contexto` (1ª vez) | Crea 2 efectores + 2 médicos MED GENERAL; imprime ids y credenciales |
| SEED-02 | `efector-demo-contexto` (2ª vez) | Idempotente: actualiza sin duplicar por `codigo_sisa` |
| SEED-03 | `efector-demo-contexto-info` | Lista ambos efectores y médicos asociados |
| SEED-04 | `efector-demo-contexto-remove` | Elimina efectores demo y PES/agenda médicos; conserva personas/usuarios |
| SEED-05 | Catálogo geo vacío o localidad huérfana | El seed crea provincia/localidad demo (`cod_bahra` DEV*) y completa igual |
| SEED-06 | `medico-med-general --efector=<id_cap_demo>` | Documento del médico ≤ 8 caracteres; usuario `medico_med_general_<id>` |

### Códigos SISA reservados

| Código | Efector |
|--------|---------|
| `DEV99001SFPUB` | CAP público otra provincia |
| `DEV99002PRIV` | Clínica privada demo |

---

## Médico MED GENERAL por efector

| ID | Escenario | Resultado esperado |
|----|-----------|-------------------|
| MED-01 | `--efector=863` | Usuario `medico_med_general_863`, doc `39999863` |
| MED-02 | `--efector=1000` o id alto | Documento `39001000` (8 dígitos, sin error de validación) |
| MED-03 | Segunda ejecución mismo efector | Reutiliza persona/PES existentes |
| MED-04 | `--agenda=0` | Crea PES sin agenda laboral |
| MED-05 | Tras seed, login médico + `set-session` | Puede operar en el efector con servicio MED GENERAL |

---

## Geografía en admin

| ID | Pantalla | Resultado esperado |
|----|----------|-------------------|
| GEO-01 | `/admin/localidades` — filtro provincia | Filtra localidades por provincia |
| GEO-02 | Efector demo tras seed sin catálogo previo | Admin provincias muestra al menos SDE + Santa Fe demo |
| GEO-03 | Crear localidad manual | Valida departamento y código postal único |

---

## Cruce con contexto paciente

Después de `efector-demo-contexto`:

| ID | Paciente | Resultado esperado |
|----|----------|-------------------|
| ADM-08 | `PUBLICO` + provincia CAP demo | Turnos / búsqueda ofrece CAP demo |
| ADM-09 | `PRIVADO` + provincia clínica demo | Ofrece clínica privada demo |
| ADM-10 | Admin filtro sector Público | Lista incluye CAP demo, excluye clínica privada |

Ver también [08-registro-contexto-paciente.md](./08-registro-contexto-paciente.md).
