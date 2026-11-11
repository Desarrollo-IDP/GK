# Reporte de Funcionalidades del Sistema GK

## Índice
1. [Archivos de la Raíz](#archivos-de-la-raíz)
2. [Módulo de Tickets](#módulo-de-tickets)
3. [Módulo de Inventario](#módulo-de-inventario)
4. [Módulo de Almacén](#módulo-de-almacén)

---

## Archivos de la Raíz

### `index.php`
**Funcionalidad Principal:** Sistema de autenticación y login principal del sistema.

**Características:**
- Autenticación de usuarios con verificación de contraseña
- Soporte para autenticación de dos factores (2FA) con Google Authenticator
- Recuperación de código de autenticación por correo electrónico usando PHPMailer
- Verificación de permisos de usuario (can_view_inventory_history, can_view_ticket_history, can_manage_users)
- Garantiza que las columnas necesarias existan en la tabla usuarios
- Comparación de nombres de usuario insensible a mayúsculas/minúsculas
- Validación de sesiones y redirección según permisos

**Acciones disponibles:**
- Login de usuarios
- Recuperación de código de autenticación 2FA
- Verificación de código 2FA
- Gestión de sesiones de usuario

---

### `auth.php`
**Funcionalidad Principal:** Sistema de autenticación y control de acceso basado en roles y permisos.

**Características:**
- Verificación de sesión de usuario (`isLoggedIn()`)
- Control de acceso por rutas (`checkAuth()`)
- Control de acceso por roles (administrador, supervisor, visualizador)
- Permisos granulares por usuario:
  - `can_view_inventory_history`: Ver historial de inventario
  - `can_view_ticket_history`: Ver historial de tickets
  - `can_manage_users`: Gestionar usuarios
- Bypass total para usuario admin
- Control de acceso a rutas específicas (/Inventario/, /tickets/)
- Funciones de verificación de permisos:
  - `canViewInventoryHistory()`
  - `canViewTicketHistory()`
  - `canManageUsers()`
  - `canViewUserHistory()`
- Función de logout

**Acciones disponibles:**
- Verificación de autenticación
- Control de acceso a módulos
- Verificación de permisos específicos
- Gestión de sesiones

---

### `gestion-usuarios.php`
**Funcionalidad Principal:** Gestión completa de usuarios del sistema.

**Características:**
- Crear nuevos usuarios
- Editar usuarios existentes
- Eliminar usuarios (soft delete)
- Asignar roles (administrador, supervisor, visualizador)
- Configurar permisos específicos por usuario:
  - Ver historial de inventario
  - Ver historial de tickets
  - Gestionar usuarios
- Configurar autenticación de dos factores (Google Authenticator)
- Asignar correo electrónico a usuarios
- Historial de acciones realizadas sobre usuarios (tabla `user_history`)
- Registro de cambios con valores antiguos y nuevos (JSON)
- Búsqueda y filtrado de usuarios
- Validación de permisos antes de realizar acciones

**Acciones disponibles:**
- Crear usuario
- Editar usuario
- Eliminar usuario
- Cambiar contraseña de usuario
- Configurar 2FA
- Asignar permisos específicos
- Ver historial de cambios de usuarios

---

### `perfil-usuario.php`
**Funcionalidad Principal:** Gestión del perfil del usuario autenticado.

**Características:**
- Visualización de información del usuario:
  - Nombre de usuario
  - Rol
  - Correo electrónico
  - Fecha de creación de cuenta
- Cambio de contraseña:
  - Verificación de contraseña actual
  - Validación de nueva contraseña (mínimo 6 caracteres)
  - Confirmación de contraseña
- Interfaz moderna con diseño responsivo
- Mensajes de éxito/error para operaciones

**Acciones disponibles:**
- Ver información del perfil
- Cambiar contraseña propia

---

### `direc.php`
**Funcionalidad Principal:** Página de selección de módulos del sistema.

**Características:**
- Interfaz de navegación principal después del login
- Acceso rápido a módulos principales:
  - Módulo de Inventario
  - Módulo de Tickets
  - Módulo de Almacén
- Botones de acceso rápido:
  - Gestión de usuarios (si tiene permisos)
  - Perfil de usuario
  - Cerrar sesión
- Diseño moderno con gradientes y efectos visuales
- Verificación de permisos antes de mostrar opciones

**Acciones disponibles:**
- Navegar a módulo de Inventario
- Navegar a módulo de Tickets
- Navegar a módulo de Almacén
- Acceder a gestión de usuarios
- Ver perfil
- Cerrar sesión

---

### `historial-usuarios.php`
**Funcionalidad Principal:** Visualización del historial completo de acciones realizadas sobre usuarios.

**Características:**
- Solo accesible para administradores
- Muestra todas las acciones realizadas:
  - Creación de usuarios
  - Edición de usuarios
  - Eliminación de usuarios
  - Cambios de permisos
- Información mostrada:
  - Acción realizada
  - Usuario objetivo
  - Usuario que realizó la acción
  - Descripción de la acción
  - Valores antiguos y nuevos (JSON)
  - Fecha y hora de la acción
- Ordenado por fecha descendente (más recientes primero)
- Tabla responsiva con diseño moderno

**Acciones disponibles:**
- Ver historial completo de usuarios
- Filtrar por tipo de acción
- Ver detalles de cambios (valores antiguos/nuevos)

---

### `gestionar-rutas.php`
**Funcionalidad Principal:** Configuración de permisos de acceso a rutas por rol.

**Características:**
- Solo accesible para administradores y supervisores
- Configuración de permisos por rol:
  - **Administrador**: Acceso total (no editable)
  - **Supervisor**: Puede tener acceso a /Inventario/ y/o /tickets/
  - **Visualizador**: Puede tener acceso a /Inventario/ y/o /tickets/
- Interfaz con checkboxes para habilitar/deshabilitar rutas
- Guardado de configuración en archivo `route_permissions.php`
- Validación de permisos antes de guardar
- Mensajes de éxito/error

**Acciones disponibles:**
- Configurar acceso a /Inventario/ por rol
- Configurar acceso a /tickets/ por rol
- Guardar cambios de configuración

---

### `route_permissions.php`
**Funcionalidad Principal:** Archivo de configuración de permisos de rutas por rol.

**Características:**
- Array PHP con configuración de permisos
- Estructura:
  ```php
  [
    'administrador' => ['*'],  // Acceso total
    'supervisor' => ['/Inventario/', '/tickets/'],
    'visualizador' => ['/Inventario/']
  ]
  ```
- Editado desde `gestionar-rutas.php`
- Leído por `auth.php` para verificar permisos

---

### `logout.php`
**Funcionalidad Principal:** Cierre de sesión de usuario.

**Características:**
- Destrucción de sesión
- Eliminación de cookies de sesión
- Redirección a página de login
- Limpieza completa de datos de sesión

**Acciones disponibles:**
- Cerrar sesión de usuario

---

### `log_activity.php`
**Funcionalidad Principal:** Función para registrar actividades del sistema.

**Características:**
- Función `logActivity()` para registrar acciones
- Parámetros:
  - `$accion`: Tipo de acción realizada
  - `$tabla_afectada`: Tabla de base de datos afectada
  - `$id_registro`: ID del registro afectado (opcional)
  - `$detalles`: Detalles adicionales (opcional)
- Registro en tabla `historial_actividades`
- Captura de información del usuario de la sesión
- Manejo de errores con logging

**Acciones disponibles:**
- Registrar actividades del sistema
- Trackear cambios en base de datos

---

### `fix_user_history.php`
**Funcionalidad Principal:** Script de mantenimiento para arreglar la tabla `user_history`.

**Características:**
- Migración de estructura antigua a nueva
- Eliminación de foreign keys problemáticas
- Agregado de nuevas columnas:
  - `nombre_usuario`
  - `id_usuario`
- Migración de datos de columnas antiguas (`performed_by`, `performed_by_id`)
- Verificación de estructura final
- **IMPORTANTE:** Debe eliminarse después de usar por seguridad

**Acciones disponibles:**
- Arreglar estructura de tabla `user_history`
- Migrar datos antiguos

---

### `what.php`
**Funcionalidad Principal:** Archivo de prueba/depuración.

**Características:**
- Utilizado para pruebas y depuración
- Puede contener código temporal

---

### `ejecutar_outlook_emails.bat`
**Funcionalidad Principal:** Script batch para ejecutar procesamiento de emails de Outlook.

**Características:**
- Ejecuta script PHP para procesar emails
- Utilizado para automatización de tareas

---

### `notificaciones.bat`
**Funcionalidad Principal:** Script batch para ejecutar notificaciones.

**Características:**
- Ejecuta script PHP para enviar notificaciones
- Utilizado para automatización de tareas

---

### `GUIA_SISTEMA_PERMISOS.md`
**Funcionalidad Principal:** Documentación completa del sistema de permisos.

**Características:**
- Descripción del sistema de permisos
- Arquitectura del sistema
- Estructura de base de datos
- Funciones de control de acceso
- Pasos de implementación
- Guías de uso
- Troubleshooting

---

### Archivos SQL
- `secureitmxcom_tickets (1).sql`: Volcado del sistema; la conexión actual utiliza `grupo_gk`
- `create_ticket_history_table.sql`: Script para crear tabla de historial de tickets
- `create_ticket_status_tracking_table.sql`: Script para crear tabla de seguimiento de estados de tickets

---

## Módulo de Tickets

### Ubicación: `/tickets/`

### Descripción General
Sistema completo de gestión de tickets de soporte técnico con integración de correo electrónico.

---

### `tickets/index.php`
**Funcionalidad:** Redirige al sistema principal de login.

---

### `tickets/inicio.php` y `tickets/inicio-moderno.php`
**Funcionalidad Principal:** Página de inicio del módulo de tickets.

**Características:**
- Dashboard de tickets
- Estadísticas generales
- Acceso rápido a funcionalidades principales

---

### Módulo: Gestión de Tickets (`tickets/modules/tickets/`)

#### `tickets.php`
**Funcionalidad Principal:** Listado y gestión de tickets.

**Características:**
- Visualización de todos los tickets activos
- Filtros disponibles:
  - Por estado (Sin Asignar, Abierto, En Proceso, etc.)
  - Por ID de ticket
  - Por búsqueda general
- Información mostrada:
  - ID del ticket
  - Categoría
  - Estado
  - Modo de contacto
  - Solicitante
  - Técnico asignado
  - Fecha de creación
- Exclusión de tickets eliminados y cerrados
- Ordenamiento por fecha de creación (más recientes primero)
- Interfaz moderna y responsiva

**Acciones disponibles:**
- Ver lista de tickets
- Filtrar tickets por estado
- Buscar tickets por ID
- Ver detalles de ticket
- Eliminar ticket (soft delete)

---

#### `new.php`
**Funcionalidad Principal:** Crear nuevo ticket.

**Características:**
- Formulario para crear ticket
- Campos disponibles:
  - Categoría
  - Estado inicial
  - Modo de contacto
  - Solicitante
  - Técnico asignado
  - Descripción del problema
  - Archivos adjuntos
- Validación de campos requeridos
- Subida de archivos adjuntos

**Acciones disponibles:**
- Crear nuevo ticket
- Adjuntar archivos

---

#### `edit.php`
**Funcionalidad Principal:** Editar ticket existente.

**Características:**
- Edición de información del ticket
- Cambio de estado
- Reasignación de técnico
- Actualización de descripción
- Gestión de archivos adjuntos

**Acciones disponibles:**
- Editar ticket
- Cambiar estado
- Reasignar técnico
- Actualizar información

---

#### `view.php`
**Funcionalidad Principal:** Visualización detallada de un ticket.

**Características:**
- Información completa del ticket
- Historial de cambios
- Archivos adjuntos
- Respuestas y comentarios
- Seguimiento de estado

**Acciones disponibles:**
- Ver detalles completos
- Descargar archivos adjuntos
- Ver historial

---

#### `all_tickets.php`
**Funcionalidad Principal:** Ver todos los tickets (incluyendo cerrados).

**Características:**
- Listado completo de tickets
- Incluye tickets cerrados
- Filtros avanzados
- Estadísticas generales

**Acciones disponibles:**
- Ver todos los tickets
- Filtrar por diferentes criterios

---

#### `closed_tickets.php`
**Funcionalidad Principal:** Ver solo tickets cerrados.

**Características:**
- Listado de tickets cerrados
- Filtros por fecha de cierre
- Estadísticas de tickets cerrados

**Acciones disponibles:**
- Ver tickets cerrados
- Filtrar por fecha

---

#### `history.php`
**Funcionalidad Principal:** Historial de cambios de un ticket.

**Características:**
- Registro completo de cambios
- Cambios de estado
- Cambios de asignación
- Comentarios y actualizaciones
- Fechas y usuarios que realizaron cambios

**Acciones disponibles:**
- Ver historial completo
- Filtrar por tipo de cambio

---

#### `add_cost.php`
**Funcionalidad Principal:** Agregar costo a un ticket.

**Características:**
- Registro de costos asociados
- Descripción del costo
- Monto
- Archivos adjuntos (facturas, recibos)
- Fecha del costo

**Acciones disponibles:**
- Agregar costo
- Adjuntar documentos de costo

---

#### `update_cost.php`
**Funcionalidad Principal:** Actualizar costo existente.

**Características:**
- Edición de información de costo
- Actualización de monto
- Modificación de descripción
- Gestión de archivos adjuntos

**Acciones disponibles:**
- Editar costo
- Actualizar información

---

#### `delete_cost.php`
**Funcionalidad Principal:** Eliminar costo de ticket.

**Características:**
- Eliminación de registro de costo
- Validación de permisos

**Acciones disponibles:**
- Eliminar costo

---

#### `download_cost_file.php`
**Funcionalidad Principal:** Descargar archivo adjunto de costo.

**Características:**
- Descarga segura de archivos
- Validación de permisos
- Verificación de existencia de archivo

**Acciones disponibles:**
- Descargar archivo de costo

---

#### `delete_cost_file.php`
**Funcionalidad Principal:** Eliminar archivo adjunto de costo.

**Características:**
- Eliminación de archivo físico
- Eliminación de registro en base de datos
- Validación de permisos

**Acciones disponibles:**
- Eliminar archivo adjunto

---

#### `get_cost_files.php`
**Funcionalidad Principal:** Obtener lista de archivos adjuntos de costo.

**Características:**
- API para obtener archivos
- Formato JSON
- Información de archivos

**Acciones disponibles:**
- Listar archivos de costo

---

#### `download_attachment.php`
**Funcionalidad Principal:** Descargar archivo adjunto de ticket.

**Características:**
- Descarga segura de archivos adjuntos
- Validación de permisos
- Verificación de existencia

**Acciones disponibles:**
- Descargar archivo adjunto

---

#### `delete_attachment.php`
**Funcionalidad Principal:** Eliminar archivo adjunto de ticket.

**Características:**
- Eliminación de archivo físico
- Eliminación de registro en base de datos

**Acciones disponibles:**
- Eliminar archivo adjunto

---

#### `modern-tickets.php`
**Funcionalidad Principal:** Interfaz moderna alternativa para gestión de tickets.

**Características:**
- Diseño moderno y responsivo
- Mejoras de UX
- Funcionalidades mejoradas

---

#### `setup_cost_attachments.php`
**Funcionalidad Principal:** Configuración de sistema de archivos adjuntos para costos.

**Características:**
- Setup inicial
- Creación de tablas necesarias
- Configuración de permisos

---

### Módulo: Solicitantes (`tickets/modules/requester/`)

#### `requester.php`
**Funcionalidad Principal:** Gestión de solicitantes de tickets.

**Características:**
- Listado de solicitantes
- Información de contacto
- Historial de tickets por solicitante
- Búsqueda y filtrado

**Acciones disponibles:**
- Ver lista de solicitantes
- Buscar solicitante
- Ver historial de tickets

---

#### `add.php`
**Funcionalidad Principal:** Agregar nuevo solicitante.

**Características:**
- Formulario de creación
- Información de contacto
- Validación de datos

**Acciones disponibles:**
- Crear solicitante

---

#### `edit.php`
**Funcionalidad Principal:** Editar solicitante existente.

**Características:**
- Edición de información
- Actualización de contacto

**Acciones disponibles:**
- Editar solicitante

---

### Módulo: Técnicos (`tickets/modules/technicians/`)

#### `technicians.php`
**Funcionalidad Principal:** Gestión de técnicos.

**Características:**
- Listado de técnicos
- Tickets asignados
- Estadísticas de rendimiento
- Búsqueda y filtrado

**Acciones disponibles:**
- Ver lista de técnicos
- Ver tickets asignados
- Ver estadísticas

---

#### `add.php`
**Funcionalidad Principal:** Agregar nuevo técnico.

**Características:**
- Formulario de creación
- Información del técnico
- Asignación de permisos

**Acciones disponibles:**
- Crear técnico

---

#### `edit.php`
**Funcionalidad Principal:** Editar técnico existente.

**Características:**
- Edición de información
- Actualización de permisos

**Acciones disponibles:**
- Editar técnico

---

### Módulo: Usuarios (`tickets/modules/users/`)

#### `users.php`
**Funcionalidad Principal:** Gestión de usuarios del módulo de tickets.

**Características:**
- Listado de usuarios
- Permisos específicos
- Roles y acceso

**Acciones disponibles:**
- Ver usuarios
- Gestionar permisos

---

#### `new.php`
**Funcionalidad Principal:** Crear nuevo usuario.

**Características:**
- Formulario de creación
- Asignación de roles
- Configuración de permisos

**Acciones disponibles:**
- Crear usuario

---

### Módulo: Configuración (`tickets/modules/configuration/`)

#### `configuration.php`
**Funcionalidad Principal:** Configuración general del sistema de tickets.

**Características:**
- Configuración de categorías
- Configuración de estados
- Configuración de modos de contacto
- Ajustes generales

**Acciones disponibles:**
- Configurar categorías
- Configurar estados
- Configurar modos de contacto

---

#### `manage.php`
**Funcionalidad Principal:** Gestión avanzada de configuración.

**Características:**
- Configuraciones avanzadas
- Ajustes del sistema

**Acciones disponibles:**
- Gestionar configuraciones

---

#### `blacklist_domains.php`
**Funcionalidad Principal:** Gestión de dominios bloqueados.

**Características:**
- Lista de dominios bloqueados
- Agregar/eliminar dominios
- Prevención de spam

**Acciones disponibles:**
- Agregar dominio a blacklist
- Eliminar dominio de blacklist
- Ver lista de dominios bloqueados

---

### Módulo: Reportes (`tickets/modules/reports/`)

#### `reports.php`
**Funcionalidad Principal:** Generación de reportes de tickets.

**Características:**
- Reportes por período
- Reportes por técnico
- Reportes por estado
- Reportes por categoría
- Exportación a PDF/Excel
- Gráficas y estadísticas

**Acciones disponibles:**
- Generar reportes
- Exportar reportes
- Ver estadísticas

---

### Funcionalidades Adicionales

#### `get_emails.php`
**Funcionalidad Principal:** Obtener emails del servidor.

**Características:**
- Conexión IMAP
- Procesamiento de emails
- Creación automática de tickets desde emails

---

#### `outlook_emails.php`
**Funcionalidad Principal:** Procesamiento de emails de Outlook.

**Características:**
- Integración con Outlook
- Procesamiento automático
- Creación de tickets

---

#### `process_emails.php`
**Funcionalidad Principal:** Procesar emails recibidos.

**Características:**
- Análisis de emails
- Extracción de información
- Creación de tickets automáticos

---

#### `send_ticket_reply.php`
**Funcionalidad Principal:** Enviar respuesta a ticket por email.

**Características:**
- Envío de respuestas
- Notificaciones por email
- Integración con PHPMailer

---

#### `download_attachment.php` (raíz)
**Funcionalidad Principal:** Descargar archivos adjuntos.

**Características:**
- Descarga segura
- Validación de permisos

---

### Cron Jobs

#### `tickets/cron/fetch_emails.php`
**Funcionalidad Principal:** Script para obtener emails automáticamente.

**Características:**
- Ejecución programada
- Procesamiento automático de emails
- Creación de tickets automáticos

---

### Archivos de Configuración

#### `tickets/conexion/db.php`
**Funcionalidad Principal:** Conexión a base de datos de tickets.

---

#### `tickets/conexion/config_imap.php`
**Funcionalidad Principal:** Configuración IMAP para emails.

---

#### `tickets/conexion/config_mailer.php`
**Funcionalidad Principal:** Configuración de PHPMailer.

---

#### `tickets/conexion/db_remot.php`
**Funcionalidad Principal:** Conexión a base de datos remota.

---

### Archivos SQL

#### `tickets/conexion/secureitmxcom_tickets.sql`
**Funcionalidad Principal:** Volcado SQL del módulo de tickets para `grupo_gk`.

---

### Scripts de Mantenimiento

#### `tickets/setup_status_tracking_table.php`
**Funcionalidad Principal:** Configurar tabla de seguimiento de estados.

---

#### `tickets/notificar_tickets_antiguos.php`
**Funcionalidad Principal:** Notificar sobre tickets antiguos.

---

---

## Módulo de Inventario

### Ubicación: `/Inventario/`

### Descripción General
Sistema completo de gestión de inventario de equipos informáticos con reportes, cartas de responsiva, entrega y préstamo.

---

### `Inventario/crud/inicio.php`
**Funcionalidad Principal:** Dashboard principal del módulo de inventario.

**Características:**
- Gráfica de flujo de empresas (Chart.js)
- Visualización de cantidad de equipos por empresa
- Tarjetas de acceso rápido por empresa
- Estadísticas generales
- Diseño responsivo

**Acciones disponibles:**
- Ver estadísticas por empresa
- Acceder a equipos de una empresa específica
- Ver gráficas de distribución

---

### Gestión de Equipos

#### `visualizar-equipos.php`
**Funcionalidad Principal:** Visualización y gestión de equipos.

**Características:**
- Listado completo de equipos
- Filtros disponibles:
  - Por cliente/empresa
  - Por fecha de registro (desde/hasta)
  - Búsqueda general (nombre, MAC, SO, tipo, fabricante, modelo, serie, observaciones)
- Información mostrada:
  - Nombre del equipo
  - Cliente
  - Responsable
  - Sistema operativo
  - Dirección MAC
  - Tipo de equipo
  - Fabricante
  - Modelo
  - Número de serie
  - Observaciones
  - Fecha de registro
- Enlaces a acciones:
  - Ver detalles
  - Editar equipo
  - Eliminar equipo
- Paginación de resultados
- Exportación a Excel

**Acciones disponibles:**
- Ver lista de equipos
- Buscar equipos
- Filtrar por cliente
- Filtrar por fecha
- Ver detalles de equipo
- Editar equipo
- Eliminar equipo
- Exportar a Excel

---

#### `agregar-equipo.php`
**Funcionalidad Principal:** Agregar nuevo equipo al inventario.

**Características:**
- Formulario completo de registro
- Campos disponibles:
  - Nombre del equipo
  - Cliente/Empresa
  - Responsable
  - Sistema operativo
  - Dirección MAC
  - Tipo de equipo
  - Fabricante
  - Modelo
  - Número de serie
  - Observaciones
- Validación de campos requeridos
- Asociación con empresa
- Registro de actividad en historial

**Acciones disponibles:**
- Crear nuevo equipo
- Asignar a empresa
- Registrar información completa

---

#### `editar-equipo.php`
**Funcionalidad Principal:** Editar equipo existente.

**Características:**
- Edición de toda la información del equipo
- Actualización de datos
- Registro de cambios en historial
- Validación de datos

**Acciones disponibles:**
- Editar información del equipo
- Actualizar datos
- Guardar cambios

---

#### `actualizar-equipo.php`
**Funcionalidad Principal:** Actualización rápida de equipo.

**Características:**
- Actualización de campos específicos
- Interfaz simplificada

**Acciones disponibles:**
- Actualizar equipo

---

#### `importar-equipos.php`
**Funcionalidad Principal:** Importación masiva de equipos desde archivo CSV.

**Características:**
- Carga de archivo CSV
- Validación de formato
- Procesamiento masivo
- Manejo de errores
- Reporte de importación

**Acciones disponibles:**
- Importar equipos desde CSV
- Validar formato
- Procesar importación masiva

---

#### `importar.php`
**Funcionalidad Principal:** Procesamiento de importación.

**Características:**
- Procesamiento del archivo
- Validación de datos
- Inserción en base de datos

---

### Gestión de Empresas

#### `visualizar-empresas.php`
**Funcionalidad Principal:** Visualización de empresas.

**Características:**
- Listado de empresas
- Información de cada empresa
- Cantidad de equipos por empresa
- Enlaces a equipos de la empresa

**Acciones disponibles:**
- Ver lista de empresas
- Ver equipos de empresa
- Acceder a detalles

---

#### `agregar-empresa.php`
**Funcionalidad Principal:** Agregar nueva empresa.

**Características:**
- Formulario de creación
- Información de la empresa
- Validación de datos

**Acciones disponibles:**
- Crear empresa

---

#### `editar-empresa.php`
**Funcionalidad Principal:** Editar empresa existente.

**Características:**
- Edición de información
- Actualización de datos

**Acciones disponibles:**
- Editar empresa

---

### Gestión de Usuarios del Módulo

#### `agregar-usuario.php`
**Funcionalidad Principal:** Agregar usuario al módulo de inventario.

**Características:**
- Creación de usuario
- Asignación de permisos
- Configuración de acceso

**Acciones disponibles:**
- Crear usuario

---

#### `editar-usuario.php`
**Funcionalidad Principal:** Editar usuario del módulo.

**Características:**
- Edición de información
- Actualización de permisos

**Acciones disponibles:**
- Editar usuario

---

#### `gestion-usuarios.php`
**Funcionalidad Principal:** Gestión completa de usuarios del módulo.

**Características:**
- Listado de usuarios
- Gestión de permisos
- Roles y acceso

**Acciones disponibles:**
- Ver usuarios
- Gestionar permisos
- Editar usuarios

---

### Reportes y Documentos

#### `generar_reporte.php`
**Funcionalidad Principal:** Generación de reportes en PDF.

**Características:**
- Generación de reportes PDF usando TCPDF
- Tipos de reportes:
  - Carta de responsiva
  - Carta de entrega
  - Carta de préstamo
- Plantillas personalizadas
- Encabezados y pies de página
- Numeración automática de documentos

**Acciones disponibles:**
- Generar carta de responsiva
- Generar carta de entrega
- Generar carta de préstamo
- Descargar PDF

---

#### `reporte.php`
**Funcionalidad Principal:** Interfaz para seleccionar tipo de reporte.

**Características:**
- Selección de tipo de reporte
- Formulario de datos
- Generación de documento

**Acciones disponibles:**
- Seleccionar tipo de reporte
- Llenar formulario
- Generar documento

---

#### `ver-documento.php`
**Funcionalidad Principal:** Visualización de documentos generados.

**Características:**
- Visualización de PDF
- Descarga de documento
- Historial de documentos

**Acciones disponibles:**
- Ver documento
- Descargar documento

---

#### `exportar_excel.php`
**Funcionalidad Principal:** Exportación de datos a Excel.

**Características:**
- Exportación de equipos
- Exportación de empresas
- Formato Excel
- Descarga directa

**Acciones disponibles:**
- Exportar equipos a Excel
- Exportar empresas a Excel
- Descargar archivo

---

### Historial y Actividades

#### `historial-actividades.php`
**Funcionalidad Principal:** Historial completo de actividades del módulo.

**Características:**
- Registro de todas las acciones
- Información mostrada:
  - Usuario que realizó la acción
  - Acción realizada
  - Tabla afectada
  - ID del registro
  - Detalles adicionales
  - Fecha y hora
- Filtros por:
  - Usuario
  - Tipo de acción
  - Tabla afectada
  - Fecha
- Ordenamiento por fecha descendente

**Acciones disponibles:**
- Ver historial completo
- Filtrar actividades
- Buscar actividades específicas

---

### API REST

#### `Inventario/crud/api/equipos.php`
**Funcionalidad Principal:** API REST para gestión de equipos.

**Características:**
- Endpoints CRUD
- Formato JSON
- Autenticación requerida

**Acciones disponibles:**
- GET: Obtener equipos
- POST: Crear equipo
- PUT: Actualizar equipo
- DELETE: Eliminar equipo

---

#### `Inventario/crud/api/empresas.php`
**Funcionalidad Principal:** API REST para gestión de empresas.

**Características:**
- Endpoints CRUD
- Formato JSON

**Acciones disponibles:**
- GET: Obtener empresas
- POST: Crear empresa
- PUT: Actualizar empresa
- DELETE: Eliminar empresa

---

#### `Inventario/crud/api/usuarios.php`
**Funcionalidad Principal:** API REST para gestión de usuarios.

**Características:**
- Endpoints CRUD
- Formato JSON

**Acciones disponibles:**
- GET: Obtener usuarios
- POST: Crear usuario
- PUT: Actualizar usuario
- DELETE: Eliminar usuario

---

#### `Inventario/crud/api/productos.php`
**Funcionalidad Principal:** API REST para productos.

**Características:**
- Endpoints CRUD
- Formato JSON

**Acciones disponibles:**
- GET: Obtener productos
- POST: Crear producto
- PUT: Actualizar producto
- DELETE: Eliminar producto

---

### Archivos de Plantillas de Reportes

#### `Inventario/crud/Reportes/Plantilla_carta_responsiva.php`
**Funcionalidad Principal:** Plantilla para carta de responsiva.

---

#### `Inventario/crud/Reportes/Plantilla_carta_entrega.php`
**Funcionalidad Principal:** Plantilla para carta de entrega.

---

#### `Inventario/crud/Reportes/Plantilla_carta_prestamo.php`
**Funcionalidad Principal:** Plantilla para carta de préstamo.

---

#### `Inventario/crud/Reportes/formulario_carta_responsiva.php`
**Funcionalidad Principal:** Formulario para generar carta de responsiva.

---

### Archivos de Configuración

#### `Inventario/crud/includes/db.php`
**Funcionalidad Principal:** Conexión a base de datos.

---

#### `Inventario/crud/includes/header.php`
**Funcionalidad Principal:** Header común del módulo.

---

#### `Inventario/crud/includes/footer.php`
**Funcionalidad Principal:** Footer común del módulo.

---

#### `Inventario/crud/includes/GoogleAuthenticator.php`
**Funcionalidad Principal:** Librería para autenticación de dos factores.

---

### Archivos de Base de Datos

#### `Inventario/db/secureitmxcom_prueba.sql`
**Funcionalidad Principal:** Script SQL de la base de datos de inventario.

---

---

## Módulo de Almacén

### Ubicación: `/almacen/`

### Descripción General
Sistema completo de gestión de almacén con control de bodegas, almacenes, productos y cartas de entrega.

---

### `almacen/index.php`
**Funcionalidad Principal:** Redirige al módulo de inicio.

---

### `almacen/modules/inicio/inicio.php`
**Funcionalidad Principal:** Dashboard principal del módulo de almacén.

**Características:**
- Estadísticas generales:
  - Total de bodegas activas
  - Total de almacenes operativos
  - Total de productos disponibles
  - Cartas de entrega pendientes
  - Productos con stock bajo
  - Valor total del inventario
- Actividad reciente:
  - Últimos movimientos
  - Productos más solicitados
  - Almacenes por capacidad
- Tarjetas de acceso rápido
- Gráficas y visualizaciones
- Diseño moderno y responsivo

**Acciones disponibles:**
- Ver estadísticas generales
- Ver actividad reciente
- Acceder a módulos específicos
- Ver alertas de stock bajo

---

### Gestión de Bodegas (`almacen/modules/bodegas/`)

#### `bodegas.php`
**Funcionalidad Principal:** Gestión completa de bodegas.

**Características:**
- Listado de bodegas
- Información de cada bodega:
  - Nombre
  - Dirección
  - Estado (Activa/Inactiva)
  - Capacidad
  - Almacenes asociados
- Crear nueva bodega
- Editar bodega existente
- Cambiar estado de bodega
- Ver almacenes de la bodega
- Búsqueda y filtrado

**Acciones disponibles:**
- Ver lista de bodegas
- Crear bodega
- Editar bodega
- Activar/Desactivar bodega
- Ver almacenes de bodega
- Buscar bodega

---

### Gestión de Almacenes (`almacen/modules/almacenes/`)

#### `almacenes.php`
**Funcionalidad Principal:** Gestión completa de almacenes.

**Características:**
- Listado de almacenes
- Información de cada almacén:
  - Nombre
  - Bodega asociada
  - Capacidad total
  - Capacidad actual
  - Porcentaje de ocupación
  - Estado (Disponible/Ocupado/Mantenimiento)
- Crear nuevo almacén
- Editar almacén existente
- Cambiar estado de almacén
- Ver productos del almacén
- Control de capacidad
- Búsqueda y filtrado

**Acciones disponibles:**
- Ver lista de almacenes
- Crear almacén
- Editar almacén
- Cambiar estado
- Ver productos
- Controlar capacidad
- Buscar almacén

---

### Gestión de Productos (`almacen/modules/productos/`)

#### `productos.php`
**Funcionalidad Principal:** Gestión completa de productos del almacén.

**Características:**
- Listado de productos
- Información de cada producto:
  - Código de producto
  - Nombre
  - Descripción
  - Almacén donde se encuentra
  - Cantidad disponible
  - Stock mínimo
  - Precio unitario
  - Estado (Disponible/Agotado/Reservado)
- Crear nuevo producto
- Editar producto existente
- Actualizar stock
- Control de stock mínimo
- Alertas de stock bajo
- Búsqueda y filtrado
- Códigos de barras

**Acciones disponibles:**
- Ver lista de productos
- Crear producto
- Editar producto
- Actualizar stock
- Ver alertas de stock bajo
- Buscar producto
- Generar código de barras

---

### Gestión de Cartas (`almacen/modules/cartas/`)

#### `cartas-entrega.php`
**Funcionalidad Principal:** Gestión de cartas de entrega.

**Características:**
- Listado de cartas de entrega
- Información de cada carta:
  - Número de carta
  - Fecha de generación
  - Estado (Pendiente/Entregada/Cancelada)
  - Productos incluidos
  - Receptor
- Crear nueva carta de entrega
- Ver carta en PDF
- Cambiar estado de entrega
- Búsqueda y filtrado

**Acciones disponibles:**
- Ver lista de cartas
- Crear carta de entrega
- Ver carta en PDF
- Cambiar estado
- Buscar carta

---

#### `generar-carta.php`
**Funcionalidad Principal:** Generar nueva carta de entrega.

**Características:**
- Formulario para crear carta
- Selección de productos
- Información del receptor
- Fecha de entrega
- Observaciones
- Generación automática de número

**Acciones disponibles:**
- Crear carta
- Seleccionar productos
- Generar PDF

---

#### `procesar-carta.php`
**Funcionalidad Principal:** Procesar creación de carta.

**Características:**
- Validación de datos
- Creación de registro
- Actualización de stock
- Generación de PDF

---

#### `ver-carta-pdf.php`
**Funcionalidad Principal:** Visualizar carta de entrega en PDF.

**Características:**
- Visualización de PDF
- Descarga de documento
- Impresión

**Acciones disponibles:**
- Ver PDF
- Descargar PDF
- Imprimir carta

---

#### `acciones-carta.php`
**Funcionalidad Principal:** Acciones sobre cartas (aprobar, cancelar, etc.).

**Características:**
- Aprobar carta
- Cancelar carta
- Marcar como entregada
- Actualización de estados

**Acciones disponibles:**
- Aprobar carta
- Cancelar carta
- Marcar como entregada

---

### Configuración (`almacen/modules/configuracion/`)

#### `configuracion.php`
**Funcionalidad Principal:** Configuración del módulo de almacén.

**Características:**
- Configuración general
- Parámetros del sistema
- Ajustes de stock mínimo
- Configuración de numeración
- Ajustes de reportes

**Acciones disponibles:**
- Configurar parámetros
- Ajustar stock mínimo
- Configurar numeración

---

### Historial (`almacen/modules/historial/`)

#### `historial.php`
**Funcionalidad Principal:** Historial de movimientos del almacén.

**Características:**
- Registro de todos los movimientos
- Información mostrada:
  - Tipo de movimiento (Entrada/Salida)
  - Producto
  - Cantidad
  - Almacén
  - Usuario que realizó el movimiento
  - Fecha y hora
  - Motivo
- Filtros por:
  - Tipo de movimiento
  - Producto
  - Almacén
  - Fecha
  - Usuario
- Ordenamiento por fecha descendente

**Acciones disponibles:**
- Ver historial completo
- Filtrar movimientos
- Buscar movimientos específicos
- Exportar historial

---

### Reportes (`almacen/modules/reportes/`)

#### `reportes.php`
**Funcionalidad Principal:** Generación de reportes del almacén.

**Características:**
- Reportes disponibles:
  - Reporte de inventario
  - Reporte de movimientos
  - Reporte de stock bajo
  - Reporte de cartas de entrega
  - Reporte de valor de inventario
- Filtros por período
- Exportación a PDF/Excel
- Gráficas y estadísticas

**Acciones disponibles:**
- Generar reportes
- Exportar reportes
- Ver estadísticas
- Filtrar por período

---

### Archivos de Configuración

#### `almacen/includes/db.php`
**Funcionalidad Principal:** Conexión a base de datos del almacén.

---

#### `almacen/includes/header.php`
**Funcionalidad Principal:** Header común del módulo.

---

#### `almacen/includes/footer.php`
**Funcionalidad Principal:** Footer común del módulo.

---

#### `almacen/includes/get_catalogos.php`
**Funcionalidad Principal:** Obtención de catálogos (tipos, categorías, etc.).

---

### Archivos de Base de Datos

#### `almacen/db/almacen.sql`
**Funcionalidad Principal:** Script SQL de la base de datos del almacén.

---

#### `almacen/db/catalogos.sql`
**Funcionalidad Principal:** Script SQL de catálogos del almacén.

---

### Documentación

#### `almacen/README.md`
**Funcionalidad Principal:** Documentación del módulo de almacén.

---

#### `almacen/NUEVA_ESTRUCTURA_MODULOS.md`
**Funcionalidad Principal:** Documentación de la nueva estructura de módulos.

---

#### `almacen/INSTRUCCIONES_CONFIGURACION.md`
**Funcionalidad Principal:** Instrucciones de configuración del módulo.

---

#### `almacen/estilo.md`
**Funcionalidad Principal:** Guía de estilos del módulo.

---

## Resumen de Funcionalidades por Módulo

### Módulo de Tickets
- ✅ Gestión completa de tickets
- ✅ Asignación de técnicos
- ✅ Gestión de solicitantes
- ✅ Integración con correo electrónico
- ✅ Archivos adjuntos
- ✅ Gestión de costos
- ✅ Historial de cambios
- ✅ Reportes y estadísticas
- ✅ Configuración de categorías y estados
- ✅ Blacklist de dominios

### Módulo de Inventario
- ✅ Gestión de equipos informáticos
- ✅ Gestión de empresas
- ✅ Búsqueda y filtrado avanzado
- ✅ Importación masiva desde CSV
- ✅ Generación de reportes PDF (cartas de responsiva, entrega, préstamo)
- ✅ Exportación a Excel
- ✅ Historial completo de actividades
- ✅ API REST para integraciones
- ✅ Dashboard con gráficas

### Módulo de Almacén
- ✅ Gestión de bodegas
- ✅ Gestión de almacenes
- ✅ Gestión de productos
- ✅ Control de stock
- ✅ Alertas de stock bajo
- ✅ Cartas de entrega
- ✅ Historial de movimientos
- ✅ Reportes y estadísticas
- ✅ Dashboard con métricas

### Sistema General
- ✅ Autenticación con 2FA
- ✅ Control de acceso por roles
- ✅ Permisos granulares
- ✅ Gestión de usuarios
- ✅ Historial de actividades
- ✅ Configuración de rutas por rol
- ✅ Perfil de usuario
- ✅ Sistema de logging

---

**Fecha de creación del reporte:** 2025-01-27
**Versión del sistema:** 1.0
