import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../services/notificaciones_service.dart';
import '../services/push_notification_service.dart';
import '../services/turnos_service.dart';

/// Bandeja de alertas in-app del paciente.
class AlertasScreen extends StatefulWidget {
  final String? authToken;
  final void Function(Map<String, dynamic> turnoStub)? onAbrirResolver;
  final void Function(int encounterId)? onAbrirResumen;
  final void Function(int touchpointId)? onAbrirFollowup;
  final int? subjectPersonaId;

  const AlertasScreen({
    Key? key,
    this.authToken,
    this.onAbrirResolver,
    this.onAbrirResumen,
    this.onAbrirFollowup,
    this.subjectPersonaId,
  }) : super(key: key);

  @override
  State<AlertasScreen> createState() => _AlertasScreenState();
}

class _AlertasScreenState extends State<AlertasScreen> {
  late NotificacionesService _svc;
  late TurnosService _turnosSvc;
  final List<Map<String, dynamic>> _items = [];
  bool _loading = true;
  String? _error;
  int _noLeidas = 0;
  final Set<String> _confirmando = {};
  final Set<String> _confirmados = {};

  @override
  void initState() {
    super.initState();
    _svc = NotificacionesService(authToken: widget.authToken);
    _turnosSvc = TurnosService(authToken: widget.authToken);
    _cargar();
  }

