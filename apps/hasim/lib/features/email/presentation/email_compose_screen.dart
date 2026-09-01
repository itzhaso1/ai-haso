
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim/core/di/providers.dart';
import 'package:hasim/core/network/api_exception.dart';

class EmailComposeScreen extends ConsumerStatefulWidget {
  const EmailComposeScreen({super.key});
  @override
  ConsumerState<EmailComposeScreen> createState() => _EmailComposeScreenState();
}

class _EmailComposeScreenState extends ConsumerState<EmailComposeScreen> {
  final _accountId = TextEditingController();
  final _to = TextEditingController();
  final _subject = TextEditingController();
  final _body = TextEditingController();
  bool _sending = false;

  @override
  void dispose() {
    _accountId.dispose();
    _to.dispose();
    _subject.dispose();
    _body.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    setState(() => _sending = true);
    try {
      await ref.read(emailRepositoryProvider).send(
            emailAccountId: int.parse(_accountId.text.trim()),
            to: _to.text.trim(),
            subject: _subject.text.trim(),
            body: _body.text.trim(),
          );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('تم إرسال الرسالة')));
        context.pop();
      }
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } catch (_) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('تعذر الإرسال')));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('رسالة جديدة')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          TextField(controller: _accountId, decoration: const InputDecoration(labelText: 'معرّف حساب البريد (email_account_id)', helperText: 'مطلوب حسب API الحالية'), keyboardType: TextInputType.number, textDirection: TextDirection.ltr),
          const SizedBox(height: 12),
          TextField(controller: _to, decoration: const InputDecoration(labelText: 'إلى'), textDirection: TextDirection.ltr),
          const SizedBox(height: 12),
          TextField(controller: _subject, decoration: const InputDecoration(labelText: 'الموضوع')),
          const SizedBox(height: 12),
          TextField(controller: _body, decoration: const InputDecoration(labelText: 'النص'), minLines: 8, maxLines: 16),
          const SizedBox(height: 16),
          FilledButton(onPressed: _sending ? null : _send, child: Text(_sending ? 'جارٍ الإرسال...' : 'إرسال')),
        ],
      ),
    );
  }
}
