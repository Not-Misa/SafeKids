package com.safekids.mobile.ui

import android.Manifest
import android.content.pm.PackageManager
import android.os.Build
import android.os.Handler
import android.os.Looper
import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.rounded.AccountCircle
import androidx.compose.material.icons.rounded.Close
import androidx.compose.material.icons.rounded.Home
import androidx.compose.material.icons.rounded.Image
import androidx.compose.material.icons.rounded.MoreVert
import androidx.compose.material.icons.rounded.Notifications
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import com.google.firebase.messaging.FirebaseMessaging
import com.safekids.mobile.BuildConfig
import com.safekids.mobile.data.Hijo
import com.safekids.mobile.data.NotificacionTutor
import com.safekids.mobile.data.PerfilHijo
import com.safekids.mobile.data.PushTokenStore
import com.safekids.mobile.data.ReporteDiario
import com.safekids.mobile.data.SafeKidsApi
import com.safekids.mobile.data.SessionStore
import com.safekids.mobile.data.TutorSession
import com.safekids.mobile.data.UiResult
import com.safekids.mobile.push.PushDestination
import com.safekids.mobile.ui.theme.SafeKidsBackground
import com.safekids.mobile.ui.theme.SafeKidsBlue
import com.safekids.mobile.ui.theme.SafeKidsMuted
import com.safekids.mobile.ui.theme.SafeKidsText
import com.safekids.mobile.ui.theme.SafeKidsYellow
import kotlinx.coroutines.launch

@Composable
fun SafeKidsApp(
  pushDestination: PushDestination? = null,
  onPushDestinationConsumed: () -> Unit = {}
) {
  val context = LocalContext.current
  val store = remember { SessionStore(context) }
  val tokenStore = remember { PushTokenStore(context) }
  val scope = rememberCoroutineScope()
  var session by remember { mutableStateOf(store.load()) }
  val mainHandler = remember { Handler(Looper.getMainLooper()) }
  val api = remember {
    SafeKidsApi(
      onUnauthorized = {
        mainHandler.post {
          store.clear()
          session = null
        }
      }
    )
  }
  val notificationPermissionLauncher = rememberLauncherForActivityResult(
    contract = ActivityResultContracts.RequestPermission(),
    onResult = {}
  )

  LaunchedEffect(session?.idUsuario) {
    val activeSession = session ?: return@LaunchedEffect
    if (activeSession.requiereCambioPassword) {
      return@LaunchedEffect
    }
    if (!BuildConfig.FIREBASE_CONFIGURED) {
      return@LaunchedEffect
    }

    if (
      Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
      context.checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) !=
      PackageManager.PERMISSION_GRANTED
    ) {
      notificationPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS)
    }

    FirebaseMessaging.getInstance().register()
  }

  Surface(
    modifier = Modifier.fillMaxSize(),
    color = SafeKidsBackground
  ) {
    when {
      session == null -> {
        LoginScreen(
          api = api,
          store = store,
          onLogin = { loadedSession -> session = loadedSession }
        )
      }

      session?.requiereCambioPassword == true -> {
        ChangePasswordScreen(
          api = api,
          session = requireNotNull(session),
          onPasswordChanged = {
            store.clear()
            session = null
            Toast.makeText(
              context,
              "Contraseña actualizada. Inicia sesión nuevamente.",
              Toast.LENGTH_LONG
            ).show()
          }
        )
      }

      else -> {
        ParentDashboardScreen(
          api = api,
          session = requireNotNull(session),
          pushDestination = pushDestination,
          onPushDestinationConsumed = onPushDestinationConsumed,
          onLogout = {
            val activeSession = requireNotNull(session)
            val token = tokenStore.load()
            store.clear()
            session = null

            if (BuildConfig.FIREBASE_CONFIGURED && token != null) {
              scope.launch {
                runCatching {
                  api.desregistrarDispositivo(activeSession, token)
                }
                runCatching { api.logout(activeSession) }
              }
            } else {
              scope.launch {
                runCatching { api.logout(activeSession) }
              }
            }
            if (BuildConfig.FIREBASE_CONFIGURED) {
              FirebaseMessaging.getInstance().unregister()
            }
          }
        )
      }
    }
  }
}

