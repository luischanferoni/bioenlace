import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

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
