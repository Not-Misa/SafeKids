package com.safekids.mobile.data

import android.content.Context

class PushTokenStore(context: Context) {
  private val preferences = context.getSharedPreferences(
    "safekids_push",
    Context.MODE_PRIVATE
  )

  fun save(token: String) {
    preferences.edit().putString("token_fcm", token).apply()
  }

  fun load(): String? {
    return preferences.getString("token_fcm", null)?.takeIf { it.isNotBlank() }
  }

  fun clear(installationId: String? = null) {
    if (installationId == null || load() == installationId) {
      preferences.edit().remove("token_fcm").apply()
    }
  }
}
