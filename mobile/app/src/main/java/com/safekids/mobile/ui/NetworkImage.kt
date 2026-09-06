package com.safekids.mobile.ui

import android.graphics.Bitmap
import android.graphics.BitmapFactory
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.produceState
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.net.URL

@Composable
fun NetworkAvatar(
  imageUrl: String?,
  name: String,
  size: Dp,
  modifier: Modifier = Modifier,
  borderColor: Color = MaterialTheme.colorScheme.primary
) {
  val bitmap = produceState<Bitmap?>(initialValue = null, imageUrl) {
    value = imageUrl?.takeIf { it.isNotBlank() }?.let { loadBitmap(it) }
  }

  Box(
    modifier = modifier
      .size(size)
      .clip(CircleShape)
      .background(Color(0xFFE7EEF7))
      .border(2.dp, borderColor, CircleShape),
    contentAlignment = Alignment.Center
  ) {
    val loadedBitmap = bitmap.value
    if (loadedBitmap != null) {
      Image(
        bitmap = loadedBitmap.asImageBitmap(),
        contentDescription = name,
        modifier = Modifier.matchParentSize(),
        contentScale = ContentScale.Crop
      )
    } else {
      Text(
        text = initials(name),
        color = MaterialTheme.colorScheme.primary,
        fontWeight = FontWeight.Bold
      )
    }
  }
}

@Composable
fun NetworkPhoto(
  imageUrl: String,
  contentDescription: String,
  modifier: Modifier = Modifier
) {
  val bitmap = produceState<Bitmap?>(initialValue = null, imageUrl) {
    value = imageUrl.takeIf { it.isNotBlank() }?.let { loadBitmap(it) }
  }

  Box(
    modifier = modifier
      .clip(RoundedCornerShape(8.dp))
      .background(Color(0xFFEEF3F8)),
    contentAlignment = Alignment.Center
  ) {
    val loadedBitmap = bitmap.value
    if (loadedBitmap != null) {
      Image(
        bitmap = loadedBitmap.asImageBitmap(),
        contentDescription = contentDescription,
        modifier = Modifier.fillMaxSize(),
        contentScale = ContentScale.Fit
      )
    } else {
      Text(
        text = "Cargando imagen...",
        color = Color(0xFF718096),
        fontWeight = FontWeight.SemiBold
      )
    }
  }
}

private suspend fun loadBitmap(url: String): Bitmap? = withContext(Dispatchers.IO) {
  runCatching {
    URL(url).openStream().use { BitmapFactory.decodeStream(it) }
  }.getOrNull()
}

private fun initials(name: String): String {
  val parts = name.trim().split(Regex("\\s+")).filter { it.isNotBlank() }
  return parts.take(2).joinToString("") { it.first().uppercase() }.ifBlank { "SK" }
}
