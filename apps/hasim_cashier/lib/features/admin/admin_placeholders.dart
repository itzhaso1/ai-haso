/// Admin placeholders for surfaces that still need dedicated write APIs.
library;

import 'package:flutter/material.dart';

import '../../core/widgets/hasim_widgets.dart';

/// Placeholder aligned with web `workspace/pos/items` until catalog write API.
class ItemsAdminPanel extends StatelessWidget {
  const ItemsAdminPanel({super.key});

  @override
  Widget build(BuildContext context) {
    return const Padding(
      padding: EdgeInsets.all(16),
      child: HsEmpty(
        title: 'إدارة الأصناف',
        subtitle:
            'قراءة الأصناف متاحة عبر /catalog. إنشاء/تعديل الأصناف ما زال عبر لوحة الويب حتى يتوفر Endpoint كتابة Additive.',
      ),
    );
  }
}