@Composable
private fun LoginScreen(
  api: SafeKidsApi,
  store: SessionStore,
  onLogin: (TutorSession) -> Unit
) {
  val scope = rememberCoroutineScope()
  var email by remember { mutableStateOf("") }
  var password by remember { mutableStateOf("") }
  var loading by remember { mutableStateOf(false) }
  var error by remember { mutableStateOf<String?>(null) }

  Column(
    modifier = Modifier
      .fillMaxSize()
      .background(SafeKidsBackground)
  ) {
    Box(
      modifier = Modifier
        .fillMaxWidth()
        .height(220.dp)
        .clip(RoundedCornerShape(bottomStart = 26.dp, bottomEnd = 26.dp))
        .background(SafeKidsBlue)
        .padding(horizontal = 28.dp, vertical = 34.dp)
    ) {
      Column(modifier = Modifier.align(Alignment.BottomStart)) {
        Text(
          text = "SafeKids",
          color = Color.White,
          fontSize = 38.sp,
          fontWeight = FontWeight.Bold
        )
        Text(
          text = "Consulta la actividad diaria de tus hijos",
          color = Color.White.copy(alpha = 0.82f),
          fontSize = 16.sp
        )
      }
    }

    Card(
      modifier = Modifier
        .fillMaxWidth()
        .padding(22.dp),
      shape = RoundedCornerShape(8.dp),
      elevation = CardDefaults.cardElevation(defaultElevation = 6.dp),
      colors = CardDefaults.cardColors(containerColor = Color.White)
    ) {
      Column(
        modifier = Modifier.padding(22.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)
      ) {
        Text(
          text = "Inicio de sesion",
          color = SafeKidsText,
          fontSize = 24.sp,
          fontWeight = FontWeight.Bold
        )
        OutlinedTextField(
          value = email,
          onValueChange = { email = it },
          modifier = Modifier.fillMaxWidth(),
          label = { Text("Correo") },
          singleLine = true,
          keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email)
        )
        OutlinedTextField(
          value = password,
          onValueChange = { password = it },
          modifier = Modifier.fillMaxWidth(),
          label = { Text("Contrasena") },
          singleLine = true,
          visualTransformation = PasswordVisualTransformation()
        )
        AnimatedVisibility(error != null) {
          Text(
            text = error.orEmpty(),
            color = MaterialTheme.colorScheme.error,
            fontWeight = FontWeight.SemiBold
          )
        }
        Button(
          onClick = {
            loading = true
            error = null
            scope.launch {
              runCatching { api.login(email, password) }
                .onSuccess {
                  store.save(it)
                  onLogin(it)
                }
                .onFailure { error = it.message ?: "No fue posible iniciar sesion." }
              loading = false
            }
          },
          modifier = Modifier
            .fillMaxWidth()
            .height(52.dp),
          enabled = !loading && email.isNotBlank() && password.isNotBlank(),
          shape = RoundedCornerShape(8.dp)
        ) {
          if (loading) {
            CircularProgressIndicator(
              modifier = Modifier.size(22.dp),
              color = Color.White,
              strokeWidth = 2.dp
            )
          } else {
            Text("Ingresar", fontSize = 16.sp, fontWeight = FontWeight.Bold)
          }
        }
        Text(
          text = "Usa la cuenta de tutor creada por la guarderia.",
          color = SafeKidsMuted,
          fontSize = 13.sp
        )
      }
    }
  }
}

@Composable
private fun ChangePasswordScreen(
  api: SafeKidsApi,
  session: TutorSession,
  onPasswordChanged: () -> Unit
) {
  val scope = rememberCoroutineScope()
  var passwordActual by remember { mutableStateOf("") }
  var passwordNueva by remember { mutableStateOf("") }
  var confirmacion by remember { mutableStateOf("") }
  var loading by remember { mutableStateOf(false) }
  var error by remember { mutableStateOf<String?>(null) }
  val criteria = listOf(
    "Entre 10 y 72 caracteres" to (passwordNueva.length in 10..72),
    "Una letra mayúscula" to passwordNueva.any { it.isUpperCase() },
    "Una letra minúscula" to passwordNueva.any { it.isLowerCase() },
    "Un número" to passwordNueva.any { it.isDigit() },
    "Un carácter especial" to passwordNueva.any { !it.isLetterOrDigit() }
  )
  val formIsValid = passwordActual.isNotBlank() &&
    criteria.all { it.second } &&
    passwordNueva == confirmacion &&
    passwordNueva != passwordActual

  LazyColumn(
    modifier = Modifier
      .fillMaxSize()
      .background(SafeKidsBackground),
    contentPadding = PaddingValues(bottom = 28.dp)
  ) {
    item {
      Box(
        modifier = Modifier
          .fillMaxWidth()
          .height(178.dp)
          .clip(RoundedCornerShape(bottomStart = 26.dp, bottomEnd = 26.dp))
          .background(SafeKidsBlue)
          .padding(horizontal = 26.dp, vertical = 28.dp)
      ) {
        Column(modifier = Modifier.align(Alignment.BottomStart)) {
          Text(
            text = "SafeKids",
            color = Color.White,
            fontSize = 34.sp,
            fontWeight = FontWeight.Bold
          )
          Text(
            text = "Protege tu cuenta",
            color = Color.White.copy(alpha = 0.84f),
            fontSize = 16.sp
          )
        }
      }
    }

    item {
      Card(
        modifier = Modifier
          .fillMaxWidth()
          .padding(22.dp),
        shape = RoundedCornerShape(8.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 5.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White)
      ) {
        Column(
          modifier = Modifier.padding(22.dp),
          verticalArrangement = Arrangement.spacedBy(14.dp)
        ) {
          Text(
            text = "Cambia tu contraseña",
            color = SafeKidsText,
            fontSize = 24.sp,
            fontWeight = FontWeight.Bold
          )
          Text(
            text = "La guardería restableció tu acceso. Debes crear una contraseña personal antes de continuar.",
            color = SafeKidsMuted,
            fontSize = 14.sp,
            lineHeight = 20.sp
          )
          OutlinedTextField(
            value = passwordActual,
            onValueChange = { passwordActual = it },
            modifier = Modifier.fillMaxWidth(),
            label = { Text("Contraseña temporal") },
            singleLine = true,
            visualTransformation = PasswordVisualTransformation()
          )
          OutlinedTextField(
            value = passwordNueva,
            onValueChange = { passwordNueva = it },
            modifier = Modifier.fillMaxWidth(),
            label = { Text("Nueva contraseña") },
            singleLine = true,
            visualTransformation = PasswordVisualTransformation()
          )
          OutlinedTextField(
            value = confirmacion,
            onValueChange = { confirmacion = it },
            modifier = Modifier.fillMaxWidth(),
            label = { Text("Confirmar contraseña") },
            singleLine = true,
            visualTransformation = PasswordVisualTransformation()
          )
          Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
            criteria.forEach { (label, valid) ->
              Text(
                text = "${if (valid) "✓" else "○"}  $label",
                color = if (valid) Color(0xFF207A45) else SafeKidsMuted,
                fontSize = 13.sp,
                fontWeight = if (valid) FontWeight.SemiBold else FontWeight.Normal
              )
            }
          }
          AnimatedVisibility(error != null) {
            Text(
              text = error.orEmpty(),
              color = MaterialTheme.colorScheme.error,
              fontWeight = FontWeight.SemiBold
            )
          }
          Button(
            onClick = {
              loading = true
              error = null
              scope.launch {
                runCatching {
                  api.cambiarPassword(
                    session = session,
                    passwordActual = passwordActual,
                    passwordNueva = passwordNueva,
                    confirmacionPassword = confirmacion
                  )
                }
                  .onSuccess { onPasswordChanged() }
                  .onFailure {
                    error = it.message ?: "No fue posible actualizar la contraseña."
                  }
                loading = false
              }
            },
            modifier = Modifier
              .fillMaxWidth()
              .height(52.dp),
            enabled = !loading && formIsValid,
            shape = RoundedCornerShape(8.dp)
          ) {
            if (loading) {
              CircularProgressIndicator(
                modifier = Modifier.size(22.dp),
                color = Color.White,
                strokeWidth = 2.dp
              )
            } else {
              Text("Guardar contraseña", fontWeight = FontWeight.Bold)
            }
          }
        }
      }
    }
  }
}

