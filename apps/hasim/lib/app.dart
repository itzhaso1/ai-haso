
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:hasim/core/theme/app_theme.dart';
import 'package:hasim/features/auth/providers/auth_controller.dart';
import 'package:hasim/router/app_router.dart';

class HasimApp extends ConsumerWidget {
  const HasimApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authControllerProvider);
    if (auth.bootstrapping) {
      return MaterialApp(
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light(),
        locale: const Locale('ar'),
        home: const Scaffold(body: Center(child: CircularProgressIndicator())),
      );
    }

    final router = ref.watch(appRouterProvider);
    return MaterialApp.router(
      title: 'حاسم',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light(),
      locale: const Locale('ar'),
      supportedLocales: const [Locale('ar'), Locale('en')],
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      builder: (context, child) => Directionality(textDirection: TextDirection.rtl, child: child ?? const SizedBox.shrink()),
      routerConfig: router,
    );
  }
}
