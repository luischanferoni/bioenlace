import 'package:flutter/material.dart';
import 'package:shared/shared.dart';
import 'package:url_launcher/url_launcher.dart';

import '../auth/personalsalud_post_login.dart';
import 'commercial_signup_screen.dart';
import '../services/staff_signup_service.dart';

enum _SignupPath { professional, privateConsultorio, clinic }

/// Alta de cuenta profesional personal. Las altas comerciales requieren checkout.
class StaffSignupScreen extends StatefulWidget {
  const StaffSignupScreen({super.key});

  @override
  State<StaffSignupScreen> createState() => _StaffSignupScreenState();
}

class _StaffSignupScreenState extends State<StaffSignupScreen> {
  bool _submitting = false;
  bool _accountCreated = false;
  _SignupPath _selectedPath = _SignupPath.professional;
  String? _pendingVerificationId;

  void _snack(String msg) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
  }

  Future<void> _openPricingPage() async {
    final opened = await launchUrl(
      Uri.parse('https://bioenlace.io/precios.html'),
      mode: LaunchMode.externalApplication,
    );
    if (!opened) {
      _snack('No pudimos abrir el simulador de precios. Intentá nuevamente.');
    }
  }

  Future<void> _verifyIdentity() async {
    final selectedPath = _selectedPath;
    setState(() => _submitting = true);
    try {
      final service = StaffSignupService();
      final verificationId =
          _pendingVerificationId ?? await service.verifyIdentity();
      _pendingVerificationId = verificationId;
      final account = await service.createAccount(
        verificationId: verificationId,
      );
      if (!mounted) return;
      setState(() {
        _accountCreated = true;
        _submitting = false;
        _pendingVerificationId = null;
      });

      final token = account['token']?.toString().trim() ?? '';
      final userId = (account['id_user'] ?? account['id_persona'] ?? '')
          .toString()
          .trim();
      if (token.isEmpty || userId.isEmpty) return;

      await AuthTokenStore.instance.write(token);
      if (!mounted) return;
      if (selectedPath != _SignupPath.professional) {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (_) => CommercialSignupScreen(
              authToken: token,
              profile: selectedPath == _SignupPath.privateConsultorio
                  ? 'CONSULTORIO'
                  : 'CLINICA',
            ),
          ),
        );
        return;
      }
      await navigatePersonalsaludAfterLogin(
        userId,
        'Personal de Salud',
        context,
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

  Widget _buildPathCard({
    required _SignupPath path,
    required String title,
    required String description,
    bool enabled = true,
  }) {
    final tokens = context.bio;
    final primary = Theme.of(context).colorScheme.primary;
    final selected = _selectedPath == path;
    return Container(
      margin: const EdgeInsets.only(bottom: BioSpacing.md),
      decoration: BoxDecoration(
        color: tokens.paperSurface,
        border: Border.all(
          color: selected ? primary : tokens.paperDividerDefault,
          width: selected ? 2 : 1,
        ),
        borderRadius: BorderRadius.circular(12),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: enabled ? () => setState(() => _selectedPath = path) : null,
        child: Padding(
          padding: const EdgeInsets.all(BioSpacing.md),
          child: Row(
            children: [
              Icon(
                selected ? Icons.radio_button_checked : Icons.radio_button_off,
                color: enabled ? primary : tokens.textMuted,
              ),
              BioSpacing.gapW(BioSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: BioTypography.title),
                    BioSpacing.gapH(BioSpacing.xs),
                    Text(
                      description,
                      style: BioTypography.bodySm.copyWith(
                        color: tokens.textMuted,
                      ),
                    ),
                  ],
                ),
              ),
              if (!enabled) ...[
                BioSpacing.gapW(BioSpacing.sm),
                const Icon(Icons.schedule, size: 18),
              ],
            ],
          ),
        ),
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
                _selectedPath == _SignupPath.professional
                    ? 'Creá tu cuenta de Personal de Salud'
                    : 'Información antes de crear tu cuenta',
                style: BioTypography.h2,
                textAlign: TextAlign.center,
              ),
              BioSpacing.gapH(BioSpacing.sm),
              Text(
                _selectedPath == _SignupPath.professional
                    ? 'Elegí qué tipo de cuenta necesitás. No necesitás un e-mail para crear una cuenta profesional.'
                    : 'La verificación con DNI y selfie es solo para confirmar tu identidad. No inicia una suscripción ni genera un cobro.',
                style: BioTypography.body.copyWith(color: tokens.textMuted),
                textAlign: TextAlign.center,
              ),
              BioSpacing.gapH(BioSpacing.xl),
              _buildPathCard(
                path: _SignupPath.professional,
                title: 'Cuenta profesional',
                description:
                    'Crear tu cuenta es gratis. Si trabajás en una institución, su administración te asigna el acceso.',
              ),
              _buildPathCard(
                path: _SignupPath.privateConsultorio,
                title: 'Consultorio propio',
                description:
                    'Suscripción mensual para un consultorio privado y un profesional. Incluye hasta 200 atenciones ambulatorias por mes.',
              ),
              _buildPathCard(
                path: _SignupPath.clinic,
                title: 'Clínica o centro',
                description:
                    'Suscripción mensual según los tipos de atención y el volumen que elijas. Para centros públicos se solicita el código SISA.',
              ),
              BioSpacing.gapH(BioSpacing.md),
              _buildSignupDetails(tokens),
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
              if (!_accountCreated) ...[
                BioButton.primary(
                  label: _submitting
                      ? 'Verificando…'
                      : _pendingVerificationId != null
                      ? 'Reintentar creación de cuenta'
                      : _selectedPath == _SignupPath.professional
                      ? 'Verificar identidad y crear cuenta'
                      : 'Continuar a verificación con Didit',
                  icon: Icons.verified_user_outlined,
                  size: BioButtonSize.lg,
                  fullWidth: true,
                  loading: _submitting,
                  onPressed: _submitting ? null : _verifyIdentity,
                ),
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
                    'Tu cuenta está creada. Si trabajás en un centro, su '
                    'AdminEfector debe sumarte para que puedas acceder a las '
                    'funciones clínicas. Crear la cuenta no te asigna a un centro. '
                    'Para volver a entrar, verificá tu identidad desde el inicio.',
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

  Widget _buildSignupDetails(BioTokens tokens) {
    final isProfessional = _selectedPath == _SignupPath.professional;
    final isConsultorio = _selectedPath == _SignupPath.privateConsultorio;
    return Container(
      padding: const EdgeInsets.all(BioSpacing.md),
      decoration: BoxDecoration(
        color: tokens.paperSurface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: tokens.paperDividerDefault),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            isProfessional
                ? 'Qué necesitás para crear tu cuenta'
                : 'Cómo funciona el alta y el precio',
            style: BioTypography.title,
          ),
          BioSpacing.gapH(BioSpacing.sm),
          Text(
            isProfessional
                ? 'Didit te pedirá fotografiar tu DNI físico y tomarte una selfie. La cuenta profesional no tiene cargo. Crear la cuenta no te asigna automáticamente a un centro.'
                : isConsultorio
                ? 'El precio estimado para un consultorio de un profesional y 200 atenciones ambulatorias es USD 4,26 por mes, antes de IVA. La suscripción tiene renovación mensual y se cobra en ARS según la cotización MEP vendedora vigente, más IVA.'
                : 'El precio mensual depende de las atenciones ambulatorias, de urgencia o de internación y de los volúmenes que elijas. La suscripción tiene renovación mensual. El simulador muestra valores de referencia en USD, sin IVA.',
            style: BioTypography.body,
          ),
          if (!isProfessional) ...[
            BioSpacing.gapH(BioSpacing.sm),
            Text(
              'Después de verificar tu identidad completarás los datos del efector. Antes de abrir Mercado Pago verás el importe exacto en ARS, el IVA, la cotización utilizada y las condiciones de renovación. Didit no procesa pagos; el efector y su licencia se activan al confirmarse el primer pago.',
              style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
            ),
            BioSpacing.gapH(BioSpacing.sm),
            TextButton.icon(
              onPressed: _openPricingPage,
              icon: const Icon(Icons.open_in_new),
              label: const Text('Ver simulador y detalle de precios'),
            ),
          ],
        ],
      ),
    );
  }
}
