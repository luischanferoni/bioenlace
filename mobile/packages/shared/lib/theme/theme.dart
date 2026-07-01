import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

import 'tokens/tokens.dart';

/// Tema único de BioEnlace ("papel" monocromático con acentos de marca).
///
/// Decisiones:
/// - Fondo de pantalla `PaperPalette.paper25` (superficies en `paper50`).
/// - AppBar fondo papel, alineado a la izquierda, con borde inferior en widget propio.
/// - Tipografía: Open Sans embebida ([BioTypography.fontFamily]).
/// - Sin dark mode.
/// - Splash táctil cálido (no tinta primaria).
/// - Acentos de marca solo en estados / CTAs.
class AppTheme {
  AppTheme._();

  /// Acceso semántico a la paleta neutra (preferir `context.bio` cuando se pueda).
  static const Color background = PaperPalette.paper25;
  static const Color surface = PaperPalette.paper50;
  static const Color surfaceSunken = PaperPalette.paper100;
  static const Color borderDefault = PaperPalette.paper300;
  static const Color borderEmphasis = PaperPalette.paper400;
  static const Color textTitle = PaperPalette.paper700;
  static const Color textBody = PaperPalette.paper600;
  static const Color textMuted = PaperPalette.paper500;

  /// Acentos BioEnlace (uso quirúrgico: estados, CTAs primarios).
  static Color get primary => IntentPalette.of(UiIntent.primary).base;
  static Color get secondary => IntentPalette.of(UiIntent.secondary).base;
  static Color get danger => IntentPalette.of(UiIntent.danger).base;
  static Color get warning => IntentPalette.of(UiIntent.warning).base;
  static Color get success => IntentPalette.of(UiIntent.success).base;

  static ThemeData get lightTheme {
    final colorScheme = ColorScheme(
      brightness: Brightness.light,
      primary: primary,
      onPrimary: IntentPalette.of(UiIntent.primary).onBase,
      primaryContainer: IntentPalette.of(UiIntent.primary).softBg,
      onPrimaryContainer: IntentPalette.of(UiIntent.primary).softFg,
      secondary: secondary,
      onSecondary: IntentPalette.of(UiIntent.secondary).onBase,
      secondaryContainer: IntentPalette.of(UiIntent.secondary).softBg,
      onSecondaryContainer: IntentPalette.of(UiIntent.secondary).softFg,
      tertiary: warning,
      onTertiary: IntentPalette.of(UiIntent.warning).onBase,
      error: danger,
      onError: IntentPalette.of(UiIntent.danger).onBase,
      errorContainer: IntentPalette.of(UiIntent.danger).softBg,
      onErrorContainer: IntentPalette.of(UiIntent.danger).softFg,
      surface: surface,
      onSurface: textTitle,
      surfaceContainerHighest: surfaceSunken,
      onSurfaceVariant: textBody,
      outline: borderEmphasis,
      outlineVariant: borderDefault,
      shadow: PaperPalette.paper900,
      scrim: const Color(0x4D1A1916),
      inverseSurface: PaperPalette.paper700,
      onInverseSurface: PaperPalette.paper50,
      inversePrimary: IntentPalette.of(UiIntent.primary).softBg,
      // Deprecados pero requeridos por algunas libs:
      // ignore: deprecated_member_use
      background: background,
      // ignore: deprecated_member_use
      onBackground: textBody,
      // ignore: deprecated_member_use
      surfaceVariant: surfaceSunken,
    );

    final textTheme = BioTypography.materialTextTheme();

    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.light,
      scaffoldBackgroundColor: background,
      canvasColor: background,
      dividerColor: borderDefault,
      hintColor: textMuted,
      splashColor: PaperPalette.paper300.withValues(alpha: 0.4),
      highlightColor: PaperPalette.paper200,
      splashFactory: InkRipple.splashFactory,
      colorScheme: colorScheme,
      textTheme: textTheme,
      primaryTextTheme: textTheme,
      fontFamily: BioTypography.fontFamily,
      extensions: const <ThemeExtension<dynamic>>[
        BioTokens.light,
      ],

      appBarTheme: AppBarTheme(
        backgroundColor: surface,
        foregroundColor: textTitle,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        surfaceTintColor: Colors.transparent,
        titleTextStyle: BioTypography.h3,
        toolbarTextStyle: BioTypography.body,
        iconTheme: const IconThemeData(color: PaperPalette.paper700, size: 22),
        actionsIconTheme:
            const IconThemeData(color: PaperPalette.paper700, size: 22),
      ),

      iconTheme: const IconThemeData(color: PaperPalette.paper700, size: 22),

      bottomNavigationBarTheme: BottomNavigationBarThemeData(
        backgroundColor: surface,
        selectedItemColor: primary,
        unselectedItemColor: textMuted,
        selectedLabelStyle: BioTypography.caption.copyWith(
          fontWeight: FontWeight.w600,
          color: primary,
        ),
        unselectedLabelStyle: BioTypography.caption,
        showSelectedLabels: true,
        showUnselectedLabels: true,
        type: BottomNavigationBarType.fixed,
        elevation: 0,
      ),

      cardTheme: CardThemeData(
        color: surface,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(BioRadius.sm),
          side: const BorderSide(
            color: borderDefault,
            width: BorderWidth.thin,
          ),
        ),
      ),

