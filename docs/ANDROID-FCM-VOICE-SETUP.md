# SERV Android FCM + Voice Notification Setup

## What this build does

The Android app is a WebView shell around `https://serv.zya.me/` with a native Firebase Messaging service. The server sends **data-only high-priority FCM** messages. The APK creates the Android notification and speaks the `voice_text` with Android Text-to-Speech.

This means voice alerts can work while the SERV screen is in the background. It is more appropriate for background voice alerts than browser `SpeechSynthesis`.

## 1. Firebase project

Use the **same Firebase project** whose service-account JSON is used by the SERV PHP server.

In Firebase Console:

1. Open the project.
2. Add an Android app.
3. Android package name: `com.serv.android`.
4. Download `google-services.json`.
5. Do not rename the file.

Firebase's official Android setup requires the app-level `google-services.json`; the file contains project/app identifiers and is not the server's private service-account key.

## 2. Server Firebase credentials

On the PHP server, place the Firebase service-account JSON at:

`storage/firebase-service-account.json`

The PHP code already expects this exact path.

Never put this service-account JSON into GitHub or the APK.

## 3. GitHub

Push this SERV folder to a GitHub repository.

Create this repository secret:

`GOOGLE_SERVICES_JSON`

Set its value to the **complete contents** of your Firebase `google-services.json` file.

Then open:

GitHub → Actions → Build SERV Android APK → Run workflow

The workflow builds:

- Debug APK — easiest for testing/installing.
- Release APK — unsigned unless you later add a release signing key.

## 4. Install the APK

Download the `SERV-Android-2.4.0` artifact from the completed GitHub Actions run.

Install the debug APK on an Android phone with Google Play services.

Open SERV and sign in normally.

Android will ask for notification permission. Allow it.

## 5. Register the phone with SERV

After login, the WebView calls the existing:

`api/fcm-token.php`

The native Firebase token is sent using the logged-in SERV session cookie. The server stores it in the existing `device_tokens` table.

You can verify registration from the SERV FCM diagnostic endpoint:

`api/fcm-status.php`

It should show the Firebase configuration and at least one registered device for the logged-in user.

## 6. Test voice

Inside the Android app:

1. Open SERV.
2. Go to Settings.
3. Find **Android voice notifications**.
4. Make sure **Speak new orders and service requests** is enabled.
5. Tap **Test voice**.

You should hear:

“SERV voice notifications are working.”

If the test is silent, check that the phone has a working Text-to-Speech engine and media volume is turned up.

## 7. Test a real FCM notification

Use two devices/accounts if possible:

- Device A: customer creates an order.
- Device B: staff account is logged into the SERV Android APK.

The server should send a data-only FCM message containing fields such as:

- `title`
- `body`
- `voice_text`
- `url`
- `notification_type`

For a new order, the phone should:

1. Receive FCM.
2. Display an Android notification.
3. Speak the message.
4. Open `orders.php` when the notification is tapped.

For hotel service requests it should open `requests.php`.

## 8. Android battery restrictions

Some Android manufacturers aggressively stop background apps. If notifications are delayed or voice stops when the screen is off, open the phone's battery settings and allow SERV to run without battery optimization/background restriction.

Also keep notification permission enabled.

## 9. Important limitation

No Android implementation can guarantee voice playback after the user force-stops the app, and OEM battery-management policies can still interfere with background execution. Normal FCM delivery and notification behaviour should be tested on the actual phones used by your staff.

## 10. Production signing

The current GitHub workflow is designed to get you a working test APK quickly. Before distributing the app commercially or publishing it to Google Play, add a release keystore and GitHub signing secrets so the release APK is signed consistently.
