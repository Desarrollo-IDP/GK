# Resumen de cambios — rama `whatsapp` y trabajo local

**Fecha del documento:** 31 de julio de 2026  
**Rama actual:** `whatsapp`  
**Comparación con:** `main`  
**Estado:** incluye commits de la rama **y** cambios locales aún no commiteados en el working tree.

---

## 1. Resumen ejecutivo

En la rama `whatsapp` se integró el canal de mensajería WhatsApp (Meta Cloud API) con el módulo de tickets: webhook, creación/vinculación de tickets, chat en vivo en la vista del ticket y adjuntos.

Además, en el trabajo local (sin commit) se añadieron mejoras de UI del módulo tickets, conversación por correo unificada estilo chat, panel de notificaciones con lecturas en servidor, y varios ajustes de correo/configuración.

---

## 2. Cambios ya commiteados en `whatsapp` (vs `main`)

Commits exclusivos de `whatsapp` (no están en `main`):

| Commit | Descripción |
|--------|-------------|
| `a872b25` | Integración WhatsApp ↔ tickets, webhook y chat en vivo |
| `4ce2ab3` | Mejoras del chat: adjuntos, lightbox y lista en vivo |

### 2.1 Módulo `whatsapp/` (nuevo)

- Configuración vía `whatsapp/.env` (tokens Meta, verify token, empresa por defecto).
- Webhook (`webhook.php`) para mensajes entrantes.
- Cliente Graph API (`includes/meta_api.php`).
- Almacenamiento de mensajes/chat (`message_store.php`, `storage/`).
- UI de chat de prueba (`chat.php`, `css/chat.css`, `js/chat.js`).
- Documentación: `whatsapp/INTEGRACION_TICKETS.md`.
- Scripts de diagnóstico/prueba (`test_webhook.php`, `diagnose_phone.php`, etc.).

### 2.2 Puente tickets ↔ WhatsApp

- `tickets/includes/whatsapp_ticket_bridge.php`: solicitantes por teléfono, crear/vincular ticket, insertar mensajes, enviar desde técnico, notificar cierre.
- `tickets/modules/tickets/whatsapp_api.php`: API JSON para envío y polling desde la vista.
- SQL: `tickets/sql/alter_ticket_messages_add_whatsapp.sql` (columnas `is_whatsapp`, etc.).
- CSS/JS del chat en ticket: `ticket-whatsapp.css`, `ticket-whatsapp.js`.

### 2.3 Cambios en pantallas de tickets (commit)

- `view.php`: panel de conversación por mensajería + switch de canal.
- `tickets.php` / `tickets_active_list.php`: listado activo refactorizado / API de tabla.
- `edit.php`: includes del bridge WhatsApp.

**Volumen aproximado del diff `main...whatsapp` (commiteado):** ~36 archivos, +6736 / −199 líneas.

---

## 3. Cambios locales pendientes de commit (working tree)

Estos cambios están en el disco sobre la rama `whatsapp`, pero **aún no están en git** (ni en `main` ni en el remoto, salvo que se suban después).

### 3.1 Tema visual / UI del módulo tickets

- Nuevo `tickets/modules/tickets/css/tickets-theme.css` (tema profesional).
- Nuevo `tickets/modules/tickets/css/tickets-responsive.css` (costos en móvil, layouts).
- Aplicación de estilos/paneles en: tickets, configuration, empresas, reports, requester, technicians, task.
- Mejoras en modales de `manage.php`, paneles de `edit.php` / `new.php`, headers de página.

### 3.2 Conversación por correo (estilo chat, como WhatsApp)

- Helper: `tickets/includes/ticket_email_conversation_helper.php`.
- API polling: `tickets/modules/tickets/api/ticket_email_messages.php`.
- JS: `tickets/modules/tickets/js/ticket-email.js`.
- En `view.php`: **una sola tarjeta** con hilo de mensajes + compositor abajo (ya no dos cards separadas: conversación + “Responder por correo”).
- Estilos alineados al chat de mensajería en `ticket-whatsapp.css` / `tickets-theme.css`.

### 3.3 Notificaciones en el header

- Icono de campana en `tickets/includes/modern-header.php`.
- Panel lateral derecho: CSS `tickets/css/notifications-panel.css`, JS `tickets/js/notifications-panel.js`.
- API: `tickets/includes/api/notifications.php`.
- Solo mensajes entrantes (correo/WhatsApp) de **tickets abiertos** (`closed_at IS NULL`).
- Lecturas **persistidas en BD** por usuario (`ticket_notification_reads`; SQL en `tickets/sql/create_ticket_notification_reads.sql`; la tabla se puede auto-crear desde la API).
- Acciones: marcar una, marcar todas, marcar al abrir el ticket, badge con conteo real.
- Migración única desde lecturas antiguas en `localStorage`.

### 3.4 Correo / configuración / otros

