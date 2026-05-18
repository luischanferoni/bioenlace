package com.bioenlace.paciente

import android.app.NotificationChannel
import android.app.NotificationManager
import android.os.Build
import android.os.Bundle
import io.flutter.embedding.android.FlutterFragmentActivity

class MainActivity : FlutterFragmentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        createTurnosNotificationChannel()
    }

    private fun createTurnosNotificationChannel() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) {
            return
        }
        val channel = NotificationChannel(
            CHANNEL_TURNOS,
            "Turnos y alertas",
            NotificationManager.IMPORTANCE_HIGH,
        ).apply {
            description = "Avisos de turnos y acciones requeridas"
        }
        val manager = getSystemService(NotificationManager::class.java)
        manager?.createNotificationChannel(channel)
    }

    companion object {
        const val CHANNEL_TURNOS = "bioenlace_turnos"
    }
}