  Future<void> _cargar() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final r = await _svc.listar(limit: 50);
    if (!mounted) return;
    setState(() {
      _loading = false;
      if (r['success'] == true) {
        _items
          ..clear()
          ..addAll(
            (r['items'] as List<dynamic>? ?? [])
                .map((e) => Map<String, dynamic>.from(e as Map)),
          );
        _noLeidas = r['no_leidas'] as int? ?? 0;
      } else {
        _error = r['message'] as String?;
      }
    });
  }

  Future<void> _marcarLeida(Map<String, dynamic> item) async {
    final id = item['id'];
    if (id is! int && id is! num) return;
    await _svc.marcarLeida(id: id is int ? id : id.toInt());
    await _cargar();
  }

  String _itemKey(Map<String, dynamic> item) {
    final id = item['id']?.toString() ?? '';
    final ref = item['notification_ref']?.toString() ??
        (item['data'] is Map
            ? (item['data'] as Map)['notification_ref']?.toString() ?? ''
            : '');
    return '$id:$ref';
  }

  Future<void> _confirmarAsistencia(
    Map<String, dynamic> item,
    Map<String, dynamic> confirmacion,
  ) async {
    final key = _itemKey(item);
    if (_confirmando.contains(key) || _confirmados.contains(key)) {
      return;
    }
    setState(() => _confirmando.add(key));
    final idTurno = confirmacion['id_turno'] as int;
    final token = confirmacion['token']?.toString();
    final r = await _turnosSvc.confirmarAsistencia(
      idTurno: idTurno,
      token: token,
      subjectPersonaId: widget.subjectPersonaId,
    );
    if (!mounted) return;
    setState(() => _confirmando.remove(key));
    if (r['success'] == true) {
      setState(() => _confirmados.add(key));
      await _marcarLeida(item);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Asistencia confirmada')),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(r['message']?.toString() ?? 'No se pudo confirmar'),
        ),
      );
    }
  }

  void _onTapItem(Map<String, dynamic> item) {
    _marcarLeida(item);
    final data = item['data'];
    if (data is! Map) return;
    final map = Map<String, dynamic>.from(data);

    // Confirmación: el botón dedicado maneja la acción; el tap sólo marca leída.
    if (PushNotificationService.confirmacionDesdeData(map) != null) {
      return;
    }

    final encounterId = PushNotificationService.encounterIdDesdePush(map);
    if (encounterId != null && widget.onAbrirResumen != null) {
      Navigator.pop(context);
      widget.onAbrirResumen!(encounterId);
      return;
    }

    final touchpointId =
        PushNotificationService.followupTouchpointIdDesdePush(map);
    if (touchpointId != null && widget.onAbrirFollowup != null) {
      Navigator.pop(context);
      widget.onAbrirFollowup!(touchpointId);
      return;
    }

    final stub = PushNotificationService.turnoStubDesdePush(map);
    if (stub != null && widget.onAbrirResolver != null) {
      Navigator.pop(context);
      widget.onAbrirResolver!(stub);
    }
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;

    return Scaffold(
      backgroundColor: tokens.paperBackground,
      appBar: BioAppBar(
        title: 'Alertas${_noLeidas > 0 ? ' ($_noLeidas)' : ''}',
        actions: [
          if (_noLeidas > 0)
            Padding(
              padding: const EdgeInsets.only(right: BioSpacing.sm),
              child: BioButton(
                label: 'Marcar todas leídas',
                intent: UiIntent.neutral,
                variant: BioButtonVariant.soft,
                size: BioButtonSize.sm,
                onPressed: () async {
                  await _svc.marcarLeida();
                  await _cargar();
                },
              ),
            ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Padding(
                  padding: BioSpacing.pageAll,
                  child: BioAlert.danger(message: _error!),
                )
              : RefreshIndicator(
                  onRefresh: _cargar,
                  child: _items.isEmpty
                      ? _buildEmpty(context)
                      : ListView.separated(
                          padding: const EdgeInsets.symmetric(
                            horizontal: BioSpacing.lg,
                            vertical: BioSpacing.md,
                          ),
                          itemCount: _items.length,
                          separatorBuilder: (_, __) =>
                              BioSpacing.gapH(BioSpacing.sm),
                          itemBuilder: (context, i) =>
                              _buildItem(context, _items[i]),
                        ),
                ),
    );
  }

  Widget _buildEmpty(BuildContext context) {
    final tokens = context.bio;
    return ListView(
      children: [
        SizedBox(height: MediaQuery.of(context).size.height * 0.18),
        Padding(
          padding: BioSpacing.pageAll,
          child: BioCard(
            color: tokens.paperSurfaceSunken,
            child: Row(
              children: [
                Icon(Icons.notifications_none_outlined, color: tokens.textMuted),
                BioSpacing.gapW(BioSpacing.md),
                Expanded(
                  child: Text(
                    'No tienes alertas.',
                    style: BioTypography.body,
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildItem(BuildContext context, Map<String, dynamic> item) {
    final leida = item['leida'] == true;
    final titulo = item['titulo']?.toString() ?? '';
    final cuerpo = item['cuerpo']?.toString() ?? '';
    final fechaLabel = formatNotificacionFecha(item['created_at']);
    final data = item['data'];
    final confirmacion = data is Map
        ? PushNotificationService.confirmacionDesdeData(
            Map<String, dynamic>.from(data),
          )
        : null;
    final key = _itemKey(item);
    final confirmado = _confirmados.contains(key);
    final confirmando = _confirmando.contains(key);

    final contenido = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                titulo,
                style: BioTypography.title.copyWith(
                  fontWeight: leida ? FontWeight.w500 : FontWeight.w700,
                ),
              ),
            ),
            if (!leida) ...[
              BioSpacing.gapW(BioSpacing.sm),
              BioBadge(label: 'Nueva', intent: UiIntent.primary),
            ],
          ],
        ),
        if (cuerpo.isNotEmpty) ...[
          BioSpacing.gapH(BioSpacing.xs),
          Text(cuerpo, style: BioTypography.bodySm),
        ],
        if (fechaLabel.isNotEmpty) ...[
          BioSpacing.gapH(BioSpacing.xs),
          Text(
            fechaLabel,
            style: BioTypography.caption.copyWith(
              color: context.bio.textMuted,
            ),
          ),
        ],
        if (confirmacion != null) ...[
          BioSpacing.gapH(BioSpacing.sm),
          if (confirmado)
            BioBadge(label: 'Confirmado', intent: UiIntent.success)
          else
            BioButton(
              label: confirmacion['action_label']?.toString() ??
                  'Confirmar asistencia',
              intent: UiIntent.primary,
              variant: BioButtonVariant.filled,
              size: BioButtonSize.sm,
              icon: Icons.check_circle_outline,
              onPressed: confirmando
                  ? null
                  : () => _confirmarAsistencia(item, confirmacion),
            ),
        ],
      ],
    );

    if (leida) {
      return BioCard(onTap: () => _onTapItem(item), child: contenido);
    }
    return BioCard.intent(
      intent: UiIntent.primary,
      onTap: () => _onTapItem(item),
      child: contenido,
    );
  }
}
