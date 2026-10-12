package com.serv.android

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.os.Build
import android.os.PowerManager
import android.media.AudioAttributes
import android.provider.Settings
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage

class ServFirebaseMessagingService : FirebaseMessagingService() {

    override fun onNewToken(token: String) {
        super.onNewToken(token)
        getSharedPreferences(AppConfig.PREFS, MODE_PRIVATE)
            .edit().putString(AppConfig.PREF_FCM_TOKEN, token).apply()
    }

    override fun onMessageReceived(message: RemoteMessage) {
        super.onMessageReceived(message)
        createChannels()

        val data = message.data
        val title = data["title"] ?: message.notification?.title ?: "SERV"
        val body = data["body"] ?: message.notification?.body ?: "You have a new SERV notification."
        val voiceText = data["voice_text"]?.takeIf { it.isNotBlank() } ?: "$title. $body"
        val url = data["url"] ?: "index.php"
        val type = data["notification_type"] ?: "general"
        val voiceEnabled = getSharedPreferences(AppConfig.PREFS, MODE_PRIVATE)
            .getBoolean(AppConfig.PREF_VOICE_ENABLED, true)

        showNotification(title, body, url, type)

        if (voiceEnabled) {
            speakInBackground(voiceText, message.priority)
        }
    }

    private fun speakInBackground(text: String, priority: Int) {
        if (priority == RemoteMessage.PRIORITY_HIGH && Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            try {
                startForegroundService(Intent(this, VoicePlaybackService::class.java).putExtra("text", text))
                return
            } catch (_: Exception) {
                // Fall back to short FCM-service execution below.
            }
        }
        val manager = getSystemService(Context.POWER_SERVICE) as PowerManager
        val wake = manager.newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, "SERV:VoiceNotification")
        wake.acquire(12_000L)
        VoiceSpeaker.speak(applicationContext, text) {
            if (wake.isHeld) wake.release()
        }
    }

    private fun showNotification(title: String, body: String, url: String, type: String) {
        val channelId = when (type) {
            "orders" -> AppConfig.CHANNEL_ORDERS
            "requests" -> AppConfig.CHANNEL_REQUESTS
            else -> AppConfig.CHANNEL_GENERAL
        }
        val intent = Intent(this, MainActivity::class.java).apply {
            putExtra("notification_url", url)
            flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
        }
        val pendingFlags = PendingIntent.FLAG_UPDATE_CURRENT or
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) PendingIntent.FLAG_IMMUTABLE else 0
        val pending = PendingIntent.getActivity(this, url.hashCode(), intent, pendingFlags)

        val builder = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            android.app.Notification.Builder(this, channelId)
        } else {
            android.app.Notification.Builder(this)
        }
            .setSmallIcon(com.serv.android.R.drawable.ic_launcher)
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(android.app.Notification.BigTextStyle().bigText(body))
            .setAutoCancel(true)
            .setContentIntent(pending)
            .setCategory(android.app.Notification.CATEGORY_EVENT)
            .setPriority(android.app.Notification.PRIORITY_HIGH)

        val nm = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        nm.notify((System.currentTimeMillis() % Int.MAX_VALUE).toInt(), builder.build())
    }

    private fun createChannels() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val nm = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        val channels = listOf(
            NotificationChannel(AppConfig.CHANNEL_ORDERS, getString(R.string.notification_channel_orders), NotificationManager.IMPORTANCE_HIGH).apply { setSound(Settings.System.DEFAULT_NOTIFICATION_URI, AudioAttributes.Builder().setUsage(AudioAttributes.USAGE_NOTIFICATION).build()) },
            NotificationChannel(AppConfig.CHANNEL_REQUESTS, getString(R.string.notification_channel_requests), NotificationManager.IMPORTANCE_HIGH).apply { setSound(Settings.System.DEFAULT_NOTIFICATION_URI, AudioAttributes.Builder().setUsage(AudioAttributes.USAGE_NOTIFICATION).build()) },
            NotificationChannel(AppConfig.CHANNEL_GENERAL, getString(R.string.notification_channel_general), NotificationManager.IMPORTANCE_DEFAULT).apply { setSound(Settings.System.DEFAULT_NOTIFICATION_URI, AudioAttributes.Builder().setUsage(AudioAttributes.USAGE_NOTIFICATION).build()) },
            NotificationChannel(AppConfig.CHANNEL_VOICE, getString(R.string.notification_channel_voice), NotificationManager.IMPORTANCE_HIGH).apply { setSound(Settings.System.DEFAULT_NOTIFICATION_URI, AudioAttributes.Builder().setUsage(AudioAttributes.USAGE_NOTIFICATION).build()) }
        )
        nm.createNotificationChannels(channels)
    }
}
