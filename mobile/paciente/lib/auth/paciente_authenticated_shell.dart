import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import 'paciente_session_prefs.dart';

/// Envuelve pantallas autenticadas con bloqueo biométrico por inactividad.
Widget wrapPacienteAuthenticatedShell({required Widget child}) {
  return BiometricSessionLockScope(
    appTitle: 'BioEnlace Paciente',
    // Solo bloquear si el usuario activó huella (mismo criterio que personal de salud).
    requireUnlockEnabled: true,
    canApplyLock: PacienteSessionPrefs.hasRestorableSession,
    // No revalidar JWT en red acá: un fallo transitorio de /auth/yo borraba el token.
    // Si el JWT expiró de verdad, el próximo request API redirige al login.
    child: child,
  );
}
