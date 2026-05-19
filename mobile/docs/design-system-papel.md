# Design System "papel" — guía operativa

Cómo está estructurado el sistema visual de las apps móviles de BioEnlace y **dónde se edita cada cosa**.

> - Filosofía y tokens en detalle: [`packages/shared/lib/theme/README.md`](../packages/shared/lib/theme/README.md).
> - Catálogo de componentes `Bio*`: [`packages/shared/lib/ui/README.md`](../packages/shared/lib/ui/README.md).
>
> Este documento es la "vista de mantenimiento": cómo encajan las piezas y a qué archivo ir para cambiar un color, un radio, el estilo de los botones, etc.

## La idea en una línea

> **Papel monocromático con acentos de marca usados con criterio.**

- La mayor parte de la UI es una escala de **grises cálidos** ("papel"); el color de marca aparece sólo en CTAs, estados y feedback.
- API estilo Bootstrap: cada widget es `intent × variant × size` (p. ej. "botón primary outline large").
- **Bordes finos sobre sombras**. Si se usa sombra, va sola (sombra **XOR** borde, nunca ambos).
- **Una fuente** (Open Sans) y una **escala fija** de tipografía/espaciado/radios.
- **Sin dark mode** por ahora. Los tokens están preparados para extender más adelante.

## Mapa: tres capas

```
┌────────────────────────────────────────────────────────────┐
│ Pantallas (mobile/paciente, mobile/medico)                 │  ←  consumen
│ - Importan `package:shared/shared.dart`                    │
│ - Usan widgets `Bio*` y tokens; nunca colores literales    │
└─────────────────────────┬──────────────────────────────────┘
                          │
┌─────────────────────────┴──────────────────────────────────┐
│ Componentes  packages/shared/lib/ui/*                      │  ←  son el lenguaje
│ - BioAppBar, BioButton, BioCard, BioBadge, BioChip,        │
│   BioInput, BioAlert, BioDivider, BioBottomNav             │
│ - Cada uno consume tokens; expone `intent / variant / size`│
└─────────────────────────┬──────────────────────────────────┘
                          │
┌─────────────────────────┴──────────────────────────────────┐
│ Tokens  packages/shared/lib/theme/tokens/*                 │  ←  fuente única
│ - PaperPalette  → escala paper50..paper900                  │
│ - IntentPalette → primary/secondary/success/danger/...     │
│ - BioRadius / BioSpacing / BioBorder / BioShadow / BioMotion│
│ - BioTypography → escala tipográfica (Open Sans)           │
│ - BioTokens     → ThemeExtension expuesto via `context.bio` │
└─────────────────────────┬──────────────────────────────────┘
                          │
                          ▼
                  ThemeData (AppTheme.lightTheme)
                  packages/shared/lib/theme/theme.dart
                  - Cablea los tokens al ThemeData global
                  - AppBar/Card/Input/Snackbar/Dialog defaults
```

**Regla de flujo**: las pantallas miran a los componentes; los componentes miran a los tokens; los tokens son la única fuente. Cambiar un valor en los tokens propaga a todo. Cambiar algo en una pantalla **no** debe nacer de un literal — si hace falta, primero se agrega al token correspondiente.

## Estructura de archivos

```
packages/shared/lib/
├── shared.dart                  # barril único: import 'package:shared/shared.dart'
├── theme/
│   ├── theme.dart               # AppTheme.lightTheme — ThemeData configurado
│   ├── README.md                # filosofía + descripción detallada de cada token
│   └── tokens/
│       ├── tokens.dart          # re-export único (importado por shared.dart)
│       ├── paper_palette.dart   # escala monocroma cálida
│       ├── ui_intent.dart       # enum UiIntent + IntentPalette
│       ├── border_width.dart    # anchos: hairline / thin / medium / thick / heavy
│       ├── bio_radius.dart      # radios: xs / sm / md / lg / pill
│       ├── bio_spacing.dart     # múltiplos de 4 + presets
│       ├── bio_shadow.dart      # sombras cálidas y bajas
│       ├── bio_motion.dart      # duraciones + curvas
│       ├── bio_typography.dart  # escala tipográfica + materialTextTheme()
│       ├── bio_border.dart      # helpers para construir Border
│       └── bio_tokens.dart      # ThemeExtension agregador + context.bio
└── ui/
    ├── README.md                # catálogo con ejemplos de cada Bio*
    ├── bio_app_bar.dart
    ├── bio_bottom_nav.dart
    ├── bio_button.dart
    ├── bio_badge.dart
    ├── bio_card.dart
    ├── bio_chip.dart
    ├── bio_divider.dart
    ├── bio_input.dart
    └── bio_alert.dart
```

## Dónde se cambia cada cosa

