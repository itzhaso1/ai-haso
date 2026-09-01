import 'package:flutter/material.dart';

import '../../core/theme/hasim_colors.dart';
import '../../core/widgets/hasim_widgets.dart';

/// Placeholder aligned with web `workspace/pos/items` until a cashier catalog
/// write API is added (read catalog already powers the cashier grid).
class ItemsAdminPanel extends StatelessWidget {
  const ItemsAdminPanel({super.key});

  @override
  Widget build(BuildContext context) {
    return const Padding(
      padding: EdgeInsets.all(16),
      child: HsEmpty(
        title: 'إدارة الأصناف',
        subtitle:
            'عرض وتعديل الأصناف يتم حاليًا من لوحة الويب. شبكة الكاشير تقرأ التصنيفات والمنتجات عبر /catalog. يلزم Endpoint إدارة Additive قبل تفعيل التحرير هنا.',
      ),
    );
  }
}

/// Placeholder aligned with web daily reports — no /api/cashier/v1/reports yet.
class DailyReportsPanel extends StatelessWidget {
  const DailyReportsPanel({super.key});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: HsCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: const [
            Text(
              'التقارير اليومية',
              style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
            ),
            SizedBox(height: 8),
            Text(
              'شاشة التقارير موجودة في موقع الكاشير. لا يتوفر بعد Endpoint للتقارير ضمن /api/cashier/v1 — لن نُنشئ API إلا بشكل Additive وبعد التوثيق.',
              style: TextStyle(fontSize: 12, color: HasimColors.muted, height: 1.45),
            ),
          ],
        ),
      ),
    );
  }
}