@Composable
private fun ParentDashboardScreen(
  api: SafeKidsApi,
  session: TutorSession,
  pushDestination: PushDestination?,
  onPushDestinationConsumed: () -> Unit,
  onLogout: () -> Unit
) {
  var childrenState by remember { mutableStateOf<UiResult<List<Hijo>>>(UiResult.Loading) }
  var selectedChild by remember { mutableStateOf<Hijo?>(null) }
  var section by remember { mutableStateOf(BottomSection.Inicio) }
  var notificationSection by remember { mutableStateOf(NotificationSection.Personales) }
  var notificationRefreshKey by remember { mutableStateOf(0L) }

  LaunchedEffect(session.idUsuario) {
    childrenState = UiResult.Loading
    childrenState = runCatching { api.misHijos(session) }
      .fold(
        onSuccess = { hijos ->
          selectedChild = hijos.singleOrNull()
          UiResult.Success(hijos)
        },
        onFailure = { UiResult.Error(it.message ?: "No fue posible consultar tus hijos.") }
      )
  }

  LaunchedEffect(childrenState, pushDestination?.idNotificacion) {
    val destination = pushDestination ?: return@LaunchedEffect
    val children = (childrenState as? UiResult.Success<List<Hijo>>)
      ?.data
      ?: return@LaunchedEffect

    val targetChild = destination.idNino
      ?.let { idNino -> children.firstOrNull { it.idNino == idNino } }
      ?: selectedChild
      ?: children.firstOrNull()

    if (targetChild != null) {
      selectedChild = targetChild
      section = BottomSection.Notificaciones
      notificationSection = if (destination.alcance == "GLOBAL") {
        NotificationSection.Globales
      } else {
        NotificationSection.Personales
      }
      notificationRefreshKey++
    }

    onPushDestinationConsumed()
  }

  Column(Modifier.fillMaxSize()) {
    when (val state = childrenState) {
      UiResult.Idle,
      UiResult.Loading -> LoadingState("Consultando informacion...")

      is UiResult.Error -> ErrorState(state.message)

      is UiResult.Success -> {
        val children = state.data
        val child = selectedChild
        if (children.isEmpty()) {
          ParentHeader(
            subtitle = "Hola, ${session.tutor.nombres}",
            onLogout = onLogout
          )
          EmptyState("Aun no tienes hijos vinculados a esta cuenta.")
        } else if (child == null) {
          ChildProfileSelectionScreen(
            children = children,
            tutorName = session.tutor.nombres,
            onSelected = { selected ->
              selectedChild = selected
              section = BottomSection.Inicio
              notificationSection = NotificationSection.Personales
            },
            onLogout = onLogout
          )
        } else {
          val switchProfile: () -> Unit = {
            selectedChild = null
            section = BottomSection.Inicio
            notificationSection = NotificationSection.Personales
          }
          when (section) {
            BottomSection.Inicio,
            BottomSection.Perfil -> ParentHeader(
              subtitle = "Hola, ${session.tutor.nombres}",
              onSwitchProfile = switchProfile,
              onLogout = onLogout
            )

            BottomSection.Notificaciones -> NotificationsHeader(
              selected = notificationSection,
              onSelected = { notificationSection = it },
              onSwitchProfile = switchProfile,
              onLogout = onLogout
            )
          }
          Box(modifier = Modifier.weight(1f)) {
            when (section) {
              BottomSection.Inicio -> HomeScreen(api, session, child)
              BottomSection.Notificaciones -> NotificationsScreen(
                api = api,
                session = session,
                child = child,
                notificationSection = notificationSection,
                externalRefreshKey = notificationRefreshKey
              )
              BottomSection.Perfil -> ProfileScreen(
                api = api,
                session = session,
                child = child
              )
            }
          }
          BottomMenu(
            selected = section,
            onSelected = { section = it }
          )
        }
      }
    }
  }
}

@Composable
private fun ParentHeader(
  subtitle: String,
  onSwitchProfile: (() -> Unit)? = null,
  onLogout: () -> Unit
) {
  Box(
    modifier = Modifier
      .fillMaxWidth()
      .clip(RoundedCornerShape(bottomStart = 24.dp, bottomEnd = 24.dp))
      .background(SafeKidsBlue)
      .padding(start = 20.dp, end = 20.dp, top = 40.dp, bottom = 24.dp)
  ) {
    Column {
      Text("SafeKids", color = Color.White, fontSize = 28.sp, fontWeight = FontWeight.Bold)
      Spacer(Modifier.height(10.dp))
      Text(subtitle, color = Color.White.copy(alpha = 0.78f), fontSize = 15.sp)
    }
    HeaderOverflowMenu(
      onSwitchProfile = onSwitchProfile,
      onLogout = onLogout,
      modifier = Modifier.align(Alignment.TopEnd)
    )
  }
}

