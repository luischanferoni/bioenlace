// lib/screens/emergency/emergency_ingreso_screen.dart
import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../../services/emergency_guardia_api.dart';
import 'dni_barcode_scan_screen.dart';

/// Admisión de paciente a guardia (pantalla nativa; no UI JSON).
class EmergencyIngresoScreen extends StatefulWidget {
  final EmergencyGuardiaApi api;
  final int? vincularGuardiaId;
  final String? vincularNombre;

  const EmergencyIngresoScreen({
    super.key,
    required this.api,
    this.vincularGuardiaId,
    this.vincularNombre,
  });

  @override
  State<EmergencyIngresoScreen> createState() => _EmergencyIngresoScreenState();
}

class _EmergencyIngresoScreenState extends State<EmergencyIngresoScreen> {
  static const _ingresaEn = [
    ('deambula', 'Deambulando (Caminando)'),
    ('silla_de_rueda', 'Silla de Rueda'),
    ('camilla', 'Camilla'),
  ];
  static const _ingresaCon = [
    ('solo', 'Solo'),
    ('familiar', 'Familiar'),
    ('policia', 'Personal Policial'),
    ('otro', 'Otro'),
    ('no_sabe', 'No sabe/No contesta'),
  ];

  final _searchController = TextEditingController();
  final _telController = TextEditingController();
  final _coberturaController = TextEditingController();
  final _situacionController = TextEditingController();

  String? _selectedPersonaId;
  String? _selectedPersonaLabel;
  List<Map<String, String>> _candidatos = [];
  bool _buscando = false;
  bool _guardando = false;
  bool _consultandoIdentidad = false;
  String? _identidadLabel;
  String? _codigoBarrasEscaneado;
  bool _nnPendiente = false;
  String _ingresaEnVal = 'deambula';
  String _ingresaConVal = 'solo';

  @override
  void dispose() {
    _searchController.dispose();
    _telController.dispose();
    _coberturaController.dispose();
    _situacionController.dispose();
    super.dispose();
  }

  bool get _telRequerido =>
      _ingresaConVal == 'familiar' ||
      _ingresaConVal == 'policia' ||
      _ingresaConVal == 'otro';

  bool get _puedeGuardar {
    if (_selectedPersonaId != null && _selectedPersonaId!.isNotEmpty) {
      return true;
    }
    if (_nnPendiente && !_esVincular) return true;
    return _codigoBarrasEscaneado != null && _codigoBarrasEscaneado!.isNotEmpty;
  }

  bool get _mostrarEscanearDni =>
      _selectedPersonaId == null &&
      !_nnPendiente &&
      _codigoBarrasEscaneado == null;

  bool get _esVincular =>
      widget.vincularGuardiaId != null && widget.vincularGuardiaId! > 0;

  bool get _mostrarResumenPaciente =>
      _selectedPersonaLabel != null ||
      (_nnPendiente && !_esVincular) ||
      (_identidadLabel != null && _selectedPersonaId == null);

  Future<void> _buscar(String q) async {
    final term = q.trim();
    if (term.length < 2) {
      setState(() => _candidatos = []);
      return;
    }
    setState(() => _buscando = true);
    try {
      final rows = await widget.api.buscarPersonaIngreso(term);
      if (!mounted) return;
      setState(() {
        _candidatos = rows;
        _buscando = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _buscando = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(userFriendlyErrorMessage(e))),
      );
    }
  }

  void _elegirPersona(Map<String, String> row) {
    setState(() {
      _selectedPersonaId = row['id'];
      _selectedPersonaLabel = row['text'];
      _identidadLabel = null;
      _codigoBarrasEscaneado = null;
      _nnPendiente = false;
      _candidatos = [];
      _searchController.text = row['text'] ?? '';
    });
  }

  void _confirmarNn() {
    setState(() {
      _nnPendiente = true;
      _selectedPersonaId = null;
      _selectedPersonaLabel = null;
      _identidadLabel = null;
      _codigoBarrasEscaneado = null;
      _candidatos = [];
    });
  }

  void _limpiarEscaneoDni() {
    _identidadLabel = null;
    _codigoBarrasEscaneado = null;
  }

