/// Operating mode. Core POS never requires Connected.
enum PosOperatingMode { standalone, connected }

class PosMode {
  const PosMode._();

  static const standaloneWorkspaceId = 1;

  static bool isStandaloneToken(String? token) =>
      token != null &&
      (token.startsWith('standalone:') || token == 'local-offline');
}
