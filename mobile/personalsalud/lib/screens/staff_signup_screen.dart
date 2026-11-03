import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:shared/shared.dart';

import 'commercial_signup_screen.dart';
import '../services/staff_signup_service.dart';

enum _SignupPath { personalFree, privateConsultorio, clinic }

/// Alta personal gratuita o inicio de un alta paga.
class StaffSignupScreen extends StatefulWidget {
  const StaffSignupScreen({super.key});

  @override
  State<StaffSignupScreen> createState() => _StaffSignupScreenState();
}

class _StaffSignupScreenState extends State<StaffSignupScreen> {
  final StaffSignupService _signupService = StaffSignupService();
  bool _submitting = false;
  bool _accountCreated = false;
  bool _loadingPlans = true;
  bool _loadingConsultorioQuote = false;
  bool _loadingClinicQuote = false;
  List<int> _attentionPresets = const [];
  final Map<String, int> _clinicAttentions = {'AMB': 5000};
  Map<String, dynamic>? _consultorioQuote;
  Map<String, dynamic>? _clinicQuote;
  String? _consultorioQuoteError;
  String? _clinicQuoteError;
  Timer? _clinicQuoteDebounce;
  int _clinicQuoteRequestId = 0;
  String? _pendingVerificationId;

  @override
  void initState() {
    super.initState();
    _loadPlansAndQuotes();
  }

  @override
  void dispose() {
    _clinicQuoteDebounce?.cancel();
    super.dispose();
  }

