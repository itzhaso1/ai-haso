
import 'package:flutter/material.dart';

class AsyncBody extends StatelessWidget {
  const AsyncBody({
    super.key,
    required this.loading,
    required this.error,
    required this.isEmpty,
    required this.onRetry,
    required this.child,
    this.emptyTitle = 'لا توجد بيانات',
    this.emptySubtitle,
  });

  final bool loading;
  final String? error;
  final bool isEmpty;
  final VoidCallback onRetry;
  final Widget child;
  final String emptyTitle;
  final String? emptySubtitle;

  @override
  Widget build(BuildContext context) {
    if (loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(error!, textAlign: TextAlign.center),
              const SizedBox(height: 12),
              FilledButton(onPressed: onRetry, child: const Text('إعادة المحاولة')),
            ],
          ),
        ),
      );
    }
    if (isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.inbox_outlined, size: 48, color: Colors.grey.shade500),
              const SizedBox(height: 12),
              Text(emptyTitle, style: Theme.of(context).textTheme.titleMedium),
              if (emptySubtitle != null) ...[
                const SizedBox(height: 6),
                Text(emptySubtitle!, textAlign: TextAlign.center, style: TextStyle(color: Colors.grey.shade600)),
              ],
            ],
          ),
        ),
      );
    }
    return child;
  }
}
