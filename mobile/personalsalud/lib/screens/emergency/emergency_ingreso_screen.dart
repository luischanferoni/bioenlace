// lib/screens/emergency/emergency_ingreso_screen.dart
import 'dart:async';

import 'package:didit_sdk/sdk_flutter.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:shared/platform/didit_platform.dart';
import 'package:shared/shared.dart';

import '../../services/emergency_guardia_api.dart';

/// Admisión de paciente a guardia (pantalla nativa; no UI JSON).
class EmergencyIngresoScreen extends StatefulWidget {
  final EmergencyGuardiaApi api;
  final int? vincularGuardiaId;
  final String? vincularNombre;

  const EmergencyIngresoScreen({
    Key? key,
    required this.api,
    this.vincularGuardiaId,
    this.vincularNombre,
  }) : super(key: key);

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
  final _documentoController = TextEditingController();
  final _barcodeController = TextEditingController();
  final _telController = TextEditingController();
  final _coberturaController = TextEditingController();
  final _situacionController = TextEditingController();

  String? _selectedPersonaId;
  String? _selectedPersonaLabel;
  List<Map<String, String>> _candidatos = [];
  bool _altaVisible = false;
  bool _buscando = false;
  bool _guardando = false;
  bool _consultandoIdentidad = false;
  bool _iniciandoDidit = false;
  String? _identidadLabel;
  String? _verificationId;
  bool _nnPendiente = false;
  String _ingresaEnVal = 'deambula';
  String _ingresaConVal = 'solo';
  String _sexoVal = '';

  @override
  void dispose() {
    _searchController.dispose();
    _documentoController.dispose();
    _barcodeController.dispose();
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
    if (!_altaVisible) return false;
    if (_nnPendiente) return true;
    if (_verificationId != null && _verificationId!.isNotEmpty) return true;
    return _identidadLabel != null && _identidadLabel!.isNotEmpty;
  }

  bool get _esVincular =>
      widget.vincularGuardiaId != null && widget.vincularGuardiaId! > 0;

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

  @override
  void initState() {
    super.initState();
    if (_esVincular) {
      _altaVisible = true;
    }
  }

  void _elegirPersona(Map<String, String> row) {
    setState(() {
      _selectedPersonaId = row['id'];
      _selectedPersonaLabel = row['text'];
      _altaVisible = false;
      _identidadLabel = null;
      _verificationId = null;
      _nnPendiente = false;
      _candidatos = [];
      _searchController.text = row['text'] ?? '';
    });
  }

