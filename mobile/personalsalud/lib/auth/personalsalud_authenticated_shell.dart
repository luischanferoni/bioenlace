import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

/// Envuelve pantallas autenticadas con bloqueo biométrico por inactividad.
Widget wrapPersonalsaludAuthenticatedShell({required Widget child}) {
  return BiometricSessionLockScope(
    appTitle: 'Personal de Salud',
    requireUnlockEnabled: true,
    child: child,
  );
}
