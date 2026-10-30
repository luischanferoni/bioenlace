import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:shared/shared.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../infra/app_texts.dart';

import '../main.dart';
import '../screens/main_screen.dart';
import '../screens/staff_signup_screen.dart';
import '../services/staff_signup_service.dart';
import 'personalsalud_authenticated_shell.dart';
import 'personalsalud_session_prefs.dart';

typedef PersonalsaludLoginSuccess =
    void Function(String userId, String userName, BuildContext loginContext);

/// Login del personal: usuario/contraseña la primera vez; luego huella del teléfono.
Widget buildPersonalsaludLoginScreen({
  required PersonalsaludLoginSuccess onLoginSuccess,
}) {
  return PersonalsaludLoginScreen(onLoginSuccess: onLoginSuccess);
}

class PersonalsaludLoginScreen extends StatefulWidget {
  const PersonalsaludLoginScreen({super.key, required this.onLoginSuccess});

  final PersonalsaludLoginSuccess onLoginSuccess;

  @override
  State<PersonalsaludLoginScreen> createState() =>
      _PersonalsaludLoginScreenState();
}

class _PersonalsaludLoginScreenState extends State<PersonalsaludLoginScreen> {
  final _biometricAuth = BiometricAuth();

  bool _checkingSession = true;
  bool _useBiometricLogin = false;
  bool _biometricAvailable = false;
  String _biometricType = '';
  bool _submitting = false;
  bool _demoAvailable = false;
  List<DemoSandboxProfile> _demoProfiles = const [];

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    final prefs = await SharedPreferences.getInstance();
    final established =
        prefs.getBool(
          PersonalsaludSessionPrefs.staffMobileLoginEstablishedKey,
        ) ??
        false;
    var hasToken = (prefs.getString('auth_token') ?? '').isNotEmpty;
    final bioAvailable = await _biometricAuth.isAvailable();
    final bioType = await _biometricAuth.getBiometricType();

    if (hasToken) {
      final check = await BearerSessionAuth.checkBearerToken(
        prefs.getString('auth_token')!,
      );
      if (check == BearerSessionCheckResult.invalid) {
        await PersonalsaludSessionPrefs.clearInvalidAuthSession();
        hasToken = false;
      }
    } else if (prefs.getBool('is_logged_in') ?? false) {
      await PersonalsaludSessionPrefs.clearInvalidAuthSession();
    }

    List<DemoSandboxProfile> demoProfiles = const [];
    String? demoProfilesError;
    try {
      demoProfiles = await DemoSandboxStaffAuth.listStaffProfiles();
    } catch (error) {
      demoProfilesError = error.toString().replaceFirst('Exception: ', '');
    }

    if (!mounted) return;
    setState(() {
      _useBiometricLogin = established && hasToken && bioAvailable;
      _biometricAvailable = bioAvailable;
      _biometricType = bioType;
      _demoProfiles = demoProfiles;
      _demoAvailable = kDebugMode && demoProfiles.isNotEmpty;
      _checkingSession = false;
    });
    if (demoProfilesError != null) {
      _snack(demoProfilesError, UiIntent.warning);
    }
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

