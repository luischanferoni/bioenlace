import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

typedef PersonalsaludLoginSuccess = void Function(
  String userId,
  String userName,
  BuildContext loginContext,
);

/// Login del personal de salud: ingreso con usuario asignado por el centro y biometría.
Widget buildPersonalsaludLoginScreen({
  required PersonalsaludLoginSuccess onLoginSuccess,
}) {
  return LoginScreen(
    appTitle: 'Personal de Salud',
    appSubtitle:
        'Ingresá con el usuario que te dio el centro. '
        'Si es tu primera vez, pedí el acceso a administración del efector.',
    welcomeMessage: '¡Bienvenido de vuelta, {userName}!',
    biometricAvailableText: 'Biometría configurada y lista para usar',
    diditBiometricWorkflowId: AppConfig.diditMedicoBiometricWorkflowId,
    appClient: 'bioenlace-personalsalud',
    onLoginSuccess: onLoginSuccess,
  );
}
