import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:shared/shared.dart';

import 'commercial_signup_screen.dart';
import '../services/staff_signup_service.dart';

/// Alta personal gratuita o inicio de un alta paga.
class StaffSignupScreen extends StatefulWidget {
  const StaffSignupScreen({super.key});

  @override
  State<StaffSignupScreen> createState() => _StaffSignupScreenState();
}

class _StaffSignupScreenState extends State<StaffSignupScreen> {
  final StaffSignupService _signupService = StaffSignupService();
  final ExpansibleController _freeTileController = ExpansibleController();
  final ExpansibleController _spaceTileController = ExpansibleController();
  final TextEditingController _centerSearchController = TextEditingController();

  bool _submitting = false;
  bool _accountCreated = false;
  bool _loadingQuote = true;
  Map<String, dynamic>? _spaceQuote;
  String? _spaceQuoteError;
  String? _pendingVerificationId;

  bool _loadingProvinces = true;
  bool _loadingCenters = true;
  String? _centersError;
  String? _provincesError;
  List<Map<String, dynamic>> _provinces = const [];
  List<Map<String, dynamic>> _centers = const [];
  String? _centerSector;
  int? _provinceId;
  Map<String, dynamic>? _selectedCenter;
  Timer? _centerSearchDebounce;
  int _centerSearchRequestId = 0;

  @override
  void initState() {
    super.initState();
    _refreshSpaceQuote();
    _loadProvinces();
    _loadCenters();
  }

  @override
  void dispose() {
    _centerSearchDebounce?.cancel();
    _freeTileController.dispose();
    _spaceTileController.dispose();
    _centerSearchController.dispose();
    super.dispose();
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

  String _catalogErrorText(Object error, String resource) {
    final fallback =
        'No pudimos cargar $resource. Reintentá en unos instantes.';
    if (error is ApiContractException) {
      final code = error.statusCode ?? 0;
      if (code == 0 || code == 404 || code >= 500) return fallback;
      return error.message;
    }
    return userFriendlyErrorMessage(error, fallback: fallback);
  }

  Map<String, dynamic> _initialSpacePlan() => {
    'classes': {
      'AMB': {'attentions_per_month': 200, 'videollamada_permitida': false},
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

  Future<void> _loadProvinces() async {
    setState(() {
      _loadingProvinces = true;
      _provincesError = null;
    });
    try {
      final provinces = await _signupService.fetchProvinces();
      if (!mounted) return;
      setState(() {
        _provinces = provinces;
        _loadingProvinces = false;
        if (!_provinceExists(provinces, _provinceId)) _provinceId = null;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _provinces = const [];
        _loadingProvinces = false;
        _provincesError = _catalogErrorText(error, 'las provincias');
      });
    }
  }

  void _scheduleCenterSearch() {
    _centerSearchDebounce?.cancel();
    _centerSearchDebounce = Timer(
      const Duration(milliseconds: 350),
      _loadCenters,
    );
  }

  Future<void> _loadCenters() async {
    final requestId = ++_centerSearchRequestId;
    setState(() {
      _loadingCenters = true;
      _centersError = null;
    });
    try {
      final centers = await _signupService.searchCenters(
        query: _centerSearchController.text,
        sector: _centerSector,
        idProvincia: _provinceId,
      );
      if (!mounted || requestId != _centerSearchRequestId) return;
      setState(() {
        _centers = centers;
        _loadingCenters = false;
        if (_provinces.isEmpty) {
          final derived = _provincesFromCenters(centers);
          if (derived.isNotEmpty) {
            _provinces = derived;
            _provincesError = null;
            if (!_provinceExists(derived, _provinceId)) _provinceId = null;
          }
        }
      });
    } catch (error) {
      if (!mounted || requestId != _centerSearchRequestId) return;
      setState(() {
        _loadingCenters = false;
        _centersError = _catalogErrorText(error, 'los centros');
      });
    }
  }

  bool _provinceExists(List<Map<String, dynamic>> provinces, int? id) {
    if (id == null) return true;
    return provinces.any(
      (province) => int.tryParse(province['id']?.toString() ?? '') == id,
    );
  }

  List<Map<String, dynamic>> _provincesFromCenters(
    List<Map<String, dynamic>> centers,
  ) {
    final seen = <int>{};
    final derived = <Map<String, dynamic>>[];
    for (final center in centers) {
      final id = int.tryParse(center['id_provincia']?.toString() ?? '');
      final name = center['provincia']?.toString().trim() ?? '';
      if (id == null || id <= 0 || name.isEmpty || !seen.add(id)) continue;
      derived.add({'id': id, 'nombre': name});
    }
    derived.sort(
      (a, b) => a['nombre'].toString().compareTo(b['nombre'].toString()),
    );
    return derived;
  }

  Future<void> _createPersonalAccount() async {
    final center = _selectedCenter;
    final idEfector = int.tryParse(center?['id_efector']?.toString() ?? '');
    if (idEfector == null || idEfector <= 0) {
      _snack('Elegí el centro de salud al que pertenecés.');
      return;
    }

    var identityCreated = false;
    String? verificationId;
    var reusedVerification = false;
    setState(() => _submitting = true);
    try {
      verificationId = await _signupService.readPendingVerificationId();
      reusedVerification = verificationId != null;
      verificationId ??= await _signupService.verifyIdentity();
      await _signupService.storePendingVerificationId(verificationId);
      reusedVerification = true;
      if (mounted) setState(() => _pendingVerificationId = verificationId);
      await _signupService.createAccount(
        verificationId: verificationId,
        idEfector: idEfector,
      );
      identityCreated = true;
      await _signupService.clearPendingVerificationId();
      if (!mounted) return;
      setState(() {
        _accountCreated = true;
        _submitting = false;
        _pendingVerificationId = null;
      });
    } catch (error) {
      if (error is ApiContractException && error.statusCode == 422) {
        try {
          await _signupService.clearPendingVerificationId();
        } catch (storageError) {
          _snack(
            'La verificación no se puede reutilizar y no pudimos borrar su '
            'referencia segura: $storageError',
          );
          if (mounted) setState(() => _submitting = false);
          return;
        }
        _pendingVerificationId = null;
        reusedVerification = false;
      }
      final message = error.toString().replaceFirst('Exception: ', '');
      if (identityCreated) {
        _snack(
          'Tu cuenta ya existe, pero no pudimos registrar el centro. '
          'Volvé a intentar: reutilizaremos tu verificación.',
        );
        if (mounted) setState(() => _submitting = false);
        return;
      }
      _snack(
        message == 'Verificación cancelada.'
            ? 'Cancelaste la verificación.'
            : reusedVerification
            ? 'Tu identidad ya fue verificada, pero no pudimos crear la cuenta. '
                  'Reintentá sin volver a verificarte.'
            : 'No pudimos continuar con la creación de tu cuenta. Intentá nuevamente. '
                  'Si el problema continúa, contactá a soporte.',
      );
      if (mounted) setState(() => _submitting = false);
    }
  }

  void _openCommercialSignup() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) =>
            CommercialSignupScreen(initialPlan: _initialSpacePlan()),
      ),
    );
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

