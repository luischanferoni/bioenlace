import 'package:flutter_test/flutter_test.dart';
import 'package:shared/auth/auth_token_store.dart';
import 'package:shared_preferences/shared_preferences.dart';

class _MemorySecureKeyValueStore implements SecureKeyValueStore {
  final Map<String, String> values = {};
  bool failDelete = false;

  @override
  Future<String?> read(String key) async => values[key];

  @override
  Future<void> write(String key, String value) async {
    values[key] = value;
  }

  @override
  Future<void> delete(String key) async {
    if (failDelete) {
      throw StateError('Secure delete failed.');
    }
    values.remove(key);
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  test(
    'migrates a legacy token into secure storage and removes the plaintext',
    () async {
      SharedPreferences.setMockInitialValues({'auth_token': 'legacy-jwt'});
      final secure = _MemorySecureKeyValueStore();
      final store = AuthTokenStore(secureStorage: secure);

      expect(await store.read(), 'legacy-jwt');
      expect(secure.values['auth_token'], 'legacy-jwt');
      expect(
        (await SharedPreferences.getInstance()).containsKey('auth_token'),
        isFalse,
      );
    },
  );

  test(
    'prefers secure storage and removes any remaining plaintext copy',
    () async {
      SharedPreferences.setMockInitialValues({'auth_token': 'legacy-jwt'});
      final secure =
          _MemorySecureKeyValueStore()..values['auth_token'] = 'secure-jwt';
      final store = AuthTokenStore(secureStorage: secure);

      expect(await store.read(), 'secure-jwt');
      expect(
        (await SharedPreferences.getInstance()).containsKey('auth_token'),
        isFalse,
      );
    },
  );

  test('writes and deletes tokens only through secure storage', () async {
    final secure = _MemorySecureKeyValueStore();
    final store = AuthTokenStore(secureStorage: secure);

    await store.write('new-jwt');
    expect(secure.values['auth_token'], 'new-jwt');
    expect(
      (await SharedPreferences.getInstance()).containsKey('auth_token'),
      isFalse,
    );

    await store.delete();
    expect(secure.values, isEmpty);
  });

  test(
    'removes legacy plaintext before reporting a secure delete failure',
    () async {
      SharedPreferences.setMockInitialValues({'auth_token': 'legacy-jwt'});
      final secure =
          _MemorySecureKeyValueStore()
            ..values['auth_token'] = 'secure-jwt'
            ..failDelete = true;
      final store = AuthTokenStore(secureStorage: secure);

      await expectLater(store.delete(), throwsStateError);
      expect(
        (await SharedPreferences.getInstance()).containsKey('auth_token'),
        isFalse,
      );
      expect(secure.values['auth_token'], 'secure-jwt');
    },
  );
}
