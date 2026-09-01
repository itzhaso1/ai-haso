# حاسم — Flutter Architecture

## 1) API Contract (Mobile v1)

Base: `{API_BASE}/api/mobile/v1`  
Auth: `Authorization: Bearer {token}`  
Workspace: `X-Workspace-Id: {id}`  
Envelope: `{ success, data, meta?, message? }`  
Errors: Arabic `message` + optional `errors`

| Area | Endpoints used |
|------|----------------|
| Auth | POST `/auth/login`, POST `/auth/logout`, GET `/auth/me` |
| Workspace | GET `/workspaces`, GET `/workspaces/current`, POST `/workspaces/switch` |
| Home | GET `/home`, GET `/unread` |
| Conversations | GET `/conversations`, GET `/{id}`, GET `/{id}/messages`, POST messages/read/archive/mute |
| Attachments | POST `/messages/{id}/attachments`, GET signed download |
| Email | inbox/sent/drafts/show/send/read/star |
| Appointments | today/upcoming/show/confirm/cancel/reschedule |
| Notifications | list/read/read-all/preferences |
| Devices | POST/DELETE `/devices` |
| Sessions | GET/DELETE `/sessions` |

**Not invented:** no endpoints beyond docs/mobile-api.md.

## 2) Architecture

Clean-ish feature-first + layered core:

```
UI (Screens/Widgets)
  → Controllers/Notifiers (Riverpod)
    → Repositories
      → ApiClient (Dio)
      → LocalCache (Hive) / SecureStore
RealtimeService (abstract) + PushService (abstract)
```

## 3) Folder layout

`apps/hasim/lib/{core,features,realtime,push,router}`

## 4) State management

**Riverpod** — testable, scalable, no BuildContext coupling.

## 5) Local storage

- `flutter_secure_storage` → token
- `shared_preferences` → workspace id, API base URL
- `Hive` → conversation/message/email list cache for offline-first feel
- `cached_network_image` → images

## 6) Realtime strategy

1. Prefer Laravel private channels when `PUSHER_*` / Reverb configured.
2. Default: **smart polling** (conversations list + open thread) — honest fallback because backend broadcast is often `log` locally.
3. Abstract `RealtimeService` so switching to Pusher/Reverb does not rewrite UI.

## 7) Navigation

`go_router` + Shell with bottom tabs:

Home · Conversations · Email · Bookings · More

Auth gate redirects to Login.

## 8) Screens (v1)

1. Login  
2. Workspace picker / switcher  
3. Home dashboard  
4. Conversations list  
5. Conversation thread (chat)  
6. Attachment viewer / picker  
7. Email list + detail + compose (API-backed)  
8. Appointments list + detail + actions  
9. Notifications  
10. Settings / account / sessions / preferences  

RTL Arabic + Cairo font from day one.
