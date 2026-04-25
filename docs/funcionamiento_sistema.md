# 🏦 Funcionamiento Completo del Sistema de Gestión Bancaria FSNET

## 📋 Resumen Ejecutivo

El sistema **FSNET** es una plataforma de gestión bancaria interna diseñada para administrar cuentas, movimientos y rendimientos de usuarios. Implementa:

- ✅ Autenticación de usuarios (Admin/Cliente)
- ✅ Gestión dual de monedas (ARS y USD)
- ✅ Cálculo automático de rendimientos diarios
- ✅ Persistencia en archivos JSON
- ✅ Respaldos remotos automáticos
- ✅ Auditoría de accesos (logs)
- ✅ Dashboards interactivos con gráficos

---

## 🏗️ Arquitectura General

### Stack Tecnológico

| Componente | Tecnología | Descripción |
|-----------|-----------|------------|
| **Backend** | PHP 7.4+ | Lógica de negocio, sesiones, cálculos |
| **Frontend** | HTML5 + CSS + JavaScript | Interfaz visual y gráficos |
| **Datos** | JSON (archivos locales) | Persistencia sin base de datos relacional |
| **Automatización** | Cron Jobs (PHP scripts) | Rendimientos y backups automáticos |
| **Almacenamiento** | Filesystem | Cuentas, usuarios, logs, respaldos |

### Diagrama de Flujo Principal

```
┌──────────────┐
│  index.php   │  ← Público (Login)
│  (Validar)   │
└──────┬───────┘
       │
       ├─→ Role: admin    ──→ admin.php (Gestión del sistema)
       │
       └─→ Role: cliente  ──→ dashboard.php (Panel usuario)
                               ↓
                          [Ver movimientos, saldos, gráficos]
                          [Múltiples cuentas ARS/USD]
```

---

## 📁 Estructura de Directorios

```
banco/
├── 📄 Raíz (Archivos PHP principales)
│   ├── index.php                    ← Login y autenticación
│   ├── dashboard.php                ← Panel de usuario (cliente)
│   ├── admin.php                    ← Panel administrativo
│   ├── ver_usuario.php              ← Detalle de usuario específico
│   ├── todos_los_movimientos.php    ← Reporte consolidado
│   ├── logout.php                   ← Cierre de sesión
│   ├── favicon.php                  ← Links de iconos
│   ├── config.json                  ← Tasas de interés globales
│   ├── logo.png                     ← Logo institucional
│   │
│   ├── 🔄 Scripts de Automatización
│   ├── cron_calcular_rendimientos.php      ← Cálculo de intereses diarios
│   ├── cron_backup_remoto.php              ← Backup remoto
│   └── backup_banco.sh                     ← Backup local (Bash)
│
├── 📂 assets/
│   ├── css/
│   │   └── style.css                ← Estilos (ARS/USD themes)
│   └── js/                          ← Scripts JavaScript (si existen)
│
├── 📂 cuentas/
│   ├── fernando.json                ← Movimientos ARS
│   ├── fernando_usd.json            ← Movimientos USD
│   ├── laura.json
│   ├── laura_usd.json
│   ├── manuel.json
│   ├── manuel_usd.json
│   └── [usuario].json / [usuario]_usd.json
│
├── 📂 users/
│   └── usuarios.json                ← Base de datos de usuarios (credenciales)
│
├── 📂 logs/
│   └── log_login.json               ← Registro de accesos (auditoría)
│
├── 📂 backups/
│   └── backup_YYYY-MM-DD_HH-MM-SS.tar.gz
│
├── 📂 favicon/
│   └── [iconos del sitio]
│
└── 📂 docs/
    └── funcionamiento_sistema.md    ← Esta documentación
```

---

## 🔐 Sistema de Autenticación

### Flujo de Login

```php
1. Usuario accede a index.php
2. Ingresa usuario y contraseña
3. Se valida contra users/usuarios.json
4. Se inicia sesión ($_SESSION)
5. Se registra acceso en logs/log_login.json (IP + fecha)
6. Se redirige según role:
   - admin    → admin.php
   - cliente  → dashboard.php
```

### Estructura de usuarios.json

```json
{
  "users": [
    {
      "username": "fernando",
      "password": "efab1q2w",        // Guardada en texto plano (⚠️ Considerar hash)
      "name": "Fernando Sanchez",
      "role": "admin"                // admin | cliente
    },
    {
      "username": "laura",
      "password": "1982",
      "name": "Laura Moffa",
      "role": "cliente"
    }
  ]
}
```

---

## 💰 Gestión de Cuentas

