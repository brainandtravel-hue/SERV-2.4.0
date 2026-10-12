SERV 2.4.0

Mobile-first hospitality operating system for restaurants and hotels.

KEY FLOWS
- Restaurant QR -> mobile menu -> order -> staff status workflow -> customer tracking.
- Hotel room QR -> configurable service hub -> service-specific menus/items or service requests.
- Owner dashboard -> sales reports -> staff accountability.
- Super Admin -> admin.php -> create and manage businesses.
- Staff actions are recorded in order_status_history and audit_logs.
- Installable PWA manifest + service worker.
- Browser notification/sound support is included where the browser permits it.

INSTALL
1. Upload the folder to PHP hosting with PDO MySQL and PHP GD enabled.
2. Open install.php.
3. Enter database credentials and create the first business owner.
4. Optionally create a SERV platform super-admin account during installation.
5. Sign in.

QR
QRs resolve to q.php and then the mobile customer experience. The package can use PHP QR Code if vendor/phpqrcode is present; otherwise app/qr.php uses a visual fallback and reports when GD is unavailable. For production-grade scannable QR generation, place a compatible PHP QR Code library in vendor/phpqrcode.

NOTIFICATIONS
The service worker handles Web Push events. Full background Web Push delivery requires a VAPID/Web Push sender configuration. The current order workflow also creates server-side notification logs and supports in-page browser notifications/sound.

UPGRADE
The application runs a small database migration on first database access. This repairs the previous missing created_at columns and adds the new order/status/service/reporting fields.

HOTEL SERVICE MENUS
- Hotel owners can create categories and items for each enabled hotel service.
- Guests see only the items belonging to the service they select (for example Restaurant, Bar, Room Service).
- Services without a menu can remain request-based.

VOICE ALERTS
- Staff can enable alerts & voice on the Orders or Service Requests page.
- New orders can be announced as “You have a new order from Table 7/Room 305.”
- New hotel service requests can be announced as “Service was requested from Room 305: Housekeeping.”
- Voice/audio playback is subject to browser permissions and requires the dashboard to remain open.


2.1.3 update: expanded Super Admin control centre, business types, monthly plans, event/conference packages, event data model, payment transaction model, device-token model, platform settings, revenue analytics, subscription controls, event controls, notification broadcast and audit/device dashboards.

SERV 2.1.3 adds a guided mobile onboarding wizard for restaurant, hotel and event-centre businesses.



SERV 2.4.0 — Direct Firebase FCM
--------------------------------
- Android push delivery now uses Firebase Cloud Messaging (FCM) directly.
- OneSignal is no longer required for transactional push delivery.
- Upload the Firebase service-account JSON separately to: storage/firebase-service-account.json
- Never commit the service-account JSON to GitHub or place it in the Android APK.
- Authenticated Android clients can register their FCM token at api/fcm-token.php.
- FCM diagnostic endpoint: api/fcm-status.php
- Existing device_tokens and notification_logs tables are reused.
- Notification channel IDs sent by SERV are serv_orders, serv_requests and serv_general.
- The Android application must create those channels and request POST_NOTIFICATIONS permission.

SERV production URL
- Primary Web URL: https://serv.zya.me/
- All SERV-generated relative links, QR destinations, redirects and API navigation use the current host dynamically.
- Deploy this release to the document root of serv.zya.me/.


ANDROID APK + FCM VOICE
-----------------------
- A native Android project is included under android/.
- The APK uses Firebase Cloud Messaging for background delivery and Android Text-to-Speech for spoken alerts.
- The native app loads the existing SERV web application at https://serv.zya.me/.
- FCM data-only high-priority messages are used so the Android service can create the notification and speak voice_text.
- Android package name: com.serv.android
- Put Firebase google-services.json in android/app/ locally or provide its complete contents as the GitHub Actions secret GOOGLE_SERVICES_JSON.
- Keep storage/firebase-service-account.json only on the PHP server.
- Full setup instructions: docs/ANDROID-FCM-VOICE-SETUP.md