  Future<void> _guardar() async {
    if (!_puedeGuardar) return;
    if (!_esVincular && _telRequerido && _telController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Indicá un teléfono de contacto.')),
      );
      return;
    }
    setState(() => _guardando = true);
    try {
      final body = <String, dynamic>{};
      if (!_esVincular) {
        body['ingresa_en'] = _ingresaEnVal;
        body['ingresa_con'] = _ingresaConVal;
      }
      final id = int.tryParse(_selectedPersonaId ?? '');
      if (id != null && id > 0) {
        body['id_persona'] = id;
      } else if (_nnPendiente && !_esVincular) {
        body['identidad_pendiente'] = true;
      } else if (_codigoBarrasEscaneado != null &&
          _codigoBarrasEscaneado!.isNotEmpty) {
        body['codigo_barras'] = _codigoBarrasEscaneado;
      }
      final tel = _telController.text.trim();
      if (tel.isNotEmpty) body['datos_contacto_tel'] = tel;
      final cob = _coberturaController.text.trim();
      if (cob.isNotEmpty) body['cobertura'] = cob;
      final sit = _situacionController.text.trim();
      if (sit.isNotEmpty) body['situacion_al_ingresar'] = sit;

      if (_esVincular) {
        await widget.api.vincularIdentidad(widget.vincularGuardiaId!, body);
      } else {
        await widget.api.ingresar(body);
      }
      if (!mounted) return;
      Navigator.pop(context, true);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(userFriendlyErrorMessage(e))),
      );
    } finally {
      if (mounted) setState(() => _guardando = false);
    }
  }

  String _labelFromPreview(Map<String, dynamic> data) {
    final identity = data['identity'] is Map
        ? Map<String, dynamic>.from(data['identity'] as Map)
        : <String, dynamic>{};
    final renaper = data['renaper'] is Map
        ? Map<String, dynamic>.from(data['renaper'] as Map)
        : <String, dynamic>{};
    String nombre = '';
    final rn = renaper['nombres'];
    if (rn is List && rn.isNotEmpty) {
      nombre = rn.first.toString();
    } else if (rn != null) {
      nombre = rn.toString();
    }
    if (nombre.isEmpty) nombre = (identity['nombre'] ?? '').toString();
    String apellido = '';
    final ra = renaper['apellido'];
    if (ra is List && ra.isNotEmpty) {
      apellido = ra.first.toString();
    } else if (ra != null) {
      apellido = ra.toString();
    }
    if (apellido.isEmpty) apellido = (identity['apellido'] ?? '').toString();
    final doc = (identity['documento'] ?? renaper['numeroDocumento'] ?? '')
        .toString();
    final fecha = (renaper['fechaNacimiento'] ??
            renaper['fecha_nacimiento'] ??
            identity['fecha_nacimiento'] ??
            '')
        .toString();
    return '${'$apellido $nombre'.trim()}${doc.isNotEmpty ? ' · DNI $doc' : ''}${fecha.isNotEmpty ? ' · $fecha' : ''}';
  }

  Future<void> _procesarCodigoBarras(String codigo) async {
    setState(() => _consultandoIdentidad = true);
    try {
      final data = await widget.api.previewRenaperComoStaff(
        codigoBarras: codigo,
      );
      if (data['encontrado'] != true) {
        throw Exception(
          data['mensaje'] ?? 'No se encontró la persona en RENAPER.',
        );
      }
      if (!mounted) return;
      setState(() {
        _codigoBarrasEscaneado = codigo;
        _nnPendiente = false;
        _selectedPersonaId = null;
        _selectedPersonaLabel = null;
        _identidadLabel = _labelFromPreview(data);
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _identidadLabel = null;
        _codigoBarrasEscaneado = null;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(userFriendlyErrorMessage(e))),
      );
    } finally {
      if (mounted) setState(() => _consultandoIdentidad = false);
    }
  }

  Future<void> _escanearDni() async {
    final codigo = await Navigator.push<String>(
      context,
      MaterialPageRoute(builder: (_) => const DniBarcodeScanScreen()),
    );
    if (!mounted || codigo == null || codigo.trim().isEmpty) return;
    await _procesarCodigoBarras(codigo.trim());
  }

  Widget _buildPacienteResumen() {
    if (!_mostrarResumenPaciente) return const SizedBox.shrink();

    final UiIntent intent;
    final String title;
    final String body;

    if (_selectedPersonaLabel != null) {
      intent = UiIntent.success;
      title = 'Paciente seleccionado';
      body = _selectedPersonaLabel!;
    } else if (_nnPendiente) {
      intent = UiIntent.warning;
      title = 'Identidad pendiente (NN)';
      body = 'Se vincula cuando aparezca el DNI.';
    } else {
      intent = UiIntent.success;
      title = 'Paciente detectado';
      body = _identidadLabel ?? '';
    }

    final palette = IntentPalette.of(intent);
    return BioCard.intent(
      intent: intent,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            title,
            style: BioTypography.caption.copyWith(
              color: palette.base,
              fontWeight: FontWeight.w600,
              letterSpacing: 0.4,
            ),
          ),
          BioSpacing.gapH(BioSpacing.xs),
          Text(
            body,
            style: BioTypography.title.copyWith(fontWeight: FontWeight.w600),
          ),
          if (_identidadLabel != null && _selectedPersonaId == null) ...[
            BioSpacing.gapH(BioSpacing.sm),
            Align(
              alignment: Alignment.centerLeft,
              child: TextButton(
                onPressed: _consultandoIdentidad ? null : _escanearDni,
                child: const Text('Escanear otro DNI'),
              ),
            ),
          ],
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          _esVincular
              ? 'Identificar ${widget.vincularNombre ?? 'NN'}'
              : 'Ingresar paciente a guardia',
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.all(BioSpacing.md),
        children: [
          TextField(
            controller: _searchController,
            decoration: InputDecoration(
              labelText: 'Buscar paciente',
              hintText: 'Apellido o documento',
              suffixIcon: _buscando
                  ? const Padding(
                      padding: EdgeInsets.all(12),
                      child: SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      ),
                    )
                  : const Icon(Icons.search),
            ),
            onChanged: (v) {
              setState(() {
                _selectedPersonaId = null;
                _selectedPersonaLabel = null;
                _nnPendiente = false;
                _limpiarEscaneoDni();
              });
              _buscar(v);
            },
          ),
          if (_candidatos.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.sm),
            ..._candidatos.map(
              (c) => ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(c['text'] ?? ''),
                onTap: () => _elegirPersona(c),
              ),
            ),
          ],
          if (!_esVincular &&
              _selectedPersonaId == null &&
              !_nnPendiente &&
              _codigoBarrasEscaneado == null) ...[
            BioSpacing.gapH(BioSpacing.sm),
            OutlinedButton(
              onPressed: _confirmarNn,
              style: OutlinedButton.styleFrom(
                foregroundColor: Theme.of(context).colorScheme.error,
                side: BorderSide(
                  color: Theme.of(context).colorScheme.error.withValues(alpha: 0.5),
                ),
              ),
              child: const Text('Sin documento / NN'),
            ),
          ],
          if (_mostrarEscanearDni) ...[
            BioSpacing.gapH(BioSpacing.md),
            OutlinedButton.icon(
              onPressed: _consultandoIdentidad ? null : _escanearDni,
              icon: const Icon(Icons.document_scanner_outlined),
              label: Text(
                _consultandoIdentidad ? 'Consultando RENAPER…' : 'Escanear DNI',
              ),
            ),
          ],
          if (_mostrarResumenPaciente) ...[
            BioSpacing.gapH(BioSpacing.md),
            _buildPacienteResumen(),
          ],
          if (!_esVincular) ...[
            BioSpacing.gapH(BioSpacing.md),
            DropdownButtonFormField<String>(
              initialValue: _ingresaEnVal,
              decoration: const InputDecoration(labelText: 'Ingresa en'),
              items: _ingresaEn
                  .map(
                    (e) => DropdownMenuItem(value: e.$1, child: Text(e.$2)),
                  )
                  .toList(),
              onChanged: (v) => setState(() => _ingresaEnVal = v ?? 'deambula'),
            ),
            BioSpacing.gapH(BioSpacing.sm),
            DropdownButtonFormField<String>(
              initialValue: _ingresaConVal,
              decoration: const InputDecoration(labelText: 'Ingresa con'),
              items: _ingresaCon
                  .map(
                    (e) => DropdownMenuItem(value: e.$1, child: Text(e.$2)),
                  )
                  .toList(),
              onChanged: (v) => setState(() {
                _ingresaConVal = v ?? 'solo';
              }),
            ),
            if (_telRequerido) ...[
              BioSpacing.gapH(BioSpacing.sm),
              TextField(
                controller: _telController,
                decoration: const InputDecoration(
                  labelText: 'Teléfono de contacto',
                ),
              ),
            ],
            BioSpacing.gapH(BioSpacing.sm),
            TextField(
              controller: _coberturaController,
              decoration: const InputDecoration(
                labelText: 'Cobertura (opcional)',
              ),
            ),
            BioSpacing.gapH(BioSpacing.sm),
            TextField(
              controller: _situacionController,
              decoration: const InputDecoration(
                labelText: 'Situación al ingresar (opcional)',
              ),
              maxLines: 2,
            ),
          ],
          BioSpacing.gapH(BioSpacing.lg),
          FilledButton(
            onPressed: _guardando || !_puedeGuardar ? null : _guardar,
            child: Text(
              _guardando
                  ? (_esVincular ? 'Vinculando…' : 'Registrando…')
                  : (_esVincular ? 'Vincular identidad' : 'Registrar ingreso'),
            ),
          ),
        ],
      ),
    );
  }
}
