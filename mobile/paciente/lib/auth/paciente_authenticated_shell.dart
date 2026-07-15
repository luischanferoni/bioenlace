import 'dart:async';

import 'package:flutter/material.dart';
import 'package:shared/shared.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'paciente_session_prefs.dart';

/// Envuelve pantallas autenticadas con bloqueo biométrico por inactividad
/// y renovación preventiva del JWT al volver a foreground.
Widget wrapPacienteAuthenticatedShell({required Widget child}) {
  return BiometricSessionLockScope(
    appTitle: 'BioEnlace Paciente',
    // Solo bloquear si el usuario activó huella (mismo criterio que personal de salud).
    requireUnlockEnabled: true,
    canApplyLock: PacienteSessionPrefs.hasRestorableSession,
    // No revalidar JWT en red acá: un fallo transitorio de /auth/yo borraba el token.
    // Si el JWT expiró de verdad, el próximo request API redirige al login.
    child: _PacienteJwtKeepAlive(child: child),
  );
}

/// Renueva el JWT al volver de background si está cerca de `exp`.
class _PacienteJwtKeepAlive extends StatefulWidget {
  const _PacienteJwtKeepAlive({required this.child});

  final Widget child;

  @override
  State<_PacienteJwtKeepAlive> createState() => _PacienteJwtKeepAliveState();
}

class _PacienteJwtKeepAliveState extends State<_PacienteJwtKeepAlive>
    with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    unawaited(_refreshIfNeeded());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      unawaited(_refreshIfNeeded());
    }
  }

  Future<void> _refreshIfNeeded() async {
    final prefs = await SharedPreferences.getInstance();
    final token = (prefs.getString('auth_token') ?? '').trim();
    if (token.isEmpty) return;
    final next = await BearerSessionAuth.ensureFreshBearerToken(
      token,
      appClient: BearerSessionAuth.appClientPaciente,
    );
    if (next != token) {
      ClientDiagnosticApi.bindSession(
        authToken: next,
        appClient: BearerSessionAuth.appClientPaciente,
      );
    }
  }

  @override
  Widget build(BuildContext context) => widget.child;
}
