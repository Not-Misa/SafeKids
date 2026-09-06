# SafeKids Backend

API REST de SafeKids desarrollada en PHP para administrar la informacion de guarderias, ninos, empleados, tutores, reportes y notificaciones.

## Tecnologias

- PHP
- MySQL 8.0.44
- API REST
- Cloudinary para imagenes
- Firebase Cloud Messaging para notificaciones push

## Configuracion local

Los archivos con credenciales no se incluyen en el repositorio. Crea localmente los archivos de configuracion a partir de:

- `database.example.ini`
- `app.example.ini`

La configuracion debe apuntar a la base de datos `safekids_v2` y permanecer fuera del repositorio.

## Ejecucion

Coloca esta carpeta dentro de XAMPP, inicia Apache y MySQL, y utiliza la ruta local de la API configurada por el frontend y la aplicacion movil.

## Endpoints

Los endpoints se encuentran en la carpeta `api/`. El archivo `conexion.php` centraliza la conexion con MySQL y `autenticacion.php` controla las sesiones y permisos.
