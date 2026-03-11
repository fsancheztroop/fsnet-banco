# Documentación del Sistema de Gestión Bancaria

## 1. Introducción General del Sistema

Este sistema fue desarrollado para la gestión y administración de cuentas bancarias de usuarios en un entorno web privado. Su objetivo principal es facilitar el manejo de cuentas, movimientos, usuarios y respaldos de información, cubriendo la necesidad de un control centralizado y seguro de operaciones bancarias internas.

Está dirigido a administradores y operadores de una entidad bancaria o financiera que requieren gestionar cuentas, usuarios y movimientos de manera sencilla y eficiente, sin depender de sistemas bancarios comerciales complejos.

**Funcionalidades generales:**
- Gestión de usuarios y cuentas
- Registro y visualización de movimientos bancarios
- Cálculo de rendimientos
- Administración de accesos y sesiones
- Respaldo y restauración de datos
- Panel de control (dashboard)

## 2. Tecnologías Utilizadas

- **Lenguajes:** PHP (backend), Python (scripts de cálculo), JavaScript (frontend)
- **Frontend:** HTML, CSS (personalizado)
- **Backend:** PHP puro (sin frameworks)
- **Base de datos:** Archivos JSON locales (no se utiliza un motor de base de datos relacional)
- **Scripts auxiliares:** Bash (para backups), Python (para cálculos)
- **Tipo de sistema:** Web (no móvil ni híbrido)

## 3. Estructura del Proyecto

```
├── admin.php
├── backup_banco.sh
├── calcular_rendimientos.py
├── config.json
├── dashboard.php
├── favicon.php
├── index.php
├── logo.png
├── logout.php
├── todos_los_movimientos.php
├── users.json
├── ver_usuario.php
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
├── backups/
│   └── backup_*.tar.gz
├── cuentas/
│   └── *.json
├── favicon/
│   └── (archivos de iconos)
├── logs/
│   └── log_login.json
├── users/
│   └── usuarios.json
```

