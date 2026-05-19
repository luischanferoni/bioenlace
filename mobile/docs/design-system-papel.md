# Guía de migración al design system "papel"

Esta guía es **operativa**: cómo mover una pantalla existente del estilo viejo
al sistema "papel" sin romper el resto de la app.

> Referencia conceptual: [`packages/shared/lib/theme/README.md`](../packages/shared/lib/theme/README.md).
> Catálogo de componentes: [`packages/shared/lib/ui/README.md`](../packages/shared/lib/ui/README.md).

## Estado actual (mayo 2026)

- ✅ Tokens (`PaperPalette`, `IntentPalette`, `BioRadius`, ...) listos.
- ✅ `AppTheme.lightTheme` reescrito (Material 3 + tokens + AppBar izquierda
  + backdrop cálido + splash neutro).
- ✅ Widgets `Bio*` listos (AppBar, BottomNav, Button, Badge, Card, Chip,
  Divider, Input, Alert).
- ✅ Pantallas migradas:
  - **`mobile/paciente/lib/screens/*.dart`** (100%, `flutter analyze` = 0 errores).
  - **`mobile/medico/lib/screens/*.dart`** (100%, `flutter analyze` = 0 errores).
    Incluye `config_wizard`, `patient_timeline`, `chat_consulta`,
    `medico_signup`, `main`, `home`, `appointments`, `acciones`, `configuracion`.
  - **`mobile/packages/shared/lib/widgets/login_screen.dart`** (Bloque D
    cerrado): `BioAppBar`, `BioAlert.success`, `BioButton.primary` (`lg`,
    `fullWidth`, `loading`), `BioButton.softPrimary` (signup) y
    `BioButton(intent: info, variant: soft)` (modo visitante). Snackbars
    via helper con `IntentPalette`.
- 🗑 Eliminado (Bloque D): `theme/color_palette.dart`, `theme/button_styles.dart`,
  getters legacy `AppTheme.primaryColor / successColor / dangerColor /
  warningColor / infoColor / dark / backgroundColor / cardColor /
  titleStyle / subTitleStyle / h1Style…h6Style` y los `export` correspondientes
  en `shared.dart`. La tabla de equivalencias de más abajo se mantiene como
  guía histórica para entender qué reemplazó a qué.

## Tabla de equivalencias (cheatsheet)

| Antes | Ahora |
|---|---|
| `AppTheme.primaryColor` | `IntentPalette.of(UiIntent.primary).base` |
| `AppTheme.secondaryColor` | `IntentPalette.of(UiIntent.secondary).base` |
| `AppTheme.dangerColor` | `IntentPalette.of(UiIntent.danger).base` |
| `AppTheme.warningColor` | `IntentPalette.of(UiIntent.warning).base` |
| `AppTheme.dark` / `Colors.black` | `PaperPalette.paper900` (o `context.bio.textTitle`) |
| `AppTheme.titleTextColor` | `context.bio.textTitle` (`paper700`) |
| `AppTheme.subTitleTextColor` | `context.bio.textMuted` (`paper500`) |
| `Colors.white` (surface) | `context.bio.paperSurface` (`paper50`) |
| `AppTheme.dividerColor` | `context.bio.paperDividerDefault` (`paper200`) |
| `AppTheme.h1Style` | `BioTypography.h1` |
| `AppTheme.titleStyle` | `BioTypography.title` |
| `AppTheme.subTitleStyle` | `BioTypography.caption` |
| `AppBar(...)` | `BioAppBar(title: '...')` |
| `ElevatedButton(...)` | `BioButton.primary(label: '...', onPressed: ...)` |
| `OutlinedButton(...)` | `BioButton.outlinePrimary(...)` / `BioButton.neutral(...)` |
| `TextButton(...)` | `BioButton(..., variant: BioButtonVariant.soft)` |
| `Card(...)` | `BioCard(child: ...)` |
| `Chip(...)` | `BioChip(label: '...', selected: ..., onTap: ...)` |
| `TextFormField(...)` | `BioInput(...)` |
| `Divider(...)` | `BioDivider()` / `BioDivider.subtle()` / `.emphasis()` |
| `Container(decoration: BoxDecoration(border: Border.all(color: AppTheme.dividerColor)))` | `Container(decoration: BoxDecoration(border: BioBorder.paperDefault))` |
| `EdgeInsets.all(16)` | `BioSpacing.pageAll` / `EdgeInsets.all(BioSpacing.lg)` |
| `SizedBox(height: 12)` | `BioSpacing.gapH(BioSpacing.md)` |
| `BorderRadius.circular(8)` | `BorderRadius.circular(BioRadius.sm)` |
| `Color(0xFF...).withOpacity(0.3)` | `color.withValues(alpha: 0.3)` |

## Receta de migración por pantalla

### 1. Importar el barril

```dart
import 'package:shared/shared.dart';
```

Esto trae todo: `AppTheme`, `PaperPalette`, `IntentPalette`, `UiIntent`,
`BioRadius`, `BioSpacing`, `BioTypography`, `BioBorder`, `BioTokens`,
`context.bio`, y todos los widgets `Bio*`.

### 2. Reemplazar el `AppBar`

```diff
- appBar: AppBar(
-   title: const Text('Próximos turnos'),
-   backgroundColor: Colors.white,
-   foregroundColor: AppTheme.dark,
-   elevation: 0,
-   centerTitle: false,
- ),
+ appBar: BioAppBar(
+   title: 'Próximos turnos',
+   actions: [
+     IconButton(icon: const Icon(Icons.notifications_outlined), onPressed: _abrirAlertas),
+   ],
+ ),
```

### 3. Reemplazar botones

