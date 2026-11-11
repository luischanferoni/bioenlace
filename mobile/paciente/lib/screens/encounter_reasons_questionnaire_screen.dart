import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../services/encounter_questionnaire_service.dart';
import '../utils/app_texts.dart';

/// Cuestionario pre-turno de motivos: el bot pregunta con opciones fijas y el paciente
/// sólo escribe en la opción «Otra» y en el cierre final (con tope de caracteres).
class EncounterReasonsQuestionnaireScreen extends StatefulWidget {
  final int encounterId;
  final String? authToken;
  final String titulo;

  const EncounterReasonsQuestionnaireScreen({
    super.key,
    required this.encounterId,
    required this.authToken,
    this.titulo = 'Motivos de la consulta',
  });

  @override
  State<EncounterReasonsQuestionnaireScreen> createState() =>
      _EncounterReasonsQuestionnaireScreenState();
}

class _EncounterReasonsQuestionnaireScreenState
    extends State<EncounterReasonsQuestionnaireScreen> {
  late final EncounterQuestionnaireService _service;
  final ScrollController _scrollController = ScrollController();
  final TextEditingController _otherController = TextEditingController();
  final TextEditingController _closingController = TextEditingController();

  Map<String, dynamic>? _state;
  bool _loading = true;
  bool _sending = false;
  String? _error;
  String? _otherForQuestionId;

  @override
  void initState() {
    super.initState();
    _service = EncounterQuestionnaireService(authToken: widget.authToken);
    _load();
  }

  @override
  void dispose() {
    _scrollController.dispose();
    _otherController.dispose();
    _closingController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final result = await _service.fetchState(widget.encounterId);
    if (!mounted) return;
    setState(() {
      _loading = false;
      if (result['success'] == true && result['data'] is Map) {
        _state = Map<String, dynamic>.from(result['data'] as Map);
      } else {
        _error = result['message']?.toString() ?? AppTexts.questionnaireLoadFailed;
      }
    });
  }

  Future<void> _submit({
    required String questionId,
    String? optionId,
    String? texto,
  }) async {
    if (_sending) return;
    setState(() => _sending = true);
    final result = await _service.answer(
      widget.encounterId,
      questionId: questionId,
      optionId: optionId,
      texto: texto,
    );
    if (!mounted) return;
    setState(() {
      _sending = false;
      if (result['success'] == true && result['data'] is Map) {
        _state = Map<String, dynamic>.from(result['data'] as Map);
        _otherForQuestionId = null;
        _otherController.clear();
        _closingController.clear();
      }
    });
    if (result['success'] != true) {
      _showError(result['message']?.toString() ?? AppTexts.questionnaireAnswerFailed);
      return;
    }
    _scrollToBottom();
  }

  void _showError(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: IntentPalette.of(UiIntent.danger).base,
      ),
    );
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scrollController.hasClients) return;
      _scrollController.animateTo(
        _scrollController.position.maxScrollExtent,
        duration: BioMotion.normal,
        curve: BioMotion.standard,
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: context.bio.paperBackground,
      appBar: BioAppBar(title: widget.titulo),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? _buildError(context)
              : _buildContent(context),
    );
  }

  Widget _buildError(BuildContext context) {
    return Center(
      child: Padding(
        padding: BioSpacing.pageAll,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            BioAlert.danger(message: _error!),
            BioSpacing.gapH(BioSpacing.lg),
            BioButton.primary(
              label: AppTexts.retry,
              icon: Icons.refresh,
              onPressed: _load,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildContent(BuildContext context) {
    final state = _state ?? const <String, dynamic>{};
    final intro = state['intro']?.toString().trim() ?? '';
    final title = state['title']?.toString().trim() ?? '';
    final inputAbierto = state['input_abierto'] == true;
    final completed = state['completed'] == true;
    final resumen = state['motivos_resumen']?.toString().trim() ?? '';
    final answered = _asMaps(state['answered']);
    final questions = _asMaps(state['questions']);
    final closing = state['closing'] is Map
        ? Map<String, dynamic>.from(state['closing'] as Map)
        : null;

    return ListView(
      controller: _scrollController,
      padding: const EdgeInsets.symmetric(
        vertical: BioSpacing.sm,
        horizontal: BioSpacing.md,
      ),
      children: [
        if (intro.isNotEmpty)
          EncounterReasonsGuideBubble(
            message: intro,
            title: title.isNotEmpty ? title : null,
          ),
        for (final answer in answered) _answeredBubble(context, answer),
        if (!inputAbierto && !completed) ...[
          BioSpacing.gapH(BioSpacing.sm),
          BioAlert.warning(
            message: AppTexts.reasonsChatClosed,
            icon: Icons.lock_clock_outlined,
          ),
        ],
        if (resumen.isNotEmpty) ...[
          BioSpacing.gapH(BioSpacing.sm),
          BioAlert.success(
            message: AppTexts.reasonsSummaryForDoctor(resumen),
            icon: Icons.summarize_outlined,
          ),
        ],
        if (completed) ...[
          BioSpacing.gapH(BioSpacing.sm),
          BioAlert.success(
            message: AppTexts.questionnaireCompleted,
            icon: Icons.check_circle_outline,
          ),
        ],
        if (inputAbierto && questions.isNotEmpty)
          _questionBlock(context, questions.first),
        if (inputAbierto && closing != null) _closingBlock(context, closing),
        BioSpacing.gapH(BioSpacing.xl),
      ],
    );
  }

  Widget _questionBlock(BuildContext context, Map<String, dynamic> question) {
    final questionId = question['id']?.toString() ?? '';
    final text = question['text']?.toString() ?? '';
    final options = _asMaps(question['options']);
    final allowOther = question['allow_other'] == true;
    final otherMax = question['other_max_length'] is int
        ? question['other_max_length'] as int
        : 120;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        EncounterReasonsGuideBubble(message: text, title: null),
        BioSpacing.gapH(BioSpacing.xs),
        for (final option in options)
          Padding(
            padding: const EdgeInsets.only(bottom: BioSpacing.xs),
            child: BioButton(
              label: option['label']?.toString() ?? '',
              intent: UiIntent.primary,
              variant: BioButtonVariant.outline,
              onPressed: _sending
                  ? null
                  : () => _submit(
                        questionId: questionId,
                        optionId: option['id']?.toString(),
                      ),
            ),
          ),
        if (allowOther) ...[
          if (_otherForQuestionId == questionId) ...[
            BioInput(
              controller: _otherController,
              maxLength: otherMax,
              maxLines: 3,
              autofocus: true,
              hint: AppTexts.questionnaireOtherHint,
              onChanged: (_) => setState(() {}),
            ),
            BioSpacing.gapH(BioSpacing.xs),
            BioButton.primary(
              label: AppTexts.questionnaireSend,
              onPressed: _sending || _otherController.text.trim().isEmpty
                  ? null
                  : () => _submit(
                        questionId: questionId,
                        optionId: 'otra',
                        texto: _otherController.text,
                      ),
            ),
          ] else
            BioButton(
              label: AppTexts.questionnaireOther,
              intent: UiIntent.neutral,
              variant: BioButtonVariant.outline,
              icon: Icons.edit_outlined,
              onPressed: _sending
                  ? null
                  : () => setState(() => _otherForQuestionId = questionId),
            ),
        ],
      ],
    );
  }

  Widget _closingBlock(BuildContext context, Map<String, dynamic> closing) {
    final text = closing['text']?.toString() ?? '';
    final hint = closing['hint']?.toString() ?? '';
    final maxLength = closing['max_length'] is int
        ? closing['max_length'] as int
        : 320;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        EncounterReasonsGuideBubble(message: text, title: null),
        BioSpacing.gapH(BioSpacing.xs),
        BioInput(
          controller: _closingController,
          maxLength: maxLength,
          maxLines: 4,
          hint: hint.isNotEmpty ? hint : AppTexts.questionnaireClosingHint,
          onChanged: (_) => setState(() {}),
        ),
        BioSpacing.gapH(BioSpacing.sm),
        BioButton.primary(
          label: AppTexts.questionnaireSend,
          onPressed: _sending
              ? null
              : () => _submit(
                    questionId: _closingId(closing),
                    texto: _closingController.text,
                  ),
        ),
        BioSpacing.gapH(BioSpacing.xs),
        BioButton(
          label: AppTexts.questionnaireSkip,
          intent: UiIntent.neutral,
          variant: BioButtonVariant.outline,
          onPressed: _sending
              ? null
              : () => _submit(questionId: _closingId(closing), texto: ''),
        ),
      ],
    );
  }

  Widget _answeredBubble(BuildContext context, Map<String, dynamic> answer) {
    final palette = IntentPalette.of(UiIntent.primary);
    final question = answer['question_text']?.toString() ?? '';
    final option = answer['option_label']?.toString() ?? '';
    final free = answer['texto_libre']?.toString().trim() ?? '';
    final reply = free.isNotEmpty
        ? (option.isNotEmpty ? '$option: $free' : free)
        : option;

    return Align(
      alignment: Alignment.centerRight,
      child: Container(
        margin: const EdgeInsets.symmetric(
          vertical: BioSpacing.xs,
          horizontal: BioSpacing.sm,
        ),
        padding: const EdgeInsets.all(BioSpacing.md),
        constraints: BoxConstraints(
          maxWidth: MediaQuery.of(context).size.width * 0.82,
        ),
        decoration: BoxDecoration(
          color: palette.base,
          borderRadius: BorderRadius.circular(BioRadius.md),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              question,
              style: BioTypography.caption.copyWith(
                color: palette.onBase.withValues(alpha: 0.78),
              ),
            ),
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              reply,
              style: BioTypography.body.copyWith(color: palette.onBase),
            ),
          ],
        ),
      ),
    );
  }

  static String _closingId(Map<String, dynamic> closing) {
    final id = closing['id']?.toString().trim() ?? '';
    return id.isNotEmpty ? id : 'cierre_libre';
  }

  static List<Map<String, dynamic>> _asMaps(dynamic raw) {
    if (raw is! List) return const [];
    return raw
        .whereType<Map>()
        .map((row) => Map<String, dynamic>.from(row))
        .toList(growable: false);
  }
}