**Descripción de carpetas y archivos:**
- **Raíz:** Archivos principales del sistema y scripts.
- **assets/**: Recursos estáticos (CSS y JS).
- **backups/**: Almacena respaldos automáticos del sistema.
- **cuentas/**: Archivos JSON con la información de cada cuenta de usuario.
- **favicon/**: Iconos para la web y dispositivos.
- **logs/**: Registros de actividad (ej: logins).
- **users/**: Información de usuarios registrados.

## 4. Detalle Archivo por Archivo

### admin.php
- **Descripción:** Panel de administración para gestionar usuarios, cuentas y movimientos.
- **Funcionalidades:** Alta, baja y modificación de usuarios/cuentas; acceso a reportes y respaldos.
- **Lógica:** Procesa formularios, valida sesiones, interactúa con archivos JSON de usuarios y cuentas.
- **Comunicación:** Incluye/require otros archivos PHP y lee/escribe en `users/usuarios.json` y `cuentas/*.json`.

### backup_banco.sh
- **Descripción:** Script Bash para generar respaldos automáticos de la información.
- **Funcionalidades:** Comprime carpetas y archivos clave en un archivo `.tar.gz` con fecha/hora.
- **Lógica:** Se ejecuta por cron o manualmente; no interactúa con la web directamente.

### calcular_rendimientos.py
- **Descripción:** Script Python para calcular rendimientos de cuentas.
- **Funcionalidades:** Lee archivos de cuentas, calcula intereses/rendimientos y actualiza los saldos.
- **Lógica:** Se ejecuta manualmente o por cron; puede ser llamado desde PHP.

### config.json
- **Descripción:** Configuración general del sistema (parámetros globales).
- **Funcionalidades:** Define rutas, parámetros de seguridad, etc.

### dashboard.php
- **Descripción:** Panel principal con resumen de actividad y accesos rápidos.
- **Funcionalidades:** Muestra estadísticas, accesos directos y alertas.
- **Lógica:** Lee datos de cuentas, usuarios y logs para mostrar información relevante.

### favicon.php
- **Descripción:** Incluye los enlaces a los iconos del sitio para navegadores y dispositivos.
- **Funcionalidades:** Proporciona los tags `<link>` para los favicons.
- **Lógica:** Se incluye en el `<head>` de las páginas principales.

### index.php
- **Descripción:** Página de inicio y login del sistema.
- **Funcionalidades:** Formulario de acceso, validación de usuario y contraseña.
- **Lógica:** Inicia sesión, redirige a dashboard o muestra errores.
- **Comunicación:** Lee `users/usuarios.json` y registra accesos en `logs/log_login.json`.

### logo.png
- **Descripción:** Logo institucional del sistema.

### logout.php
- **Descripción:** Cierra la sesión del usuario.
- **Funcionalidades:** Destruye la sesión y redirige a login.

### todos_los_movimientos.php
- **Descripción:** Muestra todos los movimientos registrados en el sistema.
- **Funcionalidades:** Listado filtrable/exportable de movimientos.
- **Lógica:** Lee todos los archivos de `cuentas/` y muestra los movimientos.

### users.json
- **Descripción:** Archivo de respaldo o histórico de usuarios.

### ver_usuario.php
- **Descripción:** Muestra el detalle de un usuario específico.
- **Funcionalidades:** Visualiza datos personales, movimientos y estado de la cuenta.
- **Lógica:** Lee el archivo correspondiente en `cuentas/` y `users/`.

### assets/css/style.css
- **Descripción:** Estilos personalizados del sistema.
- **Funcionalidades:** Define la apariencia de la interfaz.

### assets/js/
- **Descripción:** Scripts JavaScript para mejorar la experiencia de usuario.

### backups/
- **Descripción:** Carpeta donde se almacenan los respaldos automáticos.

### cuentas/*.json
- **Descripción:** Un archivo por cada cuenta de usuario, con su información y movimientos.
- **Funcionalidades:** Persistencia de datos de cuentas y movimientos.

### favicon/
- **Descripción:** Archivos de iconos para navegadores y dispositivos.

### logs/log_login.json
- **Descripción:** Registro de accesos al sistema.
- **Funcionalidades:** Guarda fecha, usuario y resultado de cada intento de login.

### users/usuarios.json
- **Descripción:** Base de datos principal de usuarios.
- **Funcionalidades:** Almacena usuarios, contraseñas (hash), roles y estados.

## 5. Instalación y Puesta en Marcha

### Requisitos
- Servidor web (Apache, Nginx, etc.)
- PHP 7.4 o superior
- Python 3.x (para scripts auxiliares)
- Bash (para backups, en sistemas Linux)
- Permisos de escritura en carpetas `cuentas/`, `logs/`, `backups/`, `users/`

### Pasos de instalación
1. Clonar o copiar todos los archivos y carpetas al servidor.
2. Configurar permisos de escritura en las carpetas mencionadas.
3. Editar `config.json` con los parámetros necesarios.
4. (Opcional) Configurar tareas programadas (cron) para backups y cálculos automáticos.
5. Acceder vía navegador a `index.php` para iniciar sesión.

### Variables de entorno
- No utiliza variables de entorno estándar, pero los parámetros clave están en `config.json`.

## 6. API

El sistema no expone una API REST pública. Toda la interacción es a través de formularios y archivos PHP.

## 7. Consideraciones Finales

- **Mantenimiento:** Revisar periódicamente los respaldos y logs. Limpiar archivos antiguos para evitar saturación.
- **Seguridad:** Cambiar contraseñas regularmente y mantener los archivos fuera del acceso público directo.
- **Mejoras sugeridas:** Migrar a una base de datos relacional, implementar autenticación de dos factores, agregar logs de auditoría más detallados.
- **Para nuevos desarrolladores:** Leer el código de `admin.php` y `dashboard.php` para entender el flujo principal. Los scripts auxiliares pueden adaptarse según necesidades.

---

*Documentación generada para facilitar la comprensión y mantenimiento del sistema.*