@Composable
private fun NotificationsHeader(
  selected: NotificationSection,
  onSelected: (NotificationSection) -> Unit,
  onSwitchProfile: () -> Unit,
  onLogout: () -> Unit
) {
  Box(
    modifier = Modifier
      .fillMaxWidth()
      .clip(RoundedCornerShape(bottomStart = 24.dp, bottomEnd = 24.dp))
      .background(SafeKidsBlue)
      .padding(start = 20.dp, end = 20.dp, top = 24.dp)
  ) {
    Column(modifier = Modifier.fillMaxWidth()) {
      Text(
        text = "SafeKids",
        color = Color.White,
        fontSize = 28.sp,
        fontWeight = FontWeight.Bold
      )
      Spacer(Modifier.height(18.dp))
      Row(
        modifier = Modifier
          .fillMaxWidth()
          .height(62.dp),
        verticalAlignment = Alignment.CenterVertically
      ) {
        NotificationHeaderTab(
          text = "Notificaciones personales",
          selected = selected == NotificationSection.Personales,
          modifier = Modifier.weight(1f),
          onClick = { onSelected(NotificationSection.Personales) }
        )
        NotificationHeaderTab(
          text = "Notificaciones globales",
          selected = selected == NotificationSection.Globales,
          modifier = Modifier.weight(1f),
          onClick = { onSelected(NotificationSection.Globales) }
        )
      }
    }
    HeaderOverflowMenu(
      onSwitchProfile = onSwitchProfile,
      onLogout = onLogout,
      modifier = Modifier.align(Alignment.TopEnd)
    )
  }
}

@Composable
private fun HeaderOverflowMenu(
  onSwitchProfile: (() -> Unit)?,
  onLogout: () -> Unit,
  modifier: Modifier = Modifier
) {
  var expanded by remember { mutableStateOf(false) }

  Box(modifier = modifier) {
    IconButton(onClick = { expanded = true }) {
      Icon(
        imageVector = Icons.Rounded.MoreVert,
        contentDescription = "Opciones",
        tint = Color.White,
        modifier = Modifier.size(30.dp)
      )
    }
    DropdownMenu(
      expanded = expanded,
      onDismissRequest = { expanded = false }
    ) {
      if (onSwitchProfile != null) {
        DropdownMenuItem(
          text = { Text("Cambiar de perfil") },
          onClick = {
            expanded = false
            onSwitchProfile()
          }
        )
      }
      DropdownMenuItem(
        text = { Text("Salir") },
        onClick = {
          expanded = false
          onLogout()
        }
      )
    }
  }
}

@Composable
private fun NotificationHeaderTab(
  text: String,
  selected: Boolean,
  modifier: Modifier,
  onClick: () -> Unit
) {
  Column(
    modifier = modifier
      .fillMaxSize()
      .clickable(onClick = onClick),
    horizontalAlignment = Alignment.CenterHorizontally,
    verticalArrangement = Arrangement.Bottom
  ) {
    Text(
      text = text,
      color = Color.White,
      fontSize = 12.sp,
      fontWeight = FontWeight.SemiBold,
      maxLines = 1
    )
    Spacer(Modifier.height(9.dp))
    if (selected) {
      Box(
        modifier = Modifier
          .fillMaxWidth(0.82f)
          .height(3.dp)
          .background(Color.White)
      )
    } else {
      Spacer(Modifier.height(3.dp))
    }
  }
}

@Composable
private fun ChildProfileSelectionScreen(
  children: List<Hijo>,
  tutorName: String,
  onSelected: (Hijo) -> Unit,
  onLogout: () -> Unit
) {
  Column(modifier = Modifier.fillMaxSize()) {
    ParentHeader(
      subtitle = "Hola, $tutorName",
      onLogout = onLogout
    )
    Column(
      modifier = Modifier
        .fillMaxWidth()
        .padding(start = 20.dp, end = 20.dp, top = 24.dp, bottom = 8.dp)
    ) {
      Text(
        text = "A que perfil deseas entrar?",
        color = SafeKidsText,
        fontSize = 25.sp,
        fontWeight = FontWeight.Bold
      )
      Spacer(Modifier.height(6.dp))
      Text(
        text = "Selecciona a uno de tus hijos para consultar su informacion.",
        color = SafeKidsMuted,
        fontSize = 14.sp
      )
    }
    LazyColumn(
      modifier = Modifier
        .fillMaxWidth()
        .weight(1f),
      contentPadding = PaddingValues(horizontal = 16.dp, vertical = 14.dp),
      verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
      items(children, key = { it.idNino }) { child ->
        ChildProfileOption(
          child = child,
          onClick = { onSelected(child) }
        )
      }
    }
  }
}

@Composable
private fun ChildProfileOption(
  child: Hijo,
  onClick: () -> Unit
) {
  Card(
    modifier = Modifier
      .fillMaxWidth()
      .height(118.dp)
      .clickable(onClick = onClick),
    shape = RoundedCornerShape(8.dp),
    colors = CardDefaults.cardColors(containerColor = Color.White),
    elevation = CardDefaults.cardElevation(defaultElevation = 4.dp)
  ) {
    Row(
      modifier = Modifier
        .fillMaxSize()
        .padding(horizontal = 18.dp, vertical = 14.dp),
      verticalAlignment = Alignment.CenterVertically
    ) {
      NetworkAvatar(
        imageUrl = child.fotoUrl,
        name = child.nombreCompleto,
        size = 82.dp
      )
      Spacer(Modifier.width(18.dp))
      Text(
        text = child.nombreCompleto,
        modifier = Modifier.weight(1f),
        color = SafeKidsText,
        fontSize = 18.sp,
        fontWeight = FontWeight.Bold,
        maxLines = 2,
        overflow = TextOverflow.Ellipsis
      )
    }
  }
}

