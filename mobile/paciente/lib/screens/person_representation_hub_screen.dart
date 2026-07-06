import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

/// Gestión de tutela (A), delegación (B) y preferencias N9.
class PersonRepresentationHubScreen extends StatefulWidget {
  final String? authToken;
  final int actorPersonaId;
  final String actorLabel;

  const PersonRepresentationHubScreen({
    super.key,
    this.authToken,
    required this.actorPersonaId,
    required this.actorLabel,
  });

  @override
  State<PersonRepresentationHubScreen> createState() =>
      _PersonRepresentationHubScreenState();
}

class _PersonRepresentationHubScreenState extends State<PersonRepresentationHubScreen> {
  late final PersonRepresentationApi _api;
  bool _loading = true;
  String? _error;

  List<Map<String, dynamic>> _vinculosTutor = [];
  List<Map<String, dynamic>> _representantes = [];
  List<Map<String, dynamic>> _pacientesACargo = [];
  bool _notifyOnRepresentativeAction = false;
  bool _savingPrefs = false;

  final _docMenorCtrl = TextEditingController();
  final _nombreMenorCtrl = TextEditingController();
  final _apellidoMenorCtrl = TextEditingController();
  final _docRepresentanteCtrl = TextEditingController();
  String _parentescoMenor = 'padre';
  String _parentescoRepresentante = 'otro';
  int _sexoMenor = 1;
  DateTime? _fechaNacimientoMenor;
  bool _solicitarMenorEnviando = false;

  @override
  void initState() {
    super.initState();
    _api = PersonRepresentationApi(authToken: widget.authToken);
    _cargar();
  }

  @override
  void dispose() {
    _docMenorCtrl.dispose();
    _nombreMenorCtrl.dispose();
    _apellidoMenorCtrl.dispose();
    _docRepresentanteCtrl.dispose();
    super.dispose();
  }

  Future<void> _cargar() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final results = await Future.wait([
      _api.fetchVinculosComoTutor(),
      _api.fetchMisRepresentantes(),
      _api.fetchPacientesACargo(),
    ]);
    if (!mounted) return;

    String? err;
    for (var i = 0; i < results.length; i++) {
      if (results[i]['success'] == true) continue;
      final msg = results[i]['message']?.toString();
      if (msg != null && msg.isNotEmpty) {
        err ??= msg;
      }
    }
    if (results[0]['success'] == true) {
      final data = results[0]['data'];
      _vinculosTutor = data is Map ? _asList(data['vinculos']) : [];
    }
    if (results[1]['success'] == true) {
      final data = results[1]['data'];
      if (data is Map) {
        _representantes = _asList(data['representantes']);
        final prefs = data['preferencias'];
        if (prefs is Map) {
          _notifyOnRepresentativeAction = prefs['notify_on_representative_action'] == true;
        }
      }
    }
    if (results[2]['success'] == true) {
      final data = results[2]['data'];
      _pacientesACargo = data is Map ? _asList(data['pacientes']) : [];
    }

