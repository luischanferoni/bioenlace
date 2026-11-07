import 'dart:math';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:intl/intl.dart';
import 'package:shared/shared.dart';
import 'package:url_launcher/url_launcher.dart';

import '../infra/app_texts.dart';
import '../services/staff_signup_service.dart';
import '../widgets/map_location_picker.dart';

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
  final _service = StaffSignupService();

  bool _submitting = false;
  bool _searchingSisaEfectores = false;
  bool _loadingMinistries = false;
  bool _termsAccepted = false;
  bool _checkoutOpened = false;
  bool _cancellingSubscription = false;
  bool _videollamada = false;
  bool _loadingQuote = false;
  bool _loadingPlans = false;
  bool _sendingEmailVerification = false;
  bool _emailStepVisible = false;
  bool _emailConfirmed = false;
  String? _emailVerificationTarget;
  String? _emailVerificationNotice;
  String? _emailVerificationError;
  int _currentStep = 0;
  int _sisaSearchRequestId = 0;
  String _sector = 'PRIVADO';
  String? _errorMessage;
  String? _verificationId;
  String? _checkoutKey;
  String? _checkoutRequestKey;
  String? _checkoutUrl;
  String? _checkoutStatus;
  bool _sandboxCheckout = false;
  bool _refreshingStatus = false;
  String? _quoteError;
  String? _providerSubscriptionStatus;
  String? _paidThroughAt;
  Map<String, dynamic>? _pricing;
  Map<String, dynamic>? _quote;
  Map<String, dynamic>? _selectedSisaEfector;
  List<Map<String, dynamic>> _sisaEfectores = const [];
  List<Map<String, dynamic>> _ministries = const [];
  int? _selectedMinistryId;
  Map<String, dynamic>? _plansCatalog;
  String? _plansError;

  // Ubicación del efector privado (mapa + resolución en backend).
  LatLng? _pickedLocation;
  Map<String, dynamic>? _resolvedLocation;
  bool _resolvingLocation = false;
  String? _locationError;
  int _locationRequestId = 0;

  /// Coordenadas para las que ya se resolvió [_resolvedLocation]. El backend se
  /// consulta al avanzar, no en cada arrastre del marcador.
  LatLng? _resolvedFor;

  /// Volumen mensual elegido por tipo de atención (`AMB`, `EMER`, `IMP`).
  /// Si un tipo no tiene entrada, no se contrata.
  final Map<String, int> _classVolumes = <String, int>{};

  /// La búsqueda de efectores SISA exige más de 3 caracteres.
  static const int _minEfectorSearchLength = 4;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    final classes = widget.initialPlan?['classes'];
    if (classes is Map) {
      classes.forEach((code, value) {
        if (value is! Map) return;
        final attentions = int.tryParse(
          value['attentions_per_month']?.toString() ?? '',
        );
        if (attentions != null && attentions > 0) {
          _classVolumes[code.toString()] = attentions;
        }
      });
      _videollamada = classes['AMB']?['videollamada_permitida'] == true;
    }
    _loadMinistries();
    _loadPlans();
    WidgetsBinding.instance.addPostFrameCallback((_) => _refreshQuote());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _nameController.dispose();
    _addressController.dispose();
    _sisaSearchController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
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
      if (_sector != 'PUBLICO') {
        if (_pickedLocation == null) {
          setState(() => _errorMessage = 'Ubicá el efector en el mapa.');
          return;
        }
        // Recién acá consultamos el backend: mover el pin no dispara llamadas.
        if (!await _ensureLocationResolved() || !mounted) return;
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

  Future<void> _loadPlans() async {
    setState(() {
      _loadingPlans = true;
      _plansError = null;
    });
    try {
      final catalog = await _service.fetchSignupPlans();
      if (!mounted) return;
      setState(() {
        _plansCatalog = catalog;
        _loadingPlans = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loadingPlans = false;
        _plansError = _errorText(error);
      });
    }
  }

  /// Catálogo de tipos de atención vendibles (`sellable_classes`).
  Map<String, dynamic> get _sellableClasses {
    final raw = _plansCatalog?['sellable_classes'];
    return raw is Map ? Map<String, dynamic>.from(raw) : const {};
  }

  /// Grupos de volumen que define el backend (`attention_volume_presets`,
  /// con fallback a `attention_volume_scale`).
  List<MapEntry<int, String>> get _volumePresets {
    final presets = _plansCatalog?['attention_volume_presets'];
    if (presets is List && presets.isNotEmpty) {
      return presets
          .whereType<Map>()
          .map((preset) {
            final attentions = int.tryParse(
              preset['attentions']?.toString() ?? '',
            );
            return attentions == null || attentions <= 0
                ? null
                : MapEntry(attentions, preset['label']?.toString() ?? '');
          })
          .whereType<MapEntry<int, String>>()
          .toList();
    }
    final scale = _plansCatalog?['attention_volume_scale'];
    if (scale is List) {
      return scale
          .map((value) => int.tryParse(value?.toString() ?? ''))
          .whereType<int>()
          .where((attentions) => attentions > 0)
          .map((attentions) => MapEntry(attentions, ''))
          .toList();
    }
    return const [];
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
            if (_pickedLocation != null) ...{
              'lat': _pickedLocation!.latitude,
              'lng': _pickedLocation!.longitude,
            },
            if (_resolvedLocation?['localidad']?['id'] != null) ...{
              'id_localidad': _resolvedLocation!['localidad']['id'],
              'localidad': _resolvedLocation!['localidad']['nombre'],
              if (_resolvedLocation!['departamento']?['nombre'] != null)
                'departamento': _resolvedLocation!['departamento']['nombre'],
              if (_resolvedLocation!['provincia']?['nombre'] != null)
                'provincia': _resolvedLocation!['provincia']['nombre'],
              if (_resolvedLocation!['pais']?['nombre'] != null)
                'pais': _resolvedLocation!['pais']['nombre'],
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

  void _onMapLocationChanged(LatLng position) {
    setState(() {
      _pickedLocation = position;
      _locationError = null;
      if (_resolvedFor != position) {
        _resolvedLocation = null;
      }
    });
  }

  /// Resuelve país/provincia/departamento/localidad para el pin elegido.
  ///
  /// Se llama al avanzar (y desde "Reintentar"), no en cada movimiento del
  /// marcador: cada resolución es una llamada al backend + geocoding.
  Future<bool> _resolveLocation() async {
    final picked = _pickedLocation;
    if (picked == null) return false;
    final requestId = ++_locationRequestId;

    setState(() {
      _resolvingLocation = true;
      _locationError = null;
    });
    try {
      final result = await _service.resolveLocation(
        latitude: picked.latitude,
        longitude: picked.longitude,
      );
      if (!mounted || requestId != _locationRequestId) return false;
      setState(() {
        _resolvingLocation = false;
        _resolvedLocation = result;
        _resolvedFor = picked;
        _addressController.text = _locationAddress(result);
      });
      return true;
    } catch (error) {
      if (!mounted || requestId != _locationRequestId) return false;
      setState(() {
        _resolvingLocation = false;
        _resolvedLocation = null;
        _resolvedFor = null;
        _locationError = _errorText(error);
      });
      return false;
    }
  }

  /// Resuelve la ubicación sólo si el pin cambió desde la última resolución.
  Future<bool> _ensureLocationResolved() async {
    final picked = _pickedLocation;
    if (picked == null) return true;
    if (_resolvedLocation != null && _resolvedFor == picked) return true;

    return _resolveLocation();
  }

  /// Dirección del efector: la que devuelve el geocoding y, si no vino, la
  /// composición localidad / departamento / provincia / país.
  String _locationAddress(Map<String, dynamic> location) {
    final direccion = location['direccion']?.toString().trim() ?? '';
    if (direccion.isNotEmpty) return direccion;

    return <String>[
      location['localidad']?['nombre']?.toString() ?? '',
      location['departamento']?['nombre']?.toString() ?? '',
      location['provincia']?['nombre']?.toString() ?? '',
      location['pais']?['nombre']?.toString() ?? '',
    ].where((parte) => parte.isNotEmpty).join(', ');
  }

  Widget _buildResolvedLocation() {
    final tokens = context.bio;
    final location = _resolvedLocation!;
    final partes = <String>[
      location['pais']?['nombre']?.toString() ?? '',
      location['provincia']?['nombre']?.toString() ?? '',
      location['localidad']?['nombre']?.toString() ?? '',
    ].where((parte) => parte.isNotEmpty).toList();
    final distancia = location['distancia_km']?.toString();
    final direccion = location['direccion']?.toString().trim() ?? '';

    return Material(
      color: tokens.paperSurface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: tokens.paperDividerDefault),
      ),
      child: Padding(
        padding: const EdgeInsets.all(BioSpacing.md),
        child: Row(
          children: [
            Icon(Icons.place_outlined, size: 18, color: tokens.textMuted),
            BioSpacing.gapW(BioSpacing.sm),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    partes.isEmpty
                        ? 'No pudimos determinar la localidad.'
                        : partes.join(' · '),
                    style: BioTypography.bodySm,
                  ),
                  if (direccion.isNotEmpty)
                    Text(
                      direccion,
                      style: BioTypography.caption.copyWith(
                        color: tokens.textMuted,
                      ),
                    ),
                  if (distancia != null)
                    Text(
                      'Localidad más cercana a $distancia km',
                      style: BioTypography.caption.copyWith(
                        color: tokens.textMuted,
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
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

  Future<void> _startCheckout() => _submitCheckout(sandbox: false);

  /// Alta de prueba (solo builds debug): salta Didit y abre el checkout de
  /// Mercado Pago sandbox. El backend debe tener `sandboxSignupEnabled`.
  Future<void> _startSandboxCheckout() async {
    // Cada prueba genera un intento nuevo; evita reusar una clave con datos
    // viejos (p. ej. si cambió el pagador de prueba).
    _checkoutRequestKey = null;
    await _submitCheckout(sandbox: true);
  }

  Future<void> _submitCheckout({required bool sandbox}) async {
    if (!_formKey.currentState!.validate()) return;
    if (_sector != 'PUBLICO' && _pickedLocation == null) {
      setState(() => _errorMessage = 'Ubicá el efector en el mapa.');
      return;
    }
    if (_sector != 'PUBLICO' && _addressController.text.trim().isEmpty) {
      setState(
        () => _errorMessage =
            'Ubicá el efector en el mapa para que detectemos la dirección.',
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
      final body = <String, dynamic>{
        'perfil': 'ESPACIO_ATENCION',
        ..._draftPayload(currentStep: 2),
        'checkout_request_key': _checkoutRequestKey ??=
            _newCheckoutRequestKey(),
        'plan': plan,
      };

      late final Map<String, dynamic> checkout;
      if (sandbox) {
        checkout = await _service.startCommercialSandboxCheckout(body: body);
      } else {
        var verificationId = _verificationId;
        if (verificationId == null) {
          verificationId = await _service.readPendingVerificationId();
          verificationId ??= await _service.verifyIdentity();
          await _service.storePendingVerificationId(verificationId);
          _verificationId = verificationId;
        }
        if (!mounted) return;
        setState(() => _submitting = true);

        checkout = await _service.startCommercialCheckout(
          verificationId: verificationId,
          body: body,
        );
        await _service.clearPendingVerificationId();
      }

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
        _sandboxCheckout = sandbox;
        _emailStepVisible = !sandbox;
        _emailConfirmed = false;
        _emailVerificationNotice = null;
        _emailVerificationError = null;
      });
      if (sandbox) {
        await _launchCheckoutUrl();
      } else {
        // El envío del mail no bloquea el pago: si falla, se avisa en la tarjeta.
        await _sendEmailVerification();
      }
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
    _classVolumes.forEach((code, attentions) {
      if (attentions <= 0) return;
      classes[code] = {
        'attentions_per_month': attentions,
        'dictado_incluido': true,
        'videollamada_permitida': code == 'AMB' && _videollamada,
      };
    });
    return classes.isEmpty ? null : {'classes': classes};
  }

  /// `es_AR` con el símbolo de moneda adelante: "$ 12.345,67".
  String _formatArs(Object? amount) {
    final value = double.tryParse(amount?.toString() ?? '');
    if (value == null) return '—';
    final formatted = NumberFormat.decimalPatternDigits(
      locale: 'es_AR',
      decimalDigits: 2,
    ).format(value);
    return '\$ $formatted';
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
              'Elegí un volumen para ver el precio.',
              style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
            )
          : Column(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Text(
                  'Precio',
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

  Future<void> _sendEmailVerification() async {
    final verificationId = _verificationId;
    if (verificationId == null || _sendingEmailVerification) return;

    setState(() {
      _sendingEmailVerification = true;
      _emailVerificationError = null;
      _emailVerificationNotice = null;
    });
    try {
      final result = await _service.sendEmailVerification(
        verificationId: verificationId,
      );
      if (!mounted) return;
      final cooldown = int.tryParse(
        result['cooldown_seconds']?.toString() ?? '',
      );
      setState(() {
        _sendingEmailVerification = false;
        _emailConfirmed = result['email_confirmed'] == true;
        _emailVerificationTarget =
            result['email']?.toString() ?? _emailController.text.trim();
        _emailVerificationNotice = result['email_confirmed'] == true
            ? 'Tu e-mail ya está confirmado.'
            : cooldown != null && cooldown > 0
            ? 'Podés pedir otro envío en $cooldown s.'
            : 'Te enviamos un mail para confirmar tu e-mail.';
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _sendingEmailVerification = false;
        _emailVerificationTarget ??= _emailController.text.trim();
        _emailVerificationError = _errorText(error);
      });
    }
  }

  Future<void> _checkEmailVerification() async {
    final verificationId = _verificationId;
    if (verificationId == null || _sendingEmailVerification) return;

    setState(() {
      _sendingEmailVerification = true;
      _emailVerificationError = null;
    });
    try {
      final result = await _service.emailVerificationStatus(
        verificationId: verificationId,
      );
      if (!mounted) return;
      final confirmed = result['email_confirmed'] == true;
      setState(() {
        _sendingEmailVerification = false;
        _emailConfirmed = confirmed;
        _emailVerificationTarget =
            result['email']?.toString() ?? _emailVerificationTarget;
        _emailVerificationNotice = confirmed
            ? '¡Listo! Confirmamos tu e-mail.'
            : 'Todavía no vimos la confirmación. Abrí el mail y tocá el enlace.';
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _sendingEmailVerification = false;
        _emailVerificationError = _errorText(error);
      });
    }
  }

  Widget _buildEmailVerificationCard() {
    final tokens = context.bio;
    final email = _emailVerificationTarget ?? _emailController.text.trim();

    return Padding(
      padding: const EdgeInsets.only(top: BioSpacing.sm),
      child: Material(
        color: tokens.paperSurface,
        clipBehavior: Clip.antiAlias,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: BorderSide(color: tokens.paperDividerDefault),
        ),
        child: Padding(
          padding: const EdgeInsets.all(BioSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('Confirmá tu e-mail', style: BioTypography.title),
              BioSpacing.gapH(BioSpacing.sm),
              Text(
                'Te enviamos un mail a $email para confirmarlo. Podés confirmarlo '
                'ahora o continuar con el pago: no es obligatorio para suscribirte.',
                style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
              ),
              BioSpacing.gapH(BioSpacing.md),
              if (_sendingEmailVerification)
                const Center(child: CircularProgressIndicator())
              else if (_emailVerificationNotice != null)
                Row(
                  children: [
                    Icon(
                      _emailConfirmed ? Icons.check_circle : Icons.mail_outline,
                      size: 18,
                      color: _emailConfirmed
                          ? IntentPalette.of(UiIntent.success).base
                          : tokens.textMuted,
                    ),
                    BioSpacing.gapW(BioSpacing.sm),
                    Expanded(
                      child: Text(
                        _emailVerificationNotice!,
                        style: BioTypography.bodySm.copyWith(
                          color: tokens.textMuted,
                        ),
                      ),
                    ),
                  ],
                ),
              if (_emailVerificationError != null) ...[
                BioSpacing.gapH(BioSpacing.sm),
                Text(
                  _emailVerificationError!,
                  style: BioTypography.bodySm.copyWith(
                    color: Theme.of(context).colorScheme.error,
                  ),
                ),
              ],
              BioSpacing.gapH(BioSpacing.md),
              BioButton.primary(
                label: 'Continuar al pago',
                icon: Icons.arrow_forward,
                size: BioButtonSize.lg,
                fullWidth: true,
                onPressed: () => setState(() => _emailStepVisible = false),
              ),
              BioSpacing.gapH(BioSpacing.sm),
              Row(
                children: [
                  Expanded(
                    child: BioButton.outlinePrimary(
                      label: 'Reenviar mail',
                      onPressed: _sendingEmailVerification || _emailConfirmed
                          ? null
                          : _sendEmailVerification,
                    ),
                  ),
                  BioSpacing.gapW(BioSpacing.sm),
                  Expanded(
                    child: BioButton.outlinePrimary(
                      label: 'Ya confirmé',
                      onPressed: _sendingEmailVerification || _emailConfirmed
                          ? null
                          : _checkEmailVerification,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _launchCheckoutUrl() async {
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

  Future<void> _openCheckout() async {
    if (!_checkoutOpened && !_termsAccepted) {
      setState(
        () => _errorMessage =
            'Confirmá que aceptás la suscripción mensual antes de continuar.',
      );
      return;
    }
    await _launchCheckoutUrl();
  }

  Future<void> _refreshCheckoutStatus() async {
    if (_refreshingStatus) return;
    final key = _checkoutKey;
    if (key == null) return;
    _refreshingStatus = true;
    final verificationId = _verificationId;
    // Sin verificación de Didit el checkout salió por la vía sandbox.
    final useSandboxStatus = _sandboxCheckout || verificationId == null;
    try {
      final status = useSandboxStatus
          ? await _service.checkoutStatusSandbox(checkoutKey: key)
          : await _service.checkoutStatus(
              verificationId: verificationId!,
              checkoutKey: key,
            );
      if (!mounted) return;
      setState(() {
        _checkoutStatus = status['status']?.toString();
        _providerSubscriptionStatus = status['provider_subscription_status']
            ?.toString();
        _paidThroughAt = status['paid_through_at']?.toString();
        _checkoutUrl = status['checkout_url']?.toString() ?? _checkoutUrl;
        _pricing = status;
        _errorMessage = null;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() => _errorMessage = _errorText(error));
    } finally {
      _refreshingStatus = false;
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
      _sandboxCheckout = false;
      _currentStep = 2;
      _errorMessage = null;
      _emailStepVisible = false;
      _emailConfirmed = false;
      _emailVerificationNotice = null;
      _emailVerificationError = null;
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
    if (!_sandboxCheckout && verificationId == null) return;

    setState(() {
      _cancellingSubscription = true;
      _errorMessage = null;
    });
    try {
      final status = _sandboxCheckout
          ? await _service.cancelCommercialSandboxCheckout(
              checkoutKey: _checkoutKey!,
            )
          : await _service.cancelCommercialCheckout(
              verificationId: verificationId!,
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
    String? hintText,
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
          hintText: hintText,
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

  /// Teléfono opcional: sólo se valida cuando hay valor y debe ser un celular
  /// en formato internacional (espejo de `ContactPhoneValidator` del backend).
  String? _validateOptionalMobile(String? value) {
    final raw = (value ?? '').trim();
    if (raw.isEmpty) return null;

    final normalized = _normalizeMobile(raw);
    if (normalized == null) return null;
    if (RegExp(r'^\+549\d{10}$').hasMatch(normalized)) return null;
    if (normalized.startsWith('+54')) {
      return 'Ingresá un celular, ej. +5491123456789.';
    }
    return RegExp(r'^\+[1-9]\d{7,14}$').hasMatch(normalized)
        ? null
        : 'Usá formato internacional, ej. +5491123456789.';
  }

  String? _normalizeMobile(String raw) {
    final trimmed = raw.trim();
    if (trimmed.isEmpty) return null;

    final hasPlus = trimmed.startsWith('+');
    final digits = trimmed.replaceAll(RegExp(r'[^0-9]'), '');
    if (digits.isEmpty) return trimmed;

    if (RegExp(r'^549\d{10}$').hasMatch(digits)) return '+$digits';

    final national = RegExp(r'^0(\d{2,4}?)15(\d{6,8})$').firstMatch(digits);
    if (!hasPlus && national != null) {
      final significant = '${national.group(1)}${national.group(2)}';
      if (significant.length == 10) return '+549$significant';
    }

    if (!hasPlus && RegExp(r'^\d{10}$').hasMatch(digits)) return '+549$digits';

    return hasPlus ? '+$digits' : trimmed;
  }

  Widget _buildClassVolumeGroup({
    required String code,
    required Map<String, dynamic> classRow,
  }) {
    final tokens = context.bio;
    final selected = _classVolumes[code];
    final label = classRow['label']?.toString() ?? code;
    final short = classRow['short']?.toString() ?? '';
    final allowsVideollamada = classRow['videollamada_allowed'] != false;
    final presets = _volumePresets;

    return Padding(
      padding: const EdgeInsets.only(bottom: BioSpacing.md),
      child: Material(
        color: tokens.paperSurface,
        clipBehavior: Clip.antiAlias,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: BorderSide(
            color: selected != null
                ? Theme.of(context).colorScheme.primary
                : tokens.paperDividerDefault,
            width: selected != null ? 2 : 1,
          ),
        ),
        child: Padding(
          padding: const EdgeInsets.all(BioSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(label, style: BioTypography.title),
              if (short.isNotEmpty)
                Text(
                  short,
                  style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
                ),
              BioSpacing.gapH(BioSpacing.sm),
              Wrap(
                spacing: BioSpacing.sm,
                runSpacing: BioSpacing.sm,
                children: presets
                    .map(
                      (preset) => BioChip(
                        label: _formatAttentions(preset.key),
                        selected: selected == preset.key,
                        icon: selected == preset.key ? Icons.check : null,
                        onTap: () => _selectVolume(code, preset.key),
                      ),
                    )
                    .toList(),
              ),
              if (code == 'AMB' && allowsVideollamada) ...[
                BioSpacing.gapH(BioSpacing.xs),
                SwitchListTile(
                  value: _videollamada,
                  onChanged: selected == null
                      ? null
                      : (value) {
                          setState(() => _videollamada = value);
                          _refreshQuote();
                        },
                  title: const Text('Atención por videollamada'),
                  subtitle: Text(
                    selected == null
                        ? 'Requiere elegir un volumen ambulatorio.'
                        : 'Habilita consultas ambulatorias por videollamada.',
                    style: BioTypography.bodySm.copyWith(
                      color: tokens.textMuted,
                    ),
                  ),
                ),
              ],
              BioSpacing.gapH(BioSpacing.md),
              _buildClassPolicy(classRow),
            ],
          ),
        ),
      ),
    );
  }

  /// Política del tipo de atención (`audio_included` / `videollamada_allowed`):
  /// lo incluido lleva un tilde y lo que no está disponible va tachado.
  Widget _buildClassPolicy(Map<String, dynamic> classRow) {
    final tokens = context.bio;
    final base = BioTypography.caption.copyWith(color: tokens.textMuted);
    final struck = base.copyWith(decoration: TextDecoration.lineThrough);
    final checkColor = IntentPalette.of(UiIntent.success).base;
    final spans = <InlineSpan>[];

    void addSeparator() {
      if (spans.isNotEmpty) {
        spans.add(TextSpan(text: '  ·  ', style: base));
      }
    }

    if (classRow['audio_included'] == true) {
      spans.add(
        WidgetSpan(
          alignment: PlaceholderAlignment.middle,
          child: Padding(
            padding: const EdgeInsets.only(right: BioSpacing.xs),
            child: Icon(Icons.check, size: 12, color: checkColor),
          ),
        ),
      );
      spans.add(TextSpan(text: 'Dictado incluido', style: base));
    } else {
      spans.add(TextSpan(text: 'Sin dictado', style: struck));
    }

    if (classRow['videollamada_allowed'] == false) {
      addSeparator();
      spans.add(TextSpan(text: 'Sin videollamada', style: struck));
    }

    return Text.rich(
      TextSpan(style: base, children: spans),
      textAlign: TextAlign.right,
    );
  }

  void _selectVolume(String code, int attentions) {
    setState(() {
      if (_classVolumes[code] == attentions) {
        _classVolumes.remove(code);
        if (code == 'AMB') _videollamada = false;
      } else {
        _classVolumes[code] = attentions;
      }
    });
    _refreshQuote();
  }

  String _formatAttentions(int attentions) =>
      '${NumberFormat.decimalPattern('es_AR').format(attentions)}/mes';

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
          'Efector o Centro de salud pública.',
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
        if (pricing != null) ...[
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
          if (!_checkoutOpened)
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
        if (_errorMessage != null) ...[
          BioSpacing.gapH(BioSpacing.sm),
          Text(
            _errorMessage!,
            style: BioTypography.bodySm.copyWith(
              color: Theme.of(context).colorScheme.error,
            ),
          ),
        ],
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
    final style = bold ? BioTypography.title : BioTypography.body;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: BioSpacing.xs),
      child: Row(
        children: [
          Expanded(child: Text(label, style: style)),
          Text(_formatArs(amount), style: style),
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
                _emailStepVisible
                    ? _buildEmailVerificationCard()
                    : _buildCheckoutStatus()
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
                  Text('Ubicación en el mapa', style: BioTypography.title),
                  BioSpacing.gapH(BioSpacing.xs),
                  Text(
                    'Buscá el lugar por el nombre que cargaste y ubicá el '
                    'efector tocando el mapa. Al continuar detectamos país, '
                    'provincia, localidad y la dirección exacta.',
                    style: BioTypography.bodySm.copyWith(
                      color: tokens.textMuted,
                    ),
                  ),
                  BioSpacing.gapH(BioSpacing.sm),
                  MapLocationPicker(
                    initialLatitude: _pickedLocation?.latitude,
                    initialLongitude: _pickedLocation?.longitude,
                    onChanged: _onMapLocationChanged,
                  ),
                  BioSpacing.gapH(BioSpacing.sm),
                  if (_resolvingLocation)
                    const Center(child: CircularProgressIndicator())
                  else if (_locationError != null)
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            _locationError!,
                            style: BioTypography.bodySm.copyWith(
                              color: Theme.of(context).colorScheme.error,
                            ),
                          ),
                        ),
                        TextButton(
                          onPressed: _resolveLocation,
                          child: const Text('Reintentar'),
                        ),
                      ],
                    )
                  else if (_resolvedLocation != null)
                    _buildResolvedLocation(),
                  BioSpacing.gapH(BioSpacing.md),
                ],
              ] else ...[
                Text(
                  'Volumen de atenciones mensual por tipo de atención',
                  style: BioTypography.title,
                ),
                BioSpacing.gapH(BioSpacing.sm),
                Text(
                  'Elegí el volumen mensual de cada tipo de atención que vas a '
                  'ofrecer. Podés dejar tipos sin elegir. El volumen define la '
                  'cotización, no la cantidad de personas que podés asignar.',
                  style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
                ),
                BioSpacing.gapH(BioSpacing.md),
                if (_loadingPlans)
                  const Padding(
                    padding: EdgeInsets.all(BioSpacing.md),
                    child: Center(child: CircularProgressIndicator()),
                  )
                else if (_plansError != null)
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(_plansError!, style: BioTypography.bodySm),
                      Align(
                        alignment: Alignment.centerRight,
                        child: TextButton(
                          onPressed: _loadPlans,
                          child: const Text('Reintentar'),
                        ),
                      ),
                    ],
                  )
                else ...[
                  for (final entry in _sellableClasses.entries)
                    if (entry.value is Map)
                      _buildClassVolumeGroup(
                        code: entry.key,
                        classRow: Map<String, dynamic>.from(entry.value as Map),
                      ),
                ],
                _buildQuoteCard(),
                _textField(
                  _emailController,
                  'E-mail para el pago y los comprobantes',
                  keyboardType: TextInputType.emailAddress,
                  hintText: 'tu@email.com',
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
                  hintText: '+5491123456789',
                  validator: _validateOptionalMobile,
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
                        loading: _submitting || _resolvingLocation,
                        onPressed: _submitting || _resolvingLocation
                            ? null
                            : _continueSignup,
                      ),
                    ),
                  ],
                ),
                if (kDebugMode && _currentStep == 2) ...[
                  BioSpacing.gapH(BioSpacing.sm),
                  TextButton.icon(
                    onPressed: _submitting || _resolvingLocation
                        ? null
                        : _startSandboxCheckout,
                    icon: const Icon(Icons.science_outlined, size: 18),
                    label: const Text(AppTexts.signupSandboxTestButton),
                  ),
                  Text(
                    AppTexts.signupSandboxTestHint,
                    textAlign: TextAlign.center,
                    style: BioTypography.bodySm.copyWith(
                      color: context.bio.textMuted,
                    ),
                  ),
                ],
              ],
              BioSpacing.gapH(BioSpacing.md),
            ],
          ),
        ),
      ),
    );
  }
}
