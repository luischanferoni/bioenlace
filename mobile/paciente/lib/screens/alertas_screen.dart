import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../services/notificaciones_service.dart';
import '../services/push_notification_service.dart';

/// Bandeja de alertas in-app del paciente.
class AlertasScreen extends StatefulWidget {
  final String? authToken;
  final void Function(Map<String, dynamic> turnoStub)? onAbrirResolver;

  const AlertasScreen({
    Key? key,
    this.authToken,
    this.onAbrirResolver,
  }) : super(key: key);

  @override
  State<AlertasScreen> createState() => _AlertasScreenState();
}

class _AlertasScreenState extends State<AlertasScreen> {
  late NotificacionesService _svc;
  final List<Map<String, dynamic>> _items = [];
  bool _loading = true;
  String? _error;
  int _noLeidas = 0;

  @override
  void initState() {
    super.initState();
    _svc = NotificacionesService(authToken: widget.authToken);
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

  void _onTapItem(Map<String, dynamic> item) {
    _marcarLeida(item);
    final data = item['data'];
    if (data is! Map) return;
    final stub = PushNotificationService.turnoStubDesdePush(
      Map<String, dynamic>.from(data),
    );
    if (stub != null && widget.onAbrirResolver != null) {
      widget.onAbrirResolver!(stub);
      Navigator.pop(context);
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