    setState(() {
      _loading = false;
      _error = err;
    });
    await PersonRepresentationContext.instance.refreshOptions(authToken: widget.authToken);
  }

  List<Map<String, dynamic>> _asList(dynamic raw) {
    if (raw is! List) return [];
    return raw.map((e) => Map<String, dynamic>.from(e as Map)).toList();
  }

  String _personaNombre(Map<String, dynamic>? p) {
    if (p == null) return '';
    final n = p['nombre']?.toString().trim() ?? '';
    final a = p['apellido']?.toString().trim() ?? '';
    return '$n $a'.trim();
  }

  String _subjectDocumento(Map<String, dynamic> link) {
    final subject = link['subject'];
    if (subject is! Map) return '';
    return subject['documento']?.toString().trim() ?? '';
  }

  String _statusLabel(String status) {
    switch (status) {
      case 'pending':
        return 'Verificación pendiente';
      case 'active':
        return 'Activo';
      case 'blocked':
        return 'Bloqueado';
      case 'revoked':
        return 'Revocado';
      default:
        return status;
    }
  }

  Map<String, dynamic>? _vinculoTutorPorDocumento(String doc) {
    final normalized = doc.trim();
    if (normalized.isEmpty) return null;
    for (final link in _vinculosTutor) {
      final status = link['status']?.toString() ?? '';
      if (!{'pending', 'active', 'blocked'}.contains(status)) continue;
      if (_subjectDocumento(link) == normalized) return link;
    }
    return null;
  }

  List<Map<String, dynamic>> get _itemsPacientesACargo {
    final items = <Map<String, dynamic>>[];
    final seenSubjects = <int>{};

    for (final link in _pacientesACargo) {
      if (link['status']?.toString() != 'active') continue;
      final id = int.tryParse('${link['subject_persona_id']}');
      if (id != null && id > 0) seenSubjects.add(id);
      items.add(link);
    }

    for (final link in _vinculosTutor) {
      final status = link['status']?.toString() ?? '';
      if (status == 'revoked' || status.isEmpty) continue;
      final id = int.tryParse('${link['subject_persona_id']}');
      if (id != null && id > 0 && seenSubjects.contains(id)) continue;
      if (id != null && id > 0) seenSubjects.add(id);
      items.add(link);
    }

    return items;
  }

  bool get _haySolicitudesTutelaPendientes =>
      _vinculosTutor.any((l) => l['status']?.toString() == 'pending');

  Future<void> _solicitarMenor() async {
    if (_solicitarMenorEnviando) return;
    final doc = _docMenorCtrl.text.trim();
    final nombre = _nombreMenorCtrl.text.trim();
    final apellido = _apellidoMenorCtrl.text.trim();
    if (doc.isEmpty) {
      _snack('Ingresá el documento del menor.');
      return;
    }
    final existente = _vinculoTutorPorDocumento(doc);
    if (existente != null) {
      final status = existente['status']?.toString() ?? '';
      _snack(
        status == 'pending'
            ? 'Ya hay una solicitud de tutela en verificación para este documento.'
            : 'Ya existe un vínculo de tutela (${_statusLabel(status)}) para este documento.',
      );
      return;
    }
    if (nombre.isEmpty || apellido.isEmpty || _fechaNacimientoMenor == null) {
      _snack('Completá nombre, apellido y fecha de nacimiento del menor.');
      return;
    }
    final fecha = _fechaNacimientoMenor!;
    final fechaYmd =
        '${fecha.year.toString().padLeft(4, '0')}-${fecha.month.toString().padLeft(2, '0')}-${fecha.day.toString().padLeft(2, '0')}';
    setState(() => _solicitarMenorEnviando = true);
    final r = await _api.solicitarMenorComoTutor({
      'relationship_type_code': _parentescoMenor,
      'documento': doc,
      'nombre': nombre,
      'apellido': apellido,
      'fecha_nacimiento': fechaYmd,
      'sexo_biologico': _sexoMenor,
      'sexo': _sexoMenor == 2 ? 'F' : 'M',
    });
    if (!mounted) return;
    setState(() => _solicitarMenorEnviando = false);
    if (r['success'] == true) {
      _docMenorCtrl.clear();
      _nombreMenorCtrl.clear();
      _apellidoMenorCtrl.clear();
      setState(() => _fechaNacimientoMenor = null);
      _snack(r['data'] is Map ? (r['data']['mensaje']?.toString() ?? 'Solicitud enviada') : 'Solicitud enviada');
      await _cargar();
    } else {
      final msg = r['message']?.toString() ?? 'No se pudo solicitar la tutela';
      _snack(msg.contains('Ya existe') ? 'Ya hay una solicitud o vínculo en curso para este menor.' : msg);
    }
  }

  Future<void> _elegirFechaNacimientoMenor() async {
    final now = DateTime.now();
    final initial = _fechaNacimientoMenor ?? DateTime(now.year - 10, now.month, now.day);
    final picked = await showDatePicker(
      context: context,
      initialDate: initial.isAfter(now) ? now : initial,
      firstDate: DateTime(now.year - 120),
      lastDate: now,
    );
    if (picked == null || !mounted) return;
    setState(() => _fechaNacimientoMenor = picked);
  }

  String get _fechaNacimientoMenorLabel {
    final d = _fechaNacimientoMenor;
    if (d == null) return 'Seleccionar fecha';
    final dd = d.day.toString().padLeft(2, '0');
    final mm = d.month.toString().padLeft(2, '0');
    return '$dd/$mm/${d.year}';
  }

  Future<void> _designarRepresentante() async {
    final doc = _docRepresentanteCtrl.text.trim();
    if (doc.isEmpty) {
      _snack('Ingresá el documento del representante.');
      return;
    }
    final r = await _api.designarRepresentante({
      'representative_documento': doc,
      'relationship_type_code': _parentescoRepresentante,
    });
    if (!mounted) return;
    if (r['success'] == true) {
      _docRepresentanteCtrl.clear();
      _snack(r['data'] is Map ? (r['data']['mensaje']?.toString() ?? 'Representante designado') : 'Representante designado');
      await _cargar();
    } else {
      _snack(r['message']?.toString() ?? 'No se pudo designar');
    }
  }

  Future<void> _revocarRepresentante(Map<String, dynamic> link) async {
    final id = link['id'];
    if (id == null) return;
    final r = await _api.revocarRepresentante({'person_related_id': id});
    if (!mounted) return;
    if (r['success'] == true) {
      await _cargar();
    } else {
      _snack(r['message']?.toString() ?? 'No se pudo revocar');
    }
  }

  Future<void> _toggleNotify(bool value) async {
    setState(() => _savingPrefs = true);
    final r = await _api.guardarPreferencias(notify: value);
    if (!mounted) return;
    setState(() {
      _savingPrefs = false;
      if (r['success'] == true) {
        _notifyOnRepresentativeAction = value;
      }
    });
    if (r['success'] != true) {
      _snack(r['message']?.toString() ?? 'No se guardaron las preferencias');
    }
  }

  Future<void> _operarPor(Map<String, dynamic> link) async {
    final subjectId = int.tryParse('${link['subject_persona_id']}');
    if (subjectId == null || subjectId <= 0) return;
    final subject = link['subject'] is Map ? Map<String, dynamic>.from(link['subject'] as Map) : null;
    final label = _personaNombre(subject);
    await PersonRepresentationContext.instance.selectSubject(
      RepresentationSubjectOption(
        personaId: subjectId,
        label: label.isNotEmpty ? label : 'Paciente $subjectId',
        regime: link['regime']?.toString(),
        status: link['status']?.toString(),
      ),
      authToken: widget.authToken,
    );
    if (!mounted) return;
    _snack('Contexto: ${label.isNotEmpty ? label : 'paciente'}');
    Navigator.pop(context);
  }

  void _snack(String msg) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: context.bio.paperBackground,
      appBar: const BioAppBar(title: 'Representación'),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _cargar,
              child: ListView(
                padding: BioSpacing.pageAll,
                children: [
                  if (_error != null) ...[
                    BioAlert.danger(message: _error!),
                    BioSpacing.gapH(BioSpacing.md),
                  ],
                  _sectionTitle('Pacientes a mi cargo'),
                  if (_haySolicitudesTutelaPendientes) ...[
                    BioAlert.info(
                      message:
                          'Hay solicitudes de tutela en verificación. Un operador del centro debe confirmar el vínculo antes de que puedas operar por el menor.',
                    ),
                    BioSpacing.gapH(BioSpacing.sm),
                  ],
                  if (_itemsPacientesACargo.isEmpty)
                    Text(
                      'Nadie a cargo todavía. Las delegaciones activas y las solicitudes de tutela aparecerán acá.',
                      style: BioTypography.bodySm,
                    )
                  else
                    ..._itemsPacientesACargo.map(_cargoTile),
                  BioSpacing.gapH(BioSpacing.lg),
                  _sectionTitle('Mis hijos (tutela)'),
                  BioSpacing.gapH(BioSpacing.sm),
                  _buildSolicitarMenorCard(),
                  BioSpacing.gapH(BioSpacing.lg),
                  _sectionTitle('Mis representantes'),
                  ..._representantes.map(_representanteTile),
                  BioSpacing.gapH(BioSpacing.sm),
                  _buildDesignarCard(),
                  BioSpacing.gapH(BioSpacing.lg),
                  _sectionTitle('Preferencias'),
                  SwitchListTile(
                    title: const Text('Avisame cuando un representante actúe'),
                    value: _notifyOnRepresentativeAction,
                    onChanged: _savingPrefs ? null : _toggleNotify,
                  ),
                ],
              ),
            ),
    );
  }

  Widget _sectionTitle(String t) {
    return Padding(
      padding: const EdgeInsets.only(bottom: BioSpacing.sm),
      child: Text(t, style: BioTypography.title.copyWith(fontWeight: FontWeight.w700)),
    );
  }

  Widget _cargoTile(Map<String, dynamic> link) {
    final subject = link['subject'] is Map ? Map<String, dynamic>.from(link['subject'] as Map) : null;
    final nombre = _personaNombre(subject);
    final status = link['status']?.toString() ?? '';
    final regime = link['regime']?.toString() ?? '';
    final relationship = link['relationship_type'];
    final parentesco = relationship is Map ? relationship['label']?.toString() : null;
    final regimeLabel = regime == 'verified_guardianship'
        ? 'Tutela'
        : regime == 'patient_delegation'
            ? 'Delegación'
            : regime;
    final subtitleParts = <String>[
      if (regimeLabel.isNotEmpty) regimeLabel,
      if (parentesco != null && parentesco.isNotEmpty) parentesco,
      _statusLabel(status),
    ];

    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(nombre.isNotEmpty ? nombre : 'Paciente'),
      subtitle: Text(subtitleParts.join(' · ')),
      trailing: status == 'active'
          ? TextButton(onPressed: () => _operarPor(link), child: const Text('Operar'))
          : status == 'pending'
              ? Text('En revisión', style: BioTypography.caption.copyWith(color: context.bio.textMuted))
              : null,
    );
  }

  Widget _representanteTile(Map<String, dynamic> link) {
    final actor = link['actor'] is Map ? Map<String, dynamic>.from(link['actor'] as Map) : null;
    final nombre = _personaNombre(actor);
    final status = link['status']?.toString() ?? '';
    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(nombre.isNotEmpty ? nombre : 'Representante'),
      subtitle: Text('Estado: $status'),
      trailing: status == 'active'
          ? IconButton(
              icon: const Icon(Icons.person_remove_outlined),
              onPressed: () => _revocarRepresentante(link),
            )
          : null,
    );
  }

  Widget _buildSolicitarMenorCard() {
    final doc = _docMenorCtrl.text.trim();
    final vinculoDoc = doc.isNotEmpty ? _vinculoTutorPorDocumento(doc) : null;
    return BioCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Solicitar tutela de menor', style: BioTypography.body.copyWith(fontWeight: FontWeight.w600)),
          if (vinculoDoc != null) ...[
            BioSpacing.gapH(BioSpacing.sm),
            BioAlert.warning(
              message: vinculoDoc['status']?.toString() == 'pending'
                  ? 'Ya enviaste una solicitud para este documento. Revisá el estado arriba en Pacientes a mi cargo.'
                  : 'Ya existe un vínculo de tutela para este documento.',
            ),
          ],
          BioSpacing.gapH(BioSpacing.sm),
          DropdownButtonFormField<String>(
            value: _parentescoMenor,
            decoration: const InputDecoration(labelText: 'Parentesco'),
            items: const [
              DropdownMenuItem(value: 'padre', child: Text('Padre')),
              DropdownMenuItem(value: 'madre', child: Text('Madre')),
              DropdownMenuItem(value: 'tutor_legal', child: Text('Tutor legal')),
            ],
            onChanged: (v) => setState(() => _parentescoMenor = v ?? 'padre'),
          ),
          BioSpacing.gapH(BioSpacing.sm),
          TextField(
            controller: _docMenorCtrl,
            decoration: const InputDecoration(labelText: 'Documento del menor'),
            keyboardType: TextInputType.number,
            onChanged: (_) => setState(() {}),
          ),
          BioSpacing.gapH(BioSpacing.sm),
          TextField(
            controller: _nombreMenorCtrl,
            decoration: const InputDecoration(labelText: 'Nombre del menor'),
            textCapitalization: TextCapitalization.words,
          ),
          BioSpacing.gapH(BioSpacing.sm),
          TextField(
            controller: _apellidoMenorCtrl,
            decoration: const InputDecoration(labelText: 'Apellido del menor'),
            textCapitalization: TextCapitalization.words,
          ),
          BioSpacing.gapH(BioSpacing.sm),
          DropdownButtonFormField<int>(
            value: _sexoMenor,
            decoration: const InputDecoration(labelText: 'Sexo registrado (RENAPER)'),
            items: const [
              DropdownMenuItem(value: 1, child: Text('Masculino')),
              DropdownMenuItem(value: 2, child: Text('Femenino')),
            ],
            onChanged: (v) => setState(() => _sexoMenor = v ?? 1),
          ),
          BioSpacing.gapH(BioSpacing.sm),
          InkWell(
            onTap: _elegirFechaNacimientoMenor,
            borderRadius: BorderRadius.circular(BioRadius.xs),
            child: InputDecorator(
              decoration: const InputDecoration(
                labelText: 'Fecha de nacimiento',
                suffixIcon: Icon(Icons.calendar_today_outlined),
              ),
              child: Text(
                _fechaNacimientoMenorLabel,
                style: BioTypography.body.copyWith(
                  color: _fechaNacimientoMenor == null
                      ? context.bio.textMuted
                      : context.bio.textBody,
                ),
              ),
            ),
          ),
          BioSpacing.gapH(BioSpacing.xs),
          Text(
            'Si el documento no está en el sistema, usamos estos datos para dar de alta al menor.',
            style: BioTypography.caption.copyWith(color: context.bio.textMuted),
          ),
          BioSpacing.gapH(BioSpacing.md),
          BioButton(
            label: _solicitarMenorEnviando ? 'Enviando…' : 'Solicitar verificación',
            onPressed: (_solicitarMenorEnviando || vinculoDoc != null) ? null : _solicitarMenor,
          ),
        ],
      ),
    );
  }

  Widget _buildDesignarCard() {
    return BioCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Designar representante', style: BioTypography.body.copyWith(fontWeight: FontWeight.w600)),
          BioSpacing.gapH(BioSpacing.sm),
          DropdownButtonFormField<String>(
            value: _parentescoRepresentante,
            decoration: const InputDecoration(labelText: 'Parentesco'),
            items: const [
              DropdownMenuItem(value: 'conyuge', child: Text('Cónyuge')),
              DropdownMenuItem(value: 'hijo', child: Text('Hijo/a')),
              DropdownMenuItem(value: 'padre', child: Text('Padre')),
              DropdownMenuItem(value: 'madre', child: Text('Madre')),
              DropdownMenuItem(value: 'otro', child: Text('Otro')),
            ],
            onChanged: (v) => setState(() => _parentescoRepresentante = v ?? 'otro'),
          ),
          BioSpacing.gapH(BioSpacing.sm),
          TextField(
            controller: _docRepresentanteCtrl,
            decoration: const InputDecoration(labelText: 'Documento del representante'),
            keyboardType: TextInputType.number,
          ),
          BioSpacing.gapH(BioSpacing.md),
          BioButton(
            label: 'Designar',
            onPressed: _designarRepresentante,
          ),
        ],
      ),
    );
  }
}
