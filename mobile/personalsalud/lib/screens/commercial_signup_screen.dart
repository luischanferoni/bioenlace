import 'dart:async';
import 'dart:math';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:shared/shared.dart';
import 'package:url_launcher/url_launcher.dart';

import '../services/staff_signup_service.dart';

class CommercialSignupScreen extends StatefulWidget {
  const CommercialSignupScreen({this.initialPlan, super.key});

  final Map<String, dynamic>? initialPlan;

  @override
  State<CommercialSignupScreen> createState() => _CommercialSignupScreenState();
}

class _CommercialSignupScreenState extends State<CommercialSignupScreen>
    with WidgetsBindingObserver {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _addressController = TextEditingController();
  final _sisaSearchController = TextEditingController();
  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();
  final _localityQueryController = TextEditingController();
  final _ambController = TextEditingController();
  final _emerController = TextEditingController();
  final _impController = TextEditingController();
  final _service = StaffSignupService();

  bool _submitting = false;
  bool _searchingLocalities = false;
  bool _searchingSisaEfectores = false;
  bool _loadingMinistries = false;
  bool _termsAccepted = false;
  bool _checkoutOpened = false;
  bool _cancellingSubscription = false;
  bool _videollamada = false;
  bool _loadingQuote = false;
  bool _ambEnabled = true;
  bool _emerEnabled = false;
  bool _impEnabled = false;
  int _currentStep = 0;
  int _sisaSearchRequestId = 0;
  String _sector = 'PRIVADO';
  String? _errorMessage;
  String? _verificationId;
  String? _checkoutKey;
  String? _checkoutRequestKey;
  String? _checkoutUrl;
  String? _checkoutStatus;
  String? _quoteError;
  String? _providerSubscriptionStatus;
  String? _paidThroughAt;
  Map<String, dynamic>? _pricing;
  Map<String, dynamic>? _quote;
  Map<String, dynamic>? _selectedLocality;
  Map<String, dynamic>? _selectedSisaEfector;
  List<Map<String, dynamic>> _localities = const [];
  List<Map<String, dynamic>> _sisaEfectores = const [];
  List<Map<String, dynamic>> _ministries = const [];
  int? _selectedMinistryId;
  Timer? _quoteDebounce;

  /// La búsqueda de efectores SISA exige más de 3 caracteres.
  static const int _minEfectorSearchLength = 4;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    final classes = widget.initialPlan?['classes'];
    if (classes is Map) {
      _ambController.text =
          classes['AMB']?['attentions_per_month']?.toString() ?? '';
      _emerController.text =
          classes['EMER']?['attentions_per_month']?.toString() ?? '';
      _impController.text =
          classes['IMP']?['attentions_per_month']?.toString() ?? '';
      if (classes.isNotEmpty) {
        _ambEnabled = classes.containsKey('AMB');
        _emerEnabled = classes.containsKey('EMER');
        _impEnabled = classes.containsKey('IMP');
        _videollamada = classes['AMB']?['videollamada_permitida'] == true;
      }
    }
    _loadMinistries();
    WidgetsBinding.instance.addPostFrameCallback((_) => _refreshQuote());
  }

  @override
  void dispose() {
    _quoteDebounce?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    _nameController.dispose();
    _addressController.dispose();
    _sisaSearchController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _localityQueryController.dispose();
    _ambController.dispose();
    _emerController.dispose();
    _impController.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && _checkoutKey != null) {
      _refreshCheckoutStatus();
    }
  }

  Future<void> _continueSignup() async {
    if (_currentStep == 0) {
      setState(() {
        _currentStep = 1;
        _errorMessage = null;
      });
      return;
    }

    if (_currentStep == 1) {
      if (!_formKey.currentState!.validate()) return;
      if (_sector == 'PUBLICO' && _selectedMinistryId == null) {
        setState(
          () => _errorMessage =
              'Seleccioná el ministerio al que pertenece el efector.',
        );
        return;
      }
      if (_sector == 'PUBLICO' && _selectedSisaEfector == null) {
        setState(() => _errorMessage = 'Buscá y seleccioná un efector.');
        return;
      }
      setState(() {
        _currentStep = 2;
        _errorMessage = null;
      });
      _refreshQuote();
      return;
    }

    await _startCheckout();
  }

  void _scheduleQuoteRefresh() {
    _quoteDebounce?.cancel();
    _quoteDebounce = Timer(const Duration(milliseconds: 400), _refreshQuote);
  }

  Future<void> _refreshQuote() async {
    final plan = _selectedPlan();
    if (plan == null) {
      if (!mounted) return;
      setState(() {
        _quote = null;
        _quoteError = null;
        _loadingQuote = false;
      });
      return;
    }

    setState(() {
      _loadingQuote = true;
      _quoteError = null;
    });
    try {
      final quote = await _service.quoteSignupPlan(plan: plan);
      if (!mounted) return;
      setState(() {
        _quote = quote;
        _loadingQuote = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _quote = null;
        _quoteError = _errorText(error);
        _loadingQuote = false;
      });
    }
  }

  Map<String, dynamic> _draftPayload({required int currentStep}) {
    final plan = _selectedPlan();
    final efector = _sector == 'PUBLICO'
        ? _selectedSisaEfector
        : {
            'nombre': _nameController.text.trim(),
            'domicilio': _addressController.text.trim(),
            if (_selectedLocality != null) ...{
              'id_localidad': _selectedLocality!['id'],
              'localidad': _selectedLocality!['nombre'],
              'departamento': _selectedLocality!['departamento'],
              'provincia': _selectedLocality!['provincia'],
            },
            'telefono': _phoneController.text.trim(),
          };
    return {
      'sector': _sector,
      'efector': efector,
      'id_billing_account_ministerio': _selectedMinistryId,
      'plan': plan ?? {'classes': <String, dynamic>{}},
      'payer_email': _emailController.text.trim(),
      'contact_phone': _phoneController.text.trim(),
      'current_step': currentStep,
      'checkout_request_key': _checkoutRequestKey,
    };
  }

  bool get _canSearchSisaEfectores =>
      _selectedMinistryId != null &&
      _sisaSearchController.text.trim().length >= _minEfectorSearchLength;

  Future<void> _loadMinistries() async {
    setState(() => _loadingMinistries = true);
    try {
      final items = await _service.fetchMinistries();
      if (!mounted) return;
      setState(() {
        _ministries = items;
        _loadingMinistries = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loadingMinistries = false;
        _errorMessage = _errorText(error);
      });
    }
  }

  Future<void> _searchLocalities() async {
    final query = _localityQueryController.text.trim();
    if (query.length < 2) {
      setState(() {
        _selectedLocality = null;
        _localities = const [];
        _errorMessage = 'Ingresá al menos dos letras para buscar la localidad.';
      });
      return;
    }

    setState(() {
      _searchingLocalities = true;
      _selectedLocality = null;
      _errorMessage = null;
    });
    try {
      final results = await _service.searchLocalities(query);
      if (!mounted) return;
      setState(() {
        _localities = results;
        _searchingLocalities = false;
        if (results.isEmpty) {
          _errorMessage = 'No encontramos localidades con ese nombre.';
        }
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _searchingLocalities = false;
        _errorMessage = _errorText(error);
      });
    }
  }

  Future<void> _searchSisaEfectores() async {
    if (_selectedMinistryId == null) {
      setState(
        () => _errorMessage =
            'Seleccioná el ministerio al que pertenece el efector.',
      );
      return;
    }
    final query = _sisaSearchController.text.trim();
    final requestId = ++_sisaSearchRequestId;
    if (query.length < _minEfectorSearchLength) {
      setState(() {
        _searchingSisaEfectores = false;
        _selectedSisaEfector = null;
        _sisaEfectores = const [];
        _errorMessage =
            'Ingresá al menos $_minEfectorSearchLength caracteres para buscar.';
      });
      return;
    }

    setState(() {
      _searchingSisaEfectores = true;
      _selectedSisaEfector = null;
      _sisaEfectores = const [];
      _errorMessage = null;
    });
    try {
      final results = await _service.searchPublicEffectors(query);
      if (!mounted ||
          requestId != _sisaSearchRequestId ||
          query != _sisaSearchController.text.trim()) {
        return;
      }
      setState(() {
        _sisaEfectores = results;
        _searchingSisaEfectores = false;
        if (results.isEmpty) {
          _errorMessage =
              'No encontramos efectores públicos disponibles con esos datos.';
        }
      });
    } catch (error) {
      if (!mounted || requestId != _sisaSearchRequestId) return;
      setState(() {
        _searchingSisaEfectores = false;
        _errorMessage = _errorText(error);
      });
    }
  }

  Future<void> _startCheckout() async {
    if (!_formKey.currentState!.validate()) return;
    if (_sector != 'PUBLICO' && _selectedLocality == null) {
      setState(
        () => _errorMessage = 'Buscá y seleccioná la localidad del efector.',
      );
      return;
    }
    if (_sector == 'PUBLICO' && _selectedMinistryId == null) {
      setState(
        () => _errorMessage =
            'Seleccioná el ministerio al que pertenece el efector.',
      );
      return;
    }
    if (_sector == 'PUBLICO' && _selectedSisaEfector == null) {
      setState(() => _errorMessage = 'Buscá y seleccioná un efector.');
      return;
    }
    final plan = _selectedPlan();
    if (plan == null) {
      setState(
        () => _errorMessage =
            'Indicá el volumen mensual de al menos una clase de atención.',
      );
      return;
    }

    setState(() {
      _submitting = true;
      _errorMessage = null;
    });
    try {
      var verificationId = _verificationId;
      if (verificationId == null) {
        verificationId = await _service.readPendingVerificationId();
        verificationId ??= await _service.verifyIdentity();
        await _service.storePendingVerificationId(verificationId);
        _verificationId = verificationId;
      }
      if (!mounted) return;
      setState(() => _submitting = true);

      final body = <String, dynamic>{
        'perfil': 'ESPACIO_ATENCION',
        ..._draftPayload(currentStep: 2),
        'checkout_request_key': _checkoutRequestKey ??=
            _newCheckoutRequestKey(),
        'plan': plan,
      };
      final checkout = await _service.startCommercialCheckout(
        verificationId: verificationId,
        body: body,
      );
      await _service.clearPendingVerificationId();
      final key = checkout['checkout_key']?.toString() ?? '';
      final url = checkout['checkout_url']?.toString() ?? '';
      final checkoutUri = Uri.tryParse(url);
      if (key.isEmpty || checkoutUri == null || checkoutUri.scheme != 'https') {
        throw StateError(
          'Mercado Pago no devolvió un enlace de checkout válido.',
        );
      }
      if (!mounted) return;
      setState(() {
        _checkoutKey = key;
        _checkoutUrl = url;
        _checkoutStatus = checkout['status']?.toString();
        _providerSubscriptionStatus = checkout['provider_subscription_status']
            ?.toString();
        _paidThroughAt = checkout['paid_through_at']?.toString();
        _pricing = checkout;
        _currentStep = 3;
        _submitting = false;
      });
    } catch (error) {
      if (error is ApiContractException && error.statusCode == 422) {
        _verificationId = null;
        try {
          await _service.clearPendingVerificationId();
        } catch (_) {}
      }
      if (!mounted) return;
      setState(() {
        _submitting = false;
        _errorMessage = _errorText(error);
      });
    }
  }

  Map<String, dynamic>? _selectedPlan() {
    final classes = <String, dynamic>{};
    for (final entry in [
      ('AMB', _ambEnabled, _ambController.text),
      ('EMER', _emerEnabled, _emerController.text),
      ('IMP', _impEnabled, _impController.text),
    ]) {
      if (!entry.$2) continue;
      final value = int.tryParse(entry.$3.trim()) ?? 0;
      if (value > 0) {
        classes[entry.$1] = {
          'attentions_per_month': value,
          'videollamada_permitida': entry.$1 == 'AMB' && _videollamada,
        };
      }
    }
    return classes.isEmpty ? null : {'classes': classes};
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

  Widget _buildQuoteCard() {
    final tokens = context.bio;
    return Container(
      margin: const EdgeInsets.only(top: BioSpacing.sm, bottom: BioSpacing.md),
      padding: const EdgeInsets.all(BioSpacing.md),
      decoration: BoxDecoration(
        color: tokens.paperSurface,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: tokens.paperDividerDefault),
      ),
      child: _loadingQuote
          ? const Center(child: CircularProgressIndicator())
          : _quoteError != null
          ? Text(_quoteError!, style: BioTypography.bodySm)
          : _quote == null
          ? Text(
              'Ingresá el volumen mensual para ver el precio estimado.',
              style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
            )
          : Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Precio estimado',
                  style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
                ),
                Text(
                  '${_formatArs(_quote!['amount_total_ars'])} por mes',
                  style: BioTypography.title,
                ),
                Text(
                  'Total mensual con impuestos incluidos.',
                  style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
                ),
              ],
            ),
    );
  }

  Future<void> _openCheckout() async {
    if (!_termsAccepted) {
      setState(
        () => _errorMessage =
            'Confirmá que aceptás la suscripción mensual antes de continuar.',
      );
      return;
    }
    final url = _checkoutUrl;
    if (url == null) return;
    final uri = Uri.parse(url);
    if (!await launchUrl(uri, mode: LaunchMode.externalApplication)) {
      if (!mounted) return;
      setState(
        () => _errorMessage = 'No pudimos abrir Mercado Pago. Reintentá.',
      );
      return;
    }
    if (mounted) setState(() => _checkoutOpened = true);
  }

  Future<void> _refreshCheckoutStatus() async {
    final key = _checkoutKey;
    final verificationId = _verificationId;
    if (key == null || verificationId == null) return;
    try {
      final status = await _service.checkoutStatus(
        verificationId: verificationId,
        checkoutKey: key,
      );
      if (!mounted) return;
      setState(() {
        _checkoutStatus = status['status']?.toString();
        _providerSubscriptionStatus = status['provider_subscription_status']
            ?.toString();
        _paidThroughAt = status['paid_through_at']?.toString();
        _checkoutUrl = status['checkout_url']?.toString();
        _pricing = status;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() => _errorMessage = _errorText(error));
    }
  }

  void _startRetryAfterFailedCheckout() {
    setState(() {
      _checkoutKey = null;
      _checkoutUrl = null;
      _checkoutStatus = null;
      _providerSubscriptionStatus = null;
      _paidThroughAt = null;
      _pricing = null;
      _checkoutOpened = false;
      _checkoutRequestKey = null;
      _currentStep = 2;
      _errorMessage = null;
    });
  }

  Future<void> _cancelSubscription() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Cancelar renovación'),
        content: const Text(
          'Se detendrán los próximos débitos. La licencia seguirá activa hasta el final del período ya pagado.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Volver'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Confirmar cancelación'),
          ),
        ],
      ),
    );
    if (confirmed != true || _checkoutKey == null) return;
    final verificationId = _verificationId;
    if (verificationId == null) return;

    setState(() {
      _cancellingSubscription = true;
      _errorMessage = null;
    });
    try {
      final status = await _service.cancelCommercialCheckout(
        verificationId: verificationId,
        checkoutKey: _checkoutKey!,
      );
      if (!mounted) return;
      setState(() {
        _checkoutStatus = status['status']?.toString();
        _providerSubscriptionStatus = status['provider_subscription_status']
            ?.toString();
        _paidThroughAt = status['paid_through_at']?.toString();
        _cancellingSubscription = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _cancellingSubscription = false;
        _errorMessage = _errorText(error);
      });
    }
  }

  String _errorText(Object error) => error
      .toString()
      .replaceFirst('Exception: ', '')
      .replaceFirst('StateError: ', '');

  String _newCheckoutRequestKey() {
    final random = Random.secure();
    return List.generate(
      24,
      (_) => random.nextInt(256).toRadixString(16).padLeft(2, '0'),
    ).join();
  }

  Widget _textField(
    TextEditingController controller,
    String label, {
    TextInputType? keyboardType,
    String? Function(String?)? validator,
    ValueChanged<String>? onChanged,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: BioSpacing.md),
      child: TextFormField(
        controller: controller,
        keyboardType: keyboardType,
        onChanged: onChanged,
        decoration: InputDecoration(
          labelText: label,
          border: const OutlineInputBorder(),
        ),
        validator:
            validator ??
            (value) => value == null || value.trim().isEmpty
                ? 'Este campo es obligatorio.'
                : null,
      ),
    );
  }

  Widget _buildClassSelector({
    required bool enabled,
    required String title,
    required String description,
    required TextEditingController controller,
    required ValueChanged<bool> onToggle,
  }) {
    final tokens = context.bio;
    return Padding(
      padding: const EdgeInsets.only(bottom: BioSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            value: enabled,
            onChanged: (value) {
              onToggle(value);
              _refreshQuote();
            },
            title: Text(title),
            subtitle: Text(
              description,
              style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
            ),
          ),
          if (enabled)
            Padding(
              padding: const EdgeInsets.only(left: BioSpacing.xl),
              child: TextFormField(
                controller: controller,
                keyboardType: TextInputType.number,
                onChanged: (_) => _scheduleQuoteRefresh(),
                decoration: const InputDecoration(
                  labelText: 'Atenciones por mes',
                  border: OutlineInputBorder(),
                ),
                validator: _validateRequiredVolume,
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildSignupProgress() {
    const steps = [
      'Tipo de espacio',
      'Datos del efector',
      'Atenciones y volumen',
      'Verificación y pago',
    ];
    final visibleStep = _checkoutKey == null ? _currentStep : 3;
    final tokens = context.bio;

    Widget row(int index) {
      final done = index < visibleStep;
      final active = index == visibleStep;
      final color = done
          ? tokens.textBody
          : active
          ? Theme.of(context).colorScheme.primary
          : tokens.textDisabled;
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 24,
              height: 24,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: done || active ? color : Colors.transparent,
                border: Border.all(color: color),
              ),
              child: done
                  ? Icon(Icons.check, size: 14, color: tokens.paperBackground)
                  : Text(
                      '${index + 1}',
                      style: BioTypography.bodySm.copyWith(
                        color: active
                            ? tokens.paperBackground
                            : tokens.textDisabled,
                      ),
                    ),
            ),
            BioSpacing.gapW(BioSpacing.sm),
            Expanded(
              child: Text(
                steps[index],
                style: BioTypography.bodySm.copyWith(
                  color: color,
                  fontWeight: active ? FontWeight.w600 : null,
                ),
              ),
            ),
          ],
        ),
      );
    }

    return Container(
      margin: const EdgeInsets.only(bottom: BioSpacing.lg),
      padding: const EdgeInsets.all(BioSpacing.md),
      decoration: BoxDecoration(
        color: tokens.paperSurface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: tokens.paperDividerDefault),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Paso ${visibleStep + 1} de ${steps.length}',
            style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
          ),
          BioSpacing.gapH(BioSpacing.sm),
          for (var i = 0; i < steps.length; i++) row(i),
        ],
      ),
    );
  }

  Widget _buildSectorStep() {
    final tokens = context.bio;

    Widget option(String value, String title, String description) {
      final selected = _sector == value;
      return Padding(
        padding: const EdgeInsets.only(bottom: BioSpacing.sm),
        child: Card(
          margin: EdgeInsets.zero,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
            side: BorderSide(
              color: selected
                  ? Theme.of(context).colorScheme.primary
                  : tokens.paperDividerDefault,
              width: selected ? 2 : 1,
            ),
          ),
          child: InkWell(
            borderRadius: BorderRadius.circular(12),
            onTap: () => setState(() {
              _sector = value;
              _selectedSisaEfector = null;
              _sisaEfectores = const [];
              _selectedMinistryId = null;
              _errorMessage = null;
            }),
            child: Padding(
              padding: const EdgeInsets.all(BioSpacing.md),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    selected
                        ? Icons.radio_button_checked
                        : Icons.radio_button_unchecked,
                    color: selected
                        ? Theme.of(context).colorScheme.primary
                        : tokens.textMuted,
                  ),
                  BioSpacing.gapW(BioSpacing.sm),
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
                ],
              ),
            ),
          ),
        ),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text('¿Qué tipo de espacio quieres crear?', style: BioTypography.title),
        BioSpacing.gapH(BioSpacing.md),
        option(
          'PRIVADO',
          'Privado',
          'Consultorio, clínica o centro privado. En el siguiente paso '
              'registrás nombre, domicilio y localidad.',
        ),
        option(
          'PUBLICO',
          'Público',
          'Efector del catálogo oficial SISA afiliado a un ministerio.',
        ),
      ],
    );
  }

  Widget _buildCheckoutStatus() {
    final tokens = context.bio;
    final status = _checkoutStatus ?? 'PENDING';
    final pricing = _pricing;
    final isSubscriptionCancelled = [
      'cancelled',
      'canceled',
    ].contains(_providerSubscriptionStatus?.toLowerCase());
    final paidThrough = _paidThroughAt == null
        ? null
        : DateTime.tryParse(_paidThroughAt!.replaceFirst(' ', 'T'));
    final paidThroughLabel = paidThrough == null
        ? null
        : '${paidThrough.day.toString().padLeft(2, '0')}/${paidThrough.month.toString().padLeft(2, '0')}/${paidThrough.year}';
    final message = status == 'ACTIVE' && isSubscriptionCancelled
        ? paidThroughLabel == null
              ? 'Renovación cancelada. La licencia sigue activa durante el período pagado y se suspenderá al vencer.'
              : 'Renovación cancelada. La licencia sigue activa hasta el $paidThroughLabel y luego se suspenderá si no renueva.'
        : switch (status) {
            'ACTIVE' =>
              'Pago confirmado. Tu efector y licencia ya están activos.',
            'CANCELLED' => 'La suscripción fue cancelada.',
            'EXPIRED' =>
              'El período pagado finalizó. La licencia está suspendida.',
            'FAILED' =>
              'El pago no fue aprobado. Podés volver a abrir el checkout.',
            _ =>
              'Esperando la confirmación de Mercado Pago. La cuenta se activa al aprobarse el primer pago.',
          };
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        BioSpacing.gapH(BioSpacing.md),
        if (pricing != null && !_checkoutOpened) ...[
          Text('Resumen de la suscripción mensual', style: BioTypography.title),
          BioSpacing.gapH(BioSpacing.sm),
          _priceRow('Neto', pricing['amount_net_ars']),
          _priceRow(
            'IVA ${(100 * (double.tryParse(pricing['vat_rate'].toString()) ?? 0)).toStringAsFixed(0)}%',
            pricing['vat_ars'],
          ),
          const Divider(),
          _priceRow('Total mensual', pricing['amount_total_ars'], bold: true),
          BioSpacing.gapH(BioSpacing.xs),
          Text(
            'La cotización se calcula en pesos y se actualiza antes de confirmar el pago.',
            style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
          ),
          BioSpacing.gapH(BioSpacing.sm),
          Text(
            'El total se debitará automáticamente cada mes. Revisá las condiciones de renovación y cancelación que muestra Mercado Pago antes de autorizar.',
            style: BioTypography.bodySm,
          ),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            value: _termsAccepted,
            onChanged: (value) =>
                setState(() => _termsAccepted = value ?? false),
            title: const Text(
              'Entiendo y acepto el cargo mensual recurrente indicado.',
            ),
            controlAffinity: ListTileControlAffinity.leading,
          ),
        ],
        Text(message, style: BioTypography.body),
        BioSpacing.gapH(BioSpacing.md),
        if (status != 'ACTIVE' && status != 'CANCELLED' && status != 'EXPIRED')
          status == 'FAILED'
              ? BioButton.primary(
                  label: 'Intentar nuevamente',
                  icon: Icons.refresh,
                  fullWidth: true,
                  onPressed: _startRetryAfterFailedCheckout,
                )
              : BioButton.primary(
                  label: _checkoutOpened
                      ? 'Volver a Mercado Pago'
                      : 'Continuar a Mercado Pago',
                  icon: Icons.open_in_new,
                  fullWidth: true,
                  onPressed: (!_checkoutOpened && !_termsAccepted)
                      ? null
                      : _openCheckout,
                ),
        BioSpacing.gapH(BioSpacing.sm),
        OutlinedButton(
          onPressed: _refreshCheckoutStatus,
          child: const Text('Consultar estado'),
        ),
        if (status == 'ACTIVE' && !isSubscriptionCancelled) ...[
          BioSpacing.gapH(BioSpacing.sm),
          OutlinedButton(
            onPressed: _cancellingSubscription ? null : _cancelSubscription,
            child: _cancellingSubscription
                ? const CircularProgressIndicator()
                : const Text('Cancelar renovación'),
          ),
        ],
      ],
    );
  }

  Widget _priceRow(String label, dynamic amount, {bool bold = false}) {
    final value = double.tryParse(amount?.toString() ?? '');
    final style = bold ? BioTypography.title : BioTypography.body;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: BioSpacing.xs),
      child: Row(
        children: [
          Expanded(child: Text(label, style: style)),
          Text('ARS ${(value ?? 0).toStringAsFixed(2)}', style: style),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    return Scaffold(
      backgroundColor: tokens.paperBackground,
      appBar: AppBar(title: const Text('Crear espacio de atención')),
      body: SafeArea(
        child: Form(
          key: _formKey,
          child: ListView(
            padding: BioSpacing.pageAll,
            children: [
              _buildSignupProgress(),
              if (_checkoutKey != null)
                _buildCheckoutStatus()
              else if (_currentStep == 0)
                _buildSectorStep()
              else if (_currentStep == 1) ...[
                Text('Datos del efector', style: BioTypography.title),
                BioSpacing.gapH(BioSpacing.md),
                if (_sector == 'PUBLICO') ...[
                  if (_loadingMinistries)
                    const Center(child: CircularProgressIndicator())
                  else
                    DropdownButtonFormField<int>(
                      initialValue: _selectedMinistryId,
                      hint: const Text('Seleccione ministerio'),
                      decoration: const InputDecoration(
                        labelText: 'Ministerio',
                        border: OutlineInputBorder(),
                      ),
                      items: _ministries
                          .map((item) {
                            final id = int.tryParse(item['id'].toString());
                            return DropdownMenuItem(
                              value: id,
                              child: Text(item['nombre']?.toString() ?? ''),
                            );
                          })
                          .where((item) => item.value != null)
                          .toList(),
                      onChanged: (value) => setState(() {
                        _selectedMinistryId = value;
                        _sisaSearchRequestId++;
                        _selectedSisaEfector = null;
                        _sisaEfectores = const [];
                        _errorMessage = null;
                      }),
                    ),
                  BioSpacing.gapH(BioSpacing.md),
                  TextFormField(
                    controller: _sisaSearchController,
                    enabled: _selectedMinistryId != null,
                    textInputAction: TextInputAction.search,
                    onFieldSubmitted: (_) => _searchSisaEfectores(),
                    onChanged: (_) {
                      _sisaSearchRequestId++;
                      setState(() {
                        _searchingSisaEfectores = false;
                        _selectedSisaEfector = null;
                        _sisaEfectores = const [];
                      });
                    },
                    decoration: InputDecoration(
                      labelText: 'Buscar efector público',
                      hintText: _selectedMinistryId == null
                          ? 'Elegí primero un ministerio'
                          : 'Nombre',
                      helperText: _selectedMinistryId == null
                          ? null
                          : 'Al menos $_minEfectorSearchLength caracteres',
                      border: const OutlineInputBorder(),
                      suffixIcon: IconButton(
                        onPressed:
                            _searchingSisaEfectores || !_canSearchSisaEfectores
                            ? null
                            : _searchSisaEfectores,
                        icon: const Icon(Icons.search),
                      ),
                    ),
                  ),
                  if (_searchingSisaEfectores)
                    const Padding(
                      padding: EdgeInsets.all(BioSpacing.md),
                      child: Center(child: CircularProgressIndicator()),
                    )
                  else if (_sisaEfectores.isNotEmpty)
                    ..._sisaEfectores.map((efector) {
                      final selected =
                          _selectedSisaEfector?['codigo_sisa'] ==
                          efector['codigo_sisa'];
                      final locality = efector['localidad']?.toString() ?? '';
                      final address = efector['domicilio']?.toString() ?? '';
                      final location = [
                        address,
                        locality,
                      ].where((value) => value.isNotEmpty).join(' · ');
                      return Card(
                        child: ListTile(
                          selected: selected,
                          title: Text(efector['nombre']?.toString() ?? ''),
                          subtitle: Text(
                            [
                              'SISA ${efector['codigo_sisa']}',
                              if (location.isNotEmpty) location,
                            ].join(' · '),
                          ),
                          trailing: selected
                              ? const Icon(Icons.check_circle)
                              : null,
                          onTap: () => setState(() {
                            _selectedSisaEfector = efector;
                            _errorMessage = null;
                          }),
                        ),
                      );
                    }),
                ] else ...[
                  _textField(_nameController, 'Nombre del efector'),
                  _textField(_addressController, 'Domicilio'),
                  TextFormField(
                    controller: _localityQueryController,
                    decoration: InputDecoration(
                      labelText: 'Localidad',
                      border: const OutlineInputBorder(),
                      suffixIcon: IconButton(
                        onPressed: _searchingLocalities
                            ? null
                            : _searchLocalities,
                        icon: _searchingLocalities
                            ? const CircularProgressIndicator()
                            : const Icon(Icons.search),
                      ),
                    ),
                    onFieldSubmitted: (_) => _searchLocalities(),
                    onChanged: (_) {
                      if (_selectedLocality != null) {
                        setState(() {
                          _selectedLocality = null;
                          _localities = const [];
                        });
                      }
                    },
                    validator: (_) => _selectedLocality == null
                        ? 'Buscá y seleccioná una localidad.'
                        : null,
                  ),
                  if (_localities.isNotEmpty && _selectedLocality == null)
                    Padding(
                      padding: const EdgeInsets.only(top: BioSpacing.xs),
                      child: DropdownButtonFormField<Map<String, dynamic>>(
                        decoration: const InputDecoration(
                          labelText: 'Resultados de localidad',
                          border: OutlineInputBorder(),
                        ),
                        items: _localities.map((item) {
                          final label =
                              '${item['nombre']}, ${item['departamento']} (${item['provincia']})';
                          return DropdownMenuItem(
                            value: item,
                            child: Text(label),
                          );
                        }).toList(),
                        onChanged: (item) {
                          if (item == null) return;
                          setState(() {
                            _selectedLocality = item;
                            _localityQueryController.text =
                                '${item['nombre']}, ${item['departamento']} (${item['provincia']})';
                          });
                        },
                      ),
                    ),
                ],
              ] else ...[
                Text(
                  'Atenciones y volumen mensual',
                  style: BioTypography.title,
                ),
                BioSpacing.gapH(BioSpacing.sm),
                Text(
                  'Elegí qué tipos de atención vas a ofrecer y el volumen '
                  'mensual estimado de cada uno. El volumen define la '
                  'cotización, no la cantidad de personas que podés asignar.',
                  style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
                ),
                BioSpacing.gapH(BioSpacing.md),
                _buildClassSelector(
                  enabled: _ambEnabled,
                  title: 'Atención ambulatoria',
                  description: 'Consultas y seguimiento en consultorio.',
                  controller: _ambController,
                  onToggle: (value) => setState(() {
                    _ambEnabled = value;
                    if (!value) _videollamada = false;
                  }),
                ),
                _buildClassSelector(
                  enabled: _emerEnabled,
                  title: 'Emergencias',
                  description: 'Guardia y urgencias.',
                  controller: _emerController,
                  onToggle: (value) => setState(() => _emerEnabled = value),
                ),
                _buildClassSelector(
                  enabled: _impEnabled,
                  title: 'Internación',
                  description: 'Pacientes internados en el efector.',
                  controller: _impController,
                  onToggle: (value) => setState(() => _impEnabled = value),
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  value: _videollamada,
                  onChanged: _ambEnabled
                      ? (value) {
                          setState(() => _videollamada = value);
                          _refreshQuote();
                        }
                      : null,
                  title: const Text('Atención por videollamada'),
                  subtitle: Text(
                    _ambEnabled
                        ? 'Habilita consultas ambulatorias por videollamada. '
                              'Suma la modalidad al precio estimado.'
                        : 'Requiere habilitar la atención ambulatoria.',
                    style: BioTypography.bodySm.copyWith(
                      color: tokens.textMuted,
                    ),
                  ),
                ),
                _buildQuoteCard(),
                _textField(
                  _emailController,
                  'E-mail para el pago y los comprobantes',
                  keyboardType: TextInputType.emailAddress,
                  validator: (value) =>
                      value == null ||
                          !RegExp(
                            r'^[^@\s]+@[^@\s]+\.[^@\s]+$',
                          ).hasMatch(value.trim())
                      ? 'Ingresá un e-mail válido.'
                      : null,
                ),
                _textField(
                  _phoneController,
                  'Teléfono de contacto (opcional)',
                  keyboardType: TextInputType.phone,
                  validator: (_) => null,
                ),
              ],
              if (_errorMessage != null) ...[
                BioSpacing.gapH(BioSpacing.sm),
                Text(
                  _errorMessage!,
                  style: BioTypography.body.copyWith(
                    color: Theme.of(context).colorScheme.error,
                  ),
                ),
              ],
              if (_checkoutKey == null) ...[
                BioSpacing.gapH(BioSpacing.md),
                Row(
                  children: [
                    if (_currentStep > 0) ...[
                      Expanded(
                        child: BioButton.outlinePrimary(
                          onPressed: _submitting
                              ? null
                              : () => setState(() {
                                  _currentStep--;
                                  _errorMessage = null;
                                }),
                          label: 'Volver',
                        ),
                      ),
                      BioSpacing.gapW(BioSpacing.sm),
                    ],
                    Expanded(
                      child: BioButton.primary(
                        label: _submitting
                            ? 'Verificando identidad…'
                            : _currentStep == 2
                            ? 'Verificar identidad y continuar al pago'
                            : 'Continuar',
                        icon: _currentStep == 2
                            ? Icons.verified_user_outlined
                            : Icons.arrow_forward,
                        fullWidth: true,
                        loading: _submitting,
                        onPressed: _submitting ? null : _continueSignup,
                      ),
                    ),
                  ],
                ),
              ],
              BioSpacing.gapH(BioSpacing.md),
            ],
          ),
        ),
      ),
    );
  }

  String? _validateRequiredVolume(String? value) {
    final amount = int.tryParse(value?.trim() ?? '');
    if (amount == null || amount <= 0) {
      return 'Ingresá un número entero mayor a cero.';
    }
    return null;
  }
}
