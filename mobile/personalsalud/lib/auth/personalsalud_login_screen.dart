import 'package:flutter/material.dart';
import 'package:shared/shared.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'personalsalud_session_prefs.dart';
import 'personalsalud_staff_activation_screen.dart';

typedef PersonalsaludLoginSuccess = void Function(
  String userId,
  String userName,
  BuildContext loginContext,
);

/// Login del personal: usuario/contraseña la primera vez; luego huella del teléfono.
Widget buildPersonalsaludLoginScreen({
  required PersonalsaludLoginSuccess onLoginSuccess,
}) {
  return PersonalsaludLoginScreen(onLoginSuccess: onLoginSuccess);
}

class PersonalsaludLoginScreen extends StatefulWidget {
  const PersonalsaludLoginScreen({
    super.key,
    required this.onLoginSuccess,
  });

  final PersonalsaludLoginSuccess onLoginSuccess;

  @override
  State<PersonalsaludLoginScreen> createState() =>
      _PersonalsaludLoginScreenState();
}

class _PersonalsaludLoginScreenState extends State<PersonalsaludLoginScreen> {
  final _usernameCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  final _biometricAuth = BiometricAuth();

  bool _checkingSession = true;
  bool _useBiometricLogin = false;
  bool _biometricAvailable = false;
  String _biometricType = '';
  bool _obscurePassword = true;
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  @override
  void dispose() {
    _usernameCtrl.dispose();
    _passwordCtrl.dispose();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    final prefs = await SharedPreferences.getInstance();
    final established =
        prefs.getBool(PersonalsaludSessionPrefs.staffMobileLoginEstablishedKey) ??
            false;
    final hasToken = (prefs.getString('auth_token') ?? '').isNotEmpty;
    final bioAvailable = await _biometricAuth.isAvailable();
    final bioType = await _biometricAuth.getBiometricType();

    if (!mounted) return;
    setState(() {
      _useBiometricLogin = established && hasToken && bioAvailable;
      _biometricAvailable = bioAvailable;
      _biometricType = bioType;
      _checkingSession = false;
    });
  }

