
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:hasim/core/di/providers.dart';
import 'package:hasim/core/models/models.dart';
import 'package:hasim/core/network/api_exception.dart';

class EmailDetailScreen extends ConsumerStatefulWidget {
  const EmailDetailScreen({super.key, required this.id});
  final int id;
  @override
  ConsumerState<EmailDetailScreen> createState() => _EmailDetailScreenState();
}

class _EmailDetailScreenState extends ConsumerState<EmailDetailScreen> {
  EmailMessageModel? _mail;
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final mail = await ref.read(emailRepositoryProvider).show(widget.id);
      setState(() { _mail = mail; _loading = false; });
    } on ApiException catch (e) {
      setState(() { _error = e.message; _loading = false; });
    } catch (_) {
      setState(() { _error = 'تعذر فتح الرسالة.'; _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('تفاصيل الرسالة'),
        actions: [
          IconButton(
            onPressed: _mail == null ? null : () async {
              await ref.read(emailRepositoryProvider).star(_mail!.id);
              await _load();
            },
            icon: Icon(_mail?.isStarred == true ? Icons.star : Icons.star_border),
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    Text(_mail!.subject ?? '(بدون موضوع)', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
                    const SizedBox(height: 8),
                    Text('من: ${_mail!.sender}'),
                    Text('إلى: ${_mail!.recipient}'),
                    const Divider(height: 24),
                    Text(_mail!.body ?? _mail!.preview ?? '', style: const TextStyle(height: 1.5)),
                  ],
                ),
    );
  }
}
