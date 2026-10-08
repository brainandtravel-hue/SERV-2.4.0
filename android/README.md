# SERV Android — FCM + Voice Notifications

This is the native Android wrapper for SERV 2.4.0. It keeps the existing SERV PHP/PWA as the UI, while Android handles Firebase Cloud Messaging and Text-to-Speech in the background.

## Firebase setup

1. Create/select the Firebase project used by SERV.
2. Add an Android app with package name `com.serv.android`.
3. Download `google-services.json`.
4. Put it at `android/app/google-services.json` for a local build, or add the full file content as the GitHub Actions secret `GOOGLE_SERVICES_JSON`.
5. The server's Firebase service-account JSON remains on the PHP server at `storage/firebase-service-account.json`; never put that file in this Android project.

## GitHub build

The repository workflow creates the Firebase config from the `GOOGLE_SERVICES_JSON` secret and builds both a debug APK and a release APK.

The debug APK is the easiest one to install for testing. The release APK produced by this project is unsigned unless you add a release keystore/signing configuration.

## Voice behaviour

SERV sends high-priority FCM data messages. The Android service creates the notification itself and speaks the `voice_text` field with Android Text-to-Speech. This is intentional: notification-only FCM messages are normally rendered by Android without giving the app the message callback needed for custom voice handling.

On some Android devices, battery optimization or manufacturer background restrictions can still delay background execution. For production use, exempt the SERV app from battery optimization if your device offers that option.
