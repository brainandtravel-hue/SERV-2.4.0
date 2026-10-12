package com.serv.android

import android.Manifest
import android.app.Activity
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import android.webkit.CookieManager
import android.webkit.JavascriptInterface
import android.webkit.WebChromeClient
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.Toast
import com.google.firebase.messaging.FirebaseMessaging

class MainActivity : Activity() {
    private lateinit var webView: WebView

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        requestNotificationPermissionIfNeeded()
        webView = WebView(this)
        configureWebView()
        setContentView(webView)
        loadTarget(intent)
        fetchFcmToken()
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        loadTarget(intent)
    }

    private fun configureWebView() {
        webView.settings.javaScriptEnabled = true
        webView.settings.domStorageEnabled = true
        webView.settings.databaseEnabled = true
        webView.settings.loadsImagesAutomatically = true
        webView.settings.javaScriptCanOpenWindowsAutomatically = false
        webView.settings.allowFileAccess = false
        webView.settings.allowContentAccess = true
        webView.webChromeClient = WebChromeClient()
        webView.addJavascriptInterface(NativeBridge(), "AndroidFCM")
        CookieManager.getInstance().setAcceptCookie(true)
        CookieManager.getInstance().setAcceptThirdPartyCookies(webView, false)

        webView.webViewClient = object : WebViewClient() {
            override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
                val uri = request.url
                return if (uri.scheme == "https" && uri.host == "serv.zya.me") {
                    false
                } else {
                    try { startActivity(Intent(Intent.ACTION_VIEW, uri)) } catch (_: Exception) {}
                    true
                }
            }

            override fun onPageFinished(view: WebView, url: String) {
                super.onPageFinished(view, url)
                view.evaluateJavascript("window.dispatchEvent(new Event('servNativeReady'));", null)
            }
        }
    }

    private fun loadTarget(intent: Intent?) {
        val path = intent?.getStringExtra("notification_url")
        val target = if (path.isNullOrBlank()) AppConfig.BASE_URL
        else AppConfig.BASE_URL + path.removePrefix("/")
        webView.loadUrl(target)
    }

    private fun fetchFcmToken() {
        FirebaseMessaging.getInstance().token.addOnSuccessListener { token ->
            getSharedPreferences(AppConfig.PREFS, MODE_PRIVATE).edit()
                .putString(AppConfig.PREF_FCM_TOKEN, token).apply()
            // serv.js reads this through AndroidFCM and registers it after the user is logged in.
            webView.post {
                webView.evaluateJavascript("window.dispatchEvent(new Event('servFcmTokenReady'));", null)
            }
        }
    }

    private fun requestNotificationPermissionIfNeeded() {
        if (Build.VERSION.SDK_INT >= 33 &&
            checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
            requestPermissions(arrayOf(Manifest.permission.POST_NOTIFICATIONS), 5001)
        }
    }

    override fun onBackPressed() {
        if (::webView.isInitialized && webView.canGoBack()) webView.goBack() else super.onBackPressed()
    }

    inner class NativeBridge {
        @JavascriptInterface
        fun getToken(): String = getSharedPreferences(AppConfig.PREFS, MODE_PRIVATE)
            .getString(AppConfig.PREF_FCM_TOKEN, "") ?: ""

        @JavascriptInterface
        fun isVoiceEnabled(): Boolean = getSharedPreferences(AppConfig.PREFS, MODE_PRIVATE)
            .getBoolean(AppConfig.PREF_VOICE_ENABLED, true)

        @JavascriptInterface
        fun setVoiceEnabled(enabled: Boolean) {
            getSharedPreferences(AppConfig.PREFS, MODE_PRIVATE).edit()
                .putBoolean(AppConfig.PREF_VOICE_ENABLED, enabled).apply()
        }

        @JavascriptInterface
        fun testVoice() {
            runOnUiThread {
                VoiceSpeaker.speak(this@MainActivity, "SERV voice notifications are working.")
            }
        }

        @JavascriptInterface
        fun toast(message: String) {
            runOnUiThread { Toast.makeText(this@MainActivity, message, Toast.LENGTH_SHORT).show() }
        }
    }
}
