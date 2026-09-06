package com.safekids.mobile.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.ColorScheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

val SafeKidsBlue = Color(0xFF2F80ED)
val SafeKidsBlueDark = Color(0xFF1267D8)
val SafeKidsYellow = Color(0xFFFFB703)
val SafeKidsBackground = Color(0xFFEEF3F9)
val SafeKidsText = Color(0xFF152235)
val SafeKidsMuted = Color(0xFF6B7280)
val SafeKidsDanger = Color(0xFFE63946)

private val LightColors: ColorScheme = lightColorScheme(
  primary = SafeKidsBlue,
  onPrimary = Color.White,
  secondary = SafeKidsYellow,
  onSecondary = Color(0xFF111827),
  background = SafeKidsBackground,
  onBackground = SafeKidsText,
  surface = Color.White,
  onSurface = SafeKidsText,
  error = SafeKidsDanger
)

@Composable
fun SafeKidsTheme(
  darkTheme: Boolean = isSystemInDarkTheme(),
  content: @Composable () -> Unit
) {
  MaterialTheme(
    colorScheme = LightColors,
    typography = MaterialTheme.typography,
    content = content
  )
}