### Estructura de Cuentas

Cada usuario puede tener **hasta 2 cuentas**:
- `[usuario].json` → Cuenta en **ARS** (Pesos Argentinos)
- `[usuario]_usd.json` → Cuenta en **USD** (Dólares Estadounidenses)

### Estructura de Movimientos

```json
[
  {
    "fecha": "2024-04-20 14:30:00",
    "tipo": "Ingreso",               // Ingreso | Egreso | Rendimiento | Cambio_Divisa
    "monto": 1000.00,
    "concepto": "Depósito inicial",
    "total_en_cuenta": 1000.00
  },
  {
    "fecha": "2024-04-21 10:00:00",
    "tipo": "Rendimiento",
    "monto": 1.67,                   // Calculado automáticamente
    "concepto": "Rendimiento diario",
    "total_en_cuenta": 1001.67
  }
]
```

### Tipos de Movimientos

| Tipo | Origen | Descripción |
|------|--------|------------|
| **Ingreso** | Manual (Admin/Usuario) | Depósito de dinero |
| **Egreso** | Manual (Admin/Usuario) | Retiro de dinero |
| **Rendimiento** | Automático (Cron) | Interés diario calculado |
| **Cambio_Divisa** | Manual (Admin) | Conversión ARS ↔ USD |

---

## 📊 Cálculo de Rendimientos

### Algoritmo de Cálculo

```
Para cada usuario y moneda:
  1. Verificar que la cuenta exista y tenga tasa positiva
  2. Bloquear el archivo (flock) para evitar conflictos
  3. Cargar todos los movimientos
  4. Validar:
     - ¿Tiene movimientos válidos?
     - ¿Ya hay rendimiento hoy?
     - ¿Saldo > 0?
  5. Calcular interés:
     Interés = (Saldo_Actual × Tasa_Mensual) / 30
  6. Crear movimiento de tipo "Rendimiento"
  7. Actualizar total_en_cuenta
  8. Guardar (JSON_PRETTY_PRINT)
  9. Desbloquear archivo
  10. Registrar en consola/log
```

### Configuración de Tasas

Archivo: `config.json`

```json
{
    "interes_mensual": 0.05,        // 5% mensual para ARS
    "interes_mensual_usd": 0.00     // 0% para USD
}
```

### Ejemplo de Cálculo

```
Saldo actual: $10,000 ARS
Tasa mensual: 5% (0.05)
Interés diario: (10,000 × 0.05) / 30 = $16.67

Si se ejecuta cron_calcular_rendimientos.php:
- Se agrega movimiento de $16.67 tipo "Rendimiento"
- Nuevo saldo: $10,016.67
- Se registra: "OK: ARS para usuario fernando (+ 16.67)"
```

### Mecanismos de Seguridad

1. **File Locking (flock)**: Evita escrituras simultáneas
2. **Deduplicación**: Verifica que no exista rendimiento hoy
3. **Validación de saldo**: Solo calcula si saldo > 0
4. **Tasa configurable**: Tasa = 0 → sin rendimiento
5. **Logging**: Registra cada operación con resultado

---

## 🎯 Paneles de Usuario

### Dashboard de Cliente (`dashboard.php`)

**Acceso**: `GET /dashboard.php?moneda=ars|usd`

**Funcionalidades**:

```
┌─────────────────────────────────────┐
│ Selector de Moneda (ARS / USD)      │
├─────────────────────────────────────┤
│ Resumen:                            │
│ • Saldo Total en Cuenta             │
│ • Capital Invertido (Ingresos - Egresos)
│ • Rendimientos Totales              │
│ • Rendimiento último día            │
│ • Rendimiento últimos 30 días       │
├─────────────────────────────────────┤
│ Gráfico de Evolución (últimos 20)   │
│ Línea con puntos de saldo por día   │
├─────────────────────────────────────┤
│ Tabla de Movimientos (agrupados):   │
│ • Fecha | Tipo | Monto | Total      │
│ • Rendimientos consecutivos se      │
│   agrupan con rango de fechas       │
└─────────────────────────────────────┘
```

**Lógica de Agrupamiento**:
- Rendimientos consecutivos se combinan en una fila
- Ejemplo: "Rendimientos (5 días: 20/04 al 24/04)"
- Facilita lectura de historial