  void _snack(String msg) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
  }

  Future<void> _loadPlansAndQuotes() async {
    try {
      final catalog = await _signupService.fetchSignupPlans();
      final rawPresets = catalog['attention_volume_presets'];
      if (rawPresets is! List) {
        throw const FormatException('El catálogo de planes no es válido.');
      }
      final presets =
          rawPresets
              .whereType<Map>()
              .map((item) => int.tryParse(item['attentions']?.toString() ?? ''))
              .whereType<int>()
              .where((value) => value > 0)
              .toSet()
              .toList()
            ..sort();
      if (presets.isEmpty) {
        throw const FormatException(
          'El catálogo no tiene volúmenes disponibles.',
        );
      }
      if (!mounted) return;
      setState(() {
        _attentionPresets = presets;
        if (!presets.contains(_clinicAttentions['AMB'])) {
          _clinicAttentions['AMB'] = presets.first;
        }
        _loadingPlans = false;
      });
      await Future.wait([_refreshConsultorioQuote(), _refreshClinicQuote()]);
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loadingPlans = false;
        _consultorioQuoteError = _priceErrorText(error);
        _clinicQuoteError = _priceErrorText(error);
      });
    }
  }

  String _priceErrorText(Object error) {
    final message = error.toString().replaceFirst('Exception: ', '').trim();
    return message.isEmpty
        ? 'No pudimos consultar el precio. Intentá nuevamente.'
        : message;
  }

  Map<String, dynamic> _consultorioPlan() => {
    'classes': {
      'AMB': {
        'attentions_per_month': 200,
        'max_pes': 1,
        'videollamada_permitida': false,
      },
    },
  };

  Map<String, dynamic>? _clinicPlan() {
    if (_clinicAttentions.isEmpty) return null;
    return {
      'classes': _clinicAttentions.map(
        (code, attentions) => MapEntry(code, {
          'attentions_per_month': attentions,
          'max_pes': (attentions / 200).ceil(),
        }),
      ),
    };
  }

  Future<void> _refreshConsultorioQuote() async {
    setState(() {
      _loadingConsultorioQuote = true;
      _consultorioQuoteError = null;
    });
    try {
      final quote = await _signupService.quoteSignupPlan(
        profile: 'CONSULTORIO',
        plan: _consultorioPlan(),
      );
      if (!mounted) return;
      setState(() {
        _consultorioQuote = quote;
        _loadingConsultorioQuote = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _consultorioQuoteError = _priceErrorText(error);
        _loadingConsultorioQuote = false;
      });
    }
  }

  Future<void> _refreshClinicQuote() async {
    final requestId = ++_clinicQuoteRequestId;
    final plan = _clinicPlan();
    if (plan == null) {
      setState(() {
        _clinicQuote = null;
        _clinicQuoteError = 'Elegí al menos un tipo de atención.';
        _loadingClinicQuote = false;
      });
      return;
    }
    setState(() {
      _loadingClinicQuote = true;
      _clinicQuoteError = null;
    });
    try {
      final quote = await _signupService.quoteSignupPlan(
        profile: 'CLINICA',
        plan: plan,
      );
      if (!mounted || requestId != _clinicQuoteRequestId) return;
      setState(() {
        _clinicQuote = quote;
        _loadingClinicQuote = false;
      });
    } catch (error) {
      if (!mounted || requestId != _clinicQuoteRequestId) return;
      setState(() {
        _clinicQuoteError = _priceErrorText(error);
        _loadingClinicQuote = false;
      });
    }
  }

  void _scheduleClinicQuote() {
    _clinicQuoteDebounce?.cancel();
    _clinicQuoteDebounce = Timer(
      const Duration(milliseconds: 350),
      _refreshClinicQuote,
    );
  }

  Future<void> _verifyIdentity(
    _SignupPath selectedPath, {
    Map<String, dynamic>? plan,
  }) async {
    setState(() => _submitting = true);
    try {
      final verificationId =
          _pendingVerificationId ?? await _signupService.verifyIdentity();
      _pendingVerificationId = verificationId;
      final account = await _signupService.createAccount(
        verificationId: verificationId,
      );
      if (!mounted) return;
      setState(() {
        _accountCreated = true;
        _submitting = false;
        _pendingVerificationId = null;
      });

      if (selectedPath == _SignupPath.personalFree) return;

      final token = account['token']?.toString().trim() ?? '';
      final userId = (account['id_user'] ?? account['id_persona'] ?? '')
          .toString()
          .trim();
      if (token.isEmpty || userId.isEmpty) return;

      await AuthTokenStore.instance.write(token);
      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => CommercialSignupScreen(
            authToken: token,
            profile: selectedPath == _SignupPath.privateConsultorio
                ? 'CONSULTORIO'
                : 'CLINICA',
            initialPlan: plan,
          ),
        ),
      );
    } catch (error) {
      final message = error.toString().replaceFirst('Exception: ', '');
      if (_accountCreated) {
        _snack(
          'Tu cuenta está creada, pero no pudimos iniciar la sesión. '
          'Volvé al inicio e ingresá verificando tu identidad.',
        );
        return;
      }
      _snack(
        message == 'Verificación cancelada.'
            ? 'Cancelaste la verificación.'
            : _pendingVerificationId != null
            ? 'Tu identidad ya fue verificada, pero no pudimos crear la cuenta. '
                  'Reintentá sin volver a verificarte.'
            : 'No pudimos continuar con la creación de tu cuenta. Intentá nuevamente. '
                  'Si el problema continúa, contactá a soporte.',
      );
      if (mounted) setState(() => _submitting = false);
    }
  }

  String _formatArs(Object? amount) {
    final value = double.tryParse(amount?.toString() ?? '');
    if (value == null) return '—';
    return NumberFormat.currency(
      locale: 'es_AR',
      symbol: 'ARS ',
      decimalDigits: 2,
    ).format(value);
  }

  Widget _buildQuoteSummary({
    required Map<String, dynamic>? quote,
    required bool loading,
    required String? error,
    required VoidCallback onRetry,
  }) {
    final tokens = context.bio;
    return Container(
      margin: const EdgeInsets.only(top: BioSpacing.sm),
      padding: const EdgeInsets.all(BioSpacing.md),
      decoration: BoxDecoration(
        color: tokens.paperBackground,
        borderRadius: BorderRadius.circular(10),
      ),
      child: loading
          ? const Center(child: CircularProgressIndicator())
          : error != null
          ? Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(error, style: BioTypography.bodySm),
                Align(
                  alignment: Alignment.centerRight,
                  child: TextButton(
                    onPressed: onRetry,
                    child: const Text('Reintentar'),
                  ),
                ),
              ],
            )
          : quote == null
          ? const Text('No hay una cotización disponible.')
          : Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${_formatArs(quote['amount_total_ars'])} por mes',
                  style: BioTypography.title,
                ),
                BioSpacing.gapH(BioSpacing.xs),
                Text(
                  'Total mensual con impuestos incluidos. ',
                  style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
                ),
              ],
            ),
    );
  }

  Widget _buildClassSelector(String code, String label) {
    final selected = _clinicAttentions.containsKey(code);
    final tokens = context.bio;
    return Padding(
      padding: const EdgeInsets.only(top: BioSpacing.sm),
      child: Column(
        children: [
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(label, style: BioTypography.body),
            value: selected,
            onChanged: _loadingPlans
                ? null
                : (enabled) {
                    setState(() {
                      if (enabled == true) {
                        _clinicAttentions[code] = _attentionPresets.isNotEmpty
                            ? _attentionPresets.first
                            : 200;
                      } else {
                        _clinicAttentions.remove(code);
                      }
                      _clinicQuote = null;
                    });
                    _scheduleClinicQuote();
                  },
          ),
          if (selected)
            Padding(
              padding: const EdgeInsets.only(left: BioSpacing.xl),
              child: DropdownButtonFormField<int>(
                initialValue: _clinicAttentions[code],
                decoration: const InputDecoration(
                  labelText: 'Atenciones por mes',
                  border: OutlineInputBorder(),
                ),
                items: _attentionPresets
                    .map(
                      (value) => DropdownMenuItem(
                        value: value,
                        child: Text(
                          '${NumberFormat.decimalPattern('es_AR').format(value)} '
                          'atenciones / mes',
                        ),
                      ),
                    )
                    .toList(),
                onChanged: _loadingPlans
                    ? null
                    : (value) {
                        if (value == null) return;
                        setState(() {
                          _clinicAttentions[code] = value;
                          _clinicQuote = null;
                        });
                        _scheduleClinicQuote();
                      },
              ),
            ),
          if (selected)
            Padding(
              padding: const EdgeInsets.only(top: BioSpacing.xs),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  code == 'AMB'
                      ? 'Consultas ambulatorias'
                      : code == 'EMER'
                      ? 'Atenciones de urgencia o guardia'
                      : 'Atenciones de internación',
                  style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
                ),
              ),
            ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;

    return Scaffold(
      backgroundColor: tokens.paperBackground,
      appBar: AppBar(title: const Text('Crear cuenta')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: BioSpacing.pageAll,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Elegí cómo usar Bioenlace',
                style: BioTypography.h2,
                textAlign: TextAlign.center,
              ),
              BioSpacing.gapH(BioSpacing.sm),
              BioSpacing.gapH(BioSpacing.xl),
              if (!_accountCreated) ...[
                Container(
                  decoration: BoxDecoration(
                    color: tokens.paperSurface,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: tokens.paperDividerDefault),
                  ),
                  child: ExpansionTile(
                    initiallyExpanded: true,
                    title: Text(
                      'Cuenta personal gratis',
                      style: BioTypography.title,
                    ),
                    subtitle: const Text('Gratis'),
                    childrenPadding: const EdgeInsets.fromLTRB(
                      BioSpacing.md,
                      0,
                      BioSpacing.md,
                      BioSpacing.md,
                    ),
                    children: [
                      Text(
                        'Crea una cuenta con perfil de paciente. Para ingresar '
                        'a Personal de Salud como profesional, un efector debe '
                        'asignarte un rol clínico.',
                        style: BioTypography.body,
                      ),
                      BioSpacing.gapH(BioSpacing.md),
                      BioButton.primary(
                        label: _signupButtonLabel,
                        icon: Icons.verified_user_outlined,
                        size: BioButtonSize.lg,
                        fullWidth: true,
                        loading: _submitting,
                        onPressed: _submitting
                            ? null
                            : () => _verifyIdentity(_SignupPath.personalFree),
                      ),
                    ],
                  ),
                ),
                BioSpacing.gapH(BioSpacing.sm),
                Container(
                  decoration: BoxDecoration(
                    color: tokens.paperSurface,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: tokens.paperDividerDefault),
                  ),
                  child: ExpansionTile(
                    title: Text(
                      'Consultorio propio',
                      style: BioTypography.title,
                    ),
                    subtitle: const Text(
                      '1 profesional · 200 atenciones por mes',
                    ),
                    childrenPadding: const EdgeInsets.fromLTRB(
                      BioSpacing.md,
                      0,
                      BioSpacing.md,
                      BioSpacing.md,
                    ),
                    children: [
                      const Text(
                        'Un espacio privado para atender todos tus pacientes. Incluye '
                        'todas las funcionalidades.',
                      ),
                      _buildQuoteSummary(
                        quote: _consultorioQuote,
                        loading: _loadingPlans || _loadingConsultorioQuote,
                        error: _consultorioQuoteError,
                        onRetry: _refreshConsultorioQuote,
                      ),
                      BioSpacing.gapH(BioSpacing.md),
                      BioButton.primary(
                        label: _signupButtonLabel,
                        icon: Icons.arrow_forward,
                        size: BioButtonSize.lg,
                        fullWidth: true,
                        loading: _submitting,
                        onPressed:
                            _submitting ||
                                _loadingPlans ||
                                _loadingConsultorioQuote ||
                                _consultorioQuote == null
                            ? null
                            : () => _verifyIdentity(
                                _SignupPath.privateConsultorio,
                                plan: _consultorioPlan(),
                              ),
                      ),
                    ],
                  ),
                ),
                BioSpacing.gapH(BioSpacing.sm),
                Container(
                  decoration: BoxDecoration(
                    color: tokens.paperSurface,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: tokens.paperDividerDefault),
                  ),
                  child: ExpansionTile(
                    title: Text('Clínica o centro', style: BioTypography.title),
                    subtitle: const Text('Elegí servicios y volumen mensual'),
                    childrenPadding: const EdgeInsets.fromLTRB(
                      BioSpacing.md,
                      0,
                      BioSpacing.md,
                      BioSpacing.md,
                    ),
                    children: [
                      const Text(
                        'Configurá los tipos de atención que brindás y el '
                        'volumen mensual aproximado.',
                      ),
                      _buildClassSelector('AMB', 'Ambulatorio'),
                      _buildClassSelector('EMER', 'Urgencia o guardia'),
                      _buildClassSelector('IMP', 'Internación'),
                      _buildQuoteSummary(
                        quote: _clinicQuote,
                        loading: _loadingPlans || _loadingClinicQuote,
                        error: _clinicQuoteError,
                        onRetry: _refreshClinicQuote,
                      ),
                      BioSpacing.gapH(BioSpacing.md),
                      BioButton.primary(
                        label: _signupButtonLabel,
                        icon: Icons.arrow_forward,
                        size: BioButtonSize.lg,
                        fullWidth: true,
                        loading: _submitting,
                        onPressed:
                            _submitting ||
                                _loadingPlans ||
                                _loadingClinicQuote ||
                                _clinicQuote == null ||
                                _clinicAttentions.isEmpty
                            ? null
                            : () => _verifyIdentity(
                                _SignupPath.clinic,
                                plan: _clinicPlan(),
                              ),
                      ),
                    ],
                  ),
                ),
              ],
              BioSpacing.gapH(BioSpacing.md),
              if (_pendingVerificationId != null) ...[
                Text(
                  'Tu identidad ya está verificada. Podés reintentar la creación '
                  'de la cuenta sin volver a hacer este paso.',
                  style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
                  textAlign: TextAlign.center,
                ),
                BioSpacing.gapH(BioSpacing.md),
              ],
              if (_accountCreated) ...[
                Container(
                  padding: const EdgeInsets.all(BioSpacing.md),
                  decoration: BoxDecoration(
                    color: tokens.paperSurface,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: tokens.paperDividerDefault),
                  ),
                  child: Text(
                    'Tu cuenta personal gratuita está lista. Para usar Personal '
                    'de Salud como profesional, pedí al administrador de tu '
                    'efector que te asigne un rol clínico. '
                    '¿Querías crear un espacio de atención pago? Volvé al inicio, '
                    'elegí Crear cuenta y seleccioná una opción paga. Tendrás que '
                    'verificar tu identidad otra vez; reconoceremos tu cuenta '
                    'existente y no crearemos otra.',
                    style: BioTypography.body,
                    textAlign: TextAlign.center,
                  ),
                ),
                BioSpacing.gapH(BioSpacing.md),
                OutlinedButton(
                  onPressed: () => Navigator.of(context).pop(),
                  child: const Text('Volver al inicio'),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  String get _signupButtonLabel => _submitting
      ? 'Verificando…'
      : _pendingVerificationId != null
      ? 'Reintentar creación de cuenta'
      : 'Continuar';
}
