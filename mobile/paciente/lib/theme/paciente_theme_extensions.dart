import 'package:flutter/material.dart';

/// Colores semánticos que no están en [ColorScheme] estándar (éxito / advertencia en snackbars, etc.).
@immutable
class PacienteSemanticColors extends ThemeExtension<PacienteSemanticColors> {
  const PacienteSemanticColors({
    required this.success,
    required this.warning,
  });

  final Color success;
  final Color warning;

  static const light = PacienteSemanticColors(
    success: Color(0xFF28A745),
    warning: Color(0xFFFED9B7),
  );

  @override
  PacienteSemanticColors copyWith({
    Color? success,
    Color? warning,
  }) {
    return PacienteSemanticColors(
      success: success ?? this.success,
      warning: warning ?? this.warning,
    );
  }

  @override
  PacienteSemanticColors lerp(
    ThemeExtension<PacienteSemanticColors>? other,
    double t,
  ) {
    if (other is! PacienteSemanticColors) return this;
    return PacienteSemanticColors(
      success: Color.lerp(success, other.success, t)!,
      warning: Color.lerp(warning, other.warning, t)!,
    );
  }
}

extension PacienteThemeContext on BuildContext {
  ThemeData get pacienteTheme => Theme.of(this);

  ColorScheme get pacienteColors => pacienteTheme.colorScheme;

  TextTheme get pacienteTextTheme => pacienteTheme.textTheme;

  PacienteSemanticColors get pacienteSemantic =>
      pacienteTheme.extension<PacienteSemanticColors>() ?? PacienteSemanticColors.light;
}