**Temas Visuales**:
- **ARS** → Azul (#007bff)
- **USD** → Verde (#28a745)

---

### Panel Administrativo (`admin.php`)

**Acceso**: Solo usuarios con `role: admin`

**Funcionalidades principales**:

```
1. Gestión de Usuarios
   - Crear/editar/eliminar usuarios
   - Asignar roles (admin/cliente)
   - Modificar contraseñas

2. Gestión de Cuentas
   - Ver todas las cuentas (ARS/USD)
   - Agregar/eliminar movimientos manuales
   - Hacer cambios de divisa
   - Limpiar/resetear cuentas

3. Reportes
   - Consolidado de todas las cuentas
   - Filtros por usuario/moneda/fecha
   - Exportar (si está implementado)

4. Configuración
   - Tasas de interés (config.json)
   - Parámetros globales

5. Auditoría
   - Ver logs de login
   - Historial de cambios (si está implementado)

6. Backups
   - Ejecutar backup manual
   - Ver historial de backups
```

---

## 🔄 Procesos Automáticos (Cron Jobs)

### 1. Cálculo de Rendimientos (`cron_calcular_rendimientos.php`)

**Propósito**: Calcular y agregar intereses diarios automáticamente

**Ejecución recomendada**:
```bash
# Cron tab (diario a las 10:00 AM)
0 10 * * * php /ruta/al/banco/cron_calcular_rendimientos.php
```

**Flujo**:
```
1. Cargar config.json (tasas)
2. Cargar usuarios.json (lista de usuarios)
3. Para cada usuario:
   a. Procesar cuenta ARS (si existe)
   b. Procesar cuenta USD (si existe)
   c. Bloquear archivo
   d. Validar y calcular interés
   e. Guardar movimiento
   f. Desbloquear
4. Encadenar ejecución de cron_backup_remoto.php
5. Registrar operaciones en stdout/logs
```

**Output esperado**:
```
--- Inicio proceso de rendimientos: 2024-04-25 10:00:15 ---
OK: ARS para usuario fernando (+ 16.67)
SKIP: USD para usuario fernando (tasa no positiva)
OK: ARS para usuario laura (+ 8.33)
SKIP: ARS para usuario serena (sin movimientos válidos)
...
>>> Iniciando cadena de Backup Remoto...
--- Fin del proceso ---
```

### 2. Backup Remoto (`cron_backup_remoto.php`)

**Propósito**: Realizar respaldo automático del sistema completo

**Automatización**: Se ejecuta encadenado al final de cron_calcular_rendimientos.php

**Acciones**:
- Comprime carpetas clave: `cuentas/`, `users/`, `logs/`, `config.json`
- Genera archivo `.tar.gz` con timestamp: `backup_YYYY-MM-DD_HH-MM-SS.tar.gz`
- Guarda en `backups/`
- (Opcionalmente) Envía a servidor remoto

### 3. Backup Local (`backup_banco.sh`)

**Propósito**: Respaldo manual de todo el sistema

**Uso**:
```bash
bash backup_banco.sh
```

**Genera**: Archivo comprimido con timestamp en `backups/`

---

## 📝 Detalle de Archivos Clave

### `index.php` (80 líneas)
- Carga `users/usuarios.json`
- Valida credenciales (usuario + contraseña)
- Inicia sesión con `$_SESSION`
- Registra login en `logs/log_login.json` (usuario, fecha, IP)
- Redirige según role (admin → admin.php | cliente → dashboard.php)

### `dashboard.php` (100 líneas)
- Requiere sesión de usuario cliente
- Parámetro `GET moneda` para seleccionar ARS/USD
- Carga movimientos del archivo correspondiente
- Calcula: saldo, capital, rendimientos, gráficos
- Agrupa rendimientos consecutivos en la tabla
- Renderiza HTML con gráficos y resumen

### `cron_calcular_rendimientos.php` (173 líneas)
- Script para ejecutar por cron
- Bloquea archivos de cuenta (flock)
- Evita rendimientos duplicados (chequea fecha)
- Calcula: `interés = (saldo × tasa) / 30`
- Agrega movimiento tipo "Rendimiento"
- Encadena ejecución de backup remoto

### `config.json`
- `interes_mensual`: Tasa ARS (ej: 0.05 = 5%)
- `interes_mensual_usd`: Tasa USD (ej: 0.00 = 0%)

### `users/usuarios.json`
- Array de usuarios con credenciales y roles
- ⚠️ Actualmente en texto plano (considerar hashing)

### `cuentas/[usuario].json`
- Array de movimientos bancarios
- Ordenados cronológicamente
- Cada movimiento incluye `total_en_cuenta` acumulado

---

## 🔒 Consideraciones de Seguridad

### ✅ Fortalezas Actuales
- Autenticación por sesión
- Separación de roles (admin/cliente)
- Validación de sesiones en cada página
- Bloqueos de archivo para evitar race conditions
- Logs de acceso para auditoría
- Aislamiento de datos por usuario (archivos separados)

### ⚠️ Problemas Identificados

| Problema | Riesgo | Solución Sugerida |
|----------|--------|-----------------|
| Contraseñas en texto plano | CRÍTICO | Usar `password_hash()` + `password_verify()` |
| Sin CSRF protection | ALTO | Agregar tokens CSRF en formularios |
| Sin HTTPS enforcement | ALTO | Redirigir HTTP → HTTPS en producción |
| Acceso directo a archivos JSON | MEDIO | Cambiar permisos (644 o no legibles vía web) |
| Errores PHP visibles | BAJO | `display_errors = 0` en producción |
| Sin rate limiting | MEDIO | Implementar límite de intentos de login |

### 🛡️ Checklist de Despliegue

- [ ] Cambiar contraseñas de usuarios (usar hash)
- [ ] Configurar permisos de archivos (chmod 640 para cuentas/)
- [ ] Desactivar display_errors en producción
- [ ] Configurar HTTPS con certificado SSL válido
- [ ] Establecer cron jobs para rendimientos y backups
- [ ] Revisar y archivar logs periódicamente
- [ ] Realizar backup de respaldo (backup del backup)
- [ ] Establecer política de rotación de backups
- [ ] Documentar proceso de recuperación ante desastres

---

## 📈 Flujos de Operación Típicos

### Flujo 1: Usuario realiza depósito

```
1. Usuario accede a dashboard.php
2. Click en "Nuevo Movimiento" (si implementado)
3. O el admin desde admin.php:
   - Selecciona usuario
   - Escribe monto
   - Escribe concepto
4. Se crea movimiento tipo "Ingreso"
5. Se calcula nuevo total_en_cuenta
6. Se guardaen cuentas/[usuario].json
7. Se refleja en dashboard al recargar
```

### Flujo 2: Cálculo automático de rendimientos

```
1. Cron ejecuta cron_calcular_rendimientos.php (ej: 10:00 AM)
2. Para cada usuario:
   - Bloquea archivo de cuenta
   - Verifica saldo > 0 y tasa > 0
   - Calcula: (saldo × tasa) / 30
   - Agrega movimiento "Rendimiento"
   - Actualiza total_en_cuenta
   - Desbloquea archivo
3. Encadena backup remoto
4. Al día siguiente, usuario ve nuevo rendimiento en dashboard
```

### Flujo 3: Cambio de moneda

```
1. Admin desde admin.php:
   - Selecciona usuario
   - Especifica monto a transferir
   - Selecciona "Cambio de divisa"
2. Sistema:
   - Resta del saldo ARS
   - Suma al saldo USD (con tasa de cambio si aplica)
   - Crea 2 movimientos: Egreso (ARS) + Ingreso (USD)
3. Usuario ve ambas cuentas actualizadas
```

---

## 🚀 Mejoras Sugeridas

### Corto Plazo (Críticas)
1. **Hash de contraseñas** - Implementar `password_hash()`
2. **CSRF tokens** - Agregar tokens en formularios
3. **Rate limiting** - Limitar intentos fallidos de login
4. **Validación de entrada** - Sanitizar datos POST/GET

### Mediano Plazo (Importantes)
1. **Migrar a SQL** - SQLite o PostgreSQL
2. **API REST** - Para futuras apps móviles
3. **Logs más detallados** - Quién hizo qué y cuándo
4. **Compresión automática** - De logs y backups antiguos
5. **2FA** - Autenticación de dos factores

### Largo Plazo (Nice-to-have)
1. **Dashboard mejorado** - Más gráficos y análisis
2. **Exportación** - PDF/Excel de reportes
3. **Notificaciones** - Email/SMS de rendimientos
4. **Múltiples bases de datos** - Para varias sucursales
5. **App móvil** - Complemento web

---

## 📞 Contacto y Soporte

- **Desarrollador**: Fernando Sánchez
- **Email**: fernandosanchez.ar@gmail.com
- **Última actualización**: 2024-04-25
- **Versión del sistema**: 2.0

---

## 📚 Referencias Internas

- [DOCUMENTACION.md](../DOCUMENTACION.md) - Documentación completa
- [config.json](../config.json) - Configuración del sistema
- [users/usuarios.json](../users/usuarios.json) - Base de usuarios
- [logs/log_login.json](../logs/log_login.json) - Auditoría de accesos

---

*Sistema de Gestión Bancaria FSNET © 2024*
