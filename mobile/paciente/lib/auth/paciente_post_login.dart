import 'dart:async';

import 'package:flutter/material.dart';
import 'package:shared/shared.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../main.dart';
import '../screens/main_screen.dart';
import '../screens/signup_screen.dart';
import '../services/chat_service.dart';
import 'paciente_authenticated_shell.dart';
import 'paciente_session_prefs.dart';

/// Tras cerrar una Activity nativa (Didit), el overlay Flutter puede no estar listo.
Future<void> waitForUiReadyAfterNativeActivity() async {
  final binding = WidgetsBinding.instance;
  if (binding.lifecycleState != AppLifecycleState.resumed) {
    final done = Completer<void>();
    late final WidgetsBindingObserver observer;
    observer = _ResumeObserver(() {
      if (!done.isCompleted) {
        binding.removeObserver(observer);
        done.complete();
      }
    });
    binding.addObserver(observer);
    try {
      await done.future.timeout(const Duration(seconds: 8));
    } on TimeoutException {
      binding.removeObserver(observer);
    }
  }
  // Teardown de VerificationActivity + primer frame tras resume.
  await Future<void>.delayed(const Duration(milliseconds: 450));
  await binding.endOfFrame;
}

class _ResumeObserver with WidgetsBindingObserver {
  _ResumeObserver(this.onResumed);
  final VoidCallback onResumed;

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      onResumed();
    }
  }
}

BuildContext? _enrollmentContext(BuildContext? fallback) {
  final navCtx = navigatorKey.currentContext;
  if (navCtx != null && navCtx.mounted) {
    return navCtx;
  }
  if (fallback != null && fallback.mounted) {
    return fallback;
  }
  return null;
}

/// Exige activar huella/Face ID del dispositivo tras registro o ingreso con Didit.
///
/// Si el dispositivo no tiene biometría y [force] es false, permite continuar
/// (el ingreso remoto Didit ya validó identidad).
///
/// Con [force], vuelve a pedir enrolamiento aunque ya hubiera huella activada
/// (p. ej. tras un registro nuevo en el mismo dispositivo) y no saltea en
/// silencio si la biometría del sistema no está disponible.
Future<bool> requirePacienteBiometricEnrollment(
  BuildContext? context, {
  bool force = false,
  bool waitForNativeReturn = false,
}) async {
  if (waitForNativeReturn) {
    await waitForUiReadyAfterNativeActivity();
  }

  if (!force && await BiometricSessionPrefs.isUnlockEnabled()) {
    debugPrint('requirePacienteBiometricEnrollment: skip (already enabled)');
    return true;
  }

  if (force) {
    await BiometricSessionPrefs.setUnlockEnabled(false);
  }

  final bio = BiometricAuth();
  final available = await bio.isAvailable();
  debugPrint(
    'requirePacienteBiometricEnrollment: available=$available force=$force',
  );
  if (!available) {
    if (!force) {
      return true;
    }
    final unavailableCtx = _enrollmentContext(context);
    if (unavailableCtx == null) {
      return false;
    }
    await showDialog<void>(
      context: unavailableCtx,
      useRootNavigator: true,
      barrierDismissible: false,
      builder: (ctx) {
        final tokens = ctx.bio;
        return AlertDialog(
          backgroundColor: tokens.paperBackground,
          title: Text('Biometría requerida', style: BioTypography.h2),
          content: Text(
            'Para usar BioEnlace Paciente necesitás tener huella o Face ID '
            'configurados en este dispositivo (Ajustes del sistema) y '
            'volver a intentar.',
            style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
          ),
          actions: [
            BioButton.primary(
              label: 'Entendido',
              size: BioButtonSize.sm,
              onPressed: () => Navigator.pop(ctx),
            ),
          ],
        );
      },
    );
    return false;
  }

  final biometricType = await bio.getBiometricType();
  final label = biometricType.isNotEmpty ? biometricType : 'Huella digital';

  while (true) {
    final dialogCtx = _enrollmentContext(context);
    if (dialogCtx == null) {
      debugPrint(
        'requirePacienteBiometricEnrollment: sin BuildContext montado',
      );
      return false;
    }

    final result = await BiometricEnrollmentPrompt.show(
      dialogCtx,
      appTitle: 'BioEnlace Paciente',
      biometricType: label,
      mandatory: true,
    );

    if (result == BiometricEnrollmentResult.success) {
      debugPrint('requirePacienteBiometricEnrollment: success');
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

/// Tras Didit (login o registro): enrolar huella local y entrar a [MainScreen].
///
/// No depende de que la pantalla que disparó Didit siga montada: usa
/// [navigatorKey] para el diálogo y la navegación.
Future<bool> enterPacienteAuthenticatedApp({
  required String userId,
  required String userName,
  String? authToken,
  Map<String, dynamic>? pacienteContexto,
  BuildContext? fallbackContext,
  bool forceBiometricEnrollment = true,
  bool waitForNativeReturn = true,
}) async {
  debugPrint(
    'enterPacienteAuthenticatedApp: start userId=$userId '
    'forceBio=$forceBiometricEnrollment waitNative=$waitForNativeReturn',
  );

  final enrolled = await requirePacienteBiometricEnrollment(
    fallbackContext,
    force: forceBiometricEnrollment,
    waitForNativeReturn: waitForNativeReturn,
  );
  if (!enrolled) {
    debugPrint('enterPacienteAuthenticatedApp: enrolment cancelled/failed');
    return false;
  }

  final prefs = await SharedPreferences.getInstance();
  await prefs.setBool('is_logged_in', true);
  await prefs.setString('user_id', userId);
  await prefs.setString('user_name', userName);
  if (authToken != null && authToken.isNotEmpty) {
    await prefs.setString('auth_token', authToken);
    PacienteContextScope.instance.bindAuthToken(authToken);
  }
  if (pacienteContexto != null) {
    PacienteContextScope.instance.applyFromRegistration(pacienteContexto);
  }
  await CrashlyticsBootstrap.setUserId(userId);
  ClientDiagnosticApi.bindSession(
    authToken: authToken ?? prefs.getString('auth_token'),
    appClient: BearerSessionAuth.appClientPaciente,
  );
  await BiometricSessionPrefs.touchActivity();

  final token = authToken ?? prefs.getString('auth_token');
  final chatService = ChatService(
    currentUserId: userId,
    currentUserName: userName,
    authToken: token,
  );

  final nav = navigatorKey.currentState;
  if (nav == null) {
    debugPrint('enterPacienteAuthenticatedApp: navigatorKey sin state');
    return false;
  }

  nav.pushAndRemoveUntil(
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
  debugPrint('enterPacienteAuthenticatedApp: navigated to MainScreen');
  return true;
}

/// Tras login exitoso (Didit o credenciales): enrolamiento biométrico y MainScreen.
Future<void> navigatePacienteAfterLogin(
  String userId,
  String userName,
  BuildContext loginContext,
) async {
  final prefs = await SharedPreferences.getInstance();
  // LoginScreen ya pudo persistir token; reutilizarlo.
  final token = prefs.getString('auth_token');

  await enterPacienteAuthenticatedApp(
    userId: userId,
    userName: userName,
    authToken: token,
    fallbackContext: loginContext.mounted ? loginContext : null,
    forceBiometricEnrollment: true,
    waitForNativeReturn: true,
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
