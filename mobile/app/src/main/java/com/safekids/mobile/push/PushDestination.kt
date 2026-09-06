package com.safekids.mobile.push

import android.content.Intent

data class PushDestination(
  val idNotificacion: Long,
  val idNino: Long?,
  val alcance: String
)

fun Intent.toPushDestination(): PushDestination? {
  val idNotificacion = getStringExtra(EXTRA_ID_NOTIFICACION)?.toLongOrNull()
    ?: getLongExtra(EXTRA_ID_NOTIFICACION, 0L).takeIf { it > 0L }
    ?: return null

  val idNino = getStringExtra(EXTRA_ID_NINO)?.toLongOrNull()
    ?: getLongExtra(EXTRA_ID_NINO, 0L).takeIf { it > 0L }

  return PushDestination(
    idNotificacion = idNotificacion,
    idNino = idNino,
    alcance = getStringExtra(EXTRA_ALCANCE).orEmpty().uppercase()
  )
}

const val EXTRA_ID_NOTIFICACION = "id_notificacion"
const val EXTRA_ID_NINO = "id_nino"
const val EXTRA_ALCANCE = "alcance"
