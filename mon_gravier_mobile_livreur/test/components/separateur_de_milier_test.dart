import 'package:flutter_test/flutter_test.dart';
import 'package:flutter/services.dart';
import 'package:mon_gravier_com_livreur/components/separateur_de_milier.dart';

void main() {
  late ThousandsSeparatorInputFormatter formatter;

  setUp(() {
    formatter = ThousandsSeparatorInputFormatter();
  });

  /// Helper to simulate typing a new value into a field that had an old value.
  TextEditingValue applyFormat(String oldText, String newText, {int? selectionOffset}) {
    final oldValue = TextEditingValue(
      text: oldText,
      selection: TextSelection.collapsed(offset: oldText.length),
    );
    final newValue = TextEditingValue(
      text: newText,
      selection: TextSelection.collapsed(offset: selectionOffset ?? newText.length),
    );
    return formatter.formatEditUpdate(oldValue, newValue);
  }

  group('ThousandsSeparatorInputFormatter', () {
    test('empty input returns empty string', () {
      final result = applyFormat('', '');
      expect(result.text, '');
    });

    test('single digit remains unchanged', () {
      final result = applyFormat('', '5');
      expect(result.text, '5');
    });

    test('two digits remain unchanged', () {
      final result = applyFormat('', '50');
      expect(result.text, '50');
    });

    test('three digits remain unchanged', () {
      final result = applyFormat('', '500');
      expect(result.text, '500');
    });

    test('"1000" becomes "1 000"', () {
      final result = applyFormat('500', '1000');
      expect(result.text, '1 000');
    });

    test('"10000" becomes "10 000"', () {
      final result = applyFormat('1 000', '10000');
      expect(result.text, '10 000');
    });

    test('"100000" becomes "100 000"', () {
      final result = applyFormat('10 000', '100000');
      expect(result.text, '100 000');
    });

    test('"1000000" becomes "1 000 000"', () {
      final result = applyFormat('100 000', '1000000');
      expect(result.text, '1 000 000');
    });

    test('"1234567" becomes "1 234 567"', () {
      final result = applyFormat('', '1234567');
      expect(result.text, '1 234 567');
    });

    test('"999" stays as "999" (no separator needed)', () {
      final result = applyFormat('99', '999');
      expect(result.text, '999');
    });

    test('deletion of separator character works correctly', () {
      // Simulate: old value is "1 000" (5 chars), new value is "1 00" (4 chars)
      // This simulates deleting the space separator
      // The formatter should handle this and produce "100"
      final oldValue = TextEditingValue(
        text: '1 000',
        selection: TextSelection.collapsed(offset: 5),
      );
      final newValue = TextEditingValue(
        text: '1 00',
        selection: TextSelection.collapsed(offset: 4),
      );

      final result = formatter.formatEditUpdate(oldValue, newValue);

      // After removing separator, digits become "100" which stays "100" (no separator needed)
      // oldValueText = "1000", newValueText = "100" -> no end-with-separator special case
      // since old text "1 000" doesn't end with separator ' ', normal path applies
      expect(result.text, '100');
    });

    test('clearing all text returns empty', () {
      final result = applyFormat('1 000', '');
      expect(result.text, '');
    });

    test('returns same value when no change in digits', () {
      // If the old value and new value are the same after removing separators,
      // the formatter returns newValue as-is
      final oldValue = TextEditingValue(
        text: '1 000',
        selection: TextSelection.collapsed(offset: 5),
      );
      final newValue = TextEditingValue(
        text: '1 000',
        selection: TextSelection.collapsed(offset: 5),
      );

      final result = formatter.formatEditUpdate(oldValue, newValue);

      expect(result.text, '1 000');
    });

    test('large numbers format correctly', () {
      final result = applyFormat('', '999999999');
      expect(result.text, '999 999 999');
    });

    test('cursor position is maintained after formatting', () {
      final result = applyFormat('', '1000');
      // Text is "1 000" (5 chars), cursor should be at end
      expect(result.selection.baseOffset, result.text.length);
    });
  });
}
