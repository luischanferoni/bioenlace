import 'dart:math';

import 'package:flutter/material.dart';
import 'package:shared/shared.dart';
import 'package:url_launcher/url_launcher.dart';

import '../services/staff_signup_service.dart';

class CommercialSignupScreen extends StatefulWidget {
  const CommercialSignupScreen({
    required this.authToken,
    this.initialPlan,
    super.key,
  });

  final String authToken;
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
  int _currentStep = 0;
  int _sisaSearchRequestId = 0;
  String _sector = 'PRIVADO';
  String? _errorMessage;
  String? _checkoutKey;
  String? _checkoutRequestKey;
  String? _checkoutUrl;
  String? _checkoutStatus;
  String? _providerSubscriptionStatus;
  String? _paidThroughAt;
  Map<String, dynamic>? _pricing;
  Map<String, dynamic>? _selectedLocality;
  Map<String, dynamic>? _selectedSisaEfector;
  List<Map<String, dynamic>> _localities = const [];
  List<Map<String, dynamic>> _sisaEfectores = const [];
  List<Map<String, dynamic>> _ministries = const [];
  int? _selectedMinistryId;

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
    }
    _loadMinistries();
  }

  @override
  void dispose() {
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
      if (_sector == 'PUBLICO' && _selectedSisaEfector == null) {
        setState(
          () => _errorMessage =
              'Buscá y seleccioná un efector del catálogo SISA.',
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
      setState(() {
        _currentStep = 2;
        _errorMessage = null;
      });
      return;
    }

    await _startCheckout();
  }

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
    final query = _sisaSearchController.text.trim();
    final requestId = ++_sisaSearchRequestId;
    if (query.length < 2) {
      setState(() {
        _searchingSisaEfectores = false;
        _selectedSisaEfector = null;
        _sisaEfectores = const [];
        _errorMessage = 'Ingresá al menos dos caracteres para buscar.';
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
      setState(
        () =>
            _errorMessage = 'Buscá y seleccioná un efector del catálogo SISA.',
      );
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
      final body = <String, dynamic>{
        'checkout_request_key': _checkoutRequestKey ??=
            _newCheckoutRequestKey(),
        'perfil': 'ESPACIO_ATENCION',
        'sector': _sector,
        'payer_email': _emailController.text.trim(),
        'contact_phone': _phoneController.text.trim(),
        'efector': _sector == 'PUBLICO'
            ? {'codigo_sisa': _selectedSisaEfector!['codigo_sisa']}
            : {
                'nombre': _nameController.text.trim(),
                'domicilio': _addressController.text.trim(),
                'id_localidad': _selectedLocality!['id'],
                'telefono': _phoneController.text.trim(),
              },
        'plan': plan,
      };
      if (_sector == 'PUBLICO' && _selectedMinistryId != null) {
        body['id_billing_account_ministerio'] = _selectedMinistryId;
      }
      final checkout = await _service.startCommercialCheckout(
        authToken: widget.authToken,
        body: body,
      );
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
      ('AMB', _ambController.text),
      ('EMER', _emerController.text),
      ('IMP', _impController.text),
    ]) {
      final value = int.tryParse(entry.$2.trim()) ?? 0;
      if (value > 0) {
        classes[entry.$1] = {'attentions_per_month': value};
      }
    }
    return classes.isEmpty ? null : {'classes': classes};
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
    if (key == null) return;
    try {
      final status = await _service.checkoutStatus(
        authToken: widget.authToken,
        checkoutKey: key,
      );
      if (!mounted) return;
      setState(() {
        _checkoutStatus = status['status']?.toString();
        _providerSubscriptionStatus = status['provider_subscription_status']
            ?.toString();
        _paidThroughAt = status['paid_through_at']?.toString();
      });
    } catch (error) {
      if (!mounted) return;
      setState(() => _errorMessage = _errorText(error));
    }
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

    setState(() {
      _cancellingSubscription = true;
      _errorMessage = null;
    });
    try {
      final status = await _service.cancelCommercialCheckout(
        authToken: widget.authToken,
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
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: BioSpacing.md),
      child: TextFormField(
        controller: controller,
        keyboardType: keyboardType,
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

  Widget _buildSignupProgress() {
    const steps = ['Sector', 'Efector', 'Servicios y volumen', 'Pago'];
    final visibleStep = _checkoutKey == null ? _currentStep : 3;
    final tokens = context.bio;
    return Padding(
      padding: const EdgeInsets.only(bottom: BioSpacing.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Paso ${visibleStep + 1} de ${steps.length}: ${steps[visibleStep]}',
            style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
          ),
          BioSpacing.gapH(BioSpacing.xs),
          LinearProgressIndicator(value: (visibleStep + 1) / steps.length),
        ],
      ),
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
          BioButton.primary(
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
              else if (_currentStep == 0) ...[
                Text(
                  '¿Qué tipo de espacio querés crear?',
                  style: BioTypography.title,
                ),
                BioSpacing.gapH(BioSpacing.md),
                DropdownButtonFormField<String>(
                  initialValue: _sector,
                  decoration: const InputDecoration(
                    labelText: 'Sector',
                    border: OutlineInputBorder(),
                  ),
                  items: const [
                    DropdownMenuItem(value: 'PRIVADO', child: Text('Privado')),
                    DropdownMenuItem(value: 'PUBLICO', child: Text('Público')),
                  ],
                  onChanged: (value) => setState(() {
                    _sector = value ?? 'PRIVADO';
                    _selectedSisaEfector = null;
                    _sisaEfectores = const [];
                    _selectedMinistryId = null;
                    _errorMessage = null;
                  }),
                ),
                BioSpacing.gapH(BioSpacing.md),
                Text(
                  _sector == 'PUBLICO'
                      ? 'Los espacios públicos requieren elegir un ministerio y un efector del catálogo oficial.'
                      : 'Vas a registrar los datos del efector privado en el siguiente paso.',
                  style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
                ),
              ] else if (_currentStep == 1) ...[
                Text('Datos del efector', style: BioTypography.title),
                BioSpacing.gapH(BioSpacing.md),
                if (_sector == 'PUBLICO') ...[
                  TextFormField(
                    controller: _sisaSearchController,
                    textInputAction: TextInputAction.search,
                    onFieldSubmitted: (_) => _searchSisaEfectores(),
                    onChanged: (_) {
                      _sisaSearchRequestId++;
                      if (_selectedSisaEfector != null ||
                          _sisaEfectores.isNotEmpty ||
                          _searchingSisaEfectores) {
                        setState(() {
                          _searchingSisaEfectores = false;
                          _selectedSisaEfector = null;
                          _sisaEfectores = const [];
                        });
                      }
                    },
                    decoration: InputDecoration(
                      labelText: 'Buscar efector público',
                      hintText: 'Nombre o código SISA',
                      border: const OutlineInputBorder(),
                      suffixIcon: IconButton(
                        onPressed: _searchingSisaEfectores
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
                  if (_selectedSisaEfector != null)
                    Padding(
                      padding: const EdgeInsets.only(bottom: BioSpacing.md),
                      child: Text(
                        'Efector seleccionado: ${_selectedSisaEfector!['nombre']}',
                        style: BioTypography.bodySm.copyWith(
                          color: tokens.textMuted,
                        ),
                      ),
                    ),
                  Padding(
                    padding: const EdgeInsets.only(bottom: BioSpacing.md),
                    child: Text(
                      'Se valida contra el catálogo SISA local. Si el efector ya está afiliado a un ministerio o tiene una cuenta de facturación, no se puede registrar de nuevo.',
                      style: BioTypography.bodySm.copyWith(
                        color: tokens.textMuted,
                      ),
                    ),
                  ),
                  if (_loadingMinistries)
                    const Center(child: CircularProgressIndicator())
                  else
                    DropdownButtonFormField<int>(
                      initialValue: _selectedMinistryId,
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
                      onChanged: (value) =>
                          setState(() => _selectedMinistryId = value),
                    ),
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
                Text('Servicios y volumen mensual', style: BioTypography.title),
                BioSpacing.gapH(BioSpacing.sm),
                Text(
                  'El volumen define la cotización, no la cantidad de personas '
                  'que podés asignar.',
                  style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
                ),
                _textField(
                  _ambController,
                  'Ambulatorias (0 si no aplica)',
                  keyboardType: TextInputType.number,
                  validator: _validateOptionalVolume,
                ),
                _textField(
                  _emerController,
                  'Emergencias (0 si no aplica)',
                  keyboardType: TextInputType.number,
                  validator: _validateOptionalVolume,
                ),
                _textField(
                  _impController,
                  'Internaciones (0 si no aplica)',
                  keyboardType: TextInputType.number,
                  validator: _validateOptionalVolume,
                ),
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
                        child: OutlinedButton(
                          onPressed: _submitting
                              ? null
                              : () => setState(() {
                                  _currentStep--;
                                  _errorMessage = null;
                                }),
                          child: const Text('Volver'),
                        ),
                      ),
                      BioSpacing.gapW(BioSpacing.sm),
                    ],
                    Expanded(
                      child: BioButton.primary(
                        label: _submitting
                            ? 'Preparando cotización…'
                            : _currentStep == 2
                            ? 'Cotizar y continuar'
                            : 'Continuar',
                        icon: _currentStep == 2
                            ? Icons.request_quote_outlined
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
              if (_checkoutKey == null)
                Text(
                  'El efector se crea y activa únicamente cuando Mercado Pago confirma el primer pago.',
                  style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
                ),
            ],
          ),
        ),
      ),
    );
  }

  String? _validateOptionalVolume(String? value) {
    if (value == null || value.trim().isEmpty) return null;
    final amount = int.tryParse(value.trim());
    if (amount == null || amount < 0) {
      return 'Ingresá un número entero mayor o igual a cero.';
    }
    return null;
  }
}
