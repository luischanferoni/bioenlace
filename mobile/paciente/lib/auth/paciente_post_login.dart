import 'package:flutter/material.dart';
import 'package:shared/shared.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../main.dart';
import '../screens/main_screen.dart';
import '../screens/signup_screen.dart';
import '../services/chat_service.dart';
import 'paciente_authenticated_shell.dart';
import 'paciente_session_prefs.dart';

/// Exige activar huella/Face ID del dispositivo tras registro o ingreso con Didit.
///
/// Si el dispositivo no tiene biometría, permite continuar (el ingreso remoto
/// Didit ya validó identidad).
Future<bool> requirePacienteBiometricEnrollment(BuildContext context) async {
  if (await BiometricSessionPrefs.isUnlockEnabled()) {
    return true;
  }

  final bio = BiometricAuth();
  if (!await bio.isAvailable()) {
    return true;
  }

  final biometricType = await bio.getBiometricType();
  final label = biometricType.isNotEmpty ? biometricType : 'Huella digital';

  while (true) {
    if (!context.mounted) return false;

    final result = await BiometricEnrollmentPrompt.show(
      context,
      appTitle: 'BioEnlace Paciente',
      biometricType: label,
      mandatory: true,
    );

    if (result == BiometricEnrollmentResult.success) {
      return true;
    }
  }
}

typedef PacienteLoginSuccess = Future<void> Function(
  String userId,
  String userName,
  BuildContext loginContext,
);

Widget buildPacienteLoginScreen({
  required PacienteLoginSuccess onLoginSuccess,
}) {
  return LoginScreen(
    appTitle: 'Bienvenido a BioEnlace',
    appSubtitle: 'Tu asistente de salud personal',
    welcomeMessage: '¡Bienvenido de vuelta, {userName}!',
    signupButtonText: '¿No tienes cuenta? Regístrate aquí',
    diditRemoteLoginAfterLogout: true,
    appClient: BearerSessionAuth.appClientPaciente,
    onLoginSuccess: onLoginSuccess,
    onNavigateToSignup: (loginContext) {
      Navigator.push(
        loginContext,
        MaterialPageRoute(builder: (_) => SignupScreen()),
      );
    },
  );
}

/// Tras login exitoso (Didit o credenciales): enrolamiento biométrico y MainScreen.
Future<void> navigatePacienteAfterLogin(
  String userId,
  String userName,
  BuildContext loginContext,
) async {
  final prefs = await SharedPreferences.getInstance();
  await prefs.setBool('is_logged_in', true);
  await prefs.setString('user_id', userId);
  await prefs.setString('user_name', userName);
  await CrashlyticsBootstrap.setUserId(userId);
  final token = prefs.getString('auth_token');
  ClientDiagnosticApi.bindSession(
    authToken: token,
    appClient: BearerSessionAuth.appClientPaciente,
  );

  if (!loginContext.mounted) return;

  final enrolled = await requirePacienteBiometricEnrollment(loginContext);
  if (!enrolled || !loginContext.mounted) return;

  final chatService = ChatService(
    currentUserId: userId,
    currentUserName: userName,
    authToken: token,
  );

  navigatorKey.currentState?.pushAndRemoveUntil(
    MaterialPageRoute<void>(
      builder: (_) => wrapPacienteAuthenticatedShell(
        child: MainScreen(
          chatService: chatService,
          authToken: token,
        ),
      ),
    ),
    (route) => false,
  );
}

/// Cierra sesión local y vuelve al login (JWT inválido o expirado).
Future<void> returnPacienteToLogin({String? message}) async {
  await PacienteSessionPrefs.clearInvalidAuthSession();
  final nav = navigatorKey.currentState;
  if (nav == null) {
    return;
  }
  nav.pushAndRemoveUntil(
    MaterialPageRoute<void>(
      builder: (loginContext) {
        if (message != null && message.isNotEmpty) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (!loginContext.mounted) return;
            ScaffoldMessenger.of(loginContext).showSnackBar(
              SnackBar(
                content: Text(message),
                backgroundColor: IntentPalette.of(UiIntent.warning).base,
              ),
            );
          });
        }
        return buildPacienteLoginScreen(
          onLoginSuccess: navigatePacienteAfterLogin,
        );
      },
    ),
    (route) => false,
  );
}

Future<bool> validatePacienteBearerOnUnlock() async {
  final prefs = await SharedPreferences.getInstance();
  final token = (prefs.getString('auth_token') ?? '').trim();
  if (token.isEmpty) {
    return false;
  }
  final check = await BearerSessionAuth.checkBearerToken(
    token,
    appClient: BearerSessionAuth.appClientPaciente,
  );
  if (check == BearerSessionCheckResult.invalid) {
    return false;
  }
  return true;
}

/// Bloquea el contenido hasta completar enrolamiento biométrico local (arranque con sesión).
class PacienteBiometricGate extends StatefulWidget {
  const PacienteBiometricGate({super.key, required this.child});

  final Widget child;

  @override
  State<PacienteBiometricGate> createState() => _PacienteBiometricGateState();
}

class _PacienteBiometricGateState extends State<PacienteBiometricGate> {
  bool _checking = true;
  bool _enrolled = false;

  @override
  void initState() {
    super.initState();
    _ensureEnrollment();
  }

  Future<void> _ensureEnrollment() async {
    final bio = BiometricAuth();
    if (!await bio.isAvailable() ||
        await BiometricSessionPrefs.isUnlockEnabled()) {
      if (!mounted) return;
      setState(() {
        _enrolled = true;
        _checking = false;
      });
      return;
    }

    if (!mounted) return;
    final ok = await requirePacienteBiometricEnrollment(context);
    if (!mounted) return;
    setState(() {
      _enrolled = ok;
      _checking = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_checking || !_enrolled) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }
    return widget.child;
  }
}
