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

  const BiometricSessionLockScope({
    super.key,
    required this.child,
    required this.appTitle,
    this.requireUnlockEnabled = false,
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

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _bootstrap();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  Future<void> _bootstrap() async {
    await BiometricSessionPrefs.touchActivity();
    await _evaluateLock();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.paused ||
        state == AppLifecycleState.inactive) {
      BiometricSessionPrefs.touchActivity();
      return;
    }
    if (state == AppLifecycleState.resumed) {
      _evaluateLock();
    }
  }

  Future<void> _evaluateLock() async {
    final bioAvailable = await _biometricAuth.isAvailable();
    final bioType = await _biometricAuth.getBiometricType();
    final shouldLock = await BiometricSessionPrefs.shouldLock(
      requireUnlockEnabled: widget.requireUnlockEnabled,
    );

    if (!mounted) return;
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
