import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hasim_cashier/core/widgets/hasim_widgets.dart';
import 'package:hasim_cashier/features/tables/table_action_wizards.dart';

void main() {
  final errors = <Object>[];

  setUp(() {
    errors.clear();
    final previous = FlutterError.onError;
    FlutterError.onError = (details) {
      errors.add(details.exception);
      previous?.call(details);
    };
    addTearDown(() => FlutterError.onError = previous);
  });

  testWidgets('product grid cards do not throw layout/semantics errors',
      (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: GridView.builder(
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 3,
              childAspectRatio: 0.72,
              mainAxisSpacing: 10,
              crossAxisSpacing: 10,
            ),
            itemCount: 9,
            itemBuilder: (_, i) => ProductCard(
              name: 'منتج تجريبي رقم $i',
              priceLabel: '15.00',
              currency: 'SAR',
              sku: 'SKU-$i',
              onAdd: () {},
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(errors, isEmpty, reason: errors.join('\n'));
  });

  testWidgets('transfer wizard dialog pumps without parentDataDirty',
      (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Builder(
          builder: (context) => Scaffold(
            body: TextButton(
              onPressed: () {
                showDialog<void>(
                  context: context,
                  builder: (_) => const TableTransferWizard(
                    title: 'نقل الطاولة',
                    currentTableName: 'T1',
                    candidates: [
                      {'id': 2, 'name': 'T2', 'status': 'available'},
                    ],
                    confirmLabel: 'تأكيد',
                  ),
                );
              },
              child: const Text('open'),
            ),
          ),
        ),
      ),
    );
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
    expect(find.text('نقل الطاولة'), findsOneWidget);
    expect(errors, isEmpty, reason: errors.join('\n'));
  });
}
