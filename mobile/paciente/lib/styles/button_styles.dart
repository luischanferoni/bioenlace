// Archivo: lib/styles/button_styles.dart
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../theme/theme.dart';

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
  // Método principal para obtener estilos de botón
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

  // Estilos filled (rellenos)
  static ButtonStyle _getFilledStyle(BuildContext context, ButtonType type, bool hasIcon) {
    final colors = _getColors(type);
    return ElevatedButton.styleFrom(
      foregroundColor: colors['text'],
      backgroundColor: colors['background'],
      padding: EdgeInsets.symmetric(
        horizontal: hasIcon ? 16 : 20, 
        vertical: 12,
      ),
      textStyle: GoogleFonts.openSans(
        fontSize: 16, 
        fontWeight: FontWeight.w600,
      ),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(22),
      ),
      elevation: 2,
    );
  }

  // Estilos outline (solo borde)
  static ButtonStyle _getOutlineStyle(BuildContext context, ButtonType type, bool hasIcon) {
    final colors = _getColors(type);
    return OutlinedButton.styleFrom(
      foregroundColor: colors['background'],
      backgroundColor: Colors.transparent,
      padding: EdgeInsets.symmetric(
        horizontal: hasIcon ? 16 : 20, 
        vertical: 12,
      ),
      textStyle: GoogleFonts.openSans(
        fontSize: 16, 
        fontWeight: FontWeight.w600,
      ),
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

  // Estilos soft (fondo suave)
  static ButtonStyle _getSoftStyle(BuildContext context, ButtonType type, bool hasIcon) {
    final colors = _getColors(type);
    return ElevatedButton.styleFrom(
      foregroundColor: colors['background'],
      backgroundColor: colors['softBackground'],
      padding: EdgeInsets.symmetric(
        horizontal: hasIcon ? 16 : 20, 
        vertical: 12,
      ),
      textStyle: GoogleFonts.openSans(
        fontSize: 16, 
        fontWeight: FontWeight.w600,
      ),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(22),
      ),
      elevation: 0,
    );
  }

  // Obtener colores según el tipo de botón
  static Map<String, Color> _getColors(ButtonType type) {
    switch (type) {
      case ButtonType.primary:
        return {
          'background': AppTheme.primaryColor,
          'text': Colors.white,
          'softBackground': AppTheme.primaryColor.withOpacity(0.2),
        };
      case ButtonType.secondary:
        return {
          'background': AppTheme.secondaryColor,
          'text': Colors.white,
          'softBackground': AppTheme.secondaryColor.withOpacity(0.1),
        };
      case ButtonType.success:
        return {
          'background': AppTheme.successColor,
          'text': Colors.white,
          'softBackground': AppTheme.successColor.withOpacity(0.1),
        };
      case ButtonType.danger:
        return {
          'background': AppTheme.dangerColor,
          'text': Colors.white,
          'softBackground': AppTheme.dangerColor.withOpacity(0.1),
        };
      case ButtonType.warning:
        return {
          'background': AppTheme.warningColor,
          'text': Colors.black87,
          'softBackground': AppTheme.warningColor.withOpacity(0.1),
        };
      case ButtonType.info:
        return {
          'background': AppTheme.infoColor,
          'text': Colors.white,
          'softBackground': AppTheme.infoColor.withOpacity(0.1),
        };
      case ButtonType.light:
        return {
          'background': AppTheme.light,
          'text': Colors.black87,
          'softBackground': AppTheme.light.withOpacity(0.3),
        };
      case ButtonType.dark:
        return {
          'background': AppTheme.dark,
          'text': Colors.white,
          'softBackground': AppTheme.dark.withOpacity(0.1),
        };
    }
  }

  // Métodos de conveniencia para cada tipo (mantener compatibilidad)
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

  // Métodos específicos para outline
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

  // Métodos específicos para soft
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

  // Botones de tamaño específico
  static ButtonStyle small(BuildContext context, {ButtonType type = ButtonType.primary, ButtonVariant variant = ButtonVariant.filled, bool hasIcon = false}) {
    final baseStyle = getStyle(context, type: type, variant: variant, hasIcon: hasIcon);
    return baseStyle.copyWith(
      padding: MaterialStateProperty.all(EdgeInsets.symmetric(
        horizontal: hasIcon ? 8 : 12, 
        vertical: 6,
      )),
      textStyle: MaterialStateProperty.all(GoogleFonts.openSans(
        fontSize: 14, 
        fontWeight: FontWeight.w500,
      )),
    );
  }

  static ButtonStyle large(BuildContext context, {ButtonType type = ButtonType.primary, ButtonVariant variant = ButtonVariant.filled, bool hasIcon = false}) {
    final baseStyle = getStyle(context, type: type, variant: variant, hasIcon: hasIcon);
    return baseStyle.copyWith(
      padding: MaterialStateProperty.all(EdgeInsets.symmetric(
        horizontal: hasIcon ? 24 : 30, 
        vertical: 16,
      )),
      textStyle: MaterialStateProperty.all(GoogleFonts.openSans(
        fontSize: 18, 
        fontWeight: FontWeight.w600,
      )),
      elevation: MaterialStateProperty.all(3),
    );
  }
}