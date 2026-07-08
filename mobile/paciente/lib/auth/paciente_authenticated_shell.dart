import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import 'paciente_session_prefs.dart';
import 'paciente_post_login.dart';

/// Envuelve pantallas autenticadas con bloqueo biométrico por inactividad.
Widget wrapPacienteAuthenticatedShell({required Widget child}) {
  return BiometricSessionLockScope(
    appTitle: 'BioEnlace Paciente',
    canApplyLock: PacienteSessionPrefs.hasRestorableSession,
    validateSessionOnUnlock: validatePacienteBearerOnUnlock,
    onSessionExpired: () => returnPacienteToLogin(
      message: 'Tu sesión expiró. Ingresá de nuevo.',
    ),
    child: child,
  );
}
