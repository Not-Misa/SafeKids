import java.util.Properties

plugins {
  id("com.android.application")
  id("org.jetbrains.kotlin.android")
  id("org.jetbrains.kotlin.plugin.compose")
}

val localProperties = Properties().apply {
  val localPropertiesFile = rootProject.file("local.properties")
  if (localPropertiesFile.exists()) {
    localPropertiesFile.inputStream().use(::load)
  }
}

val keystorePropertiesFile = rootProject.file("keystore.properties")
val keystoreProperties = Properties().apply {
  if (keystorePropertiesFile.exists()) {
    keystorePropertiesFile.inputStream().use(::load)
  }
}
val releaseSigningConfigured = listOf(
  "storeFile",
  "storePassword",
  "keyAlias",
  "keyPassword"
).all { !keystoreProperties.getProperty(it).isNullOrBlank() }

val safeKidsApiBaseUrl = (
  providers.gradleProperty("safekids.api.baseUrl").orNull
    ?: localProperties.getProperty(
      "safekids.api.baseUrl",
      "http://10.0.2.2/SafeKids-api/api/"
    )
  )
  .trim()
  .trimEnd('/') + "/"
val escapedSafeKidsApiBaseUrl = safeKidsApiBaseUrl
  .replace("\\", "\\\\")
  .replace("\"", "\\\"")

val firebaseConfigured = file("google-services.json").exists()
if (firebaseConfigured) {
  apply(plugin = "com.google.gms.google-services")
}

android {
  namespace = "com.safekids.mobile"
  compileSdk = 35

  defaultConfig {
    applicationId = "com.safekids.mobile"
    minSdk = 26
    targetSdk = 35
    versionCode = 1
    versionName = "1.0"
    buildConfigField("boolean", "FIREBASE_CONFIGURED", firebaseConfigured.toString())
    buildConfigField("String", "SAFEKIDS_API_BASE_URL", "\"$escapedSafeKidsApiBaseUrl\"")
  }

  signingConfigs {
    if (releaseSigningConfigured) {
      create("release") {
        storeFile = rootProject.file(keystoreProperties.getProperty("storeFile"))
        storePassword = keystoreProperties.getProperty("storePassword")
        keyAlias = keystoreProperties.getProperty("keyAlias")
        keyPassword = keystoreProperties.getProperty("keyPassword")
      }
    }
  }

  buildTypes {
    release {
      isMinifyEnabled = false
      signingConfig = signingConfigs.findByName("release")
      proguardFiles(
        getDefaultProguardFile("proguard-android-optimize.txt"),
        "proguard-rules.pro"
      )
    }
  }

  compileOptions {
    sourceCompatibility = JavaVersion.VERSION_17
    targetCompatibility = JavaVersion.VERSION_17
  }

  kotlinOptions {
    jvmTarget = "17"
  }

  buildFeatures {
    buildConfig = true
    compose = true
  }
}

dependencies {
  implementation(platform("com.google.firebase:firebase-bom:34.16.0"))
  implementation(platform("androidx.compose:compose-bom:2024.12.01"))
  implementation("androidx.activity:activity-compose:1.9.3")
  implementation("androidx.core:core-splashscreen:1.2.0")
  implementation("androidx.compose.material3:material3")
  implementation("androidx.compose.material:material-icons-extended")
  implementation("androidx.compose.ui:ui")
  implementation("androidx.compose.ui:ui-graphics")
  implementation("androidx.compose.ui:ui-tooling-preview")
  implementation("androidx.lifecycle:lifecycle-runtime-ktx:2.8.7")
  implementation("com.google.firebase:firebase-messaging")
  implementation("org.jetbrains.kotlinx:kotlinx-coroutines-android:1.9.0")

  debugImplementation("androidx.compose.ui:ui-tooling")
  debugImplementation("androidx.compose.ui:ui-test-manifest")
}

val validateReleaseConfiguration = tasks.register(
  "validateReleaseConfiguration"
) {
  doLast {
    if (!safeKidsApiBaseUrl.startsWith("https://")) {
      throw GradleException(
        "La variante release requiere safekids.api.baseUrl con HTTPS."
      )
    }
  }
}

tasks.matching {
  it.name == "assembleRelease" || it.name == "bundleRelease"
}.configureEach {
  dependsOn(validateReleaseConfiguration)
}
