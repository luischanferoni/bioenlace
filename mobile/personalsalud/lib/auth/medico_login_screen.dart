import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../config/medico_dev_config.dart';
import '../screens/medico_signup_screen.dart';
import 'medico_dev_login.dart';

typedef MedicoLoginSuccess = void Function(
  String userId,
  String userName,
  BuildContext loginContext,
);

/// Login médico con registro, simulación de acceso e ingreso biométrico.
Widget buildMedicoLoginScreen({
  required MedicoLoginSuccess onLoginSuccess,
}) {
  return LoginScreen(
    appTitle: 'Personal de Salud',
    appSubtitle: 'Tu espacio de trabajo en el efector',
    welcomeMessage: '¡Bienvenido de vuelta, {userName}!',
    goToHomeButtonText: 'Ir al inicio de la app',
    biometricAvailableText: 'Biometría configurada y lista para usar',
    diditBiometricWorkflowId: AppConfig.diditMedicoBiometricWorkflowId,
    onLoginSuccess: onLoginSuccess,
    onNavigateToSignup: (loginContext) {
      Navigator.push(
        loginContext,
        MaterialPageRoute(
          builder: (_) => const MedicoSignupScreen(),
        ),
      );
    },
    onNavigateToHome:
        MedicoDevConfig.showDevHomeButton ? navigateMedicoDevHome : null,
  );
}
