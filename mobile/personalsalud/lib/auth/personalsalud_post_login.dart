import 'package:flutter/material.dart';
import 'package:shared/shared.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../main.dart';
import '../screens/config_wizard_screen.dart';
import 'personalsalud_authenticated_shell.dart';
import 'personalsalud_login_screen.dart';
import 'personalsalud_session_prefs.dart';

Future<bool> _staffBiometricEnrollmentComplete() async {
  final prefs = await SharedPreferences.getInstance();
  final established =
      prefs.getBool(PersonalsaludSessionPrefs.staffMobileLoginEstablishedKey) ??
          false;
  final unlockEnabled = await BiometricSessionPrefs.isUnlockEnabled();
  if (unlockEnabled && !established) {
    await prefs.setBool(
      PersonalsaludSessionPrefs.staffMobileLoginEstablishedKey,
      true,
    );
    return true;
  }
  return established && unlockEnabled;
}

/// Exige activar huella/Face ID inmediatamente tras el primer acceso con credenciales.
Future<bool> requirePersonalsaludBiometricEnrollment(
  BuildContext context,
) async {
  if (await _staffBiometricEnrollmentComplete()) {
    return true;
  }

  final bio = BiometricAuth();
  if (!await bio.isAvailable()) {
    if (context.mounted) {
      await showDialog<void>(
        context: context,
        barrierDismissible: false,
        builder: (ctx) {
          final tokens = ctx.bio;
          return AlertDialog(
            backgroundColor: tokens.paperBackground,
            title: Text('Biometría requerida', style: BioTypography.h2),
            content: Text(
              'Este dispositivo debe tener huella digital o Face ID configurado '
              'para usar Personal de Salud.',
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
    }
    return false;
  }

  final biometricType = await bio.getBiometricType();
  final label = biometricType.isNotEmpty ? biometricType : 'Huella digital';

  while (true) {
    if (!context.mounted) return false;

    final result = await BiometricEnrollmentPrompt.show(
      context,
      appTitle: 'Personal de Salud',
      biometricType: label,
      mandatory: true,
    );

    if (result == BiometricEnrollmentResult.success) {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool(
        PersonalsaludSessionPrefs.staffMobileLoginEstablishedKey,
        true,
      );
      await prefs.remove(
        PersonalsaludSessionPrefs.staffBiometricEnrollmentDeclinedKey,
      );
      return true;
    }

    if (!context.mounted) return false;
  }
}

Future<void> _returnToLoginAfterEnrollmentFailure() async {
  await returnPersonalsaludToLogin();
}

/// Cierra sesión local y vuelve a la pantalla de login.
Future<void> returnPersonalsaludToLogin({String? message}) async {
  await PersonalsaludSessionPrefs.clearInvalidAuthSession();
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
                backgroundColor: IntentPalette.of(UiIntent.danger).base,
              ),
            );
          });
        }
        return buildPersonalsaludLoginScreen(
          onLoginSuccess: navigatePersonalsaludAfterLogin,
        );
      },
    ),
    (route) => false,
  );
}

/// Tras login con credenciales: huella obligatoria y wizard de efector/servicio/área.
Future<void> navigatePersonalsaludAfterLogin(
  String userId,
  String userName,
  BuildContext loginContext,
) async {
  final prefs = await SharedPreferences.getInstance();
  final loginToken = prefs.getString('auth_token');

  await PersonalsaludSessionPrefs.clearOperationalContext(keepAuthToken: true);
  await prefs.setBool('is_logged_in', true);
  await prefs.setString('user_id', userId);
  await prefs.setString('user_name', userName);
  await CrashlyticsBootstrap.setUserId(userId);
  if (loginToken != null && loginToken.isNotEmpty) {
    await prefs.setString('auth_token', loginToken);
  }

  if (!loginContext.mounted) return;

  final enrolled =
      await requirePersonalsaludBiometricEnrollment(loginContext);
  if (!enrolled) {
    await _returnToLoginAfterEnrollmentFailure();
    return;
  }

  await BiometricSessionPrefs.touchActivity();

  if (!loginContext.mounted) return;
  openPersonalsaludSessionWizard(
    userId: userId,
    userName: userName,
    authToken: loginToken,
  );
}

/// Abre el wizard de sesión operativa (efector / servicio / área).
void openPersonalsaludSessionWizard({
  required String userId,
  required String userName,
  String? authToken,
}) {
  final wizard = ConfigWizardScreen(
    userId: userId,
    userName: userName,
    authToken: authToken,
  );

  navigatorKey.currentState?.pushAndRemoveUntil(
    MaterialPageRoute(
      builder: (_) => wrapPersonalsaludAuthenticatedShell(child: wizard),
    ),
    (route) => false,
  );
}

/// Si falta encounter en API o en prefs, limpia contexto y vuelve al wizard.
Future<void> recoverPersonalsaludOperationalSession({
  required String userId,
  required String userName,
  String? authToken,
}) async {
  final prefs = await SharedPreferences.getInstance();
  final token = authToken ?? prefs.getString('auth_token');
  await PersonalsaludSessionPrefs.clearOperationalContext(keepAuthToken: true);
  if (token != null && token.isNotEmpty) {
    await prefs.setString('auth_token', token);
  }
  openPersonalsaludSessionWizard(
    userId: userId,
    userName: userName,
    authToken: token,
  );
}

bool isPersonalsaludEncounterSessionError(Object error) {
  final msg = error.toString().toLowerCase();
  return msg.contains('encounter configurado') ||
      msg.contains('encounter_class') ||
      msg.contains('área de atención configurada');
}

/// Bloquea el contenido autenticado hasta completar el enrolamiento biométrico.
class PersonalsaludBiometricGate extends StatefulWidget {
  const PersonalsaludBiometricGate({super.key, required this.child});

  final Widget child;

  @override
  State<PersonalsaludBiometricGate> createState() =>
      _PersonalsaludBiometricGateState();
}

class _PersonalsaludBiometricGateState extends State<PersonalsaludBiometricGate> {
  bool _checking = true;
  bool _enrolled = false;

  @override
  void initState() {
    super.initState();
    _ensureEnrollment();
  }

  Future<void> _ensureEnrollment() async {
    if (await _staffBiometricEnrollmentComplete()) {
      if (!mounted) return;
      setState(() {
        _enrolled = true;
        _checking = false;
      });
      return;
    }

    if (!mounted) return;
    final ok = await requirePersonalsaludBiometricEnrollment(context);
    if (!ok) {
      await _returnToLoginAfterEnrollmentFailure();
      return;
    }

    if (!mounted) return;
    setState(() {
      _enrolled = true;
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