@Composable
private fun HomeScreen(api: SafeKidsApi, session: TutorSession, child: Hijo) {
  var selectedNotification by remember { mutableStateOf<NotificacionTutor?>(null) }
  var state by remember(child.idNino) {
    mutableStateOf<UiResult<HomeOverview>>(UiResult.Loading)
  }

  LaunchedEffect(child.idNino) {
    state = runCatching {
      val notifications = api.notificacionesDeMiHijo(session, child.idNino)
      HomeOverview(
        report = api.reportesDeMiHijo(session, child.idNino).firstOrNull(),
        personalNotification = notifications.firstOrNull { it.alcance == "PERSONAL" },
        globalNotification = notifications.firstOrNull { it.alcance == "GLOBAL" }
      )
    }.fold(
      onSuccess = { UiResult.Success(it) },
      onFailure = { UiResult.Error(it.message ?: "No fue posible cargar el inicio.") }
    )
  }

  LazyColumn(
    modifier = Modifier.fillMaxSize(),
    contentPadding = PaddingValues(18.dp),
    verticalArrangement = Arrangement.spacedBy(14.dp)
  ) {
    item { ChildHeroCard(child) }
    when (val current = state) {
      UiResult.Idle,
      UiResult.Loading -> item { LoadingState("Cargando actividad reciente...") }
      is UiResult.Error -> item { ErrorState(current.message) }
      is UiResult.Success -> {
        item {
          LatestReportCard(current.data.report)
        }
        item {
          LatestNotificationPreviewCard(
            title = "Ultima notificacion personal",
            notification = current.data.personalNotification,
            onOpen = { selectedNotification = current.data.personalNotification }
          )
        }
        item {
          LatestNotificationPreviewCard(
            title = "Ultima notificacion general",
            notification = current.data.globalNotification,
            onOpen = { selectedNotification = current.data.globalNotification }
          )
        }
      }
    }
  }

  selectedNotification?.let { notification ->
    NotificationDetailDialog(
      notification = notification,
      onDismiss = { selectedNotification = null }
    )
  }
}

@Composable
private fun NotificationsScreen(
  api: SafeKidsApi,
  session: TutorSession,
  child: Hijo,
  notificationSection: NotificationSection,
  externalRefreshKey: Long
) {
  val scope = rememberCoroutineScope()
  var refreshKey by remember { mutableStateOf(0) }
  var selectedNotification by remember { mutableStateOf<NotificacionTutor?>(null) }
  var state by remember(child.idNino, refreshKey, externalRefreshKey) {
    mutableStateOf<UiResult<List<NotificacionTutor>>>(UiResult.Loading)
  }

  LaunchedEffect(child.idNino, refreshKey, externalRefreshKey) {
    state = runCatching { api.notificacionesDeMiHijo(session, child.idNino) }
      .fold(
        onSuccess = { UiResult.Success(it) },
        onFailure = { UiResult.Error(it.message ?: "No fue posible consultar avisos.") }
      )
  }

  LazyColumn(
    modifier = Modifier.fillMaxSize(),
    contentPadding = PaddingValues(18.dp),
    verticalArrangement = Arrangement.spacedBy(12.dp)
  ) {
    when (val current = state) {
      UiResult.Idle,
      UiResult.Loading -> item { LoadingState("Cargando avisos...") }
      is UiResult.Error -> item { ErrorState(current.message) }
      is UiResult.Success -> {
        val filteredNotifications = current.data.filter { notification ->
          when (notificationSection) {
            NotificationSection.Personales ->
              notification.alcance.equals("PERSONAL", ignoreCase = true)

            NotificationSection.Globales ->
              notification.alcance.equals("GLOBAL", ignoreCase = true)
          }
        }
        if (filteredNotifications.isEmpty()) {
          val emptyMessage = when (notificationSection) {
            NotificationSection.Personales -> "No hay notificaciones personales recientes."
            NotificationSection.Globales -> "No hay notificaciones globales recientes."
          }
          item { EmptyState(emptyMessage) }
        } else {
          items(filteredNotifications, key = { it.idNotificacion }) { notification ->
            NotificationCard(
              notification = notification,
              onOpen = { selectedNotification = notification },
              onMark = {
                scope.launch {
                  runCatching {
                    api.marcarNotificacionVista(
                      session = session,
                      idNino = child.idNino,
                      idNotificacion = notification.idNotificacion,
                      confirmar = notification.requiereConfirmacion
                    )
                  }.onSuccess { refreshKey++ }
                }
              }
            )
          }
        }
      }
    }
  }

  selectedNotification?.let { notification ->
    NotificationDetailDialog(
      notification = notification,
      onDismiss = { selectedNotification = null },
      onMark = {
        scope.launch {
          runCatching {
            api.marcarNotificacionVista(
              session = session,
              idNino = child.idNino,
              idNotificacion = notification.idNotificacion,
              confirmar = notification.requiereConfirmacion
            )
          }.onSuccess {
            selectedNotification = null
            refreshKey++
          }
        }
      }
    )
  }
}

