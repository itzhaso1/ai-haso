import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api/cashier_api.dart';
import '../../core/config/app_config.dart';

class PosBlockedScreen extends ConsumerWidget {
  const PosBlockedScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.lock_outline, size: 56, color: Color(0xFF64748B)),
              const SizedBox(height: 16),
              Text(
                'الكاشير غير متاح في باقتك الحالية',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
              ),
              const SizedBox(height: 8),
              const Text(
                'قم بترقية الباقة لتفعيل ميزة الكاشير في مساحة العمل الحالية.',
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 24),
              FilledButton(
                onPressed: () async {
                  final uri = Uri.parse('${AppConfig.apiBase}/workspace/billing');
                  await launchUrl(uri, mode: LaunchMode.externalApplication);
                },
                child: const Text('عرض الباقات'),
              ),
              TextButton(
                onPressed: () => context.go('/login'),
                child: const Text('تسجيل الخروج / تغيير الحساب'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

Future<bool> ensurePosEnabled(WidgetRef ref) async {
  try {
    final data = await ref.read(cashierApiProvider).get('/bootstrap');
    return data['pos_enabled'] == true;
  } on ApiException catch (e) {
    if (e.statusCode == 403) return false;
    rethrow;
  }
}