  Future<DemoSandboxProfile?> _pickDemoProfile() async {
    if (_demoProfiles.isEmpty) return null;
    if (_demoProfiles.length == 1) return _demoProfiles.first;
    return showModalBottomSheet<DemoSandboxProfile>(
      context: context,
      showDragHandle: true,
      builder: (sheetCtx) {
        return SafeArea(
          child: ListView(
            shrinkWrap: true,
            children: [
              Padding(
                padding: BioSpacing.pageHorizontal.copyWith(top: BioSpacing.sm),
                child: Text(
                  AppTexts.loginChooseRole,
                  style: BioTypography.title.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              BioSpacing.gapH(BioSpacing.sm),
              ..._demoProfiles.map(
                (p) => ListTile(
                  leading: Icon(_iconForDemoRole(p.role)),
                  title: Text(p.label),
                  onTap: () => Navigator.of(sheetCtx).pop(p),
                ),
              ),
              BioSpacing.gapH(BioSpacing.sm),
            ],
          ),
        );
      },
    );
  }

  IconData _iconForDemoRole(String role) {
    switch (role) {
      case 'enfermeria':
        return Icons.medical_services_outlined;
      case 'administrativo':
        return Icons.badge_outlined;
      default:
        // 'staff' y 'odontologia' son ambos del rol Médico.
        return Icons.health_and_safety_outlined;
    }
  }

  Future<void> _onProbarDemo() async {
    if (!kDebugMode || _submitting) return;
    final profile = await _pickDemoProfile();
    if (profile == null || !mounted) return;
    await _enterDemo(profile);
  }

  Future<void> _enterDemo(DemoSandboxProfile profile) async {
    if (!kDebugMode || _submitting) return;
    setState(() => _submitting = true);
    try {
      final data = await DemoSandboxStaffAuth.enter(role: profile.role);
      final user = data['user'] is Map
          ? Map<String, dynamic>.from(data['user'] as Map)
          : <String, dynamic>{};
      final persona = data['persona'] is Map
          ? Map<String, dynamic>.from(data['persona'] as Map)
          : <String, dynamic>{};
      final session = data['session'] is Map
          ? Map<String, dynamic>.from(data['session'] as Map)
          : <String, dynamic>{};
      final token = data['token']?.toString() ?? '';

      if (token.isEmpty) {
        throw Exception('La demo no devolvió token de sesión.');
      }

      final userId = (user['id'] ?? persona['id_persona'] ?? '').toString();
      final userName =
          user['name']?.toString().trim() ??
          '${persona['nombre'] ?? ''} ${persona['apellido'] ?? ''}'.trim();
      final displayName = userName.isNotEmpty ? userName : profile.label;

      await PersonalsaludSessionPrefs.clearOnLogout();
      await _persistLogin(
        userId: userId,
        userName: displayName,
        token: token,
        markEstablished: false,
      );

      final prefs = await SharedPreferences.getInstance();
      final idEfector = int.tryParse('${session['id_efector'] ?? 0}') ?? 0;
      final idServicio = int.tryParse('${session['id_servicio'] ?? 0}') ?? 0;
      final idPes =
          int.tryParse('${session['id_profesional_efector_servicio'] ?? 0}') ??
          0;
      final encounter = (session['encounter_class']?.toString() ?? 'AMB')
          .trim();
      if (idEfector <= 0 || idServicio <= 0 || idPes <= 0) {
        throw Exception('La demo no trajo contexto operativo completo.');
      }

      await prefs.setInt('efector_id', idEfector);
      await prefs.setString(
        'efector_nombre',
        session['efector_nombre']?.toString() ?? 'Demo',
      );
      await prefs.setInt('servicio_id', idServicio);
      await prefs.setString(
        'servicio_nombre',
        session['servicio_nombre']?.toString() ?? 'MED GENERAL',
      );
      await prefs.setString('encounter_class', encounter);
      await prefs.setString(
        'encounter_class_label',
        session['encounter_class_label']?.toString() ?? 'Ambulatoria',
      );
      await prefs.setInt('id_profesional_efector_servicio', idPes);
      await prefs.setBool('config_completed', true);
      await prefs.setBool(PersonalsaludSessionPrefs.demoSandboxActiveKey, true);
      await CrashlyticsBootstrap.setUserId(userId);

      if (!mounted) return;
      navigatorKey.currentState?.pushAndRemoveUntil(
        MaterialPageRoute<void>(
          builder: (_) => wrapPersonalsaludAuthenticatedShell(
            child: MainScreen(
              userId: userId,
              userName: displayName,
              authToken: token,
              idProfesionalEfectorServicio: idPes.toString(),
            ),
          ),
        ),
        (route) => false,
      );
    } catch (e) {
      _snack(e.toString().replaceFirst('Exception: ', ''), UiIntent.danger);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _loginExisting() async {
    if (_submitting) return;
    setState(() => _submitting = true);
    try {
      final data = await StaffSignupService().loginExisting();
      final user = data['user'] is Map
          ? Map<String, dynamic>.from(data['user'] as Map)
          : <String, dynamic>{};
      final persona = data['persona'] is Map
          ? Map<String, dynamic>.from(data['persona'] as Map)
          : <String, dynamic>{};
      final token = data['token']?.toString();

      if (token == null || token.isEmpty) {
        throw Exception('No se pudo iniciar sesión.');
      }

      final userId = (user['id'] ?? persona['id_persona'] ?? '').toString();
      final userName =
          user['name']?.toString().trim() ??
          '${persona['nombre'] ?? ''} ${persona['apellido'] ?? ''}'.trim();

      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(PersonalsaludSessionPrefs.demoSandboxActiveKey);

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
      _snack(e.toString().replaceFirst('Exception: ', ''), UiIntent.danger);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _loginWithBiometrics() async {
    if (!_biometricAvailable) {
      _snack(AppTexts.loginBiometricUnavailable, UiIntent.warning);
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
        _snack(AppTexts.loginReverifyIdentity, UiIntent.warning);
        return;
      }

      final sessionCheck = await BearerSessionAuth.checkBearerToken(token);
      if (sessionCheck == BearerSessionCheckResult.invalid) {
        await PersonalsaludSessionPrefs.clearInvalidAuthSession();
        if (!mounted) return;
        setState(() => _useBiometricLogin = false);
        _snack(AppTexts.loginSessionExpired, UiIntent.warning);
        return;
      }
      if (sessionCheck == BearerSessionCheckResult.networkError) {
        _snack(AppTexts.loginSessionCheckFailed, UiIntent.warning);
        return;
      }

      final userId = prefs.getString('user_id') ?? '';
      final userName = prefs.getString('user_name') ?? 'Usuario';
      if (userId.isEmpty) {
        setState(() => _useBiometricLogin = false);
        _snack(AppTexts.loginSessionIncomplete, UiIntent.warning);
        return;
      }

      ClientDiagnosticApi.bindSession(
        authToken: token,
        appClient: 'bioenlace-personalsalud',
      );
      await BiometricSessionPrefs.setUnlockEnabled(true);
      await BiometricSessionPrefs.touchActivity();

      if (!mounted) return;
      widget.onLoginSuccess(userId, userName, context);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _useAnotherAccount() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(
      PersonalsaludSessionPrefs.staffMobileLoginEstablishedKey,
    );
    await prefs.remove('auth_token');
    await prefs.setBool('is_logged_in', false);
    if (!mounted) return;
    setState(() {
      _useBiometricLogin = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_checkingSession) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
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
                  Text(AppTexts.appTitle, style: BioTypography.h1),
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
                    onPressed: _submitting || !_biometricAvailable
                        ? null
                        : _loginWithBiometrics,
                  ),
                  BioSpacing.gapH(BioSpacing.md),
                  BioButton.softPrimary(
                    label: 'Usar otra cuenta',
                    icon: Icons.switch_account,
                    fullWidth: true,
                    onPressed: _submitting ? null : _useAnotherAccount,
                  ),
                  BioSpacing.gapH(BioSpacing.lg),
                  const PrivacyPolicyLink(),
                  if (_demoAvailable) ...[
                    BioSpacing.gapH(BioSpacing.md),
                    _buildProbarDemoButton(),
                  ],
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
                  AppTexts.appTitle,
                  style: BioTypography.h1,
                  textAlign: TextAlign.center,
                ),
                BioSpacing.gapH(BioSpacing.sm),
                Text(
                  AppTexts.loginFirstTime,
                  style: BioTypography.body.copyWith(color: tokens.textMuted),
                  textAlign: TextAlign.center,
                ),
                BioSpacing.gapH(BioSpacing.xxl),
                BioButton.primary(
                  label: _submitting ? 'Ingresando…' : 'Ingresar con identidad',
                  icon: Icons.fingerprint,
                  size: BioButtonSize.lg,
                  fullWidth: true,
                  loading: _submitting,
                  onPressed: _submitting ? null : _loginExisting,
                ),
                BioSpacing.gapH(BioSpacing.md),
                BioButton.softPrimary(
                  label: 'Crear cuenta',
                  icon: Icons.person_add_alt_outlined,
                  fullWidth: true,
                  onPressed: _submitting
                      ? null
                      : () {
                          Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) => const StaffSignupScreen(),
                            ),
                          );
                        },
                ),
                BioSpacing.gapH(BioSpacing.lg),
                const PrivacyPolicyLink(),
                if (_demoAvailable) ...[
                  BioSpacing.gapH(BioSpacing.md),
                  _buildProbarDemoButton(),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildProbarDemoButton() {
    return BioButton(
      label: _submitting ? 'Abriendo demo…' : 'Probar demo',
      icon: Icons.science_outlined,
      intent: UiIntent.neutral,
      variant: BioButtonVariant.soft,
      fullWidth: true,
      loading: _submitting,
      onPressed: _submitting ? null : _onProbarDemo,
    );
  }
}
