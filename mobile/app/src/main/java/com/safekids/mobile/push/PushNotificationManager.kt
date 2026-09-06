package com.safekids.mobile.push

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.graphics.Color
import com.safekids.mobile.MainActivity
import com.safekids.mobile.R

object PushNotificationManager {
  const val CHANNEL_ID = "safekids_alertas"

  fun createChannel(context: Context) {
    val manager = context.getSystemService(NotificationManager::class.java)
    val channel = NotificationChannel(
      CHANNEL_ID,
      context.getString(R.string.safekids_notification_channel_name),
      NotificationManager.IMPORTANCE_HIGH
    ).apply {
      description = context.getString(R.string.safekids_notification_channel_description)
      enableVibration(true)
    }
    manager.createNotificationChannel(channel)
  }

  fun show(
    context: Context,
    title: String,
    body: String,
    data: Map<String, String>
  ) {
    createChannel(context)

    val idNotificacion = data[EXTRA_ID_NOTIFICACION]?.toLongOrNull()
      ?: System.currentTimeMillis()
    val intent = Intent(context, MainActivity::class.java).apply {
      flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
      putExtra(EXTRA_ID_NOTIFICACION, idNotificacion.toString())
      putExtra(EXTRA_ID_NINO, data[EXTRA_ID_NINO].orEmpty())
      putExtra(EXTRA_ALCANCE, data[EXTRA_ALCANCE].orEmpty())
    }
    val pendingIntent = PendingIntent.getActivity(
      context,
      idNotificacion.hashCode(),
      intent,
      PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
    )

    val notification = Notification.Builder(context, CHANNEL_ID)
      .setSmallIcon(R.drawable.ic_launcher_foreground)
      .setColor(Color.rgb(47, 128, 237))
      .setContentTitle(title)
      .setContentText(body)
      .setStyle(Notification.BigTextStyle().bigText(body))
      .setContentIntent(pendingIntent)
      .setAutoCancel(true)
      .setCategory(Notification.CATEGORY_MESSAGE)
      .build()

    context.getSystemService(NotificationManager::class.java)
      .notify(idNotificacion.hashCode(), notification)
  }
}
