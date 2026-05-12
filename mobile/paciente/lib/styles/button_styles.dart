// Archivo: lib/styles/button_styles.dart
import 'package:flutter/material.dart';

import '../theme/paciente_theme_extensions.dart';

enum ButtonType {
  primary,
  secondary,
  success,
  danger,
  warning,
  info,
  light,
  dark,
}

enum ButtonVariant {
  filled,
  outline,
  soft,
}

class ButtonStyles {
  static ButtonStyle getStyle(
    BuildContext context, {
    required ButtonType type,
    ButtonVariant variant = ButtonVariant.filled,
    bool hasIcon = false,
  }) {
    switch (variant) {
      case ButtonVariant.filled:
        return _getFilledStyle(context, type, hasIcon);
      case ButtonVariant.outline:
        return _getOutlineStyle(context, type, hasIcon);
      case ButtonVariant.soft:
        return _getSoftStyle(context, type, hasIcon);
    }
  }

  static ButtonStyle _getFilledStyle(BuildContext context, ButtonType type, bool hasIcon) {
    final colors = _getColors(context, type);
    final tt = context.pacienteTextTheme;
    return ElevatedButton.styleFrom(
      foregroundColor: colors['text'],
      backgroundColor: colors['background'],
      padding: EdgeInsets.symmetric(
        horizontal: hasIcon ? 16 : 20,
        vertical: 12,
      ),
      textStyle: tt.labelLarge?.copyWith(fontWeight: FontWeight.w600),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(22),
      ),
      elevation: 2,
    );
  }

  static ButtonStyle _getOutlineStyle(BuildContext context, ButtonType type, bool hasIcon) {
    final colors = _getColors(context, type);
    final tt = context.pacienteTextTheme;
    return OutlinedButton.styleFrom(
      foregroundColor: colors['background'],
      backgroundColor: Colors.transparent,
      padding: EdgeInsets.symmetric(
        horizontal: hasIcon ? 16 : 20,
        vertical: 12,
      ),
      textStyle: tt.labelLarge?.copyWith(fontWeight: FontWeight.w600),
      side: BorderSide(
        color: colors['background']!,
        width: 1.5,
      ),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(22),
      ),
      elevation: 0,
    );
  }

  static ButtonStyle _getSoftStyle(BuildContext context, ButtonType type, bool hasIcon) {
    final colors = _getColors(context, type);
    final tt = context.pacienteTextTheme;
    return ElevatedButton.styleFrom(
      foregroundColor: colors['background'],
      backgroundColor: colors['softBackground'],
      padding: EdgeInsets.symmetric(
        horizontal: hasIcon ? 16 : 20,
        vertical: 12,
      ),
      textStyle: tt.labelLarge?.copyWith(fontWeight: FontWeight.w600),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(22),
      ),
      elevation: 0,
    );
  }

  static Map<String, Color> _getColors(BuildContext context, ButtonType type) {
    final cs = context.pacienteColors;
    final sem = context.pacienteSemantic;
    switch (type) {
      case ButtonType.primary:
        return {
          'background': cs.primary,
          'text': cs.onPrimary,
          'softBackground': cs.primary.withValues(alpha: 0.2),
        };
      case ButtonType.secondary:
        return {
          'background': cs.secondary,
          'text': cs.onSecondary,
          'softBackground': cs.secondary.withValues(alpha: 0.1),
        };
      case ButtonType.success:
        return {
          'background': sem.success,
          'text': cs.onPrimary,
          'softBackground': sem.success.withValues(alpha: 0.1),
        };
      case ButtonType.danger:
        return {
          'background': cs.error,
          'text': cs.onError,
          'softBackground': cs.error.withValues(alpha: 0.1),
        };
      case ButtonType.warning:
        return {
          'background': sem.warning,
          'text': cs.onSurface,
          'softBackground': sem.warning.withValues(alpha: 0.1),
        };
      case ButtonType.info:
        return {
          'background': cs.secondary,
          'text': cs.onSecondary,
          'softBackground': cs.secondary.withValues(alpha: 0.1),
        };
      case ButtonType.light:
        return {
          'background': cs.surfaceContainerHighest,
          'text': cs.onSurface,
          'softBackground': cs.surfaceContainerHighest.withValues(alpha: 0.3),
        };
      case ButtonType.dark:
        return {
          'background': cs.onSurface,
          'text': cs.surface,
          'softBackground': cs.onSurface.withValues(alpha: 0.1),
        };
    }
  }

  static ButtonStyle primary(BuildContext context, {ButtonVariant variant = ButtonVariant.filled, bool hasIcon = false}) {
    return getStyle(context, type: ButtonType.primary, variant: variant, hasIcon: hasIcon);
  }

  static ButtonStyle secondary(BuildContext context, {ButtonVariant variant = ButtonVariant.filled, bool hasIcon = false}) {
    return getStyle(context, type: ButtonType.secondary, variant: variant, hasIcon: hasIcon);
  }

  static ButtonStyle success(BuildContext context, {ButtonVariant variant = ButtonVariant.filled, bool hasIcon = false}) {
    return getStyle(context, type: ButtonType.success, variant: variant, hasIcon: hasIcon);
  }

  static ButtonStyle danger(BuildContext context, {ButtonVariant variant = ButtonVariant.filled, bool hasIcon = false}) {
    return getStyle(context, type: ButtonType.danger, variant: variant, hasIcon: hasIcon);
  }

  static ButtonStyle warning(BuildContext context, {ButtonVariant variant = ButtonVariant.filled, bool hasIcon = false}) {
    return getStyle(context, type: ButtonType.warning, variant: variant, hasIcon: hasIcon);
  }

  static ButtonStyle info(BuildContext context, {ButtonVariant variant = ButtonVariant.filled, bool hasIcon = false}) {
    return getStyle(context, type: ButtonType.info, variant: variant, hasIcon: hasIcon);
  }

  static ButtonStyle light(BuildContext context, {ButtonVariant variant = ButtonVariant.filled, bool hasIcon = false}) {
    return getStyle(context, type: ButtonType.light, variant: variant, hasIcon: hasIcon);
  }

  static ButtonStyle dark(BuildContext context, {ButtonVariant variant = ButtonVariant.filled, bool hasIcon = false}) {
    return getStyle(context, type: ButtonType.dark, variant: variant, hasIcon: hasIcon);
  }

  static ButtonStyle primaryOutline(BuildContext context, {bool hasIcon = false}) {
    return getStyle(context, type: ButtonType.primary, variant: ButtonVariant.outline, hasIcon: hasIcon);
  }

  static ButtonStyle secondaryOutline(BuildContext context, {bool hasIcon = false}) {
    return getStyle(context, type: ButtonType.secondary, variant: ButtonVariant.outline, hasIcon: hasIcon);
  }

  static ButtonStyle successOutline(BuildContext context, {bool hasIcon = false}) {
    return getStyle(context, type: ButtonType.success, variant: ButtonVariant.outline, hasIcon: hasIcon);
  }

  static ButtonStyle dangerOutline(BuildContext context, {bool hasIcon = false}) {
    return getStyle(context, type: ButtonType.danger, variant: ButtonVariant.outline, hasIcon: hasIcon);
  }

  static ButtonStyle warningOutline(BuildContext context, {bool hasIcon = false}) {
    return getStyle(context, type: ButtonType.warning, variant: ButtonVariant.outline, hasIcon: hasIcon);
  }

  static ButtonStyle infoOutline(BuildContext context, {bool hasIcon = false}) {
    return getStyle(context, type: ButtonType.info, variant: ButtonVariant.outline, hasIcon: hasIcon);
  }

  static ButtonStyle primarySoft(BuildContext context, {bool hasIcon = false}) {
    return getStyle(context, type: ButtonType.primary, variant: ButtonVariant.soft, hasIcon: hasIcon);
  }

  static ButtonStyle secondarySoft(BuildContext context, {bool hasIcon = false}) {
    return getStyle(context, type: ButtonType.secondary, variant: ButtonVariant.soft, hasIcon: hasIcon);
  }

  static ButtonStyle successSoft(BuildContext context, {bool hasIcon = false}) {
    return getStyle(context, type: ButtonType.success, variant: ButtonVariant.soft, hasIcon: hasIcon);
  }

  static ButtonStyle dangerSoft(BuildContext context, {bool hasIcon = false}) {
    return getStyle(context, type: ButtonType.danger, variant: ButtonVariant.soft, hasIcon: hasIcon);
  }

  static ButtonStyle warningSoft(BuildContext context, {bool hasIcon = false}) {
    return getStyle(context, type: ButtonType.warning, variant: ButtonVariant.soft, hasIcon: hasIcon);
  }

  static ButtonStyle infoSoft(BuildContext context, {bool hasIcon = false}) {
    return getStyle(context, type: ButtonType.info, variant: ButtonVariant.soft, hasIcon: hasIcon);
  }

  static ButtonStyle small(BuildContext context, {ButtonType type = ButtonType.primary, ButtonVariant variant = ButtonVariant.filled, bool hasIcon = false}) {
    final baseStyle = getStyle(context, type: type, variant: variant, hasIcon: hasIcon);
    final tt = context.pacienteTextTheme;
    return baseStyle.copyWith(
      padding: WidgetStateProperty.all(EdgeInsets.symmetric(
        horizontal: hasIcon ? 8 : 12,
        vertical: 6,
      )),
      textStyle: WidgetStateProperty.all(tt.labelMedium?.copyWith(fontWeight: FontWeight.w500)),
    );
  }

  static ButtonStyle large(BuildContext context, {ButtonType type = ButtonType.primary, ButtonVariant variant = ButtonVariant.filled, bool hasIcon = false}) {
    final baseStyle = getStyle(context, type: type, variant: variant, hasIcon: hasIcon);
    final tt = context.pacienteTextTheme;
    return baseStyle.copyWith(
      padding: WidgetStateProperty.all(EdgeInsets.symmetric(
        horizontal: hasIcon ? 24 : 30,
        vertical: 16,
      )),
      textStyle: WidgetStateProperty.all(tt.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
      elevation: WidgetStateProperty.all(3),
    );
  }
}