@Composable
private fun ProfileScreen(
  api: SafeKidsApi,
  session: TutorSession,
  child: Hijo
) {
  var state by remember(child.idNino) {
    mutableStateOf<UiResult<PerfilHijo>>(UiResult.Loading)
  }

  LaunchedEffect(child.idNino) {
    state = runCatching { api.perfilHijo(session, child.idNino) }
      .fold(
        onSuccess = { UiResult.Success(it) },
        onFailure = { UiResult.Error(it.message ?: "No fue posible consultar el perfil.") }
      )
  }

  LazyColumn(
    modifier = Modifier.fillMaxSize(),
    contentPadding = PaddingValues(18.dp),
    verticalArrangement = Arrangement.spacedBy(14.dp)
  ) {
    when (val current = state) {
      UiResult.Idle,
      UiResult.Loading -> item { LoadingState("Cargando perfil...") }
      is UiResult.Error -> item { ErrorState(current.message) }
      is UiResult.Success -> {
        val perfil = current.data
        item { ChildHeroCard(perfil.hijo) }
        item {
          InfoCard("Expediente medico") {
            InfoLine("Tipo de sangre", perfil.expediente.tipoSangre ?: "No registrado")
            InfoLine("Alergias", perfil.expediente.alergias ?: "Sin alergias registradas")
            InfoLine("Padecimientos", perfil.expediente.padecimientos ?: "Sin padecimientos")
            InfoLine("Medicamentos", perfil.expediente.medicamentosHabituales ?: "Ninguno")
            InfoLine(
              "Restricciones alimentarias",
              perfil.expediente.restriccionesAlimentarias ?: "Ninguna"
            )
            InfoLine("Medico", perfil.expediente.medicoNombre ?: "No registrado")
            InfoLine("Telefono medico", perfil.expediente.medicoTelefono ?: "No registrado")
          }
        }
        item {
          InfoCard("Tutores") {
            if (perfil.tutores.isEmpty()) {
              Text("No hay tutores registrados.", color = SafeKidsMuted)
            } else {
              perfil.tutores.forEach { tutor ->
                PersonBlock(
                  title = tutor.nombreCompleto,
                  subtitle = tutor.parentesco,
                  lines = listOfNotNull(
                    tutor.telefono?.let { "Telefono: $it" },
                    tutor.email?.let { "Correo: $it" },
                    tutor.direccion?.let { "Direccion: $it" },
                    if (tutor.esPrincipal) "Tutor principal" else null,
                    if (tutor.autorizadoRecoger) "Autorizado para recoger" else null
                  )
                )
              }
            }
          }
        }
        item {
          InfoCard("Contactos de emergencia") {
            if (perfil.contactos.isEmpty()) {
              Text("No hay contactos de emergencia registrados.", color = SafeKidsMuted)
            } else {
              perfil.contactos.forEach { contacto ->
                PersonBlock(
                  title = contacto.nombreCompleto,
                  subtitle = contacto.parentesco ?: "Contacto",
                  lines = listOfNotNull(
                    "Telefono: ${contacto.telefono}",
                    contacto.email?.let { "Correo: $it" },
                    contacto.direccion?.let { "Direccion: $it" },
                    contacto.prioridadEmergencia?.let { "Prioridad: $it" },
                    if (contacto.autorizadoRecoger) "Autorizado para recoger" else null,
                    contacto.observaciones?.let { "Notas: $it" }
                  )
                )
              }
            }
          }
        }
      }
    }
  }
}

