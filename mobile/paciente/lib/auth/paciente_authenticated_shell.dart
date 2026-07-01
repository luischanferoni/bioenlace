import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

/// Envuelve pantallas autenticadas con bloqueo biométrico por inactividad.
Widget wrapPacienteAuthenticatedShell({required Widget child}) {
  return BiometricSessionLockScope(
    appTitle: 'BioEnlace Paciente',
    child: child,
  );
}
