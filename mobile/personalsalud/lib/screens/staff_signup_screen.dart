import 'dart:async';

import 'package:flutter/material.dart';
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
  String? _pendingVerificationId;

  bool _loadingProvinces = true;
  bool _loadingCenters = false;
  String? _centersError;
  String? _provincesError;
  List<Map<String, dynamic>> _provinces = const [];
  List<Map<String, dynamic>> _centers = const [];
  String? _centerSector;
  int? _provinceId;
  Map<String, dynamic>? _selectedCenter;
  Timer? _centerSearchDebounce;
  int _centerSearchRequestId = 0;

  /// La búsqueda de centros sólo se dispara con más de 3 caracteres.
  static const int _minCenterSearchLength = 4;

  bool _instantCloseFreeTile = false;
  bool _instantCloseSpaceTile = false;

  @override
  void initState() {
    super.initState();
    _loadProvinces();
    //_loadCenters();
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

  String get _centerQuery => _centerSearchController.text.trim();

  bool get _canSearchCenters => _centerQuery.length >= _minCenterSearchLength;

  /// Cierra el tile hermano sin animación.
  ///
  /// Si ambos animan a la vez (uno se pliega arriba y el otro se abre), el tile
  /// que el usuario abrió "viaja" por la pantalla mientras se revela: se
  /// percibe como un delay. Cerrar el hermano de golpe deja una sola animación.
  void _closeOtherTile(
    ExpansibleController controller, {
    required bool isFree,
  }) {
    setState(() {
      if (isFree) {
        _instantCloseFreeTile = true;
      } else {
        _instantCloseSpaceTile = true;
      }
    });
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      controller.collapse();
      setState(() {
        if (isFree) {
          _instantCloseFreeTile = false;
        } else {
          _instantCloseSpaceTile = false;
        }
      });
    });
  }

  void _scheduleCenterSearch() {
    _centerSearchDebounce?.cancel();
    _centerSearchDebounce = Timer(
      const Duration(milliseconds: 350),
      _loadCenters,
    );
  }

  Future<void> _loadCenters() async {
    if (!_canSearchCenters) {
      _centerSearchDebounce?.cancel();
      if (!mounted) return;
      setState(() {
        _centers = const [];
        _centersError = null;
        _loadingCenters = false;
        _centerSearchRequestId++;
      });
      return;
    }
    final requestId = ++_centerSearchRequestId;
    setState(() {
      _loadingCenters = true;
      _centersError = null;
    });
    try {
      final centers = await _signupService.searchCenters(
        query: _centerQuery,
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
      MaterialPageRoute(builder: (_) => const CommercialSignupScreen()),
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
          'Guardaremos tu solicitud para '
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
          onChanged: (text) {
            _centerSearchDebounce?.cancel();
            final canSearch = text.trim().length > 3;
            setState(() {
              if (!canSearch) {
                _centers = const [];
                _centersError = null;
                _loadingCenters = false;
                _centerSearchRequestId++;
              }
            });
            if (canSearch) _scheduleCenterSearch();
          },
          decoration: InputDecoration(
            labelText: 'Buscar centro',
            hintText: 'Nombre',
            helperText: 'Ingresá al menos $_minCenterSearchLength caracteres',
            border: const OutlineInputBorder(),
            suffixIcon: IconButton(
              onPressed: _loadingCenters || !_canSearchCenters
                  ? null
                  : _loadCenters,
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
                  onPressed: _canSearchCenters ? _loadCenters : null,
                  child: const Text('Reintentar'),
                ),
              ),
            ],
          )
        else if (_centers.isEmpty && _canSearchCenters)
          Text(
            'No encontramos centros con esos filtros.',
            style: BioTypography.bodySm.copyWith(
              color: IntentPalette.of(UiIntent.warning).base,
            ),
            textAlign: TextAlign.center,
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
                Material(
                  color: tokens.paperSurface,
                  clipBehavior: Clip.antiAlias,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                    side: BorderSide(color: tokens.paperDividerDefault),
                  ),
                  child: ExpansionTile(
                    controller: _freeTileController,
                    initiallyExpanded: true,
                    expansionAnimationStyle: _instantCloseFreeTile
                        ? AnimationStyle.noAnimation
                        : null,
                    onExpansionChanged: (expanded) {
                      if (!expanded) return;
                      _closeOtherTile(_spaceTileController, isFree: false);
                    },
                    title: Text(
                      'Cuenta personal gratis',
                      style: BioTypography.title,
                    ),
                    subtitle: Text('GRATIS', style: BioTypography.caption),
                    childrenPadding: const EdgeInsets.fromLTRB(
                      BioSpacing.lg,
                      BioSpacing.lg,
                      BioSpacing.md,
                      BioSpacing.md,
                    ),
                    children: [
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
                Material(
                  color: tokens.paperSurface,
                  clipBehavior: Clip.antiAlias,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                    side: BorderSide(color: tokens.paperDividerDefault),
                  ),
                  child: ExpansionTile(
                    controller: _spaceTileController,
                    expansionAnimationStyle: _instantCloseSpaceTile
                        ? AnimationStyle.noAnimation
                        : null,
                    onExpansionChanged: (expanded) {
                      if (!expanded) return;
                      _closeOtherTile(_freeTileController, isFree: true);
                    },
                    title: Text(
                      'Crear espacio de atención',
                      style: BioTypography.title,
                    ),
                    subtitle: Text(
                      'SUSCRIPCIÓN MENSUAL',
                      style: BioTypography.caption,
                    ),
                    childrenPadding: const EdgeInsets.fromLTRB(
                      BioSpacing.lg,
                      BioSpacing.lg,
                      BioSpacing.md,
                      BioSpacing.md,
                    ),
                    children: [
                      Text(
                        'Seguí los pasos para crear un espacio de atención pago. '
                        'Presioná el boton de abajo para comenzar a crear tu centro de atención. ',
                        style: BioTypography.bodyLg,
                      ),
                      BioSpacing.gapH(BioSpacing.md),
                      BioButton.primary(
                        label: 'Comenzar',
                        icon: Icons.arrow_forward,
                        size: BioButtonSize.lg,
                        fullWidth: true,
                        onPressed: _openCommercialSignup,
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
