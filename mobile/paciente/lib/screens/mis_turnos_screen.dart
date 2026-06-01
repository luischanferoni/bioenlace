import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../services/turnos_service.dart';
import 'chat_medico_screen.dart';
import 'chat_motivos_screen.dart';

/// Pantalla "Mis turnos": próximos e historial con paginación por sección.
class MisTurnosScreen extends StatefulWidget {
  final String? authToken;
  final String userId;
  final String? userName;

  const MisTurnosScreen({
    Key? key,
    required this.authToken,
    required this.userId,
    this.userName,
  }) : super(key: key);

  @override
  State<MisTurnosScreen> createState() => _MisTurnosScreenState();
}

class _MisTurnosScreenState extends State<MisTurnosScreen> {
  static const int _pageLimit = 15;

  late TurnosService _turnosService;
  final List<Map<String, dynamic>> _pendientes = [];
  final List<Map<String, dynamic>> _pasados = [];
  int _totalPendientes = 0;
  int _totalPasados = 0;
  bool _loading = true;
  bool _loadingMasP = false;
  bool _loadingMasPa = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _turnosService = TurnosService(authToken: widget.authToken);
    _cargarInicial();
  }

  bool get _hayMasP => _pendientes.length < _totalPendientes;
  bool get _hayMasPa => _pasados.length < _totalPasados;

  List<Map<String, dynamic>> _asMapList(dynamic raw) {
    if (raw is! List) return [];
    return raw.map((e) => Map<String, dynamic>.from(e as Map)).toList();
  }

  Future<void> _cargarInicial() async {
    setState(() {
      _loading = true;
      _error = null;
      _pendientes.clear();
      _pasados.clear();
    });
    final r1 = await _turnosService.getMisTurnos(
      alcance: 'pendientes',
      limit: _pageLimit,
      offset: 0,
    );
    final r2 = await _turnosService.getMisTurnos(
      alcance: 'pasados',
      limit: _pageLimit,
      offset: 0,
    );
    if (!mounted) return;
    if (r1['success'] != true && r2['success'] != true) {
      setState(() {
        _loading = false;
        _error = (r1['message'] ?? r2['message']) as String? ??
            'Error al cargar turnos';
      });
      return;
    }
    setState(() {
      _loading = false;
      if (r1['success'] == true) {
        _pendientes.clear();
        _pendientes.addAll(_asMapList(r1['turnos']));
        _totalPendientes = r1['total'] as int? ?? _pendientes.length;
      }
      if (r2['success'] == true) {
        _pasados.clear();
        _pasados.addAll(_asMapList(r2['turnos']));
        _totalPasados = r2['total'] as int? ?? _pasados.length;
      }
      if (r1['success'] != true) {
        _error = r1['message'] as String?;
      } else if (r2['success'] != true) {
        _error = r2['message'] as String?;
      }
    });
  }

  Future<void> _cargarMasPendientes() async {
    if (_loadingMasP || !_hayMasP) return;
    setState(() => _loadingMasP = true);
    final r = await _turnosService.getMisTurnos(
      alcance: 'pendientes',
      limit: _pageLimit,
      offset: _pendientes.length,
    );
    if (!mounted) return;
    setState(() {
      _loadingMasP = false;
      if (r['success'] == true) {
        _pendientes.addAll(_asMapList(r['turnos']));
        _totalPendientes = r['total'] as int? ?? _pendientes.length;
      }
    });
  }

  Future<void> _cargarMasPasados() async {
    if (_loadingMasPa || !_hayMasPa) return;
    setState(() => _loadingMasPa = true);
    final r = await _turnosService.getMisTurnos(
      alcance: 'pasados',
      limit: _pageLimit,
      offset: _pasados.length,
    );
    if (!mounted) return;
    setState(() {
      _loadingMasPa = false;
      if (r['success'] == true) {
        _pasados.addAll(_asMapList(r['turnos']));
        _totalPasados = r['total'] as int? ?? _pasados.length;
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    return Scaffold(
      backgroundColor: tokens.paperBackground,
      appBar: const BioAppBar(title: 'Mis turnos'),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null && _pendientes.isEmpty && _pasados.isEmpty
              ? Center(
                  child: Padding(
                    padding: BioSpacing.pageAll,
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        BioAlert.danger(message: _error!),
                        BioSpacing.gapH(BioSpacing.lg),
                        BioButton.primary(
                          label: 'Reintentar',
                          icon: Icons.refresh,
                          onPressed: _cargarInicial,
                        ),
                      ],
                    ),
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _cargarInicial,
                  child: ListView(
                    padding: BioSpacing.pageAll,
                    children: [
                      if (_error != null) ...[
                        BioAlert.danger(message: _error!),
                        BioSpacing.gapH(BioSpacing.md),
                      ],
                      _seccionTitle('Próximos'),
                      BioSpacing.gapH(BioSpacing.sm),
                      if (_pendientes.isEmpty)
                        _empty('No tienes turnos próximos.'),
                      ..._pendientes.map((t) => Padding(
                            padding:
                                const EdgeInsets.only(bottom: BioSpacing.sm),
                            child: _cardTurno(context, t),
                          )),
                      if (_loadingMasP) _loaderInline(),
                      if (_hayMasP && !_loadingMasP)
                        Padding(
                          padding: const EdgeInsets.only(top: BioSpacing.sm),
                          child: BioButton(
                            label: 'Cargar más',
                            intent: UiIntent.neutral,
                            variant: BioButtonVariant.soft,
                            size: BioButtonSize.sm,
                            onPressed: _cargarMasPendientes,
                          ),
                        ),
                      BioSpacing.gapH(BioSpacing.xl),
                      _seccionTitle('Anteriores'),
                      BioSpacing.gapH(BioSpacing.sm),
                      if (_pasados.isEmpty) _empty('No hay turnos anteriores.'),
                      ..._pasados.map((t) => Padding(
                            padding:
                                const EdgeInsets.only(bottom: BioSpacing.sm),
                            child: _cardTurno(context, t),
                          )),
                      if (_loadingMasPa) _loaderInline(),
                      if (_hayMasPa && !_loadingMasPa)
                        Padding(
                          padding: const EdgeInsets.only(top: BioSpacing.sm),
                          child: BioButton(
                            label: 'Cargar más',
                            intent: UiIntent.neutral,
                            variant: BioButtonVariant.soft,
                            size: BioButtonSize.sm,
                            onPressed: _cargarMasPasados,
                          ),
                        ),
                    ],
                  ),
                ),
    );
  }

  Widget _seccionTitle(String label) {
    return Text(label, style: BioTypography.h3);
  }

  Widget _empty(String text) {
    return Text(
      text,
      style: BioTypography.bodySm.copyWith(color: context.bio.textMuted),
    );
  }

  Widget _loaderInline() {
    return const Padding(
      padding: EdgeInsets.symmetric(vertical: BioSpacing.sm),
      child: Center(
        child: SizedBox(
          width: 24,
          height: 24,
          child: CircularProgressIndicator(strokeWidth: 2),
        ),
      ),
    );
  }

  Widget _cardTurno(BuildContext context, Map<String, dynamic> t) {
    final tokens = context.bio;
    final tipoAtencion = t['tipo_atencion'] as String? ?? 'presencial';
    final idConsulta = t['id_consulta'];
    final puedeChat = tipoAtencion == 'teleconsulta' && idConsulta != null;
    final puedeMotivos =
        idConsulta != null && turnoMotivosInputAbiertoEnProducto(t);
    final tituloMotivos = 'Motivos · ${t['fecha']} ${t['hora']}';
    final estado = t['estado_label']?.toString() ?? '';

    final acciones = <Widget>[
      if (puedeMotivos)
        BioButton.outlinePrimary(
          label: 'Cargar motivos',
          size: BioButtonSize.sm,
          icon: Icons.edit_note,
          onPressed: () {
            Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => ChatMotivosScreen(
                  consultaId: idConsulta as int,
                  authToken: widget.authToken,
                  userId: widget.userId,
                  userName: widget.userName ?? 'Paciente',
                  titulo: tituloMotivos,
                ),
              ),
            );
          },
        ),
      if (puedeChat)
        BioButton.primary(
          label: 'Abrir chat',
          size: BioButtonSize.sm,
          icon: Icons.chat_bubble_outline,
          onPressed: () {
            Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => ChatMedicoScreen(
                  consultaId: idConsulta as int,
                  authToken: widget.authToken,
                  userId: widget.userId,
                  userName: widget.userName ?? 'Paciente',
                  titulo: 'Chat con ${t['profesional'] ?? 'médico'}',
                ),
              ),
            );
          },
        ),
    ];

    return BioCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  '${t['fecha']} · ${t['hora']}',
                  style: BioTypography.title,
                ),
              ),
              if (estado.isNotEmpty) ...[
                BioSpacing.gapW(BioSpacing.sm),
                BioBadge.neutral(estado),
              ],
            ],
          ),
          if (t['servicio'] != null) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(t['servicio'].toString(), style: BioTypography.bodySm),
          ],
          if (t['profesional'] != null) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              'Con: ${t['profesional']}',
              style: BioTypography.bodySm,
            ),
          ],
          BioSpacing.gapH(BioSpacing.xs),
          Text(
            tipoAtencion == 'teleconsulta'
                ? 'Consulta por chat'
                : 'Presencial',
            style: BioTypography.caption.copyWith(
              color: tipoAtencion == 'teleconsulta'
                  ? IntentPalette.of(UiIntent.primary).base
                  : tokens.textMuted,
              fontWeight: FontWeight.w600,
            ),
          ),
          if (acciones.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.sm),
            Wrap(
              spacing: BioSpacing.sm,
              runSpacing: BioSpacing.xs,
              children: acciones,
            ),
          ],
        ],
      ),
    );
  }
}
