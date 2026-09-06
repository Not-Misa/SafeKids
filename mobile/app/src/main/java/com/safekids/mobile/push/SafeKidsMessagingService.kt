package com.safekids.mobile.push

import android.os.Build
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import com.safekids.mobile.data.PushTokenStore
import com.safekids.mobile.data.SafeKidsApi
import com.safekids.mobile.data.SessionStore
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch

class SafeKidsMessagingService : FirebaseMessagingService() {
  override fun onRegistered(installationId: String) {
    super.onRegistered(installationId)
    PushTokenStore(this).save(installationId)

    val session = SessionStore(this).load() ?: return
    if (session.requiereCambioPassword) return
    CoroutineScope(SupervisorJob() + Dispatchers.IO).launch {
      runCatching {
        SafeKidsApi().registrarDispositivo(
          session = session,
          tokenFcm = installationId,
          nombreDispositivo = nombreDispositivo()
        )
      }
    }
  }

  override fun onUnregistered(installationId: String) {
    super.onUnregistered(installationId)
    PushTokenStore(this).clear(installationId)
  }

  override fun onMessageReceived(message: RemoteMessage) {
    super.onMessageReceived(message)
    val title = message.notification?.title
      ?: message.data["titulo"]
      ?: "SafeKids"
    val body = message.notification?.body
      ?: message.data["mensaje"]
      ?: "Tienes una nueva notificacion."

    PushNotificationManager.show(
      context = this,
      title = title,
      body = body,
      data = message.data
    )
  }

  private fun nombreDispositivo(): String {
    return listOf(Build.MANUFACTURER, Build.MODEL)
      .filter { it.isNotBlank() }
      .joinToString(" ")
      .take(120)
  }
}
