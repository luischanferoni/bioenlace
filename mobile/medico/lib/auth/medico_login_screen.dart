import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

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
    appTitle: 'BioEnlace Médico',
    appSubtitle: 'Acceso para profesionales de la salud',
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
    onNavigateToHome: navigateMedicoDevHome,
  );
}