| Qué querés cambiar | A qué archivo voy | Notas |
|---|---|---|
| El color de marca (`primary`, `danger`, etc.) | `theme/tokens/ui_intent.dart` (clase `IntentPalette` y mapa por `UiIntent`) | Cambia automáticamente botones, badges, alerts, focos de input, chips activos. |
| Un gris del fondo / texto / borde | `theme/tokens/paper_palette.dart` (`paperN`) | Y, si afecta a "qué token usa el body", también `theme/tokens/bio_tokens.dart` (campos `paperBackground`, `textBody`, etc.). |
| La tipografía (familia, tamaños, pesos) | `theme/tokens/bio_typography.dart` | `materialTextTheme()` ya cablea el `TextTheme` al `ThemeData`. |
| Espaciados (padding default de pantalla, gaps) | `theme/tokens/bio_spacing.dart` | Hay presets (`pageAll`, `card`, `sectionGapY`, `gapH/gapW`). |
| Radios (cards, botones, chips) | `theme/tokens/bio_radius.dart` | Default global del sistema: `sm` (6 px). |
| Anchos de borde | `theme/tokens/border_width.dart` | `thin` default, `medium` para énfasis, `thick` para outlines grandes. |
| Sombras | `theme/tokens/bio_shadow.dart` | Tres escalones (`xs/sm/md`). Recordá: sombra XOR borde. |
| Duraciones y curvas de animación | `theme/tokens/bio_motion.dart` | `instant/fast/normal/slow` + `standard/emphasized/decelerate`. |
| Defaults globales del `ThemeData` (AppBar, Scaffold, Card, Input, Snackbar, Dialog, Chip…) | `theme/theme.dart` | Es donde se "cablean" los tokens al theme. Si una pantalla muestra un default raro (p. ej. una `Card` con sombra Material), se ajusta acá. |
| Aspecto / comportamiento de un widget `Bio*` | `ui/<bio_xxx>.dart` | Si la mayoría de las pantallas necesitan que el botón primary tenga otro tamaño default, se cambia ahí, no en cada pantalla. |
| Mapping "key del dominio → estado visual" (p. ej. `EN_RESOLUCION` → warning) | Donde se usa (pantalla / renderer), siempre vía `UiIntent` | Las reglas de oro están en `theme/README.md` ("`EN_RESOLUCION` se renderiza con `UiIntent.warning`"). |
| Acceso desde código de pantalla a un token (`fondo`, `divider`, `texto muted`…) | `theme/tokens/bio_tokens.dart` (`BioTokens` + `context.bio`) | Si necesitás un nuevo campo accesible desde el contexto, lo agregás ahí. |

## Cómo se usa en una pantalla

```dart
import 'package:flutter/material.dart';
import 'package:shared/shared.dart';  // único import del DS

class TurnosScreen extends StatelessWidget {
  const TurnosScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    return Scaffold(
      appBar: BioAppBar(title: 'Próximos turnos'),
      body: Padding(
        padding: BioSpacing.pageAll,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Hoy', style: BioTypography.h2),
            BioSpacing.gapH(BioSpacing.md),
            BioCard.intent(
              intent: UiIntent.warning,
              onTap: _resolver,
              child: Row(
                children: [
                  Icon(Icons.warning_amber_outlined,
                       color: IntentPalette.of(UiIntent.warning).base),
                  const SizedBox(width: BioSpacing.sm),
                  Expanded(
                    child: Text('Tu turno del viernes necesita resolución',
                                style: BioTypography.body.copyWith(color: tokens.textBody))),
                  BioBadge.warning('En resolución'),
                ],
              ),
            ),
            BioSpacing.gapH(BioSpacing.lg),
            BioButton.primary(
              label: 'Reservar nuevo turno',
              size: BioButtonSize.lg,
              fullWidth: true,
              onPressed: _abrirAsistente,
            ),
          ],
        ),
      ),
    );
  }

  void _resolver() {}
  void _abrirAsistente() {}
}
```

Cosas a notar:

- **Un solo `import`**: todo el DS sale de `package:shared/shared.dart`.
- `BioAppBar` reemplaza al `AppBar` de Material; no hay que setear `backgroundColor` ni `centerTitle` — el theme global lo deja como debe.
- **No** se usa `Colors.x` ni `Color(0xFF...)`. Para color: `IntentPalette.of(...)` o `context.bio.<campo>` (`paperSurface`, `textBody`, `paperBorderDefault`, …).
- **No** se usan números crudos para espaciar: `BioSpacing.md`, `BioSpacing.gapH(...)`, `BioSpacing.pageAll`.
- Si una sección necesita un estado, se elige el `UiIntent` que corresponde (`warning` para "necesita atención", `danger` para errores, `success` para confirmaciones), no un color.

