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
  final _docRepresentanteCtrl = TextEditingController();
  String _parentescoMenor = 'padre';
  String _parentescoRepresentante = 'otro';

  @override
  void initState() {
    super.initState();
    _api = PersonRepresentationApi(authToken: widget.authToken);
    _cargar();
  }

  @override
  void dispose() {
    _docMenorCtrl.dispose();
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
      _api.fetchPreferencias(),
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
    if (results[3]['success'] == true) {
      final data = results[3]['data'];
      if (data is Map && data.containsKey('notify_on_representative_action')) {
        _notifyOnRepresentativeAction = data['notify_on_representative_action'] == true;
      }
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

  Future<void> _solicitarMenor() async {
    final doc = _docMenorCtrl.text.trim();
    if (doc.isEmpty) {
      _snack('Ingresá el documento del menor.');
      return;
    }
    final r = await _api.solicitarMenorComoTutor({
      'relationship_type_code': _parentescoMenor,
      'documento': doc,
    });
    if (!mounted) return;
    if (r['success'] == true) {
      _docMenorCtrl.clear();
      _snack(r['data'] is Map ? (r['data']['mensaje']?.toString() ?? 'Solicitud enviada') : 'Solicitud enviada');
      await _cargar();
    } else {
      _snack(r['message']?.toString() ?? 'No se pudo solicitar la tutela');
    }
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
                  if (_pacientesACargo.isEmpty)
                    Text('Nadie a cargo por delegación activa.', style: BioTypography.bodySm)
                  else
                    ..._pacientesACargo.where((l) => l['status'] == 'active').map(_linkTileOperar),
                  BioSpacing.gapH(BioSpacing.lg),
                  _sectionTitle('Mis hijos (tutela)'),
                  ..._vinculosTutor.map(_vinculoTutorTile),
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
                    title: const Text('Avisarme cuando un representante actúe'),
                    subtitle: const Text('Notificación push e inbox (N9)'),
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

  Widget _linkTileOperar(Map<String, dynamic> link) {
    final subject = link['subject'] is Map ? Map<String, dynamic>.from(link['subject'] as Map) : null;
    final nombre = _personaNombre(subject);
    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(nombre.isNotEmpty ? nombre : 'Paciente'),
      subtitle: Text(link['regime']?.toString() ?? ''),
      trailing: TextButton(
        onPressed: () => _operarPor(link),
        child: const Text('Operar'),
      ),
    );
  }

  Widget _vinculoTutorTile(Map<String, dynamic> link) {
    final subject = link['subject'] is Map ? Map<String, dynamic>.from(link['subject'] as Map) : null;
    final nombre = _personaNombre(subject);
    final status = link['status']?.toString() ?? '';
    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(nombre.isNotEmpty ? nombre : 'Menor'),
      subtitle: Text('Estado: $status'),
      trailing: status == 'active'
          ? TextButton(onPressed: () => _operarPor(link), child: const Text('Operar'))
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
    return BioCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Solicitar tutela de menor', style: BioTypography.body.copyWith(fontWeight: FontWeight.w600)),
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
          ),
          BioSpacing.gapH(BioSpacing.md),
          BioButton(
            label: 'Solicitar verificación',
            onPressed: _solicitarMenor,
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
