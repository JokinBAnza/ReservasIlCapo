# Despliegue de reservas.ilcapo.net — guía paso a paso

## Qué hay en esta carpeta

| Archivo | Qué es |
|---|---|
| `reservas-ilcapo.zip` | La aplicación completa, lista para subir |
| `reservas.sql` | La base de datos (estructura + las 44 mesas + usuario del personal) |
| `env.produccion` | La configuración de producción (ya viaja dentro del ZIP como `.env`) |
| `htaccess-raiz.txt` | Solo necesario si el subdominio no puede apuntar a `/public` |
| `crear-paquete.ps1` | Regenera el ZIP si la aplicación cambia (necesita Docker encendido) |

## Lo que tiene que dar la persona del hosting (Arsys)

1. Subdominio `reservas.ilcapo.net` creado, apuntando (si se puede) a la subcarpeta `public` de su carpeta.
2. Una base de datos MySQL nueva: **host, nombre, usuario y contraseña**.
3. Un acceso **FTP o SFTP** a la carpeta del subdominio.
4. Confirmación de que el PHP del plan es **8.2 o superior**.

## Pasos del despliegue

### 1. Crear la cuenta de email (gratis, 10 minutos)

1. Crear una cuenta en **brevo.com** (plan gratuito: 300 emails/día, de sobra).
2. En el menú: **SMTP & API → SMTP** → copiar el usuario y la clave SMTP.
3. Para que los emails no caigan en spam, en Brevo → **Senders & Domains** añadir el dominio `ilcapo.net` y seguir sus instrucciones (la persona de Arsys tendrá que añadir 2-3 registros DNS que Brevo indica).

### 2. Rellenar la configuración

Editar el archivo `.env` (está dentro del ZIP, o editarlo después de subir, vía FTP).
Buscar las líneas con `RELLENAR` y completar:

- `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` → los datos de la base de datos de Arsys
- `MAIL_USERNAME`, `MAIL_PASSWORD` → los datos SMTP de Brevo

**Ojo**: `.env` es un archivo oculto. En FileZilla: Servidor → "Forzar mostrar archivos ocultos".

### 3. Subir la aplicación

1. Conectar por FTP con FileZilla (los datos que dé la persona de Arsys).
2. Subir el **contenido descomprimido** del ZIP a la carpeta del subdominio
   (o subir el ZIP y descomprimirlo desde el administrador de archivos del panel, si lo tiene).
3. Si el subdominio **no** pudo apuntar a `/public`: subir también `htaccess-raiz.txt`
   a la raíz de la carpeta, renombrado exactamente a `.htaccess`.

### 4. Importar la base de datos

1. Entrar en el **phpMyAdmin** del panel de Arsys.
2. Seleccionar la base de datos nueva → pestaña **Importar** → elegir `reservas.sql` → Continuar.
3. Debe crear las tablas, las 44 mesas y el usuario del personal.

### 5. Probar (en este orden)

1. Abrir `https://reservas.ilcapo.net` → debe verse el formulario de reservas.
2. Hacer una reserva de prueba con un email real → debe llegar el email de confirmación.
3. Probar el botón "Anular mi reserva" del email.
4. Entrar en `https://reservas.ilcapo.net/entrar` con:
   - Usuario: `personal@ilcapo.net`
   - Contraseña inicial: `JENPwSkP6B3Y5gM5`
5. **Inmediatamente**: menú "Contraseña" → cambiarla por una vuestra.
6. Comprobar que la reserva de prueba aparece en el listado y anularla.

### 6. Conectar con la web principal

Pedir a la persona del WordPress que añada un botón/entrada de menú
**"Reservar"** apuntando a `https://reservas.ilcapo.net`.

Y configurar la respuesta automática de WhatsApp Business con el mismo enlace.

## Después de publicar

- **Copias de seguridad**: una vez por semana, phpMyAdmin → Exportar → guardar el
  archivo. Arsys suele incluir copias automáticas del hosting completo — preguntar
  a la persona del hosting para confirmarlo.
- **RGPD**: el formulario recoge datos personales (nombre, teléfono, email). Conviene
  añadir una línea de aviso de privacidad en el formulario y en la web. Pendiente.
- **Cambios futuros en la app**: tras cualquier cambio en el código, ejecutar
  `crear-paquete.ps1` y volver a subir por FTP (el `.env` del servidor no hay que
  tocarlo: al regenerar el ZIP, no machacar el `.env` ya configurado del servidor).

## Horarios y límites (recordatorio)

Todo se ajusta en `config/reservas.php` (dentro del ZIP / del servidor):
turnos y horas, duración de la reserva (2 h), máximo por franja (10),
máximo por teléfono (2) y combinaciones de mesas contiguas.
