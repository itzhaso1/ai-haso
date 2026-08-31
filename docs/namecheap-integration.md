# Namecheap Integration

## Architecture

- `DomainRegistrarInterface` defines provider contract.
- `NamecheapRegistrar` implements provider logic.
- `DomainService` orchestrates business flow and queue jobs.
- `NamecheapXmlParser` parses API XML responses.
- `DomainProviderException` carries safe provider errors.

Binding:

- `DomainRegistrarInterface` is bound to `NamecheapRegistrar` in `AppServiceProvider`.

## Environment configuration

```env
NAMECHEAP_ENV=sandbox
NAMECHEAP_API_USER=your_api_user
NAMECHEAP_API_KEY=your_api_key
NAMECHEAP_USERNAME=your_username
NAMECHEAP_CLIENT_IP=your_allowed_client_ip
NAMECHEAP_TIMEOUT=20
NAMECHEAP_CONNECT_TIMEOUT=8
```

Base URLs are configured in `config/services.php`:

- Sandbox: `https://api.sandbox.namecheap.com/xml.response`
- Production: `https://api.namecheap.com/xml.response`

## Commands used

Current implementation supports:

- `namecheap.domains.check`
- `namecheap.domains.create`
- `namecheap.domains.getInfo`
- `namecheap.domains.getList`
- `namecheap.domains.dns.getHosts`
- `namecheap.domains.dns.setHosts`
- `namecheap.domains.renew`

## Request behavior

- Server-side credentials only.
- Explicit connect + request timeouts.
- Provider errors parsed from XML `ApiResponse Status="ERROR"`.
- Safe operational metadata is saved in `website_domain_operations` and `website_domains.metadata`.

## Error handling

All provider failures are normalized into `DomainProviderException` with:

- user-safe message
- provider error codes/messages (structured)

Controller-level flows return user-friendly errors while retaining technical context in operation metadata for diagnostics.

## Sandbox testing guidance

1. Keep `NAMECHEAP_ENV=sandbox`.
2. Ensure the API user and whitelisted `NAMECHEAP_CLIENT_IP` match sandbox account settings.
3. Run domain search and purchase flows from dashboard domain module.
4. Inspect queued domain jobs and operation records for provider responses.
