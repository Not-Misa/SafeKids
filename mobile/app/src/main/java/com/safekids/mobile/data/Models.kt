package com.safekids.mobile.data

data class TutorSession(
  val idUsuario: Long,
  val idGuarderia: Long,
  val email: String,
  val token: String,
  val tokenExpiraEn: String,
  val requiereCambioPassword: Boolean,
  val tutor: Tutor
)

data class Tutor(
  val idTutor: Long,
  val nombres: String,
  val apellidos: String,
  val telefono: String?,
  val fotoUrl: String?
) {
  val nombreCompleto: String
    get() = listOf(nombres, apellidos).filter { it.isNotBlank() }.joinToString(" ")
}

data class Hijo(
  val idNino: Long,
  val nombres: String,
  val apellidos: String,
  val fechaNacimiento: String,
  val edad: Int,
  val genero: String,
  val fotoUrl: String?,
  val estado: String,
  val fechaIngreso: String?,
  val parentesco: String,
  val alergias: String?,
  val padecimientos: String?,
  val tipoSangre: String?,
  val reportesHoy: Int,
  val notificacionesPendientes: Int
) {
  val nombreCompleto: String
    get() = listOf(nombres, apellidos).filter { it.isNotBlank() }.joinToString(" ")
}

data class PerfilHijo(
  val hijo: Hijo,
  val expediente: ExpedienteMedico,
  val tutores: List<TutorPerfil>,
  val contactos: List<ContactoEmergencia>
)

data class ExpedienteMedico(
  val tipoSangre: String?,
  val alergias: String?,
  val padecimientos: String?,
  val medicamentosHabituales: String?,
  val restriccionesAlimentarias: String?,
  val medicoNombre: String?,
  val medicoTelefono: String?,
  val institucionMedica: String?,
  val indicacionesEmergencia: String?,
  val observaciones: String?
)

data class TutorPerfil(
  val idTutor: Long,
  val nombreCompleto: String,
  val parentesco: String,
  val telefono: String?,
  val telefonoAlterno: String?,
  val email: String?,
  val direccion: String?,
  val esPrincipal: Boolean,
  val autorizadoRecoger: Boolean
)

data class ContactoEmergencia(
  val idContacto: Long,
  val nombreCompleto: String,
  val parentesco: String?,
  val telefono: String,
  val email: String?,
  val direccion: String?,
  val autorizadoRecoger: Boolean,
  val prioridadEmergencia: Int?,
  val observaciones: String?
)

data class ReporteDiario(
  val idEvento: Long,
  val fechaHora: String,
  val titulo: String?,
  val descripcion: String?,
  val nivel: String,
  val tipoNombre: String,
  val empleado: String,
  val imagenes: List<ImagenEvento>
)

data class ImagenEvento(
  val idImagen: Long,
  val url: String,
  val descripcion: String?
)

data class NotificacionTutor(
  val idNotificacion: Long,
  val alcance: String,
  val titulo: String,
  val mensaje: String,
  val prioridad: String,
  val imagenUrl: String?,
  val requiereConfirmacion: Boolean,
  val publicadaEn: String,
  val tipoNombre: String,
  val empleado: String,
  val vista: Boolean,
  val confirmada: Boolean,
  val respuesta: String?
)

sealed interface UiResult<out T> {
  data object Idle : UiResult<Nothing>
  data object Loading : UiResult<Nothing>
  data class Success<T>(val data: T) : UiResult<T>
  data class Error(val message: String) : UiResult<Nothing>
}
