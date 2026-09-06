# SafeKids Frontend

Aplicacion web de SafeKids para la administracion de guarderias infantiles.

## Descripcion

Este frontend permite a administradores y empleados gestionar la informacion de los ninos, empleados, tutores, reportes diarios y notificaciones generales o personalizadas.

La aplicacion esta desarrollada con Angular y consume una API REST desarrollada en PHP.

## Funciones principales

- Inicio de sesion y control de acceso por roles.
- Administracion de ninos, empleados y tutores.
- Consulta de perfiles y expedientes infantiles.
- Registro e historial de reportes diarios.
- Creacion de notificaciones generales y personalizadas.
- Adjuntar y visualizar imagenes en notificaciones.
- Historial de notificaciones.

## Tecnologias

- Angular 22
- TypeScript
- HTML y CSS
- API REST en PHP
- MySQL 8.0.44

## Instalacion

Requiere Node.js y npm instalados.

```bash
npm install
```

## Servidor de desarrollo

```bash
npm start
```

Despues, abre `http://localhost:4200/` en el navegador.

## Compilacion

```bash
npm run build
```

Los archivos compilados se generan en la carpeta `dist/`. Esta carpeta no se incluye en el repositorio porque se puede generar nuevamente.

## Configuracion

La URL de la API se configura en `src/app/config/api.config.ts`.

Las credenciales, contrasenas y configuraciones privadas se mantienen fuera del repositorio.

## Autor

Angel
