import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:shared/shared.dart';

import 'commercial_signup_screen.dart';
import '../services/staff_signup_service.dart';

enum _SignupPath { personalFree, attentionSpace }

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
  bool _loadingQuote = true;
  Map<String, dynamic>? _spaceQuote;
  String? _spaceQuoteError;
  String? _pendingVerificationId;

  @override
  void initState() {
    super.initState();
    _refreshSpaceQuote();
  }

  void _snack(String msg) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
  }

  String _priceErrorText(Object error) {
    final message = error.toString().replaceFirst('Exception: ', '').trim();
    return message.isEmpty
        ? 'No pudimos consultar el precio. Intentá nuevamente.'
        : message;
  }

  Map<String, dynamic> _initialSpacePlan() => {
    'classes': {
      'AMB': {
        'attentions_per_month': 200,
      },
    },
  };

  Future<void> _refreshSpaceQuote() async {
    setState(() {
      _loadingQuote = true;
      _spaceQuoteError = null;
    });
    try {
      final quote = await _signupService.quoteSignupPlan(
        plan: _initialSpacePlan(),
      );
      if (!mounted) return;
      setState(() {
        _spaceQuote = quote;
        _loadingQuote = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _spaceQuoteError = _priceErrorText(error);
        _loadingQuote = false;
      });
    }
  }

  Future<void> _verifyIdentity(
    _SignupPath selectedPath, {
    Map<String, dynamic>? plan,
  }) async {
    var identityCreated = false;
    setState(() => _submitting = true);
    try {
      final verificationId =
          _pendingVerificationId ?? await _signupService.verifyIdentity();
      _pendingVerificationId = verificationId;
      final account = await _signupService.createAccount(
        verificationId: verificationId,
      );
      identityCreated = true;
      if (selectedPath == _SignupPath.personalFree) {
        if (!mounted) return;
        setState(() {
          _accountCreated = true;
          _submitting = false;
          _pendingVerificationId = null;
        });
        return;
      }

      final token = account['token']?.toString().trim() ?? '';
      final userId = (account['id_user'] ?? account['id_persona'] ?? '')
          .toString()
          .trim();
      if (token.isEmpty || userId.isEmpty) {
        throw StateError(
          'La cuenta existe, pero la API no devolvió los datos necesarios para continuar.',
        );
      }

      await AuthTokenStore.instance.write(token);
      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => CommercialSignupScreen(
            authToken: token,
            initialPlan: plan,
          ),
        ),
      );
    } catch (error) {
      final message = error.toString().replaceFirst('Exception: ', '');
      if (identityCreated) {
        _snack(
          'Tu cuenta ya existe, pero no pudimos iniciar el alta del espacio. '
          'Volvé a Crear cuenta y elegí la opción paga; verificaremos tu '
          'identidad otra vez y reutilizaremos esta cuenta.',
        );
        if (mounted) setState(() => _submitting = false);
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
                        label: _submitting
                            ? 'Verificando…'
                            : _pendingVerificationId != null
                            ? 'Reintentar creación de cuenta'
                            : 'Crear cuenta personal gratis',
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
                      'Crear espacio de atención',
                      style: BioTypography.title,
                    ),
                    subtitle: const Text('Suscripción mensual'),
                    childrenPadding: const EdgeInsets.fromLTRB(
                      BioSpacing.md,
                      0,
                      BioSpacing.md,
                      BioSpacing.md,
                    ),
                    children: [
                      Text(
                        'Configurá un espacio público o privado, elegí los '
                        'tipos de atención y estimá su volumen mensual. '
                        'Como referencia, 200 atenciones ambulatorias por mes '
                        'equivalen aproximadamente a la carga de un profesional; '
                        'no limitan cuántas personas podés asignar.',
                        style: BioTypography.body,
                      ),
                      _buildQuoteSummary(
                        quote: _spaceQuote,
                        loading: _loadingQuote,
                        error: _spaceQuoteError,
                        onRetry: _refreshSpaceQuote,
                      ),
                      BioSpacing.gapH(BioSpacing.md),
                      BioButton.primary(
                        label: _submitting
                            ? 'Verificando…'
                            : _pendingVerificationId != null
                            ? 'Reintentar creación de cuenta'
                            : 'Crear espacio de atención',
                        icon: Icons.arrow_forward,
                        size: BioButtonSize.lg,
                        fullWidth: true,
                        loading: _submitting,
                        onPressed:
                            _submitting || _loadingQuote || _spaceQuote == null
                            ? null
                            : () => _verifyIdentity(
                                _SignupPath.attentionSpace,
                                plan: _initialSpacePlan(),
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

}
