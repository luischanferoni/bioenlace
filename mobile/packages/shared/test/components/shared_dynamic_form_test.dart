import 'package:flutter_test/flutter_test.dart';
import 'package:shared/shared.dart';

void main() {
  test('shared form components preserve the caller app client', () {
    final form = DynamicForm(
      formConfig: const <String, dynamic>{'fields': <dynamic>[]},
      appClient: 'personalsalud-flutter',
      onSubmit: (_) {},
    );
    final selector = SearchableCardSelector(
      appClient: 'paciente-flutter',
      onChanged: (_) {},
    );

    expect(form.appClient, 'personalsalud-flutter');
    expect(selector.appClient, 'paciente-flutter');
  });
}