  void _snack(String message, UiIntent intent) {
    if (!mounted) return;
    final palette = IntentPalette.of(intent);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), backgroundColor: palette.base),
    );
  }

  Future<void> _persistLogin({
    required String userId,
    required String userName,
    required String? token,
    required bool markEstablished,
  }) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('is_logged_in', true);
    await prefs.setString('user_id', userId);
    await prefs.setString('user_name', userName);
    if (token != null && token.isNotEmpty) {
      await prefs.setString('auth_token', token);
    }
    if (markEstablished) {
      await prefs.setBool(
        PersonalsaludSessionPrefs.staffMobileLoginEstablishedKey,
        true,
      );
    }
    ClientDiagnosticApi.bindSession(
      authToken: token,
      appClient: 'bioenlace-personalsalud',
    );
  }

  Future<void> _submitCredentials() async {
    if (_submitting) return;
    final username = _usernameCtrl.text.trim();
    final password = _passwordCtrl.text;
    if (username.isEmpty || password.isEmpty) {
      _snack('Ingresá usuario y contraseña.', UiIntent.warning);
      return;
    }

    setState(() => _submitting = true);
    try {
      final data = await StaffCredentialAuth.login(
        username: username,
        password: password,
      );
      final payload = data['data'] is Map
          ? Map<String, dynamic>.from(data['data'] as Map)
          : <String, dynamic>{};
      final user = payload['user'] is Map
          ? Map<String, dynamic>.from(payload['user'] as Map)
          : <String, dynamic>{};
      final persona = payload['persona'] is Map
          ? Map<String, dynamic>.from(payload['persona'] as Map)
          : <String, dynamic>{};
      final token = payload['token']?.toString();

      final userId = (user['id'] ?? persona['id_persona'] ?? '').toString();
      final userName = user['name']?.toString().trim() ??
          '${persona['nombre'] ?? ''} ${persona['apellido'] ?? ''}'.trim();

      await _persistLogin(
        userId: userId,
        userName: userName.isNotEmpty ? userName : 'Usuario',
        token: token,
        markEstablished: false,
      );
      await CrashlyticsBootstrap.setUserId(userId);

      if (!mounted) return;
      widget.onLoginSuccess(
        userId,
        userName.isNotEmpty ? userName : 'Usuario',
        context,
      );
    } catch (e) {
      _snack(
        e.toString().replaceFirst('Exception: ', ''),
        UiIntent.danger,
      );
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _loginWithBiometrics() async {
    if (!_biometricAvailable) {
      _snack('La huella no está disponible en este dispositivo.', UiIntent.warning);
      return;
    }

    setState(() => _submitting = true);
    try {
      final result = await _biometricAuth.authenticate(
        'Confirmá con $_biometricType para ingresar a Personal de Salud',
      );
      if (result['success'] != true) {
        final isUserCancel = result['isUserCancel'] == true;
        final error = result['error'] as String?;
        if (!isUserCancel && error != null) {
          _snack(error, UiIntent.danger);
        }
        return;
      }

      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token');
      if (token == null || token.isEmpty) {
        setState(() => _useBiometricLogin = false);
        _snack('Volvé a ingresar con usuario y contraseña.', UiIntent.warning);
        return;
      }

      final userId = prefs.getString('user_id') ?? '';
      final userName = prefs.getString('user_name') ?? 'Usuario';
      if (userId.isEmpty) {
        setState(() => _useBiometricLogin = false);
        _snack('Sesión incompleta. Ingresá con usuario y contraseña.', UiIntent.warning);
        return;
      }

      ClientDiagnosticApi.bindSession(
        authToken: token,
        appClient: 'bioenlace-personalsalud',
      );
      await BiometricSessionPrefs.touchActivity();

      if (!mounted) return;
      widget.onLoginSuccess(userId, userName, context);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _switchToCredentials() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(PersonalsaludSessionPrefs.staffMobileLoginEstablishedKey);
    await prefs.remove('auth_token');
    await prefs.setBool('is_logged_in', false);
    if (!mounted) return;
    setState(() {
      _useBiometricLogin = false;
      _passwordCtrl.clear();
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_checkingSession) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }

    final tokens = context.bio;

    if (_useBiometricLogin) {
      return Scaffold(
        backgroundColor: tokens.paperBackground,
        body: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: BioSpacing.pageAll,
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const BioLogo(height: 56),
                  BioSpacing.gapH(BioSpacing.xl),
                  Text('Personal de Salud', style: BioTypography.h1),
                  BioSpacing.gapH(BioSpacing.sm),
                  Text(
                    'Ingresá con $_biometricType',
                    style: BioTypography.body.copyWith(color: tokens.textMuted),
                    textAlign: TextAlign.center,
                  ),
                  BioSpacing.gapH(BioSpacing.xxl),
                  if (_biometricType.isNotEmpty)
                    BioAlert.success(
                      message: '$_biometricType configurada y lista para usar',
                    ),
                  BioSpacing.gapH(BioSpacing.lg),
                  BioButton.primary(
                    label: _submitting
                        ? 'Autenticando…'
                        : 'Ingresar con $_biometricType',
                    icon: Icons.fingerprint,
                    size: BioButtonSize.lg,
                    fullWidth: true,
                    loading: _submitting,
                    onPressed:
                        _submitting || !_biometricAvailable ? null : _loginWithBiometrics,
                  ),
                  BioSpacing.gapH(BioSpacing.md),
                  BioButton.softPrimary(
                    label: 'Usar otro usuario',
                    icon: Icons.switch_account,
                    fullWidth: true,
                    onPressed: _submitting ? null : _switchToCredentials,
                  ),
                  BioSpacing.gapH(BioSpacing.lg),
                  const PrivacyPolicyLink(),
                ],
              ),
            ),
          ),
        ),
      );
    }

    return Scaffold(
      backgroundColor: tokens.paperBackground,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: BioSpacing.pageAll,
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Center(child: BioLogo(height: 56)),
                BioSpacing.gapH(BioSpacing.xl),
                Text(
                  'Personal de Salud',
                  style: BioTypography.h1,
                  textAlign: TextAlign.center,
                ),
                BioSpacing.gapH(BioSpacing.sm),
                Text(
                  'Primera vez: activá tu cuenta con el código de administración '
                  'o ingresá si ya elegiste contraseña.',
                  style: BioTypography.body.copyWith(color: tokens.textMuted),
                  textAlign: TextAlign.center,
                ),
                BioSpacing.gapH(BioSpacing.xxl),
                TextField(
                  controller: _usernameCtrl,
                  decoration: const InputDecoration(labelText: 'Usuario'),
                  textInputAction: TextInputAction.next,
                  autocorrect: false,
                  enabled: !_submitting,
                ),
                BioSpacing.gapH(BioSpacing.md),
                TextField(
                  controller: _passwordCtrl,
                  decoration: InputDecoration(
                    labelText: 'Contraseña',
                    suffixIcon: IconButton(
                      icon: Icon(
                        _obscurePassword
                            ? Icons.visibility
                            : Icons.visibility_off,
                      ),
                      onPressed: _submitting
                          ? null
                          : () => setState(
                                () => _obscurePassword = !_obscurePassword,
                              ),
                    ),
                  ),
                  obscureText: _obscurePassword,
                  onSubmitted: (_) => _submitCredentials(),
                  enabled: !_submitting,
                ),
                BioSpacing.gapH(BioSpacing.lg),
                BioButton.primary(
                  label: _submitting ? 'Ingresando…' : 'Ingresar',
                  icon: Icons.login,
                  size: BioButtonSize.lg,
                  fullWidth: true,
                  loading: _submitting,
                  onPressed: _submitting ? null : _submitCredentials,
                ),
                BioSpacing.gapH(BioSpacing.md),
                BioButton.softPrimary(
                  label: 'Activar cuenta con código',
                  icon: Icons.vpn_key_outlined,
                  fullWidth: true,
                  onPressed: _submitting
                      ? null
                      : () {
                          Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) =>
                                  const PersonalsaludStaffActivationScreen(),
                            ),
                          );
                        },
                ),
                BioSpacing.gapH(BioSpacing.lg),
                const PrivacyPolicyLink(),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