      dividerTheme: const DividerThemeData(
        color: PaperPalette.paper200,
        thickness: BorderWidth.thin,
        space: 1,
      ),

      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: surface,
        hintStyle: BioTypography.body.copyWith(color: textMuted),
        labelStyle: BioTypography.bodySm.copyWith(color: textBody),
        helperStyle: BioTypography.caption,
        errorStyle: BioTypography.caption.copyWith(color: danger),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: BioSpacing.md,
          vertical: BioSpacing.md,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(BioRadius.sm),
          borderSide: const BorderSide(
            color: borderDefault,
            width: BorderWidth.thin,
          ),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(BioRadius.sm),
          borderSide: const BorderSide(
            color: borderDefault,
            width: BorderWidth.thin,
          ),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(BioRadius.sm),
          borderSide: BorderSide(
            color: primary,
            width: BorderWidth.medium,
          ),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(BioRadius.sm),
          borderSide: BorderSide(
            color: danger,
            width: BorderWidth.thin,
          ),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(BioRadius.sm),
          borderSide: BorderSide(
            color: danger,
            width: BorderWidth.medium,
          ),
        ),
        disabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(BioRadius.sm),
          borderSide: const BorderSide(
            color: PaperPalette.paper200,
            width: BorderWidth.thin,
          ),
        ),
      ),

      snackBarTheme: SnackBarThemeData(
        backgroundColor: PaperPalette.paper700,
        contentTextStyle:
            BioTypography.body.copyWith(color: PaperPalette.paper50),
        behavior: SnackBarBehavior.floating,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(BioRadius.sm),
        ),
      ),

      dialogTheme: DialogThemeData(
        backgroundColor: surface,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(BioRadius.md),
          side: const BorderSide(
            color: borderEmphasis,
            width: BorderWidth.thin,
          ),
        ),
        titleTextStyle: BioTypography.h3,
        contentTextStyle: BioTypography.body,
      ),

      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: surface,
        modalBackgroundColor: surface,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        modalElevation: 0,
        modalBarrierColor: const Color(0x4D1A1916),
        shape: RoundedRectangleBorder(
          borderRadius: BioRadius.top(BioRadius.md),
          side: const BorderSide(
            color: borderEmphasis,
            width: BorderWidth.thin,
          ),
        ),
      ),

      chipTheme: ChipThemeData(
        backgroundColor: surface,
        selectedColor: IntentPalette.of(UiIntent.primary).softBg,
        disabledColor: PaperPalette.paper100,
        labelStyle: BioTypography.bodySm,
        secondaryLabelStyle: BioTypography.bodySm.copyWith(
          color: IntentPalette.of(UiIntent.primary).softFg,
        ),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(BioRadius.xs),
          side: const BorderSide(
            color: borderDefault,
            width: BorderWidth.thin,
          ),
        ),
        side: const BorderSide(
          color: borderDefault,
          width: BorderWidth.thin,
        ),
        padding: const EdgeInsets.symmetric(
          horizontal: BioSpacing.sm,
          vertical: BioSpacing.xs,
        ),
      ),

      progressIndicatorTheme: ProgressIndicatorThemeData(
        color: primary,
        circularTrackColor: PaperPalette.paper200,
        linearTrackColor: PaperPalette.paper200,
      ),

      tooltipTheme: TooltipThemeData(
        decoration: BoxDecoration(
          color: PaperPalette.paper700,
          borderRadius: BorderRadius.circular(BioRadius.xs),
        ),
        textStyle:
            BioTypography.caption.copyWith(color: PaperPalette.paper50),
        padding: const EdgeInsets.symmetric(
          horizontal: BioSpacing.sm,
          vertical: BioSpacing.xs,
        ),
      ),

      pageTransitionsTheme: const PageTransitionsTheme(
        builders: <TargetPlatform, PageTransitionsBuilder>{
          TargetPlatform.android: FadeForwardsPageTransitionsBuilder(),
          TargetPlatform.iOS: CupertinoPageTransitionsBuilder(),
        },
      ),
    );
  }
}
