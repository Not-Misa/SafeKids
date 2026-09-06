package com.safekids.mobile.data

import android.content.Context

class SessionStore(context: Context) {
  private val preferences = context.getSharedPreferences("safekids_session", Context.MODE_PRIVATE)

  fun save(session: TutorSession) {
    preferences.edit()
      .putLong("id_usuario", session.idUsuario)
      .putLong("id_guarderia", session.idGuarderia)
      .putString("email", session.email)
      .putString("token", session.token)
      .putString("token_expira_en", session.tokenExpiraEn)
      .putBoolean("requiere_cambio_password", session.requiereCambioPassword)
      .putLong("id_tutor", session.tutor.idTutor)
      .putString("tutor_nombres", session.tutor.nombres)
      .putString("tutor_apellidos", session.tutor.apellidos)
      .putString("tutor_telefono", session.tutor.telefono)
      .putString("tutor_foto_url", session.tutor.fotoUrl)
      .apply()
  }

  fun load(): TutorSession? {
    val idUsuario = preferences.getLong("id_usuario", 0)
    val idGuarderia = preferences.getLong("id_guarderia", 0)
    val idTutor = preferences.getLong("id_tutor", 0)
    val token = preferences.getString("token", "").orEmpty()
    val tokenExpiraEn = preferences.getString("token_expira_en", "").orEmpty()
    if (
      idUsuario <= 0 ||
      idGuarderia <= 0 ||
      idTutor <= 0 ||
      token.isBlank() ||
      tokenExpiraEn.isBlank()
    ) {
      clear()
      return null
    }

    return TutorSession(
      idUsuario = idUsuario,
      idGuarderia = idGuarderia,
      email = preferences.getString("email", "").orEmpty(),
      token = token,
      tokenExpiraEn = tokenExpiraEn,
      requiereCambioPassword = preferences.getBoolean("requiere_cambio_password", false),
      tutor = Tutor(
        idTutor = idTutor,
        nombres = preferences.getString("tutor_nombres", "").orEmpty(),
        apellidos = preferences.getString("tutor_apellidos", "").orEmpty(),
        telefono = preferences.getString("tutor_telefono", null),
        fotoUrl = preferences.getString("tutor_foto_url", null)
      )
    )
  }

  fun clear() {
    preferences.edit().clear().apply()
  }
}
