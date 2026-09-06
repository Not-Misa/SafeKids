# SafeKids

Sistema de gestión y comunicación para guarderías infantiles.

## Descripción

SafeKids es un proyecto desarrollado para la materia Proyecto Integrador 2, correspondiente al quinto cuatrimestre de la carrera de Ingeniería en Tecnologías de la Información.

El sistema busca facilitar la administración de la información de los niños y mejorar la comunicación entre la guardería y sus tutores mediante una aplicación web y una aplicación móvil.

## Componentes del sistema

### Aplicación web

Está dirigida a administradores y empleados de la guardería. Permite:

- Gestionar empleados, niños y tutores.
- Consultar el expediente de cada niño.
- Registrar reportes diarios.
- Adjuntar evidencias fotográficas.
- Crear notificaciones generales y personalizadas.
- Consultar el historial de notificaciones.
- Administrar información de contactos de emergencia.

### Aplicación móvil

Está dirigida a padres y tutores. Permite:

- Consultar la información de sus hijos.
- Revisar reportes diarios.
- Consultar notificaciones generales y personalizadas.
- Visualizar imágenes adjuntas.
- Consultar información de tutores y contactos de emergencia.

## Tecnologías utilizadas

- Angular
- PHP
- MySQL 8.0.44
- Kotlin
- Android Studio
- Firebase Cloud Messaging
- Cloudinary
- HTML, CSS y JavaScript
- Git y GitHub

## Arquitectura

La aplicación web y la aplicación móvil consumen una API REST desarrollada en PHP. Esta API se conecta con una base de datos MySQL para consultar y administrar la información del sistema.

```text
Aplicación web Angular ─┐
                        ├── API REST PHP ─── MySQL
Aplicación móvil Kotlin ─┘