@Composable
private fun ChildHeroCard(child: Hijo) {
  Card(
    modifier = Modifier.fillMaxWidth(),
    shape = RoundedCornerShape(8.dp),
    elevation = CardDefaults.cardElevation(defaultElevation = 4.dp),
    colors = CardDefaults.cardColors(containerColor = Color.White)
  ) {
    Row(
      modifier = Modifier.padding(16.dp),
      verticalAlignment = Alignment.CenterVertically
    ) {
      NetworkAvatar(
        imageUrl = child.fotoUrl,
        name = child.nombreCompleto,
        size = 82.dp
      )
      Spacer(Modifier.width(14.dp))
      Column(Modifier.weight(1f)) {
        Text(child.nombreCompleto, fontSize = 21.sp, fontWeight = FontWeight.Bold)
        Text("${child.edad} anos - ${child.estado}", color = SafeKidsMuted)
        if (!child.alergias.isNullOrBlank()) {
          Text("Alergias: ${child.alergias}", color = MaterialTheme.colorScheme.error)
        }
        Spacer(Modifier.height(8.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
          StatusPill("${child.reportesHoy} reportes hoy", SafeKidsBlue)
          StatusPill("${child.notificacionesPendientes} avisos", SafeKidsYellow)
        }
      }
    }
  }
}

@Composable
private fun LatestReportCard(report: ReporteDiario?) {
  InfoCard("Ultimo reporte") {
    if (report == null) {
      Text("No hay reportes recientes.", color = SafeKidsMuted)
    } else {
      Text(report.tipoNombre, fontSize = 18.sp, fontWeight = FontWeight.Bold)
      if (!report.titulo.isNullOrBlank()) {
        Text(report.titulo, fontWeight = FontWeight.SemiBold)
      }
      if (!report.descripcion.isNullOrBlank()) {
        Text(report.descripcion, color = SafeKidsMuted)
      }
      Text(report.fechaHora.take(16), color = SafeKidsMuted, fontSize = 12.sp)
    }
  }
}

@Composable
private fun LatestNotificationPreviewCard(
  title: String,
  notification: NotificacionTutor?,
  onOpen: () -> Unit
) {
  InfoCard(
    title = title,
    modifier = if (notification != null) Modifier.clickable(onClick = onOpen) else Modifier
  ) {
    if (notification == null) {
      Text("No hay notificaciones recientes.", color = SafeKidsMuted)
    } else {
      Row(verticalAlignment = Alignment.CenterVertically) {
        Dot(if (notification.alcance == "GLOBAL") SafeKidsBlue else SafeKidsYellow)
        Spacer(Modifier.width(8.dp))
        Text(
          text = notification.titulo,
          modifier = Modifier.weight(1f),
          fontSize = 18.sp,
          fontWeight = FontWeight.Bold,
          maxLines = 1,
          overflow = TextOverflow.Ellipsis
        )
        if (!notification.imagenUrl.isNullOrBlank()) {
          NotificationAttachmentIndicator()
        }
      }
      Text(notification.mensaje, color = SafeKidsMuted, maxLines = 3, overflow = TextOverflow.Ellipsis)
      Text(notification.publicadaEn.take(16), color = SafeKidsMuted, fontSize = 12.sp)
    }
  }
}

@Composable
private fun NotificationCard(
  notification: NotificacionTutor,
  onOpen: () -> Unit,
  onMark: () -> Unit
) {
  Card(
    modifier = Modifier
      .fillMaxWidth()
      .clickable(onClick = onOpen),
    shape = RoundedCornerShape(8.dp),
    colors = CardDefaults.cardColors(containerColor = Color.White),
    elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
  ) {
    Column(
      modifier = Modifier.padding(16.dp),
      verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
      Row(verticalAlignment = Alignment.CenterVertically) {
        Dot(if (notification.alcance == "GLOBAL") SafeKidsBlue else SafeKidsYellow)
        Spacer(Modifier.width(8.dp))
        Text(
          text = notification.titulo,
          modifier = Modifier.weight(1f),
          fontWeight = FontWeight.Bold,
          fontSize = 17.sp,
          maxLines = 1,
          overflow = TextOverflow.Ellipsis
        )
        StatusPill(notification.alcance.lowercase(), SafeKidsBlue)
      }
      Row(verticalAlignment = Alignment.CenterVertically) {
        Text(
          text = notification.mensaje,
          modifier = Modifier.weight(1f),
          color = SafeKidsMuted
        )
        if (!notification.imagenUrl.isNullOrBlank()) {
          Spacer(Modifier.width(12.dp))
          NotificationAttachmentIndicator()
        }
      }
      Text(
        "${notification.tipoNombre} - ${notification.publicadaEn.take(16)}",
        color = SafeKidsMuted,
        fontSize = 12.sp
      )

      if (!notification.vista || (notification.requiereConfirmacion && !notification.confirmada)) {
        Button(
          onClick = onMark,
          modifier = Modifier.fillMaxWidth(),
          shape = RoundedCornerShape(8.dp)
        ) {
          Text(if (notification.requiereConfirmacion) "Confirmar aviso" else "Marcar como visto")
        }
      } else {
        Text(
          text = if (notification.confirmada) "Aviso confirmado" else "Aviso visto",
          color = SafeKidsBlue,
          fontWeight = FontWeight.SemiBold
        )
      }
    }
  }
}

@Composable
private fun NotificationAttachmentIndicator() {
  Column(
    horizontalAlignment = Alignment.CenterHorizontally,
    verticalArrangement = Arrangement.spacedBy(2.dp)
  ) {
    Icon(
      imageVector = Icons.Rounded.Image,
      contentDescription = null,
      modifier = Modifier.size(29.dp),
      tint = SafeKidsBlue
    )
    Text(
      text = "Abrir",
      color = SafeKidsBlue,
      fontSize = 11.sp,
      fontWeight = FontWeight.Bold
    )
  }
}

@Composable
private fun NotificationDetailDialog(
  notification: NotificacionTutor,
  onDismiss: () -> Unit,
  onMark: (() -> Unit)? = null
) {
  Dialog(
    onDismissRequest = onDismiss,
    properties = DialogProperties(usePlatformDefaultWidth = false)
  ) {
    Surface(
      modifier = Modifier
        .fillMaxWidth()
        .padding(horizontal = 18.dp)
        .heightIn(max = 720.dp),
      shape = RoundedCornerShape(8.dp),
      color = Color.White,
      shadowElevation = 12.dp
    ) {
      Column(
        modifier = Modifier
          .fillMaxWidth()
          .verticalScroll(rememberScrollState())
          .padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp)
      ) {
        Row(
          verticalAlignment = Alignment.Top
        ) {
          Column(
            modifier = Modifier.weight(1f),
            verticalArrangement = Arrangement.spacedBy(4.dp)
          ) {
            Text(
              text = notification.tipoNombre.ifBlank { "Notificacion" },
              color = SafeKidsBlue,
              fontSize = 12.sp,
              fontWeight = FontWeight.Bold
            )
            Text(
              text = notification.titulo,
              color = SafeKidsText,
              fontSize = 22.sp,
              fontWeight = FontWeight.Bold
            )
          }
          IconButton(onClick = onDismiss) {
            Icon(
              imageVector = Icons.Rounded.Close,
              contentDescription = "Cerrar",
              tint = SafeKidsMuted
            )
          }
        }

        Row(
          horizontalArrangement = Arrangement.spacedBy(8.dp),
          verticalAlignment = Alignment.CenterVertically
        ) {
          StatusPill(notification.alcance.lowercase(), SafeKidsBlue)
          StatusPill(
            notification.prioridad.lowercase(),
            if (notification.prioridad == "URGENTE") MaterialTheme.colorScheme.error else SafeKidsYellow
          )
        }

        Text(
          text = notification.mensaje,
          color = SafeKidsText,
          lineHeight = 22.sp
        )

        notification.imagenUrl?.takeIf { it.isNotBlank() }?.let { imageUrl ->
          NetworkPhoto(
            imageUrl = imageUrl,
            contentDescription = "Imagen adjunta de ${notification.titulo}",
            modifier = Modifier
              .fillMaxWidth()
              .heightIn(min = 190.dp, max = 420.dp)
          )
        }

        HorizontalDivider()
        Text(
          text = "Publicado por ${notification.empleado.ifBlank { "la guarderia" }}",
          color = SafeKidsMuted,
          fontSize = 13.sp
        )
        Text(
          text = notification.publicadaEn.take(16),
          color = SafeKidsMuted,
          fontSize = 12.sp
        )

        if (
          onMark != null &&
          (!notification.vista ||
            (notification.requiereConfirmacion && !notification.confirmada))
        ) {
          Button(
            onClick = onMark,
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(8.dp)
          ) {
            Text(
              if (notification.requiereConfirmacion) {
                "Confirmar aviso"
              } else {
                "Marcar como visto"
              }
            )
          }
        }
      }
    }
  }
}

@Composable
private fun InfoCard(
  title: String,
  modifier: Modifier = Modifier,
  content: @Composable ColumnScope.() -> Unit
) {
  Card(
    modifier = modifier.fillMaxWidth(),
    shape = RoundedCornerShape(8.dp),
    colors = CardDefaults.cardColors(containerColor = Color.White),
    elevation = CardDefaults.cardElevation(defaultElevation = 3.dp)
  ) {
    Column(
      modifier = Modifier.padding(16.dp),
      verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
      Text(title, fontSize = 20.sp, fontWeight = FontWeight.Bold, color = SafeKidsText)
      HorizontalDivider()
      content()
    }
  }
}