- Ajustes en `config_mailer.php`, `config_imap.php`, `get_empresa_email_config.php`, `empresa_email_config.php`.
- `outlook_emails.php`, `send_ticket_reply.php`.
- Cambios menores en `index.php`, `forgot-password.php`.
- Refino del bridge WhatsApp y del JS/CSS del chat.

**Archivos nuevos (untracked) relevantes:**

- `tickets/css/notifications-panel.css`
- `tickets/js/notifications-panel.js`
- `tickets/includes/api/notifications.php`
- `tickets/includes/ticket_email_conversation_helper.php`
- `tickets/modules/tickets/api/ticket_email_messages.php`
- `tickets/modules/tickets/css/tickets-theme.css`
- `tickets/modules/tickets/css/tickets-responsive.css`
- `tickets/modules/tickets/js/ticket-email.js`
- `tickets/sql/create_ticket_notification_reads.sql`

---

## 4. Comparativa `main` vs `whatsapp`

### 4.1 Divergencia actual

| Dirección | Commits | Contenido principal |
|-----------|---------|---------------------|
| Solo en `whatsapp` | **2** | Integración WhatsApp + mejoras de chat |
| Solo en `main` | **3** | Notificaciones email de tareas; updates de reportes entrega/responsiva/memoria técnica e Inventario |

Punto de ancestro común (merge-base): `8b6f7bd`.

### 4.2 Qué trae `whatsapp` que `main` no tiene

- Carpeta completa `whatsapp/`.
- Bridge y APIs de tickets para WhatsApp.
- UI de chat en `view.php` / listados relacionados.
- Dependencia de columnas `is_whatsapp` (y schema relacionado) en `ticket_messages`.

### 4.3 Qué trae `main` que `whatsapp` no tiene

- `tickets/includes/task_mailer_helper.php` + hooks en `task/new.php` y `process_subtask.php` (email al asignar tarea/subtarea).
- Cambios en plantillas/contadores de Inventario (entrega, responsiva, memoria técnica, logs/debug).

### 4.4 Solapamiento de archivos (riesgo de conflicto al fusionar)

En el diff **commiteado** `main...whatsapp`, los archivos de tickets más sensibles son:

- `tickets/modules/tickets/view.php`
- `tickets/modules/tickets/tickets.php`
- `tickets/modules/tickets/edit.php`
- `tickets/includes/tickets_active_list.php`

Los 3 commits solo-de-`main` **no tocan** esos archivos de tickets (tocan task mailer e Inventario). Por eso un `merge`/`rebase` de **código ya commiteado** entre `main` y `whatsapp` debería ser **relativamente limpio** (sin conflictos obvios en la simulación `merge-tree`).

**Importante:** el working tree local modifica muchas pantallas (`view.php`, `edit.php`, `new.php`, `manage.php`, etc.). Si se fusiona sin commitear o stash, habrá fricción operativa aunque el merge de commits sea limpio.

---

## 5. Si fusiono `main` y `whatsapp`: problemas posibles

### 5.1 Riesgos técnicos / de merge

1. **Working tree sucio:** conviene commitear o hacer stash de los cambios locales (UI, notificaciones, email chat) **antes** de mergear, para no mezclar conflictos de merge con cambios sin rastrear.
2. **`view.php` / `tickets.php`:** si en el futuro `main` también evoluciona esas pantallas, el conflicto será alto; hoy el riesgo entre los commits actuales es bajo.
3. **Schema BD:** tras fusionar hacia producción, hay que aplicar:
   - `alter_ticket_messages_add_whatsapp.sql` (si no está).
   - Tabla `ticket_notification_reads` (si se llevan las notificaciones).
4. **Dependencias de archivos:** `view.php` / helpers de email hacen `require` del bridge WhatsApp. Si alguien despliega solo parte de la rama sin la carpeta `whatsapp/`, **sí puede romper** la vista de tickets.
5. **Doble canal en la UI:** tickets WhatsApp muestran switch Mensajería/Correo; hay que probar ambos flujos tras el merge.
6. **Commits de Inventario/tareas en `main`:** no deberían chocar con WhatsApp, pero hay que verificar que el merge los incorpore (no perder notificaciones de tareas ni plantillas PDF).

### 5.2 Orden recomendado al fusionar

1. Commitear (o stash) el trabajo local de UI/notificaciones/correo.
2. En `whatsapp`: `git fetch` + merge (o rebase) de `main` → resolver lo que salga → probar tickets + task + reportes.
3. Aplicar SQL de WhatsApp (y notificaciones si aplica).
4. Configurar `whatsapp/.env` **solo si** se va a usar el canal; ver sección 6.
5. Mergear a `main` cuando esté estable, o abrir PR `whatsapp` → `main`.

### 5.3 Checklist de prueba post-merge

