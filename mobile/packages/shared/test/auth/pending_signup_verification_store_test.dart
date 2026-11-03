import 'package:flutter_test/flutter_test.dart';
import 'package:shared/auth/auth_token_store.dart';
import 'package:shared/auth/pending_signup_verification_store.dart';

class _MemorySecureKeyValueStore implements SecureKeyValueStore {
  final Map<String, String> values = {};

  @override
  Future<String?> read(String key) async => values[key];

  @override
  Future<void> write(String key, String value) async {
    values[key] = value;
  }

  @override
  Future<void> delete(String key) async {
    values.remove(key);
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('stores and reads verification IDs using secure storage', () async {
    final secure = _MemorySecureKeyValueStore();
    final store = PendingSignupVerificationStore(secureStorage: secure);

    await store.write('  didit-session-id  ');

    expect(await store.read(), 'didit-session-id');
    expect(
      secure.values[PendingSignupVerificationStore.verificationKey],
      'didit-session-id',
    );
  });

  test('deletes the pending verification after account creation', () async {
    final secure = _MemorySecureKeyValueStore();
    final store = PendingSignupVerificationStore(secureStorage: secure);

    await store.write('didit-session-id');
    await store.delete();

    expect(await store.read(), isNull);
  });

  test('rejects empty verification IDs', () async {
    final store = PendingSignupVerificationStore(
      secureStorage: _MemorySecureKeyValueStore(),
    );

    await expectLater(store.write('  '), throwsArgumentError);
  });
}