@Composable
private fun PersonBlock(title: String, subtitle: String, lines: List<String>) {
  Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
    Text(title, fontWeight = FontWeight.Bold, color = SafeKidsText)
    Text(subtitle, color = SafeKidsBlue, fontWeight = FontWeight.SemiBold)
    lines.forEach { line ->
      Text(line, color = SafeKidsMuted, fontSize = 13.sp)
    }
  }
  HorizontalDivider(Modifier.padding(vertical = 8.dp))
}

@Composable
private fun SectionTitle(title: String, subtitle: String) {
  Column {
    Text(title, fontSize = 24.sp, fontWeight = FontWeight.Bold, color = SafeKidsText)
    Text(subtitle, color = SafeKidsMuted)
  }
}

@Composable
private fun InfoLine(label: String, value: String) {
  Column {
    Text(label, fontWeight = FontWeight.Bold, color = SafeKidsText)
    Text(value, color = SafeKidsMuted)
  }
}

@Composable
private fun StatusPill(text: String, color: Color) {
  Box(
    modifier = Modifier
      .clip(RoundedCornerShape(30.dp))
      .background(color.copy(alpha = 0.14f))
      .padding(horizontal = 10.dp, vertical = 5.dp)
  ) {
    Text(
      text = text,
      color = if (color == SafeKidsYellow) Color(0xFF8A5A00) else color,
      fontSize = 12.sp
    )
  }
}

@Composable
private fun Dot(color: Color) {
  Box(
    modifier = Modifier
      .size(12.dp)
      .clip(CircleShape)
      .background(color)
  )
}

@Composable
private fun BottomMenu(selected: BottomSection, onSelected: (BottomSection) -> Unit) {
  val menuShape = RoundedCornerShape(topStart = 18.dp, topEnd = 18.dp)

  Row(
    modifier = Modifier
      .fillMaxWidth()
      .height(82.dp)
      .shadow(elevation = 8.dp, shape = menuShape)
      .clip(menuShape)
      .background(Color.White)
      .padding(horizontal = 8.dp, vertical = 4.dp),
    horizontalArrangement = Arrangement.SpaceEvenly,
    verticalAlignment = Alignment.CenterVertically
  ) {
    BottomMenuItem(
      text = "Inicio",
      icon = Icons.Rounded.Home,
      selected = selected == BottomSection.Inicio,
      modifier = Modifier.weight(1f),
      onClick = { onSelected(BottomSection.Inicio) }
    )
    BottomMenuItem(
      text = "Notificaciones",
      icon = Icons.Rounded.Notifications,
      selected = selected == BottomSection.Notificaciones,
      modifier = Modifier.weight(1f),
      onClick = { onSelected(BottomSection.Notificaciones) }
    )
    BottomMenuItem(
      text = "Perfil",
      icon = Icons.Rounded.AccountCircle,
      selected = selected == BottomSection.Perfil,
      modifier = Modifier.weight(1f),
      onClick = { onSelected(BottomSection.Perfil) }
    )
  }
}

@Composable
private fun BottomMenuItem(
  text: String,
  icon: ImageVector,
  selected: Boolean,
  modifier: Modifier,
  onClick: () -> Unit
) {
  val itemColor = if (selected) SafeKidsBlue else SafeKidsBlue.copy(alpha = 0.62f)

  Column(
    modifier = modifier
      .fillMaxSize()
      .clickable(onClick = onClick)
      .padding(top = 6.dp),
    horizontalAlignment = Alignment.CenterHorizontally,
    verticalArrangement = Arrangement.Center
  ) {
    Icon(
      imageVector = icon,
      contentDescription = text,
      modifier = Modifier.size(27.dp),
      tint = itemColor
    )
    Spacer(Modifier.height(3.dp))
    Text(
      text = text,
      color = itemColor,
      fontSize = 12.sp,
      fontWeight = FontWeight.SemiBold,
      maxLines = 1
    )
    Spacer(Modifier.height(5.dp))
    if (selected) {
      Box(
        modifier = Modifier
          .width(48.dp)
          .height(3.dp)
          .background(SafeKidsBlue)
      )
    } else {
      Spacer(Modifier.height(3.dp))
    }
  }
}

@Composable
private fun LoadingState(text: String) {
  Box(
    modifier = Modifier
      .fillMaxWidth()
      .padding(28.dp),
    contentAlignment = Alignment.Center
  ) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
      CircularProgressIndicator()
      Spacer(Modifier.height(12.dp))
      Text(text, color = SafeKidsMuted)
    }
  }
}

@Composable
private fun ErrorState(message: String) {
  Card(
    modifier = Modifier
      .fillMaxWidth()
      .padding(18.dp),
    shape = RoundedCornerShape(8.dp),
    colors = CardDefaults.cardColors(containerColor = Color.White)
  ) {
    Text(
      text = message,
      modifier = Modifier.padding(18.dp),
      color = MaterialTheme.colorScheme.error,
      fontWeight = FontWeight.SemiBold
    )
  }
}

@Composable
private fun EmptyState(message: String) {
  Card(
    modifier = Modifier
      .fillMaxWidth()
      .padding(18.dp),
    shape = RoundedCornerShape(8.dp),
    colors = CardDefaults.cardColors(containerColor = Color.White)
  ) {
    Text(
      text = message,
      modifier = Modifier.padding(18.dp),
      color = SafeKidsMuted
    )
  }
}

private data class HomeOverview(
  val report: ReporteDiario?,
  val personalNotification: NotificacionTutor?,
  val globalNotification: NotificacionTutor?
)

private enum class BottomSection {
  Inicio,
  Notificaciones,
  Perfil
}

private enum class NotificationSection {
  Personales,
  Globales
}
