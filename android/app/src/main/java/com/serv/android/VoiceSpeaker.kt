package com.serv.android

import android.content.Context
import android.speech.tts.TextToSpeech
import android.speech.tts.UtteranceProgressListener
import java.util.Locale
import java.util.concurrent.atomic.AtomicBoolean

object VoiceSpeaker {
    @Volatile private var tts: TextToSpeech? = null
    private val speaking = AtomicBoolean(false)

    fun speak(context: Context, text: String, onFinished: (() -> Unit)? = null) {
        val clean = text.trim()
        if (clean.isEmpty()) {
            onFinished?.invoke()
            return
        }
        synchronized(this) {
            tts?.stop()
            tts?.shutdown()
            tts = TextToSpeech(context.applicationContext) { status ->
                if (status != TextToSpeech.SUCCESS) {
                    onFinished?.invoke()
                    return@TextToSpeech
                }
                val engine = tts ?: run {
                    onFinished?.invoke()
                    return@TextToSpeech
                }
                val preferred = Locale("en", "NG")
                val result = engine.setLanguage(preferred)
                if (result == TextToSpeech.LANG_MISSING_DATA || result == TextToSpeech.LANG_NOT_SUPPORTED) {
                    engine.language = Locale.US
                }
                engine.setSpeechRate(0.9f)
                engine.setPitch(1.0f)
                engine.setOnUtteranceProgressListener(object : UtteranceProgressListener() {
                    override fun onStart(utteranceId: String?) { speaking.set(true) }
                    override fun onDone(utteranceId: String?) {
                        speaking.set(false)
                        onFinished?.invoke()
                    }
                    override fun onError(utteranceId: String?) {
                        speaking.set(false)
                        onFinished?.invoke()
                    }
                })
                val params = android.os.Bundle()
                params.putFloat(TextToSpeech.Engine.KEY_PARAM_VOLUME, 1.0f)
                engine.speak(clean, TextToSpeech.QUEUE_FLUSH, params, "serv-${System.currentTimeMillis()}")
            }
        }
    }

    fun stop() {
        synchronized(this) {
            tts?.stop()
            speaking.set(false)
        }
    }
}