  void _mostrarAlta() {
    setState(() {
      _altaVisible = true;
      _selectedPersonaId = null;
      _selectedPersonaLabel = null;
      _identidadLabel = null;
      _verificationId = null;
      _nnPendiente = false;
      final term = _searchController.text.trim();
      if (RegExp(r'^\d+$').hasMatch(term)) {
        _documentoController.text = term;
      }
    });
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
      } else if (_verificationId != null && _verificationId!.isNotEmpty) {
        body['verification_id'] = _verificationId;
      } else {
        final barcode = _barcodeController.text.trim();
        final documento = _documentoController.text.trim();
        if (barcode.isNotEmpty) body['codigo_barras'] = barcode;
        if (documento.isNotEmpty) body['documento'] = documento;
        if (_sexoVal.isNotEmpty) {
          body['sexo_biologico'] = int.tryParse(_sexoVal);
        }
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

  Future<void> _consultarIdentidad() async {
    final barcode = _barcodeController.text.trim();
    final documento = _documentoController.text.trim();
    if (barcode.isEmpty && (documento.isEmpty || _sexoVal.isEmpty)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Indicá documento y sexo, o el código de barras del DNI.'),
        ),
      );
      return;
    }
    setState(() => _consultandoIdentidad = true);
    try {
      final data = await widget.api.previewRenaperComoStaff(
        documento: documento.isEmpty ? null : documento,
        sexoBiologico: _sexoVal.isEmpty ? null : int.tryParse(_sexoVal),
        codigoBarras: barcode.isEmpty ? null : barcode,
      );
      if (data['encontrado'] != true) {
        throw Exception(
          data['mensaje'] ?? 'No se encontró la persona en RENAPER.',
        );
      }
      if (!mounted) return;
      setState(() {
        _verificationId = null;
        _nnPendiente = false;
        _identidadLabel = _labelFromPreview(data);
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _identidadLabel = null);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(userFriendlyErrorMessage(e))),
      );
    } finally {
      if (mounted) setState(() => _consultandoIdentidad = false);
    }
  }

  Future<void> _identificarConDidit() async {
    if (!isDiditSupported) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text(diditUnsupportedPlatformMessage)),
      );
      return;
    }
    final workflowId = await DiditConfigResolver.resolvePacienteKycWorkflowId();
    if (!mounted) return;
    if (workflowId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Didit no está configurado en el servidor. Usá el DNI o contactá a soporte.',
          ),
        ),
      );
      return;
    }
    setState(() => _iniciandoDidit = true);
    try {
      final result = await DiditSdk.startVerificationWithWorkflow(
        workflowId,
        config: const DiditConfig(
          languageCode: 'es',
          loggingEnabled: true,
        ),
      ).timeout(const Duration(minutes: 10));
      if (!mounted) return;
      switch (result) {
        case VerificationCompleted(:final session):
          if (session.status != VerificationStatus.approved) {
            throw Exception(
              'La verificación Didit quedó en ${session.status.name}.',
            );
          }
          setState(() {
            _verificationId = session.sessionId;
            _identidadLabel = 'Didit aprobado';
          });
        case VerificationCancelled():
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Verificación Didit cancelada.')),
          );
        case VerificationFailed(:final error):
          throw Exception(error.message);
      }
    } on MissingPluginException {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text(diditMissingPluginMessage)),
      );
    } on TimeoutException {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Didit tardó demasiado. Intentá de nuevo.'),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(userFriendlyErrorMessage(e))),
      );
    } finally {
      if (mounted) setState(() => _iniciandoDidit = false);
    }
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
              labelText: 'Paciente conocido',
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
              });
              _buscar(v);
            },
          ),
          if (_selectedPersonaLabel != null) ...[
            BioSpacing.gapH(BioSpacing.sm),
            Text('Seleccionado: $_selectedPersonaLabel',
                style: BioTypography.bodySm),
          ],
          if (_candidatos.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.sm),
            ..._candidatos.map(
              (c) => ListTile(
                title: Text(c['text'] ?? ''),
                onTap: () => _elegirPersona(c),
              ),
            ),
          ],
          if (!_altaVisible)
            TextButton(
              onPressed: _mostrarAlta,
              child: const Text('Identificar con DNI'),
            ),
          if (_altaVisible) ...[
            BioSpacing.gapH(BioSpacing.md),
            Text('Identidad con DNI', style: BioTypography.title),
            BioSpacing.gapH(BioSpacing.sm),
            TextField(
              controller: _barcodeController,
              decoration: const InputDecoration(
                labelText: 'Código de barras del DNI (opcional)',
              ),
              onChanged: (_) => setState(() {
                _identidadLabel = null;
                _verificationId = null;
                _nnPendiente = false;
              }),
            ),
            BioSpacing.gapH(BioSpacing.sm),
            TextField(
              controller: _documentoController,
              decoration: const InputDecoration(labelText: 'Documento'),
              keyboardType: TextInputType.number,
              onChanged: (_) => setState(() {
                _identidadLabel = null;
                _verificationId = null;
                _nnPendiente = false;
              }),
            ),
            BioSpacing.gapH(BioSpacing.sm),
            DropdownButtonFormField<String>(
              value: _sexoVal.isEmpty ? null : _sexoVal,
              decoration: const InputDecoration(labelText: 'Sexo (como en el DNI)'),
              items: const [
                DropdownMenuItem(value: '1', child: Text('Femenino')),
                DropdownMenuItem(value: '2', child: Text('Masculino')),
              ],
              onChanged: (v) => setState(() {
                _sexoVal = v ?? '';
                _identidadLabel = null;
                _verificationId = null;
                _nnPendiente = false;
              }),
            ),
            BioSpacing.gapH(BioSpacing.sm),
            OutlinedButton(
              onPressed: _consultandoIdentidad ? null : _consultarIdentidad,
              child: Text(
                _consultandoIdentidad ? 'Consultando…' : 'Consultar identidad',
              ),
            ),
            BioSpacing.gapH(BioSpacing.sm),
            OutlinedButton(
              onPressed: _iniciandoDidit ? null : _identificarConDidit,
              child: Text(
                _iniciandoDidit ? 'Abriendo Didit…' : 'Foto del DNI (Didit)',
              ),
            ),
            if (!_esVincular) ...[
              BioSpacing.gapH(BioSpacing.sm),
              OutlinedButton(
                onPressed: () => setState(() {
                  _nnPendiente = true;
                  _verificationId = null;
                  _identidadLabel = 'Identidad pendiente (NN)';
                }),
                child: const Text('Sin documento / NN'),
              ),
            ],
            if (_identidadLabel != null) ...[
              BioSpacing.gapH(BioSpacing.sm),
              Text('Identidad: $_identidadLabel',
                  style: BioTypography.bodySm),
            ],
          ],
          if (!_esVincular) ...[
          BioSpacing.gapH(BioSpacing.md),
          DropdownButtonFormField<String>(
            value: _ingresaEnVal,
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
            value: _ingresaConVal,
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
