# SafeKids - Guia rapida de la base de datos

## Datos generales

- Base de datos: `safekids_v2`
- Motor verificado: MySQL 8.0.44
- Fecha del respaldo: 6 de agosto de 2026
- Codificacion: `utf8mb4`
- Total de tablas: 20

## Archivos entregados

1. `SafeKids_BD_ESTRUCTURA_2026-08-06.sql`
   Contiene la creacion de la base de datos, tablas, columnas, llaves
   primarias, llaves foraneas, indices y restricciones. Es el archivo
   recomendado para explicar o entregar la estructura.

2. `SafeKids_BD_COMPLETA_CON_DATOS_2026-08-06.sql`
   Contiene toda la estructura y los registros actuales. Debe manejarse
   como respaldo privado porque incluye datos personales, hashes de
   contrasenas, tokens de sesion y registros de dispositivos.

3. `SafeKids_BD_LIMPIA_ESTRUCTURA_2026-08-06.sql`
   Version recomendada para exposicion. Conserva toda la estructura,
   relaciones, indices y restricciones, pero elimina las instrucciones
   tecnicas agregadas por `mysqldump`.

4. `SafeKids_BD_LIMPIA_CON_DATOS_2026-08-06.sql`
   Version legible con estructura y registros actuales. Tambien debe
   tratarse como archivo privado.

Las versiones limpias conservan `SET NAMES utf8mb4` y la desactivacion
temporal de `FOREIGN_KEY_CHECKS`. Estas instrucciones permiten conservar
acentos y crear las tablas relacionadas sin errores. Al final del script
las llaves foraneas se activan nuevamente.

## Objetos existentes

- Tablas: 20
- Vistas: 0
- Procedimientos almacenados: 0
- Funciones almacenadas: 0
- Triggers: 0
- Eventos programados: 0

La logica de negocio se encuentra actualmente en la API PHP. La base de
datos protege la integridad principalmente mediante llaves foraneas,
indices, restricciones `UNIQUE`, valores enumerados y reglas
`ON DELETE` / `ON UPDATE`.

## Organizacion de las tablas

### Guarderias y acceso

- `guarderias`: registra las guarderias independientes.
- `roles`: catalogo de roles del sistema.
- `usuarios`: credenciales y estado de las cuentas.
- `empleados`: datos laborales del personal.
- `tutores`: datos de padres o tutores.

### Menores y responsables

- `ninos`: informacion principal de cada menor.
- `nino_tutor`: relacion muchos a muchos entre menores y tutores.
- `contactos_autorizados`: familiares o personas autorizadas.
- `nino_contacto`: relacion entre menores y contactos autorizados.
- `expedientes_medicos`: alergias, padecimientos y datos medicos.

### Actividad diaria

- `tipos_evento`: catalogo de alimentacion, sueno, incidentes y otros.
- `eventos_nino`: reportes o actividades registradas para un menor.
- `imagenes_evento`: evidencias fotograficas vinculadas a reportes.
- `asistencias`: entradas y salidas de los menores.

### Notificaciones

- `tipos_notificacion`: catalogo de motivos de notificacion.
- `notificaciones`: avisos globales o mensajes personales.
- `notificacion_destinatarios`: relaciona cada notificacion con sus
  tutores destinatarios y permite controlar su lectura.
- `dispositivos_fcm`: dispositivos registrados para notificaciones push.

### Seguridad y seguimiento

- `refresh_tokens`: sesiones renovables de los usuarios.
- `bitacora`: espacio para registrar acciones relevantes del sistema.

## Relaciones importantes

1. Casi todas las entidades principales se relacionan con
   `guarderias`. Esto permite que SafeKids trabaje con varias guarderias
   independientes sin mezclar su informacion.
2. Un tutor puede tener varios hijos y un menor puede tener uno o dos
   tutores mediante `nino_tutor`.
3. Un menor puede tener hasta dos contactos autorizados mediante
   `nino_contacto`; esta regla tambien se valida desde la API.
4. Los reportes diarios se guardan en `eventos_nino` y pueden tener
   imagenes relacionadas en `imagenes_evento`.
5. Una notificacion personal se relaciona con un menor. Una notificacion
   global pertenece a una guarderia y se distribuye a sus tutores.
6. `notificacion_destinatarios` permite saber que tutor recibio y leyo
   cada notificacion.

## Orden sugerido para la explicacion

1. Explicar que `guarderias` es la entidad que separa los datos de cada
   institucion.
2. Presentar los usuarios del sistema: empleados, tutores y menores.
3. Explicar las relaciones muchos a muchos `nino_tutor` y
   `nino_contacto`.
4. Mostrar el expediente medico y los reportes diarios.
5. Explicar las notificaciones globales y personales.
6. Mencionar las llaves foraneas, indices y restricciones utilizadas
   para conservar la integridad.
7. Aclarar que la API PHP contiene la logica de negocio y que actualmente
   no se requieren procedimientos almacenados ni triggers.

## Importacion en MySQL Workbench

Abrir el archivo SQL recomendado, ejecutar todo el script y actualizar
la lista de esquemas. El script crea `safekids_v2`, la selecciona con
`USE` y despues crea todos sus objetos.

Por seguridad, no se deben publicar ni enviar a terceros los archivos de
configuracion `database.ini`, `cloudinary.ini` o `firebase-service-account.json`.