- [ ] Listado de tickets abiertos / cerrados.
- [ ] Crear / editar / ver ticket **sin** WhatsApp (solo correo).
- [ ] Ticket con canal WhatsApp: chat, adjuntos, polling.
- [ ] Respuesta por correo (compositor unificado).
- [ ] Campana de notificaciones (abrir ticket, marcar leídas).
- [ ] Asignación de tarea/subtarea (email de `main`).
- [ ] Generación de reportes Inventario (si aplica el entorno).

---

## 6. ¿Qué pasa si **no** configuro la API de WhatsApp?

### 6.1 Respuesta corta

**No se rompe el flujo general de tickets (correo, CRUD, listados, tareas).**  
WhatsApp queda **desactivado de facto**: no entran mensajes por webhook útil, y no se pueden enviar respuestas por Meta. El resto del sistema sigue operando.

### 6.2 Comportamiento detallado

| Área | Sin `.env` / sin tokens | ¿Rompe el sistema? |
|------|-------------------------|--------------------|
| Cargar `view.php` / `edit.php` | Incluyen el bridge; el bridge carga `whatsapp/includes/*`. **No requieren** tokens para renderizar. | No |
| Ticket normal (correo) | Se muestra conversación por correo; sin switch de mensajería (si no es ticket WA). | No |
| Ticket detectado como WhatsApp | Se muestra el UI de chat; al **enviar**, la API Meta responde error tipo *«WhatsApp no configurado»* / rechazo de envío. | No rompe la página; falla solo el envío WA |
| Webhook Meta | Sin `verify_token` / secrets, Meta no validará el webhook; no se crearán tickets desde WA. | No afecta tickets existentes |
| Cierre de ticket + aviso WA | `ticketsWhatsappNotifyTicketClosed()` hace `return` inmediato si `!whatsappIsConfigured()`. | No |
| Chat de prueba `whatsapp/chat.php` / `api/chat.php` | El `send` lanza error claro pidiendo configurar `.env`. | Solo ese módulo |
| Notificaciones del header | Siguen funcionando con mensajes de correo (y WA si hubiera histórico en BD). | No |
| Correo SMTP / IMAP | Independiente de WhatsApp. | No |

Función de control:

```php
function whatsappIsConfigured(): bool
{
    // Requiere access_token + phone_number_id + verify_token
}
```

El envío de texto también valida tokens y, si faltan, devuelve:

`['ok' => false, 'error' => 'WhatsApp no configurado']`.

### 6.3 Condiciones en las que **sí** habría problema

1. **Desplegar código de la rama sin la carpeta `whatsapp/`** → fatales por `require` del bridge en `view.php` / helpers.
2. **No correr el ALTER de columnas `is_whatsapp`** y luego usar lógica que asume la columna en inserts → errores SQL en flujos WA (el bridge intenta asegurar schema en varios caminos, pero conviene aplicar el SQL oficial).
3. **Marcar tickets como WhatsApp / modo de contacto WhatsApp** en producción sin API → la UI de chat aparece, pero los técnicos verán fallos al enviar (ruido operativo, no caída del sitio).
4. **Webhook público mal configurado** (URL expuesta sin verify) → riesgo de seguridad/ruido en logs; no es necesario exponerlo si no usas WA.

### 6.4 Recomendación práctica

- Si **aún no** van a usar WhatsApp en ese entorno: pueden mergear/desplegar la rama, **omitir** `whatsapp/.env`, y operar solo por correo. Eviten asignar modo de contacto WhatsApp a tickets reales hasta configurar Meta.
- Si **sí** van a usarlo: copiar `whatsapp/.env.example` → `.env`, llenar tokens, configurar webhook en Meta, definir `WHATSAPP_DEFAULT_EMPRESA_ID` (y opcionalmente `WHATSAPP_CONTACT_MODE_ID`), aplicar SQL, probar con `whatsapp/status.php` / suite de tests.

---

## 7. Mapa rápido de archivos clave

```
whatsapp/                          # Módulo Meta Cloud API
tickets/includes/whatsapp_ticket_bridge.php
tickets/modules/tickets/whatsapp_api.php
tickets/modules/tickets/view.php   # Chat WA + correo unificado (+ cambios locales)
tickets/includes/modern-header.php # Campana de notificaciones
tickets/includes/api/notifications.php
tickets/includes/ticket_email_conversation_helper.php
tickets/sql/alter_ticket_messages_add_whatsapp.sql
tickets/sql/create_ticket_notification_reads.sql
```

---

## 8. Conclusión

- La rama `whatsapp` aporta un canal nuevo **opcional** respecto a `main`.
- Fusionar `main` ↔ `whatsapp` con los commits actuales debería ser manejable; el mayor cuidado es el **trabajo local sin commit** y el **schema/config** en el entorno.
- **No configurar la API de WhatsApp no rompe el flujo de tickets por correo**; solo desactiva envío/recepción por Meta. Lo que sí puede romper es desplegar el código incompleto (sin carpeta `whatsapp/`) o usar el canal WA en UI sin tokens.

---

*Documento generado a partir del estado del repositorio local (`whatsapp` vs `main`) y del working tree al 31/07/2026.*
