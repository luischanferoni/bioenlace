import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared/shared.dart';

void main() {
  final json = {
    'sections': [
      {
        'key': 'b',
        'title': 'Odontograma',
        'order': 2,
        'fields': [
          {'key': 'p18', 'label': 'Pieza 18', 'value': 'caries', 'order': 1},
        ],
      },
      {
        'key': 'a',
        'label': 'Agudeza visual',
        'order': 1,
        'fields': [
          {'key': 'od', 'label': 'OD', 'value': '10', 'unit': '/10', 'format': 'integer', 'order': 1},
          {'key': 'vacio', 'label': 'x', 'value': ''},
        ],
      },
      {'key': 'c', 'title': 'Notas', 'order': 3, 'items': ['uno', ' ', 'dos']},
    ],
  };

  test('parsea y ordena secciones y descarta campos vacíos', () {
    final s = ClinicalSection.listFromJson(json);
    expect(s.map((e) => e.key), ['a', 'b', 'c']);
    expect(s.first.title, 'Agudeza visual');
    expect(s.first.fields.length, 1);
    expect(clinicalFieldText(s.first.fields.first), 'OD: 10 /10');
    expect(s.last.items, ['uno', 'dos']);
  });

  test('acepta el alias secciones', () {
    expect(ClinicalSection.listFromJson({'secciones': json['sections']}).length, 3);
    expect(ClinicalSection.listFromJson(null), isEmpty);
  });

  testWidgets('renderiza campos e ítems de cualquier sección', (tester) async {
    final sections = ClinicalSection.listFromJson(json);
    await tester.pumpWidget(MaterialApp(home: Scaffold(body: ClinicalSectionList(sections: sections))));
    expect(find.text('Pieza 18: caries'), findsOneWidget);
    expect(find.text('OD: 10 /10'), findsOneWidget);
    expect(find.text('uno'), findsOneWidget);
  });
}
