# SafeKidsMobile

Aplicacion Android nativa para padres/tutores de SafeKids.

## Alcance MVP

- Inicio de sesion de tutor.
- Inicio con selector de hijo y resumen general.
- Tarjeta de ultimo reporte.
- Tarjeta de ultima notificacion personal.
- Tarjeta de ultima notificacion general.
- Historial de notificaciones globales/personales.
- Notificaciones push personales y globales mediante Firebase Cloud Messaging.
- Apertura directa del hijo y del apartado correspondiente al tocar un aviso.
- Perfil del hijo con expediente, tutores y contactos de emergencia.
- Marcar notificaciones como vistas o confirmadas.

## Backend

Por defecto la app usa:

```text
http://10.0.2.2/SafeKids-api/api/
```

Ese host funciona en el emulador de Android para comunicarse con el servidor local
de la PC. Si pruebas en un telefono fisico, configura
`safekids.api.baseUrl` dentro de `local.properties`:

```text
app/src/main/java/com/safekids/mobile/data/SafeKidsApi.kt
```

por la IP local de tu computadora, por ejemplo:

```text
safekids.api.baseUrl=http://192.168.1.20/SafeKids-api/api/

La variante `debug` permite HTTP para pruebas locales. La variante `release`
requiere una URL HTTPS.

## Firma de la version release

1. Ejecuta `scripts/create_release_keystore.ps1`.
2. Copia `keystore.properties.example` como `keystore.properties`.
3. Completa la ruta, alias y contrasenas del keystore.
4. Define una URL HTTPS en `local.properties` o usa
   `-Psafekids.api.baseUrl=https://tu-dominio/SafeKids-api/api/`.
5. Ejecuta `.\gradlew.bat assembleRelease`.

El keystore y `keystore.properties` estan ignorados por Git. Conserva una copia
segura: se necesita la misma clave para publicar actualizaciones futuras.
```

## Como abrirlo

1. Abre Android Studio.
2. Selecciona **Open**.
3. Abre la carpeta:

```text
C:\Users\angel\Documents\Codex\2026-06-24\estoy-desarrollando-un-proyecto-llamado-safekids-2\SafeKidsMobile
```

4. Espera la sincronizacion de Gradle.
5. Ejecuta la app en un emulador Android.

La primera sincronizacion puede necesitar internet para descargar AndroidX Compose,
Firebase y otros paquetes.

## Configurar Firebase Cloud Messaging

1. Crea un proyecto llamado `SafeKids` en Firebase.
2. Agrega una aplicacion Android con el paquete:

```text
com.safekids.mobile
```

3. Descarga `google-services.json` y colocalo en:

```text
SafeKidsMobile\app\google-services.json
```

4. En Firebase abre **Configuracion del proyecto > Cuentas de servicio** y genera
   una nueva clave privada.
5. Renombra el archivo descargado como `firebase-service-account.json` y colocalo
   fuera del directorio publico:

```text
C:\xampp\safekids-config\firebase-service-account.json
```

6. Confirma que **Firebase Cloud Messaging API (V1)** este habilitada y vuelve a
   compilar la aplicacion.

La cuenta de servicio contiene una clave privada. Nunca debe copiarse dentro de
`SafeKidsMobile`, `SafeKids-api`, `htdocs` ni subirse a un repositorio.

## Para probar login

La app solo permite iniciar sesion con usuarios de rol `TUTOR`. Si intentas entrar
con el administrador web, mostrara que esta app es para padres o tutores.