```diff
- ElevatedButton(
-   style: ElevatedButton.styleFrom(
-     backgroundColor: AppTheme.primaryColor,
-     foregroundColor: Colors.white,
-     shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
-     padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
-   ),
-   onPressed: _confirmar,
-   child: const Text('Confirmar'),
- )
+ BioButton.primary(label: 'Confirmar', onPressed: _confirmar)
```

### 4. Reemplazar cards / contenedores con borde

```diff
- Container(
-   decoration: BoxDecoration(
-     color: Colors.white,
-     borderRadius: BorderRadius.circular(8),
-     border: Border.all(color: const Color(0xFFE0E0E0)),
-   ),
-   padding: const EdgeInsets.all(12),
-   child: ...,
- )
+ BioCard(child: ...)
```

Si la card representa un estado, usar el constructor con cinta:

```dart
BioCard.intent(
  intent: UiIntent.warning,
  child: ...,
);
```

### 5. Reemplazar badges / chips de estado

```diff
- Container(
-   padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
-   decoration: BoxDecoration(
-     color: Colors.orange.shade100,
-     borderRadius: BorderRadius.circular(4),
-   ),
-   child: Text('En resolución',
-       style: TextStyle(color: Colors.orange.shade900, fontSize: 12)),
- )
+ BioBadge.warning('En resolución', icon: Icons.warning_amber_outlined)
```

### 6. Reemplazar inputs

```diff
- TextFormField(
-   controller: _emailCtrl,
-   decoration: InputDecoration(
-     labelText: 'Email',
-     border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
-   ),
- )
+ BioInput(
+   controller: _emailCtrl,
+   label: 'Email',
+   keyboardType: TextInputType.emailAddress,
+ )
```

### 7. Espaciado y tipografía

```diff
- Padding(
-   padding: const EdgeInsets.all(16),
-   child: Column(
-     children: [
-       Text('Mis turnos', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w600)),
-       const SizedBox(height: 12),
-       Text('Acá vas a ver tus próximos turnos', style: TextStyle(color: Colors.grey)),
-     ],
-   ),
- )
+ Padding(
+   padding: BioSpacing.pageAll,
+   child: Column(
+     children: [
+       Text('Mis turnos', style: BioTypography.h2),
+       BioSpacing.gapH(BioSpacing.md),
+       Text('Acá vas a ver tus próximos turnos', style: BioTypography.bodySm),
+     ],
+   ),
+ )
```

### 8. Verificar

Después de cada pantalla migrada:

```powershell
cd mobile/paciente
flutter analyze
```

(o `mobile/medico`). Cero errores nuevos. Los warnings preexistentes
(`use_build_context_synchronously` en `main.dart`, etc.) se ignoran hasta
limpieza dedicada.

## Orden sugerido (referencia histórica)

> Las apps `mobile/paciente` y `mobile/medico` ya están migradas. La guía
> de orden queda como referencia para pantallas nuevas o para iteraciones
> futuras que toquen `shared/`.

1. **Home / próximos turnos** — alta visibilidad, prueba `BioAppBar` +
   `BioCard.intent(UiIntent.warning)` + `BioBadge.warning` para
   `EN_RESOLUCION`.
2. **Listado de alertas** — `BioAlert` + `BioCard`.
3. **Detalle de turno** — `BioCard` + `BioBadge` + `BioButton`.
4. **Configuración** — `BioCard` + `BioDivider.subtle`.
5. **Login / Onboarding** — `BioInput` + `BioButton.primary` size `lg`
   `fullWidth: true`.
6. **Asistente / UI JSON** — `BioInput` + `BioButton` + `BioChip` desde el
   renderer.

## Cuándo NO migrar (todavía)

- Pantallas que están **a punto de ser reescritas** por otra razón (ej.
  flujo del asistente que está en rediseño). Esperar el rediseño y armarlo
  directamente con `Bio*`.
- Vistas Yii del backend — esto **es solo Flutter**. La web Yii sigue con
  Bootstrap.

## Cuándo agregar un token / componente nuevo

- Si una pantalla necesita **un color nuevo** que no está en `PaperPalette`
  o `IntentPalette` → discutir antes y agregarlo a los tokens, **no**
  hardcodear en la pantalla.
- Si una pantalla necesita **un layout/forma recurrente** que no cubre
  `BioCard` / `BioAlert` / etc. → proponer un nuevo widget `Bio*` en
  `packages/shared/lib/ui/` y documentarlo en `ui/README.md`.

## Limpieza final (✅ ejecutada en Bloque D)

La limpieza ya se hizo. Quedan acá los pasos a modo de bitácora:

1. ✅ Borrados los getters legacy en `packages/shared/lib/theme/theme.dart`
   (sección "Compatibilidad temporal").
2. ✅ Borrados `packages/shared/lib/theme/color_palette.dart` y
   `packages/shared/lib/theme/button_styles.dart`.
3. ✅ Borrado el archivo huérfano `mobile/paciente/lib/styles/color_palette.dart`
   (era una copia local de `AppColors`, ya sin imports).
4. ✅ Quitados los `export 'theme/color_palette.dart'` y
   `export 'theme/button_styles.dart'` de `packages/shared/lib/shared.dart`.
5. ✅ `flutter analyze` en paciente / médico / shared → 0 errores, 0 warnings.
   Solo quedan **2 infos** preexistentes en `shared` (deprecaciones de
   `Radio.groupValue/onChanged` en `ui_json_screen.dart`).

Si alguna pantalla nueva intenta usar `AppTheme.primaryColor` o similares,
ya no compila: usar `IntentPalette`, `PaperPalette` o `context.bio` según
la tabla de equivalencias.
