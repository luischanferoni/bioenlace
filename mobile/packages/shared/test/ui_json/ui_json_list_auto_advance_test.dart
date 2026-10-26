import 'package:flutter_test/flutter_test.dart';
import 'package:shared/shared.dart';

void main() {
  test('auto-advance only picks lists with one selectable item', () {
    final definition = <String, dynamic>{
      'blocks': [
        {
          'kind': 'list',
          'draft_field': 'triage_zona',
          'selection': {'mode': 'single'},
          'items': [
            {'id': 'zona_cabeza_cuello'},
            {'id': 'zona_pecho'},
          ],
        },
      ],
    };

    expect(UiJsonSingleListPick.fromDefinition(definition), isNull);
  });

  test('auto-advance picks the only selectable list item', () {
    final definition = <String, dynamic>{
      'blocks': [
        {
          'kind': 'list',
          'draft_field': 'triage_zona',
          'selection': {'mode': 'single'},
          'items': [
            {'id': 'zona_cabeza_cuello'},
          ],
        },
      ],
    };

    final pick = UiJsonSingleListPick.fromDefinition(definition);

    expect(pick?.draftField, 'triage_zona');
    expect(pick?.itemId, 'zona_cabeza_cuello');
  });
}