## Cuándo agregar / extender vs. consumir

| Necesidad | Acción correcta |
|---|---|
| Un color de marca nuevo (p. ej. un acento púrpura para una campaña) | Agregar el `UiIntent` y su `IntentPalette` en `theme/tokens/ui_intent.dart`. No introducir `Color(0xFF...)` en la pantalla. |
| Un nivel de gris que no está | Agregar `paperN` en `theme/tokens/paper_palette.dart` siguiendo la progresión cálida; si va a ser de uso frecuente, exponerlo en `bio_tokens.dart`. |
| Un espaciado / radio / borde nuevo | Extender el token correspondiente, no duplicarlo inline. |
| Una variación de un widget existente (otro size, otra variant) | Extender el widget en `ui/*` con un nuevo enum/factory, no copiar el widget en otra pantalla. |
| Un layout recurrente que `BioCard` / `BioAlert` / etc. no cubren | Proponer un widget `Bio*` nuevo en `ui/` y documentarlo en `ui/README.md`. |
| Un comportamiento puntual de una sola pantalla | Construirlo con los `Bio*` y tokens existentes; no romper el sistema por un caso aislado. |

## Reglas de oro (resumen)

1. **Tokens, no literales.** Cero `Color(0xFF...)`, cero `EdgeInsets.all(13)`, cero `fontSize: 17`.
2. **Intent declarativo.** "Este botón es `primary filled`", no "este botón es de tal hex".
3. **`BioAppBar`, no `AppBar`.** El fondo de la pantalla siempre es papel (`paper50`).
4. **Borde XOR sombra.** Nunca ambos.
5. **`Bio*` antes que Material directo.** Si te falta un componente, agregalo al sistema.
6. **`color.withValues(alpha: x)`**, no `withOpacity`.
7. **`EN_RESOLUCION` se renderiza con `UiIntent.warning`** (decisión cerrada en `theme/README.md`).

## Cheatsheet: equivalencias (sólo si aparece código viejo)

Las apps `paciente` y `medico` ya no usan estos APIs, pero si surge un fragmento legado o una pantalla nueva intenta apoyarse en convenciones Material crudas, la traducción es:

| Antes (Material / AppTheme legacy) | Ahora |
|---|---|
| `Colors.white` (surface) | `context.bio.paperSurface` (`paper50`) |
| `Colors.black` / "negro puro" | `context.bio.textTitle` (`paper700`) — **nunca** `#000`. |
| Color "primary" hardcodeado | `IntentPalette.of(UiIntent.primary).base` |
| Color "danger/warning" hardcodeado | `IntentPalette.of(UiIntent.danger).base` etc. |
| `Divider()` Material | `BioDivider()` / `BioDivider.subtle()` / `.emphasis()` |
| `AppBar(...)` | `BioAppBar(title: '...')` |
| `ElevatedButton(...)` | `BioButton.primary(label: '...', onPressed: ...)` |
| `OutlinedButton(...)` | `BioButton.outlinePrimary(...)` o `BioButton.neutral(...)` |
| `TextButton(...)` | `BioButton(..., variant: BioButtonVariant.soft)` |
| `Card(...)` | `BioCard(child: ...)` |
| `Chip(...)` | `BioChip(label: ..., selected: ..., onTap: ...)` |
| `TextFormField(...)` | `BioInput(...)` |
| `Container` con borde + padding manuales | `BioCard(child: ...)` o `Container(decoration: BoxDecoration(border: BioBorder.paperDefault))` |
| `EdgeInsets.all(16)` | `BioSpacing.pageAll` (o `EdgeInsets.all(BioSpacing.lg)`) |
| `SizedBox(height: 12)` | `BioSpacing.gapH(BioSpacing.md)` |
| `BorderRadius.circular(8)` | `BorderRadius.circular(BioRadius.sm)` |
| `Color(0xFF...).withOpacity(0.3)` | `color.withValues(alpha: 0.3)` |
| `TextStyle(fontSize: 22, fontWeight: FontWeight.w600)` | `BioTypography.h2` |

## Verificación rápida

Antes de cerrar un cambio sobre `packages/shared/lib/theme/*` o `packages/shared/lib/ui/*`:

```powershell
cd mobile/packages/shared
flutter analyze
```

Y, si el cambio toca tokens (afecta a todas las pantallas):

```powershell
cd mobile/paciente
flutter analyze
cd ../medico
flutter analyze
```

No deberían aparecer errores nuevos. Los `info` preexistentes (`use_build_context_synchronously` en `main.dart`, deprecaciones de `RadioGroup` en `ui_json_screen.dart`, etc.) se ignoran salvo limpieza dedicada.
