import 'dart:async';

import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';
import '../ui/ui.dart';
import 'biometric_auth.dart';
import 'biometric_session_prefs.dart';

/// Bloquea el contenido autenticado tras inactividad y pide huella/Face ID.
class BiometricSessionLockScope extends StatefulWidget {
  final Widget child;
  final String appTitle;

  /// Si `true`, solo bloquea cuando el usuario activó biometría (p. ej. staff).
  final bool requireUnlockEnabled;

  /// Si retorna `false`, no se aplica bloqueo (p. ej. sin JWT persistido).
  final Future<bool> Function()? canApplyLock;

  /// Tras desbloquear con huella, valida JWT. Si devuelve `false`, se llama [onSessionExpired].
  final Future<bool> Function()? validateSessionOnUnlock;

  final Future<void> Function()? onSessionExpired;

  const BiometricSessionLockScope({
    super.key,
    required this.child,
    required this.appTitle,
    this.requireUnlockEnabled = false,
    this.canApplyLock,
    this.validateSessionOnUnlock,
    this.onSessionExpired,
  });

  @override
  State<BiometricSessionLockScope> createState() =>
      _BiometricSessionLockScopeState();
}

class _BiometricSessionLockScopeState extends State<BiometricSessionLockScope>
    with WidgetsBindingObserver {
  final _biometricAuth = BiometricAuth();
  bool _locked = false;
  bool _checking = true;
  bool _authenticating = false;
  bool _biometricAvailable = false;
  String _biometricType = '';
  Timer? _idleCheckTimer;

  static const _idlePollInterval = Duration(seconds: 10);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _bootstrap();
  }

  @override
  void dispose() {
    _idleCheckTimer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  Future<void> _bootstrap() async {
    await _evaluateLock();
    if (!_locked) {
      await BiometricSessionPrefs.touchActivity();
    }
    _startIdleCheckTimer();
  }

  void _startIdleCheckTimer() {
    _idleCheckTimer?.cancel();
    _idleCheckTimer = Timer.periodic(_idlePollInterval, (_) {
      if (!mounted || _locked || _checking || _authenticating) {
        return;
      }
      unawaited(_evaluateLock());
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      // No re-evaluar mientras el prompt biométrico está activo: el sistema
      // pausa la app al mostrar BiometricPrompt y un resume intermedio
      // disparaba otro unlock / validaciones en paralelo.
      if (_authenticating) {
        return;
      }
      unawaited(_evaluateLock());
    }
  }

  Future<void> _evaluateLock() async {
    if (_authenticating) {
      return;
    }

    final sessionOk =
        widget.canApplyLock == null || await widget.canApplyLock!();
    final bioAvailable = await _biometricAuth.isAvailable();
    final bioType = await _biometricAuth.getBiometricType();
    final shouldLock = sessionOk &&
        await BiometricSessionPrefs.shouldLock(
          requireUnlockEnabled: widget.requireUnlockEnabled,
        );

    if (!mounted || _authenticating) return;
    setState(() {
      _biometricAvailable = bioAvailable;
      _biometricType = bioType;
      _locked = shouldLock && bioAvailable;
      _checking = false;
    });

    if (_locked) {
      _unlockWithBiometrics(auto: true);
    }
  }

  Future<void> _unlockWithBiometrics({bool auto = false}) async {
    if (_authenticating || !_biometricAvailable) return;

    setState(() => _authenticating = true);
    try {
      final result = await _biometricAuth.authenticate(
        'Confirmá con $_biometricType para continuar en ${widget.appTitle}',
      );
      if (!mounted) return;

      if (result['success'] == true) {
        if (widget.validateSessionOnUnlock != null) {
          final sessionOk = await widget.validateSessionOnUnlock!();
          if (!sessionOk) {
            setState(() => _authenticating = false);
            await widget.onSessionExpired?.call();
            return;
          }
        }
        await BiometricSessionPrefs.touchActivity();
        setState(() {
          _locked = false;
          _authenticating = false;
        });
        return;
      }

      setState(() => _authenticating = false);
      if (!auto && result['isUserCancel'] != true) {
        final error = result['error']?.toString();
        if (error != null && error.isNotEmpty) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(error)),
          );
        }
      }
    } catch (_) {
      if (mounted) setState(() => _authenticating = false);
    }
  }

  void _onUserActivity() {
    if (_locked || _checking) return;
    BiometricSessionPrefs.touchActivity();
  }

  @override
  Widget build(BuildContext context) {
    if (_checking) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }

    return Listener(
      onPointerDown: (_) => _onUserActivity(),
      behavior: HitTestBehavior.translucent,
      child: Stack(
        fit: StackFit.expand,
        children: [
          widget.child,
          if (_locked) _buildLockOverlay(context),
        ],
      ),
    );
  }

  Widget _buildLockOverlay(BuildContext context) {
    final tokens = context.bio;
    return Material(
      color: tokens.paperBackground,
      child: SafeArea(
        child: Center(
          child: Padding(
            padding: BioSpacing.pageAll,
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const BioLogo(height: 56),
                BioSpacing.gapH(BioSpacing.xl),
                Text(
                  widget.appTitle,
                  style: BioTypography.h1,
                  textAlign: TextAlign.center,
                ),
                BioSpacing.gapH(BioSpacing.sm),
                Text(
                  'Por seguridad, confirmá tu identidad para continuar',
                  style: BioTypography.body.copyWith(color: tokens.textMuted),
                  textAlign: TextAlign.center,
                ),
                BioSpacing.gapH(BioSpacing.xxl),
                BioButton.primary(
                  label: _authenticating
                      ? 'Autenticando…'
                      : 'Desbloquear con $_biometricType',
                  icon: Icons.fingerprint,
                  size: BioButtonSize.lg,
                  fullWidth: true,
                  loading: _authenticating,
                  onPressed: _authenticating || !_biometricAvailable
                      ? null
                      : () => _unlockWithBiometrics(),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
