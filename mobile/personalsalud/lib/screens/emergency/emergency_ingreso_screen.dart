// lib/screens/emergency/emergency_ingreso_screen.dart
import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../../services/emergency_guardia_api.dart';

/// Admisión de paciente a guardia (pantalla nativa; no UI JSON).
class EmergencyIngresoScreen extends StatefulWidget {
  final EmergencyGuardiaApi api;

  const EmergencyIngresoScreen({
    Key? key,
    required this.api,
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
  final _apellidoController = TextEditingController();
  final _nombreController = TextEditingController();
  final _documentoController = TextEditingController();
  final _telController = TextEditingController();
  final _coberturaController = TextEditingController();
  final _situacionController = TextEditingController();

  String? _selectedPersonaId;
  String? _selectedPersonaLabel;
  List<Map<String, String>> _candidatos = [];
  bool _altaVisible = false;
  bool _buscando = false;
  bool _guardando = false;
  String _ingresaEnVal = 'deambula';
  String _ingresaConVal = 'solo';
  DateTime? _fechaNac;
  String _sexoVal = '';

  @override
  void dispose() {
    _searchController.dispose();
    _apellidoController.dispose();
    _nombreController.dispose();
    _documentoController.dispose();
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
    return _apellidoController.text.trim().isNotEmpty &&
        _nombreController.text.trim().isNotEmpty &&
        _documentoController.text.trim().isNotEmpty &&
        _fechaNac != null &&
        _sexoVal.isNotEmpty;
  }

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
      _altaVisible = false;
      _candidatos = [];
      _searchController.text = row['text'] ?? '';
    });
  }

  void _mostrarAlta() {
    setState(() {
      _altaVisible = true;
      _selectedPersonaId = null;
      _selectedPersonaLabel = null;
      final term = _searchController.text.trim();
      if (RegExp(r'^\d+$').hasMatch(term)) {
        _documentoController.text = term;
      } else if (term.isNotEmpty) {
        _apellidoController.text = term;
      }
    });
  }

  Future<void> _guardar() async {
    if (!_puedeGuardar) return;
    if (_telRequerido && _telController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Indicá un teléfono de contacto.')),
      );
      return;
    }
    setState(() => _guardando = true);
    try {
      final body = <String, dynamic>{
        'ingresa_en': _ingresaEnVal,
        'ingresa_con': _ingresaConVal,
      };
      final id = int.tryParse(_selectedPersonaId ?? '');
      if (id != null && id > 0) {
        body['id_persona'] = id;
      } else {
        body['apellido'] = _apellidoController.text.trim();
        body['nombre'] = _nombreController.text.trim();
        body['documento'] = _documentoController.text.trim();
        body['fecha_nacimiento'] =
            '${_fechaNac!.year.toString().padLeft(4, '0')}-${_fechaNac!.month.toString().padLeft(2, '0')}-${_fechaNac!.day.toString().padLeft(2, '0')}';
        body['sexo_biologico'] = _sexoVal;
      }
      final tel = _telController.text.trim();
      if (tel.isNotEmpty) body['datos_contacto_tel'] = tel;
      final cob = _coberturaController.text.trim();
      if (cob.isNotEmpty) body['cobertura'] = cob;
      final sit = _situacionController.text.trim();
      if (sit.isNotEmpty) body['situacion_al_ingresar'] = sit;

      await widget.api.ingresar(body);
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

  Future<void> _elegirFechaNac() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _fechaNac ?? DateTime(now.year - 30),
      firstDate: DateTime(1900),
      lastDate: now,
    );
    if (picked != null) setState(() => _fechaNac = picked);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Ingresar paciente a guardia'),
      ),
      body: ListView(
        padding: const EdgeInsets.all(BioSpacing.md),
        children: [
          Text(
            'Buscá por apellido o documento. Si no está en el sistema, registralo. '
            'Quien ya está en la cola de este efector no aparece.',
            style: BioTypography.bodySm,
          ),
          BioSpacing.gapH(BioSpacing.md),
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
                style: BioTypography.bodySecondary),
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
              child: const Text('El paciente no está en el sistema'),
            ),
          if (_altaVisible) ...[
            BioSpacing.gapH(BioSpacing.md),
            Text('Alta mínima', style: BioTypography.title),
            BioSpacing.gapH(BioSpacing.sm),
            TextField(
              controller: _apellidoController,
              decoration: const InputDecoration(labelText: 'Apellido'),
              onChanged: (_) => setState(() {}),
            ),
            BioSpacing.gapH(BioSpacing.sm),
            TextField(
              controller: _nombreController,
              decoration: const InputDecoration(labelText: 'Nombre'),
              onChanged: (_) => setState(() {}),
            ),
            BioSpacing.gapH(BioSpacing.sm),
            TextField(
              controller: _documentoController,
              decoration: const InputDecoration(labelText: 'Documento'),
              keyboardType: TextInputType.number,
              onChanged: (_) => setState(() {}),
            ),
            BioSpacing.gapH(BioSpacing.sm),
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Fecha de nacimiento'),
              subtitle: Text(
                _fechaNac == null
                    ? '—'
                    : '${_fechaNac!.day}/${_fechaNac!.month}/${_fechaNac!.year}',
              ),
              trailing: const Icon(Icons.calendar_today),
              onTap: _elegirFechaNac,
            ),
            BioSpacing.gapH(BioSpacing.sm),
            DropdownButtonFormField<String>(
              value: _sexoVal.isEmpty ? null : _sexoVal,
              decoration: const InputDecoration(labelText: 'Sexo'),
              items: const [
                DropdownMenuItem(value: '1', child: Text('Femenino')),
                DropdownMenuItem(value: '2', child: Text('Masculino')),
              ],
              onChanged: (v) => setState(() => _sexoVal = v ?? ''),
            ),
          ],
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
          BioSpacing.gapH(BioSpacing.lg),
          FilledButton(
            onPressed: _guardando || !_puedeGuardar ? null : _guardar,
            child: Text(_guardando ? 'Registrando…' : 'Registrar ingreso'),
          ),
        ],
      ),
    );
  }
}