  Widget _buildSectorFilter() {
    final options = <(String, String)>[
      ('PUBLICO', 'Público'),
      ('PRIVADO', 'Privado'),
    ];
    return Wrap(
      spacing: BioSpacing.sm,
      children: options.map((option) {
        final selected = _centerSector == option.$1;
        return ChoiceChip(
          label: Text(option.$2),
          selected: selected,
          onSelected: (_) {
            setState(() {
              _centerSector = selected ? null : option.$1;
              _selectedCenter = null;
            });
            _loadCenters();
          },
        );
      }).toList(),
    );
  }

  Widget _buildCenterPicker() {
    final tokens = context.bio;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text('¿En qué centro de salud trabajás?', style: BioTypography.title),
        BioSpacing.gapH(BioSpacing.xs),
        Text(
          'Elegí un centro activo en Bioenlace. Guardaremos tu solicitud para '
          'que la administración del centro pueda incorporarte.',
          style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
        ),
        BioSpacing.gapH(BioSpacing.md),
        _buildSectorFilter(),
        BioSpacing.gapH(BioSpacing.sm),
        if (_provincesError != null)
          Padding(
            padding: const EdgeInsets.only(bottom: BioSpacing.sm),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    _provincesError!,
                    style: BioTypography.bodySm.copyWith(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                ),
                TextButton(
                  onPressed: _loadingProvinces ? null : _loadProvinces,
                  child: const Text('Reintentar'),
                ),
              ],
            ),
          ),
        DropdownButtonFormField<int>(
          initialValue: _provinceId ?? 0,
          isExpanded: true,
          decoration: const InputDecoration(
            labelText: 'Provincia',
            border: OutlineInputBorder(),
          ),
          items: [
            const DropdownMenuItem<int>(
              value: 0,
              child: Text('Todas las provincias'),
            ),
            ..._provinces
                .map((province) {
                  final id = int.tryParse(province['id']?.toString() ?? '');
                  return DropdownMenuItem<int>(
                    value: id,
                    child: Text(province['nombre']?.toString() ?? ''),
                  );
                })
                .where((item) => item.value != null),
          ],
          onChanged: _loadingProvinces || _provinces.isEmpty
              ? null
              : (value) {
                  setState(() {
                    _provinceId = value == 0 ? null : value;
                    _selectedCenter = null;
                  });
                  _loadCenters();
                },
        ),
        BioSpacing.gapH(BioSpacing.sm),
        TextFormField(
          controller: _centerSearchController,
          textInputAction: TextInputAction.search,
          onFieldSubmitted: (_) => _loadCenters(),
          onChanged: (_) => _scheduleCenterSearch(),
          decoration: InputDecoration(
            labelText: 'Buscar centro',
            hintText: 'Nombre, código SISA o localidad',
            border: const OutlineInputBorder(),
            suffixIcon: IconButton(
              onPressed: _loadingCenters ? null : _loadCenters,
              icon: const Icon(Icons.search),
            ),
          ),
        ),
        BioSpacing.gapH(BioSpacing.sm),
        if (_loadingCenters)
          const Padding(
            padding: EdgeInsets.all(BioSpacing.md),
            child: Center(child: CircularProgressIndicator()),
          )
        else if (_centersError != null)
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(_centersError!, style: BioTypography.bodySm),
              Align(
                alignment: Alignment.centerRight,
                child: TextButton(
                  onPressed: _loadCenters,
                  child: const Text('Reintentar'),
                ),
              ),
            ],
          )
        else if (_centers.isEmpty)
          Text(
            'No encontramos centros con esos filtros.',
            style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
          )
        else
          ..._centers.map((center) {
            final id = int.tryParse(center['id_efector']?.toString() ?? '');
            final selected =
                _selectedCenter?['id_efector']?.toString() ==
                center['id_efector']?.toString();
            final location = [
              center['localidad']?.toString() ?? '',
              center['provincia']?.toString() ?? '',
            ].where((value) => value.isNotEmpty).join(' · ');
            final sector = center['sector']?.toString() == 'PRIVADO'
                ? 'Privado'
                : 'Público';
            return Card(
              child: ListTile(
                selected: selected,
                title: Text(center['nombre']?.toString() ?? ''),
                subtitle: Text(
                  [
                    sector,
                    if (location.isNotEmpty) location,
                    if ((center['codigo_sisa']?.toString() ?? '').isNotEmpty)
                      'SISA ${center['codigo_sisa']}',
                  ].join(' · '),
                ),
                trailing: selected ? const Icon(Icons.check_circle) : null,
                onTap: id == null
                    ? null
                    : () => setState(() {
                        _selectedCenter = center;
                      }),
              ),
            );
          }),
        if (_selectedCenter != null) ...[
          BioSpacing.gapH(BioSpacing.sm),
          Text(
            'Centro seleccionado: ${_selectedCenter!['nombre']}',
            style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
          ),
        ],
      ],
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
                    controller: _freeTileController,
                    initiallyExpanded: true,
                    onExpansionChanged: (expanded) {
                      if (expanded) _spaceTileController.collapse();
                    },
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
                        'Crea tu cuenta de personal de salud y elegí el centro '
                        'donde trabajás. Un efector debe asignarte un rol '
                        'clínico para habilitar las funciones asistenciales.',
                        style: BioTypography.body,
                      ),
                      BioSpacing.gapH(BioSpacing.md),
                      _buildCenterPicker(),
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
                        onPressed: _submitting || _selectedCenter == null
                            ? null
                            : _createPersonalAccount,
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
                    controller: _spaceTileController,
                    onExpansionChanged: (expanded) {
                      if (expanded) _freeTileController.collapse();
                    },
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
                        label: 'Crear espacio de atención',
                        icon: Icons.arrow_forward,
                        size: BioButtonSize.lg,
                        fullWidth: true,
                        onPressed: _loadingQuote ? null : _openCommercialSignup,
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
                    'Tu cuenta personal gratuita está lista y registramos tu '
                    'solicitud para el centro elegido. Cuando la administración '
                    'del centro te asigne un rol clínico, podrás ingresar a las '
                    'funciones asistenciales. '
                    '¿Querías crear un espacio de atención pago? Volvé al inicio, '
                    'elegí Crear cuenta y seleccioná una opción paga.',
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
