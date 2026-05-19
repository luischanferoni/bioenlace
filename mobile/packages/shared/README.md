# `shared` — Librería compartida BioEnlace (paciente + médico)

Paquete Flutter interno que vive en `mobile/packages/shared` y se consume desde
ambas apps (`mobile/paciente`, `mobile/medico`) vía `dependencies: shared:
path: ../packages/shared` en sus respectivos `pubspec.yaml`.

## Qué hay acá

```
lib/
├── shared.dart                 # Punto de entrada (barril). Re-exporta todo lo público.
├── auth/                       # Auth biométrica.
├── config/                     # ApiConfig (URLs, headers).
├── theme/                      # Design system "papel" — ver theme/README.md
│   ├── theme.dart              # AppTheme.lightTheme (Material 3 configurado).
│   ├── color_palette.dart      # [legacy] paleta vieja.
│   ├── button_styles.dart      # [legacy] estilos de botón anteriores.
│   └── tokens/                 # ← Tokens del design system (paper, intent, ...).
├── ui/                         # ← Widgets Bio* del design system — ver ui/README.md
└── ui_json/                    # Renderer de descriptores ui_json (flows del asistente).
```

## Cómo importar

Una sola línea expone todo lo público:

```dart
import 'package:shared/shared.dart';
```

Eso trae:

- `AppTheme.lightTheme` (configurado con Material 3 + tokens "papel").
- `PaperPalette`, `IntentPalette`, `UiIntent`, `BorderWidth`, `BioRadius`,
  `BioSpacing`, `BioShadow`, `BioMotion`, `BioTypography`, `BioBorder`,
  `BioTokens` y la extensión `context.bio`.
- Widgets: `BioAppBar`, `BioBottomNav`, `BioButton`, `BioBadge`, `BioCard`,
  `BioChip`, `BioDivider`, `BioInput`, `BioAlert`.
- `ApiConfig`, `LoginScreen`, `UiJsonScreen`, `FlowSnapshot`, etc.

## Design system "papel"

El sistema visual de BioEnlace ("papel" monocromático con acentos de marca)
está documentado en dos lugares:

- **[`lib/theme/README.md`](lib/theme/README.md)** — filosofía, paleta, intents,
  tokens, decisiones de diseño.
- **[`lib/ui/README.md`](lib/ui/README.md)** — catálogo de componentes `Bio*`
  con ejemplos de uso.

Para migrar una pantalla existente al sistema "papel" hay una guía paso a paso
en [`mobile/docs/design-system-papel.md`](../../../mobile/docs/design-system-papel.md).

## Compatibilidad con código legacy

Mientras se migran pantallas, conviven:

- **Nuevo**: `AppTheme.lightTheme` consume `PaperPalette` + `IntentPalette`.
  Los componentes `Bio*` son la API recomendada.
- **Legacy**: `AppTheme.primaryColor`, `secondaryColor`, `dark`, `h1Style`, etc.
  siguen como getters (delegan a tokens nuevos). `color_palette.dart` y
  `button_styles.dart` quedan hasta que todas las apps migren — luego se borran.

Cuando todo `mobile/paciente` y `mobile/medico` use solo `Bio*` y `context.bio`,
se eliminan los shims legacy en una sola PR.

## Convenciones

- **No** importar `package:flutter/material.dart` con tema custom en cada
  pantalla; siempre `AppTheme.lightTheme` resuelto vía `MaterialApp(theme: ...)`.
- **No** usar colores literales (`Color(0xFF...)`) en pantallas; siempre
  `PaperPalette.*` o `IntentPalette.of(...)` o `context.bio.*`.
- **No** usar espaciados arbitrarios (`EdgeInsets.all(13)`); siempre
  `BioSpacing.*`.
- **No** usar radios arbitrarios; siempre `BioRadius.*`.
- **No** usar `withOpacity` (deprecado en Flutter 3.27); usar `withValues(alpha: ...)`.
