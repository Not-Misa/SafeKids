package com.safekids.mobile.data

import com.safekids.mobile.BuildConfig
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder
import java.nio.charset.StandardCharsets
import java.time.LocalDate

class SafeKidsApi(
  private val baseUrl: String = BASE_URL,
  private val onUnauthorized: (() -> Unit)? = null
) {
  suspend fun login(email: String, password: String): TutorSession {
    val json = postJson(
      endpoint = "login.php",
      body = JSONObject()
        .put("usuario_login", email.trim())
        .put("password_login", password)
        .put("cliente", "ANDROID"),
      token = null
    )

    val usuario = json.getJSONObject("usuario")
    val rol = usuario.getString("rol")
    if (rol != "TUTOR") {
      throw IllegalStateException("Esta app movil es para padres o tutores.")
    }

    val tutorJson = usuario.optJSONObject("tutor")
      ?: throw IllegalStateException("Tu cuenta no tiene perfil de tutor vinculado.")

    return TutorSession(
      idUsuario = usuario.getLong("id_usuario"),
      idGuarderia = usuario.getLong("id_guarderia"),
      email = usuario.getString("email"),
      token = json.getString("token"),
      tokenExpiraEn = json.getString("token_expira_en"),
      requiereCambioPassword = usuario.optBoolean("requiere_cambio_password", false),
      tutor = Tutor(
        idTutor = tutorJson.getLong("id_tutor"),
        nombres = tutorJson.optString("nombres"),
        apellidos = tutorJson.optString("apellidos"),
        telefono = tutorJson.optNullableString("telefono"),
        fotoUrl = tutorJson.optNullableString("foto_url")
      )
    )
  }

  suspend fun misHijos(session: TutorSession): List<Hijo> {
    val json = getJson(
      endpoint = "mis_hijos.php",
      params = mapOf(
        "id_guarderia" to session.idGuarderia.toString(),
        "id_usuario" to session.idUsuario.toString()
      ),
      token = session.token
    )
    return json.getJSONArray("hijos").mapObjects { it.toHijo() }
  }

  suspend fun perfilHijo(session: TutorSession, idNino: Long): PerfilHijo {
    val json = getJson(
      endpoint = "perfil_hijo.php",
      params = mapOf(
        "id_guarderia" to session.idGuarderia.toString(),
        "id_usuario" to session.idUsuario.toString(),
        "id_nino" to idNino.toString()
      ),
      token = session.token
    )
    val nino = json.getJSONObject("nino")
    val expediente = nino.optJSONObject("expediente_medico")
    val hijo = nino.toHijo().copy(
      tipoSangre = expediente?.optNullableString("tipo_sangre"),
      alergias = expediente?.optNullableString("alergias"),
      padecimientos = expediente?.optNullableString("padecimientos")
    )
    return PerfilHijo(
      hijo = hijo,
      expediente = ExpedienteMedico(
        tipoSangre = expediente?.optNullableString("tipo_sangre"),
        alergias = expediente?.optNullableString("alergias"),
        padecimientos = expediente?.optNullableString("padecimientos"),
        medicamentosHabituales = expediente?.optNullableString("medicamentos_habituales"),
        restriccionesAlimentarias = expediente?.optNullableString("restricciones_alimentarias"),
        medicoNombre = expediente?.optNullableString("medico_nombre"),
        medicoTelefono = expediente?.optNullableString("medico_telefono"),
        institucionMedica = expediente?.optNullableString("institucion_medica"),
        indicacionesEmergencia = expediente?.optNullableString("indicaciones_emergencia"),
        observaciones = expediente?.optNullableString("observaciones")
      ),
      tutores = nino.optJSONArray("tutores")?.mapObjects { it.toTutorPerfil() }.orEmpty(),
      contactos = nino.optJSONArray("contactos_autorizados")?.mapObjects {
        it.toContactoEmergencia()
      }.orEmpty()
    )
  }

  suspend fun reportesDeMiHijo(
    session: TutorSession,
    idNino: Long,
    desde: String = LocalDate.now().minusDays(7).toString(),
    hasta: String = LocalDate.now().toString()
  ): List<ReporteDiario> {
    val json = getJson(
      endpoint = "reportes_de_mi_hijo.php",
      params = mapOf(
        "id_guarderia" to session.idGuarderia.toString(),
        "id_usuario" to session.idUsuario.toString(),
        "id_nino" to idNino.toString(),
        "desde" to desde,
        "hasta" to hasta
      ),
      token = session.token
    )
    return json.getJSONArray("reportes").mapObjects { it.toReporte() }
  }

  suspend fun notificacionesDeMiHijo(
    session: TutorSession,
    idNino: Long,
    desde: String = LocalDate.now().minusDays(30).toString(),
    hasta: String = LocalDate.now().toString()
  ): List<NotificacionTutor> {
    val json = getJson(
      endpoint = "notificaciones_de_mi_hijo.php",
      params = mapOf(
        "id_guarderia" to session.idGuarderia.toString(),
        "id_usuario" to session.idUsuario.toString(),
        "id_nino" to idNino.toString(),
        "desde" to desde,
        "hasta" to hasta
      ),
      token = session.token
    )
    return json.getJSONArray("notificaciones").mapObjects { it.toNotificacion() }
  }

  suspend fun marcarNotificacionVista(
    session: TutorSession,
    idNino: Long,
    idNotificacion: Long,
    confirmar: Boolean
  ) {
    postJson(
      endpoint = "marcar_notificacion_vista.php",
      body = JSONObject()
        .put("id_guarderia", session.idGuarderia)
        .put("id_usuario", session.idUsuario)
        .put("id_nino", idNino)
        .put("id_notificacion", idNotificacion)
        .put("confirmar", confirmar),
      token = session.token
    )
  }

  suspend fun registrarDispositivo(
    session: TutorSession,
    tokenFcm: String,
    nombreDispositivo: String
  ) {
    postJson(
      endpoint = "registrar_dispositivo.php",
      body = JSONObject()
        .put("id_guarderia", session.idGuarderia)
        .put("id_usuario", session.idUsuario)
        .put("token_fcm", tokenFcm)
        .put("nombre_dispositivo", nombreDispositivo),
      token = session.token
    )
  }

  suspend fun desregistrarDispositivo(
    session: TutorSession,
    tokenFcm: String
  ) {
    postJson(
      endpoint = "desregistrar_dispositivo.php",
      body = JSONObject()
        .put("id_guarderia", session.idGuarderia)
        .put("id_usuario", session.idUsuario)
        .put("token_fcm", tokenFcm),
      token = session.token
    )
  }

  suspend fun cambiarPassword(
    session: TutorSession,
    passwordActual: String,
    passwordNueva: String,
    confirmacionPassword: String
  ) {
    postJson(
      endpoint = "cambiar_password.php",
      body = JSONObject()
        .put("password_actual", passwordActual)
        .put("password_nueva", passwordNueva)
        .put("confirmacion_password", confirmacionPassword),
      token = session.token
    )
  }

  suspend fun logout(session: TutorSession) {
    postJson(
      endpoint = "logout.php",
      body = JSONObject(),
      token = session.token
    )
  }

  private suspend fun getJson(
    endpoint: String,
    params: Map<String, String>,
    token: String
  ): JSONObject {
    val query = params.entries.joinToString("&") { (key, value) ->
      "${key.urlEncoded()}=${value.urlEncoded()}"
    }
    return request(
      url = "$baseUrl$endpoint?$query",
      method = "GET",
      body = null,
      token = token
    )
  }

  private suspend fun postJson(
    endpoint: String,
    body: JSONObject,
    token: String?
  ): JSONObject {
    return request(
      url = "$baseUrl$endpoint",
      method = "POST",
      body = body.toString(),
      token = token
    )
  }

  private suspend fun request(
    url: String,
    method: String,
    body: String?,
    token: String?
  ): JSONObject =
    withContext(Dispatchers.IO) {
      val connection = URL(url).openConnection() as HttpURLConnection
      connection.requestMethod = method
      connection.connectTimeout = 15000
      connection.readTimeout = 15000
      connection.setRequestProperty("Accept", "application/json")
      connection.setRequestProperty("User-Agent", "SafeKidsMobile/1.0")
      connection.setRequestProperty("ngrok-skip-browser-warning", "1")
      if (!token.isNullOrBlank()) {
        connection.setRequestProperty("Authorization", "Bearer $token")
      }

      if (body != null) {
        connection.doOutput = true
        connection.setRequestProperty("Content-Type", "application/json; charset=UTF-8")
        connection.outputStream.use { output ->
          output.write(body.toByteArray(StandardCharsets.UTF_8))
        }
      }

      val status = connection.responseCode
      val stream = if (status in 200..299) connection.inputStream else connection.errorStream
      val response = stream?.use { input ->
        BufferedReader(InputStreamReader(input, StandardCharsets.UTF_8)).readText()
      }.orEmpty()

      val json = JSONObject(response.ifBlank { "{}" })
      if (status == HttpURLConnection.HTTP_UNAUTHORIZED && token != null) {
        onUnauthorized?.invoke()
      }
      if (status !in 200..299 || json.optString("status") == "error") {
        throw IllegalStateException(json.optString("mensaje", "No fue posible conectar con SafeKids."))
      }

      json
    }

  private fun JSONObject.toHijo(): Hijo {
    val expediente = optJSONObject("expediente_resumen")
    val actividad = optJSONObject("actividad")
    val relacion = optJSONObject("relacion")

    return Hijo(
      idNino = getLong("id_nino"),
      nombres = optString("nombres"),
      apellidos = optString("apellidos"),
      fechaNacimiento = optString("fecha_nacimiento"),
      edad = optInt("edad"),
      genero = optString("genero"),
      fotoUrl = optNullableString("foto_url"),
      estado = optString("estado"),
      fechaIngreso = optNullableString("fecha_ingreso"),
      parentesco = relacion?.optString("parentesco").orEmpty(),
      alergias = expediente?.optNullableString("alergias"),
      padecimientos = expediente?.optNullableString("padecimientos"),
      tipoSangre = expediente?.optNullableString("tipo_sangre"),
      reportesHoy = actividad?.optInt("reportes_hoy") ?: 0,
      notificacionesPendientes = actividad?.optInt("notificaciones_pendientes") ?: 0
    )
  }

  private fun JSONObject.toReporte(): ReporteDiario {
    val tipo = optJSONObject("tipo")
    val empleado = optJSONObject("empleado")
    val imagenes = optJSONArray("imagenes")?.mapObjects {
      ImagenEvento(
        idImagen = it.getLong("id_imagen"),
        url = it.optString("url"),
        descripcion = it.optNullableString("descripcion")
      )
    }.orEmpty()

    return ReporteDiario(
      idEvento = getLong("id_evento"),
      fechaHora = optString("fecha_hora_evento"),
      titulo = optNullableString("titulo"),
      descripcion = optNullableString("descripcion"),
      nivel = optString("nivel"),
      tipoNombre = tipo?.optString("nombre").orEmpty(),
      empleado = listOf(
        empleado?.optString("nombres").orEmpty(),
        empleado?.optString("apellidos").orEmpty()
      ).filter { it.isNotBlank() }.joinToString(" "),
      imagenes = imagenes
    )
  }

  private fun JSONObject.toNotificacion(): NotificacionTutor {
    val tipo = optJSONObject("tipo")
    val empleado = optJSONObject("empleado")
    val destinatario = optJSONObject("destinatario")

    return NotificacionTutor(
      idNotificacion = getLong("id_notificacion"),
      alcance = optString("alcance"),
      titulo = optString("titulo"),
      mensaje = optString("mensaje"),
      prioridad = optString("prioridad"),
      imagenUrl = optNullableString("imagen_url"),
      requiereConfirmacion = optBoolean("requiere_confirmacion"),
      publicadaEn = optString("publicada_en"),
      tipoNombre = tipo?.optString("nombre").orEmpty(),
      empleado = listOf(
        empleado?.optString("nombres").orEmpty(),
        empleado?.optString("apellidos").orEmpty()
      ).filter { it.isNotBlank() }.joinToString(" "),
      vista = destinatario?.optBoolean("vista") ?: false,
      confirmada = destinatario?.optBoolean("confirmada") ?: false,
      respuesta = destinatario?.optNullableString("respuesta")
    )
  }

  private fun JSONObject.toTutorPerfil(): TutorPerfil {
    return TutorPerfil(
      idTutor = getLong("id_tutor"),
      nombreCompleto = listOf(optString("nombres"), optString("apellidos"))
        .filter { it.isNotBlank() }
        .joinToString(" "),
      parentesco = optString("parentesco"),
      telefono = optNullableString("telefono"),
      telefonoAlterno = optNullableString("telefono_alterno"),
      email = optNullableString("email"),
      direccion = optNullableString("direccion"),
      esPrincipal = optBoolean("es_principal"),
      autorizadoRecoger = optBoolean("autorizado_recoger")
    )
  }

  private fun JSONObject.toContactoEmergencia(): ContactoEmergencia {
    return ContactoEmergencia(
      idContacto = getLong("id_contacto"),
      nombreCompleto = listOf(optString("nombres"), optString("apellidos"))
        .filter { it.isNotBlank() }
        .joinToString(" "),
      parentesco = optNullableString("parentesco"),
      telefono = optString("telefono"),
      email = optNullableString("email"),
      direccion = optNullableString("direccion"),
      autorizadoRecoger = optBoolean("autorizado_recoger"),
      prioridadEmergencia = if (has("prioridad_emergencia") && !isNull("prioridad_emergencia")) {
        optInt("prioridad_emergencia")
      } else {
        null
      },
      observaciones = optNullableString("observaciones")
    )
  }

  private fun String.urlEncoded(): String =
    URLEncoder.encode(this, StandardCharsets.UTF_8.name())

  private fun JSONObject.optNullableString(key: String): String? {
    if (!has(key) || isNull(key)) return null
    return optString(key).takeIf { it.isNotBlank() && it != "null" }
  }

  private fun <T> JSONArray.mapObjects(transform: (JSONObject) -> T): List<T> {
    val items = mutableListOf<T>()
    for (index in 0 until length()) {
      items += transform(getJSONObject(index))
    }
    return items
  }

  companion object {
    val BASE_URL: String = BuildConfig.SAFEKIDS_API_BASE_URL
  }
}
