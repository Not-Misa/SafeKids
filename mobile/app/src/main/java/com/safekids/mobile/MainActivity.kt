package com.safekids.mobile

import android.content.Intent
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
import com.safekids.mobile.push.PushDestination
import com.safekids.mobile.push.PushNotificationManager
import com.safekids.mobile.push.toPushDestination
import com.safekids.mobile.ui.SafeKidsApp
import com.safekids.mobile.ui.theme.SafeKidsTheme

class MainActivity : ComponentActivity() {
  private var pushDestination by mutableStateOf<PushDestination?>(null)

  override fun onCreate(savedInstanceState: Bundle?) {
    installSplashScreen()
    super.onCreate(savedInstanceState)
    PushNotificationManager.createChannel(this)
    pushDestination = intent.toPushDestination()

    setContent {
      SafeKidsTheme {
        SafeKidsApp(
          pushDestination = pushDestination,
          onPushDestinationConsumed = { pushDestination = null }
        )
      }
    }
  }

  override fun onNewIntent(intent: Intent) {
    super.onNewIntent(intent)
    setIntent(intent)
    pushDestination = intent.toPushDestination()
  }
}
