# Pruebas E2E de SafeKids

`e2e_api.ps1` valida los recorridos principales de la API con una guarderia
temporal. La prueba elimina sus usuarios, menores, reportes, notificaciones y
sesiones al terminar, incluso cuando detecta un error.

Cobertura principal:

- autenticacion WEB y ANDROID;
- roles ADMIN, EMPLEADO y TUTOR;
- aislamiento entre guarderias;
- alta y actualizacion de empleados;
- contrasenas temporales, cambio obligatorio y revocacion;
- alta de un menor con dos tutores y dos contactos;
- un tutor vinculado a dos hijos;
- expediente medico y cambios de estado;
- reportes diarios;
- notificaciones personales y globales;
- lectura y confirmacion desde la app movil;
- registro y baja de dispositivos FCM;
- cierre de sesion.

Ejecutar desde `C:\xampp\htdocs\SafeKids-api`:

```powershell
.\tests\e2e_api.ps1 -DbPassword $env:SAFEKIDS_DB_PASSWORD
```

La API y MySQL deben estar activos. La contrasena de MySQL se recibe como
parametro y no se almacena dentro del script.

Para incluir una carga real de imagen a Cloudinary y comprobar que el adjunto
aparece en los historiales web y movil:

```powershell
.\tests\e2e_api.ps1 -DbPassword $env:SAFEKIDS_DB_PASSWORD -IncludeCloudinary
```

La imagen y los datos temporales se eliminan al terminar la prueba.
