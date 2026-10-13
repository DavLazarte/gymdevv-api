# Fase 0 – Reconocimiento e Inventario Técnico

> **Ramas creadas antes del análisis**: `feat/multitenant` creada y activa en `devgym` y `cross-fit-coach-app`. En `gymdevv-api` se trabaja sobre el directorio raíz según lo acordado.
> **Fuente de verdad del esquema**: `gymdevv-api/docs/schema/gym_coach_multitenant_v3.sql` (MySQL 8.0+, `utf8mb4_unicode_ci`, 21 tablas).

---

## A. `devgym` (Frontend Base – Gestión de Gimnasio)

### A.1. Stack, Librerías de UI, Estilos y Variables de Entorno
- **Framework**: Next.js `16.0.10`, React `19.2.0`, TypeScript `^5` (`devgym/package.json`).
- **Estilos y Temas**: Tailwind CSS v4 (`@tailwindcss/postcss`, `tw-animate-css`). En `devgym/app/globals.css:6-120` define variables CSS en espacio de color `oklch(...)` para `:root` y `.dark` (`--background`, `--foreground`, `--primary`, `--secondary`, `--accent`, `--destructive`, `--sidebar-*`, `--chart-1..5`, `--success`, `--warning`). Actualmente el color primario está fijo en coral/naranja (`oklch(0.65 0.19 35)`).
- **Librerías UI y Utilidades**:
  - Primitivas Radix UI completas + componentes `shadcn/ui` en `devgym/components/ui/*`.
  - Iconos: `lucide-react` (`^0.454.0`).
  - Gráficos: `recharts` (`2.15.4`).
  - Formularios y validación: `react-hook-form` (`^7.60.0`), `@hookform/resolvers` (`^3.10.0`), `zod` (`3.25.76`).
  - Fechas: `date-fns` (`4.1.0`), `react-day-picker` (`9.8.0`).
  - Comandos / Combobox: `cmdk` (`1.0.4`), `vaul` (`^0.9.9`), `sonner` (`^1.7.4`).
  - PWA: `devgym/public/manifest.json` configurado y referenciado en `devgym/app/layout.tsx:26`.
- **Variables de Entorno** (`devgym/.env.local`):
  - `NEXT_PUBLIC_API_URL`: URL base del backend actual (usada tanto por `devgym/lib/api.ts:6` como por el proxy server-side `devgym/app/api/proxy/[...path]/route.ts:4`).

### A.2. Rutas, Páginas y Vistas (`devgym/app/**`)

| Ruta | Archivo | Rol Requerido (`useRequireRole`) | Estado de Conexión API | Propósito y Lógica en el Front |
|---|---|---|---|---|
| `/` | `app/page.tsx` | Público (redirige) | Usa `AuthContext` | Si hay sesión activa redirige según `user.role`: `admin -> /admin`, `reception -> /reception`, `client -> /client` (`L16-24`). |
| `/login` | `app/login/page.tsx` | Público | Real (`POST /login`) | Renderiza `<LoginForm />` (`components/login-form.tsx`). |
| `/admin` | `app/admin/page.tsx` (`layout.tsx`) | `admin` (`gym_owner`) | Real (`GET /dashboard/stats`, `GET /clases`, `POST /asistencias`, `POST /socios`, `POST /membresias`, `POST /pagos-gym`, `POST /salidas-gym`) | Dashboard principal con KPIs (`sociosActivos`, `ingresosHoy`, `salidasHoy`, `balanceHoy`, `asistenciasHoy`, `membresiasVencenProximamente`), gráfico `recharts` de ingresos/gastos últimos 6 meses (`L383-453`), alertas de vencimiento y accesos rápidos a diálogos (`SocioDialog`, `MembresiaDialog`, `PagoDialog`, `SalidaDialog`, `RegistrarAsistenciaDialog`). |
| `/admin/socios` | `app/admin/socios/page.tsx` | `admin` | Real (`GET/POST/PUT/DELETE /socios`, `POST /socios/:id/create-user`) | CRUD paginado de socios con búsqueda (debounce 500ms) y filtro por estado (`todos`, `activo`, `inactivo`, `vencido`). Permite crear usuario de acceso o restablecer password (`L51-106`), y abrir `MembresiaDialog` preseleccionando el socio. |
| `/admin/instructores` | `app/admin/instructores/page.tsx` | `admin` | Real (`GET /socios?tipo_persona=instructor`, `POST/PUT/DELETE /socios`) | CRUD de instructores reutilizando el endpoint `/socios` con `tipo_persona: "instructor"`. Adapta campos `especialidades` (CSV <-> array) y `biografia` (`observaciones`). |
| `/admin/planes` | `app/admin/planes/page.tsx` | `admin` | Real (`GET /servicios?tipo_servicio=plan`, `POST/PUT/DELETE /servicios`) | **Nota de dominio**: En `devgym` "Planes" son **Tipos de Membresía** (`tipos_membresia` en SQL v3), no planes de entrenamiento. Soporta modalidad por tiempo (`duracion_dias`) o por créditos (`creditos` + `duracion_dias`). |
| `/admin/membresias` | `app/admin/membresias/page.tsx` | `admin` | Real (`GET/POST/PUT/DELETE /membresias`) | Listado paginado, filtros por estado (`todas`, `activa`, `vencida`, `pendiente`), creación con opción de registrar cobro inicial en el mismo flujo (`MembresiaDialog`), y cálculo visual de progreso de días o créditos consumidos (`L202-238`). |
| `/admin/clases` | `app/admin/clases/page.tsx` | `admin` | Real (`GET/POST/PUT/DELETE /clases`) | CRUD de horarios/clases grupales (`dias_semana` CSV `"Lunes,Miércoles"`, `hora_inicio`, `hora_fin`, `duracion_minutos`, `cupo_maximo`, `id_coach`, `minutos_limite_reserva`, `minutos_limite_cancelacion`). |
| `/admin/asistencias` | `app/admin/asistencias/page.tsx` | `admin` | Real (`GET/POST /asistencias`) | Historial paginado de asistencias (50 por página) con filtro local por fecha (`hoy`, `ayer`, `semana`, `todas`) y modal `RegistrarAsistenciaDialog` (soporta asistencia a clase o "Acceso Libre"). |
| `/admin/pagos` | `app/admin/pagos/page.tsx` | `admin` | Real (`GET/POST /pagos-gym`) | Historial de cobros paginado, métricas (`totalRecaudado`, `deudaTotal`, `pagosHoy`) y registro de pago vinculado a socio + membresía (`PagoDialog`). |
| `/admin/salidas` | `app/admin/salidas/page.tsx` | `admin` | Real (`GET/POST/PUT/DELETE /salidas-gym`, `GET /clases/instructor-stats`) | Módulo de egresos/gastos del gimnasio (`servicio`, `pago_instructor`, `mantenimiento`, `insumos`, `otros`). Incluye calculadora de liquidación a instructores por clases dictadas en un rango de fechas (`SalidaDialog:112-142`). |
| `/admin/configuracion` | `app/admin/configuracion/page.tsx` | `admin` | **100% Mock / Hardcoded** | Formulario de datos del gimnasio, horarios de apertura y switches de notificaciones con estado local (`L21-61`) y `setTimeout` simulado (`L63-70`). Además envuelve la vista en un segundo `<AdminLayout>` duplicado respecto a `app/admin/layout.tsx`. |
| `/client` | `app/client/page.tsx` | `client` (`gym_socio`) | Real (`GET /perfil`, `GET /reservas`, `GET /asistencias`, `GET /clases-disponibles`, `DELETE /reservas/:id`) | Dashboard del alumno: muestra tarjeta de membresía activa (días restantes o barra de créditos), calcula **racha de semanas consecutivas** en el front (`calculateStreak` en `L125-205`), lista próximas reservas con botón de cancelar y accesos rápidos. |
| `/client/book` | `app/client/book/page.tsx` | `client` | Real (`GET /clases-disponibles`, `POST /reservas`, `DELETE /reservas/:id`) | Calendario semanal interactivo (`addDays`, `startOfWeek`) para reservar/cancelar turnos de clases. Valida cupos, membresía activa y anticipación. |
| `/client/history` | `app/client/history/page.tsx` | `client` | Real (`GET /asistencias`, `GET /reservas`, `DELETE /reservas/:id`) | Historial unificado de asistencias y reservas del alumno con filtros por estado y mes. |
| `/client/payments` | `app/client/payments/page.tsx` | `client` | Real (`GET /perfil`, `GET /membresias`, `GET /pagos-gym`) | Estado de cuenta del alumno, historial de membresías y pagos realizados. |
| `/client/profile` | `app/client/profile/page.tsx` | `client` | Real (`GET /perfil`, `POST /perfil`) | Edición de perfil del socio (nombre, teléfono, dirección, fecha de nacimiento, cambio de contraseña opcional). |
| `/reception` | `app/reception/page.tsx` | Sin `useRequireRole` | **100% Mock / Hardcoded** | Panel de recepción con métricas fijas (`127` clientes, `24` clases) y lista fija (`CrossFit`, `Yoga`, `Spinning`, `L49-133`). |
| `/reception/attendance` | `app/reception/attendance/page.tsx` | Sin `useRequireRole` | **100% Mock** (`@/lib/mock-data`) | Check-in rápido de asistencia buscando en `mockClients` y `mockClasses`. |
| `/reception/book` | `app/reception/book/page.tsx` | Sin `useRequireRole` | **100% Mock** (`@/lib/mock-data`) | Reserva de clase en nombre de un cliente usando `mockClients` y `mockClasses`. |
| `/reception/search` | `app/reception/search/page.tsx` | Sin `useRequireRole` | **100% Mock** (`@/lib/mock-data`) | Buscador de clientes y estado de membresía usando `mockClients` y `mockMembership`. |

### A.3. Componentes Reutilizables (`devgym/components/**`)
- **Layouts y Navegación**:
  - `admin-layout.tsx` y `admin-sidebar.tsx`: Sidebar de escritorio + drawer móvil con links a `/admin/*` y botón de cerrar sesión.
  - `app-header.tsx` y `mobile-nav.tsx`: Header superior y barra de navegación inferior móvil para vistas de `client` y `reception`.
- **Diálogos de Negocio (ABM)**:
  - `socio-dialog.tsx`: Alta/edición de socio + switch `crearUsuario` / `resetPassword` (`L194-274`).
  - `instructor-dialog.tsx`: Alta/edición de instructor con especialidades y biografía.
  - `plan-dialog.tsx`: Alta/edición de plan comercial (`tipo_plan: "fecha" | "creditos"`, `duracion_dias`, `creditos`, `precio`).
  - `membresia-dialog.tsx`: Asignación de membresía con buscador de socio, cálculo de fecha de fin (`addDays`), y switch `registrar_pago` para cobrar en el acto (`L293-368`).
  - `pago-dialog.tsx`: Registro de cobro con `Command` combobox de socios (búsqueda con debounce 300ms), selección de membresía y visualización de `saldo_pendiente` (`L307-330`).
  - `clase-dialog.tsx`: Configuración de clase grupal (días de semana, horarios, cupo, instructor, límites de reserva y cancelación en minutos).
  - `registrar-asistencia-dialog.tsx`: Registro manual de asistencia por clase o libre, validando membresía activa/créditos del socio.
  - `salida-dialog.tsx`: Registro de gastos y calculadora de pago a instructores.
- **Tarjetas y Badges**:
  - `class-card.tsx`: Tarjeta de turno/clase con barra de ocupación (`enrolled/capacity`), horarios y acción de reservar/cancelar.
  - `membership-card.tsx`: Resumen visual del estado de membresía del alumno.
  - `status-badge.tsx`: Badge semántico para estados (`active`, `expiring`, `expired`).

### A.4. Autenticación y Manejo de Estado
- **Estado**: No utiliza Redux ni Zustand; emplea React Context (`contexts/AuthContext.tsx`) para sesión y estado local (`useState` + llamadas imperativas a `api.*` en `useEffect`) en cada página.
- **Flujo de Auth** (`contexts/AuthContext.tsx` y `hooks/useRequireRole.ts`):
  - `login(email, password)` llama a `api.login(email, password)` (`POST /login`).
  - Guarda `auth_token` y `auth_user` en `localStorage` (`lib/api.ts:96`, `contexts/AuthContext.tsx:78`).
  - Mapeo de roles actuales del backend viejo a roles de UI (`contexts/AuthContext.tsx:59-67`, `hooks/useRequireRole.ts:27-39`):
    - `"gym_owner"` $\rightarrow$ `"admin"` (rutas `/admin/*`)
    - `"gym_staff"` $\rightarrow$ `"reception"` (rutas `/reception/*`)
    - `"gym_socio"` $\rightarrow$ `"client"` (rutas `/client/*`)
  - **Detalle de rendimiento**: `useRequireRole` (`hooks/useRequireRole.ts:23`) ejecuta `api.getUser()` (`GET /user`) en cada montaje de layout/página de forma independiente al `AuthContext`.

### A.5. Consumo de API Actual (`devgym/lib/api.ts` y Proxy)
- **Cliente HTTP Centralizado**: Clase `ApiClient` instanciada como singleton `api` en `devgym/lib/api.ts:681`.
- **Headers enviados** (`lib/api.ts:28-39`):
  - `Content-Type: application/json`, `Accept: application/json`
  - `Cache-Control: no-cache, no-store, must-revalidate`, `Pragma: no-cache`, `Expires: 0`
  - `Authorization: Bearer ${token}` (leído de `localStorage.getItem("auth_token")`)
  - **No envía `X-Tenant-Host` actualmente**.
- **Fallback automático a Proxy y Reintentos** (`lib/api.ts:15-26, 41-85`):
  - Si una petición directa a `NEXT_PUBLIC_API_URL` falla con HTTP `415` o error de red/CORS (`Failed to fetch`), activa `this.useProxy = true`, guarda `force_proxy_mode = "true"` en `localStorage` y redirige el tráfico a `/api/proxy/...`.
  - Reintenta hasta `MAX_RETRIES = 2` con backoff exponencial **únicamente en métodos `GET`** (nunca en `POST`/`PUT`/`DELETE` para evitar duplicar registros).
- **Ruta Proxy Next.js** (`devgym/app/api/proxy/[...path]/route.ts`):
  - Reenvía `GET`, `POST`, `PUT`, `DELETE` hacia `${NEXT_PUBLIC_API_URL}/${path}`.
  - Filtra los headers `host`, `connection`, `referer`, `origin` (`L41-49`). *(Importante para Fase 1: deberá preservar o inyectar `X-Tenant-Host`)*.
- **Catálogo de Endpoints que `devgym` llama hoy** (`devgym/lib/api.ts`):

| Método | Ruta actual | Parámetros / Body | Respuesta esperada por el Front |
|---|---|---|---|
| `POST` | `/login` | `{ email, password }` | `{ access_token, user: { id, name, email, role_name } }` |
| `POST` | `/logout` | - | `{ message }` |
| `GET` | `/user` | - | `{ user, roles }` |
| `GET` | `/perfil` | - | `{ socio, membresia_activa, stats }` |
| `POST` | `/perfil` | `{ nombre, email, telefono, direccion, fecha_nacimiento, password? }` | `{ socio, user }` |
| `GET` | `/socios` | `?search=&estado=&page=&per_page=&tipo_persona=` | `{ socios: Socio[], pagination }` o array paginado Laravel |
| `GET` | `/socios/:id` | - | `Socio` |
| `POST` | `/socios` | `{ nombre, tipo_documento, num_documento, direccion, telefono, email, fecha_nacimiento, estado, tipo_persona, crear_usuario?, password?, especialidades?, observaciones? }` | `Socio` |
| `PUT` | `/socios/:id` | Igual a `POST /socios` + `reset_password?` | `Socio` |
| `DELETE` | `/socios/:id` | - | `{ message }` |
| `POST` | `/socios/:id/create-user` | `{ email, password }` | `{ user }` |
| `GET` | `/servicios?tipo_servicio=plan` | - | `Plan[]` (`idservicio`, `nombre`, `descripcion`, `precio`, `duracion_dias`, `creditos`, `estado`) |
| `POST` | `/servicios` | `{ nombre, descripcion, precio, duracion_dias, creditos, estado, tipo_servicio: "plan" }` | `Plan` |
| `PUT` | `/servicios/:id` | Igual a `POST /servicios` | `Plan` |
| `DELETE` | `/servicios/:id` | - | `{ message }` |
| `GET` | `/membresias` | `?id_socio=&estado=&search=&page=` | `{ data: Membresia[], current_page, last_page, total }` |
| `POST` | `/membresias` | `{ id_socio, id_plan, fecha_inicio, registrar_pago?, metodo_pago?, monto_pago?, observaciones_pago? }` | `Membresia` |
| `PUT` | `/membresias/:id` | `{ estado?, fecha_inicio?, fecha_fin?, creditos_restantes? }` | `Membresia` |
| `DELETE` | `/membresias/:id` | - | `{ message }` |
| `GET` | `/clases` | - | `Clase[]` |
| `POST` | `/clases` | `{ nombre, idservicio, id_coach, dias_semana, hora_inicio, hora_fin, duracion_minutos, cupo_maximo, estado, minutos_limite_reserva, minutos_limite_cancelacion }` | `Clase` |
| `PUT` | `/clases/:id` | Igual a `POST /clases` | `Clase` |
| `DELETE` | `/clases/:id` | - | `{ message }` |
| `GET` | `/clases/instructor-stats` | `?instructor_id=&start_date=&end_date=` | `{ total_classes: number, details: { date, day, time, class_name }[] }` |
| `GET` | `/clases-disponibles` | `?start_date=&end_date=` | `{ instancias: ClassSlot[] }` |
| `GET` | `/reservas` | `?id_persona=&fecha=` | `{ reservas: Reserva[] }` |
| `POST` | `/reservas` | `{ id_clase_gym, fecha_reserva, id_persona? }` | `Reserva` |
| `DELETE` | `/reservas/:id` | - | `{ message }` |
| `GET` | `/asistencias` | `?page=&per_page=` | `{ asistencias: Asistencia[], meta }` |
| `POST` | `/asistencias` | `{ id_persona, id_clase_gym?, id_reserva? }` | `Asistencia` |
| `GET` | `/pagos-gym` | `?id_persona=&search=&page=&per_page=` | `{ pagos: Pago[], meta, deuda_total }` |
| `POST` | `/pagos-gym` | `{ id_persona, id_membresia, monto, metodo_pago, fecha_pago, observaciones? }` | `Pago` |
| `GET` | `/salidas-gym` | `?tipo=&fecha_inicio=&fecha_fin=&search=&page=&per_page=` | `{ salidas: Salida[], meta, stats }` |
| `POST` | `/salidas-gym` | `{ id_persona?, monto, descripcion, tipo_salida, fecha }` | `Salida` |
| `PUT` | `/salidas-gym/:id` | Igual a `POST /salidas-gym` | `Salida` |
| `DELETE` | `/salidas-gym/:id` | - | `{ message }` |
| `GET` | `/dashboard/stats` | - | `DashboardStats` + `monthlyFinancials` + `expiringMemberships` + `todayClasses` |

---

## B. `cross-fit-coach-app` (App Fuente – Planificación y Seguimiento del Coach)

### B.1. Persistencia Confirmada y Stack Técnico
- **Confirmación de Base de Datos**: Persiste en **SQLite** mediante **Prisma ORM `7.8.0`** con el adaptador `@prisma/adapter-libsql` y `@libsql/client` (`cross-fit-coach-app/lib/db.ts:1-16`).
- **Archivo SQLite activo**: `cross-fit-coach-app/.env` define `DATABASE_URL="file:./dev.db"` (existe `dev.db` con `dev.db-wal` activo de ~1 MB, además de un archivo secundario `crossfit-app.db`).
- **Arquitectura actual**: No tiene endpoints REST; los Server Components (`app/coach/**/page.tsx`, `app/alumno/**/page.tsx`) leen directamente de `prisma.*` y los Client Components ejecutan mutaciones mediante **Next.js Server Actions** en `cross-fit-coach-app/lib/actions.ts` (492 líneas, 25 funciones exportadas).
- **Librerías clave**: Next.js `16.2.6`, React `19.2.4`, Tailwind v4, `@dnd-kit/core` + `@dnd-kit/sortable` (tablero Kanban de semanas), `date-fns`, `lucide-react`, `recharts`.

### B.2. Modelo de Datos Actual (`cross-fit-coach-app/prisma/schema.prisma`)
1. `User`: `id` (cuid), `nombre`, `email` (unique), `password` (texto plano), `role` (`"coach"` | `"alumno"`), `avatar`, `telefono`, `estado` (`"activo"` | `"pausado"` | `"inactivo"`), `cuota` (Float), `vencimiento` (String ISO date), `notas`, `gimnasio` (String JSON para config del coach), `fechaAlta`.
2. `TipoPlan`: `id` (cuid), `nombre`, `descripcion`, `color` (hex), `icono`.
3. `AlumnoPlan`: `id` (cuid), `userId`, `tipoPlanId` (`@@unique([userId, tipoPlanId])`).
4. `Semana`: `id` (cuid), `numero` (Int), `fechaInicio` (String), `notas`, `titulo`, `estado` (`"planificacion"` | `"en-curso"` | `"finalizado"`), `tipoPlanId`.
5. `Dia`: `id` (cuid), `dia` (`"Lunes"`..`"Sábado"`), `contenido` (String Markdown libre), `notas`, `calentamiento`, `descanso` (Boolean), `orden` (Int `0..5`), `semanaId`.
6. `Link`: `id` (cuid), `titulo`, `url`, `diaId`.
7. `Asistencia`: `id` (cuid), `userId`, `diaId`, `fecha`, `completado` (Boolean) (`@@unique([userId, diaId])`).
8. `RM`: `id` (cuid), `userId`, `ejercicio` (String), `kg` (Float — almacena kg, repeticiones o segundos según el ejercicio), `fecha`, `reps` (Int default 1).
9. `Comentario`: `id` (cuid), `userId`, `coachId`, `semanaId`, `diaId`, `texto`, `fecha`, `autorNombre`, `autorRol` (`"coach"` | `"alumno"`).
10. `Pago`: `id` (cuid), `userId`, `monto` (Float), `fecha`, `mes`, `estado` (`"pagado"` | `"pendiente"` | `"vencido"`), `metodo` (`"efectivo"` | `"transferencia"` | `"mercadopago"`).

### B.3. Pantallas y Flujos Completos (`cross-fit-coach-app`)

| Ruta / Componente | Propósito y Flujo | Estado de Persistencia |
|---|---|---|
| `/` (`app/page.tsx`) | Login único por email y password (`verifyLogin` en `lib/actions.ts:395-416`). Compara password en texto plano y guarda sesión en `localStorage.getItem("crossfit-user")` (`lib/auth-context.tsx:36`). | Real (Prisma `User`) |
| `/coach` (`components/coach/coach-dashboard-view.tsx`) | Dashboard del coach: KPIs (alumnos activos, cuotas vencidas/por vencer, ingresos del mes, planes en curso), alertas de vencimiento, comentarios recientes, selector rápido de planes del propio coach (`updateCoachProfile`) y vista embebida de `<WorkoutDashboard />` para que el coach vea su propio entrenamiento en el gimnasio (`L109-124`). | Real (Prisma) |
| `/coach/planes` (`components/coach/planes-view.tsx`) | ABM de planes (`TipoPlan`) con nombre, descripción, selector de 10 colores hex predefinidos y contador de semanas/alumnos asignados. Al hacer clic en un plan navega a `/coach/kanban?planId=...`. | Real (`createTipoPlan`, `updateTipoPlan`, `deleteTipoPlan`) |
| `/coach/kanban` (`components/coach/kanban-board.tsx`) | Tablero Kanban de semanas por `TipoPlan` con 3 columnas (`planificacion`, `en-curso`, `finalizado`) usando `@dnd-kit/core`. Permite crear semana (crea automáticamente 6 días `Lunes`..`Sábado`, con `Miércoles` como `descanso: true` por defecto en `lib/actions.ts:145-154`), duplicar semana completa con sus días y links (`duplicateSemana`), editar día con barra de herramientas Markdown (`#`, `##`, `**`, `*`, `-`, `[link](url)`), gestionar links adjuntos y escribir comentarios en la semana. | Real (`createSemana`, `duplicateSemana`, `updateSemanaEstado`, `updateDia`, `addLinkToDia`, `removeLink`) |
| `/coach/alumnos` (`components/coach/alumnos-view.tsx`) | Listado de alumnos, creación (`createAlumno` con password default `"alumno123"`), edición, asignación múltiple de planes (`AlumnoPlan`), reseteo de clave (`resetStudentPassword`) y modal de detalle con pestañas de RMs, Pagos y Asistencias. | Real (Prisma) |
| `/coach/alumnos/[id]` (`app/coach/alumnos/[id]/page.tsx`) | Vista dedicada de detalle de un alumno (RMs con gráfico `recharts`, pagos, asistencias, comentarios). | **Mock** (`importa de @/lib/mock-data` en `L25`; no persiste cambios). |
| `/coach/pagos` (`components/coach/pagos-view.tsx`) | Gestión de pagos/cuotas de alumnos (`createPago`, `updatePagoEstado`). Al registrar un pago `"pagado"`, si el vencimiento del alumno ya pasó, extiende automáticamente `User.vencimiento` +1 mes desde hoy (`lib/actions.ts:375-391`). | Real (Prisma) |
| `/coach/perfil` (`components/coach/coach-profile-view.tsx`) | Edita perfil del coach, cambia contraseña y selecciona qué `TipoPlan` tiene asignados el propio coach. | Real (`updateCoachProfile`) |
| `/coach/configuracion` (`components/coach/config-form.tsx`) | Edita nombre del gimnasio, `aliasPago`, teléfono, email, dirección, descripción, horarios y color primario. Guarda un JSON serializado dentro de `User.gimnasio` del coach (`lib/actions.ts:448-491`). | Real (`User.gimnasio` JSON) |
| `/alumno/[id]` (`components/student/workout-dashboard.tsx`) | Vista principal del alumno: selector de plan (si tiene varios), selector de semana (solo semanas con `estado === "en-curso"`, `app/alumno/[id]/page.tsx:32`), carrusel de los 6 días (`Lunes`..`Sábado`), parser inteligente de entrenamientos con cálculo automático de pesos/reps/tiempos según RMs del alumno, embeds de YouTube desplegables, botón `"Marcar como completado"` (`toggleAsistencia`), y comentarios por semana/día. Incluye bloqueo de pantalla si la cuota está vencida (`L155-165`). | Real (Prisma) |
| `/alumno/[id]/rms` (`components/student/student-rms-view.tsx`) | Gestión y calculadora de RMs del alumno en 4 categorías (`Fuerza`, `Weightlifting`, `Capacidades`, `Running`), historial por ejercicio y tabla de porcentajes (105% a 30%). | Real (`addRM`, `deleteRM`) |
| `/alumno/[id]/pagos` (`app/alumno/[id]/pagos/page.tsx`) | Estado de cuota del alumno, días hasta el vencimiento e historial de pagos. | Real (Prisma) |
| `/alumno/[id]/comentarios` (`app/alumno/[id]/comentarios/page.tsx`) | Historial de comentarios del alumno. | **Mock** (`@/lib/mock-data` en `L14`). |
| `/alumno/[id]/perfil` (`components/student/student-profile-view.tsx`) | Edición de nombre, email, teléfono y contraseña del alumno (`updateStudentProfile`). | Real (Prisma) |

### B.4. Lógica de Negocio Crítica en `cross-fit-coach-app` (Cálculos de RM, Parser y Reglas)

1. **Categorías de RM y Almacenamiento Polimórfico en `RM.kg`** (`lib/constants.ts:1-38`, `components/student/student-rms-view.tsx:100-156`):
   - `Fuerza`: `Back Squat`, `Front Squat`, `Deadlift`, `Strict Press`, `Bench Press` $\rightarrow$ unidad: **kg** (se toma el máximo: `Math.max`).
   - `Weightlifting`: `Snatch`, `Clean`, `Jerk` $\rightarrow$ unidad: **kg** (`Math.max`).
   - `Capacidades`: `Pull-ups`, `Chest to Bar`, `Muscle-ups (Bar)`, `Muscle-ups (Ring)`, `HSPU (Strict)`, `HSPU (Kipping)`, `Toes to Bar`, `Handstand Walk`, `Double Unders`, `Wall Balls`, `Box Jumps`, `Burpees`, `Row (Cal)`, `Assault Bike (Cal)`, `Otro` $\rightarrow$ unidad: **repeticiones** guardadas en `kg` (`Math.max`).
   - `Running`: `5km`, `1k` $\rightarrow$ unidad: **segundos totales** (`minutos * 60 + segundos`) guardados en `kg` (se toma el mínimo tiempo como mejor marca: `Math.min`, `workout-dashboard.tsx:450-452`).
   - **Ejercicios personalizados (`"Otro"`)**: Codifica metadatos en el string del nombre (`student-rms-view.tsx:133-138`): `"${customName} [${customNote}] (Tiempo)"` o `"${customName} (Reps)"`.

2. **Parser Inteligente de Texto del Día y Cálculo de Porcentajes / Tiempos** (`components/student/workout-dashboard.tsx:394-663`):
   - Escanea el texto Markdown de `Dia.contenido` línea por línea manteniendo una variable de estado `activeRMContext` (el último ejercicio detectado en las líneas superiores).
   - **Mapeo de sinónimos/alias** (`L422-442`):
     - `"clean & jerk"` o `"c&j"` $\rightarrow$ busca RM de `"Clean & Jerk"`, o fallback a `"Jerk"` / `"Clean"`.
     - `"strict press"` o `"press estricto"` $\rightarrow$ `"Press Estricto"` / `"Strict Press"`.
     - `"ohs"` o `"overhead squat"` $\rightarrow$ usa RM de `"Snatch"`.
   - **Cálculo de porcentaje simple `([+-]?\d+)%`** (`L526-599`):
     - Si el ejercicio es de **Peso (`kg`)**: `weight = Math.round(((maxRM * percent) / 100) * 10) / 10` $\rightarrow$ inyecta badge `con ${weight}kg`.
     - Si el ejercicio es de **Capacidad (`reps`)**: `reps = Math.round((maxRM * percent) / 100)` $\rightarrow$ inyecta badge `con ${reps} reps`.
   - **Cálculo de rango de porcentajes `(\d+)%?\s*(?:a|al|-)\s+(\d+)%`** (`L468-525`):
     - Excluye falsos positivos de series como `"2x5 - 50%"` (`/\b\d+x\s*\d+\s*-\s*\d+%/i`).
     - Calcula `val1` y `val2` y muestra `con ${val1} a ${val2} kg` (o `reps`).
   - **Predictor de Running por Distancia + Porcentaje** (`L560-574`):
     - Si `activeRMContext` es `"5km"` (`baseDist = 5000`) o `"1k"` (`baseDist = 1000`) y la línea menciona metros `(\d+)\s*m\b`, escala el tiempo base: `baseTime = maxRM * (distanceMeters / baseDist)`.
     - Si el porcentaje lleva signo (`+10%` o `-10%`), aplica `Math.round(baseTime * (1 - percent / 100))`; si no lleva signo, `Math.round((baseTime * percent) / 100)`, formateado en `mm:ss` o `Xs`.
   - **Predictor de Running por RPE (`(\d+)\s*m\s*(?:rpe|rp)\s*(\d+)`)** (`L601-646`):
     - Toma el RM de `"1k"` (o `"5km"` como fallback), calcula el ritmo por metro `basePacePerMeter = baseTimeSeconds / baseDistance` y proyecta `targetTime = Math.round((distanceMeters * basePacePerMeter) * (rpeValue / 10))`.

3. **Bloqueo de Plan por Cuota Vencida** (`workout-dashboard.tsx:24-30, 114-165`):
   - Calcula `daysLeft = differenceInDays(new Date(alumno.vencimiento), today)`.
   - Si `daysLeft <= 3 && daysLeft >= 0`: muestra alerta roja con botón para copiar el `aliasPago` del gimnasio.
   - Si `daysLeft < 0`: oculta las semanas/días y muestra pantalla `"Plan Bloqueado"` con el alias de pago.

---

## C. `gymdevv-api` (Estado Actual del Backend Laravel)

- **Versión de Laravel y PHP**: `laravel/framework` `^13.17` (instalado `v13.35.0` en `composer.lock`), PHP `^8.3` (en el entorno Laragon local está disponible `php-8.3.16-Win32-vs16-x64`).
- **Laravel Sanctum**: **Ya instalado** (`laravel/sanctum: ^4.0` en `composer.json:11`), configurado en `config/sanctum.php` y con rutas API habilitadas en `bootstrap/app.php:10` (`api: __DIR__.'/../routes/api.php'`).
- **Base de Datos en `.env`**:
  - Actualmente `gymdevv-api/.env:23` tiene `DB_CONNECTION=sqlite` apuntando a `database/database.sqlite` y las variables MySQL comentadas (`# DB_HOST=127.0.0.1`, `# DB_DATABASE=laravel`). Debe actualizarse a `DB_CONNECTION=mysql` apuntando a la base MySQL 8 donde se ejecutará el esquema v3.
- **Migraciones existentes** (`gymdevv-api/database/migrations/`):
  1. `0001_01_01_000000_create_users_table.php` (crea tabla `users` estándar de Laravel, `password_reset_tokens` y `sessions` — `users` debe reemplazarse por la estructura multi-tenant `usuarios`).
  2. `0001_01_01_000001_create_cache_table.php` (`cache` y `cache_locks`).
  3. `0001_01_01_000002_create_jobs_table.php` (`jobs`, `job_batches`, `failed_jobs`).
  4. `2026_10_11_222321_create_personal_access_tokens_table.php` (`personal_access_tokens` de Sanctum).
- **Estructura de Carpetas**: Instalación limpia de Laravel 13 (`app/Http/Controllers/Controller.php`, `app/Models/User.php`, `routes/api.php` solo con `GET /user` bajo `auth:sanctum`). Aún no existen modelos de negocio, middlewares de tenant, traits ni policies.

---

## D. Tabla de Cruce (Pantallas / Flujos $\rightarrow$ Origen $\rightarrow$ Tablas SQL v3 $\rightarrow$ Endpoints)

| Pantalla / Flujo | App de Origen | Tablas SQL v3 Involucradas | Endpoints Necesarios (Catálogo Fase 1) |
|---|---|---|---|
| Bootstrap de tema, logo y módulos del gimnasio | Nueva (Decisiones #1 y #3) + `cross-fit-coach-app` (`app-config.json`) | `tenants` | `GET /api/tenant/config`, `PUT /api/tenant/config` |
| Login, Logout y Sesión actual | Ambas (`devgym/app/login`, `coach/app/page.tsx`) | `tenants`, `usuarios`, `alumnos`, `personal_access_tokens` | `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/auth/me` |
| Dashboard Admin / Coach (KPIs financieros, asistencias, vencimientos, comentarios recientes) | Ambas (`devgym/app/admin/page.tsx`, `coach-dashboard-view.tsx`) | `alumnos`, `membresias`, `pagos`, `asistencias`, `turnos`, `grupos`, `comentarios`, `planes` | `GET /api/dashboard/stats` |
| Gestión de Alumnos / Socios (ABM, búsqueda, alta/reseteo de acceso) | Ambas (`devgym/app/admin/socios`, `coach/alumnos-view.tsx`) | `usuarios`, `alumnos`, `membresias`, `alumno_planes` | `GET /api/alumnos`, `POST /api/alumnos`, `GET /api/alumnos/{id}`, `PUT /api/alumnos/{id}`, `DELETE /api/alumnos/{id}`, `POST /api/alumnos/{id}/acceso` |
| Gestión de Coaches / Instructores | `devgym` (`app/admin/instructores`) | `usuarios` (`rol = 'coach'`) | `GET /api/usuarios?rol=coach`, `POST /api/usuarios`, `PUT /api/usuarios/{id}`, `DELETE /api/usuarios/{id}` |
| Catálogo de Tipos de Membresía (mensual / créditos) | `devgym` (`app/admin/planes`) | `tipos_membresia` | `GET /api/tipos-membresia`, `POST /api/tipos-membresia`, `PUT /api/tipos-membresia/{id}`, `DELETE /api/tipos-membresia/{id}` |
| Membresías de Alumnos (asignación, renovación, cobro inicial) | Ambas (`devgym/app/admin/membresias`, `coach/alumnos-view.tsx`) | `membresias`, `tipos_membresia`, `pagos`, `movimientos_credito` | `GET /api/membresias`, `POST /api/membresias`, `GET /api/membresias/{id}`, `PUT /api/membresias/{id}`, `DELETE /api/membresias/{id}` |
| Pagos y Estado de Cuenta | Ambas (`devgym/app/admin/pagos`, `coach/pagos-view.tsx`, `client/payments`) | `pagos`, `membresias`, `alumnos` | `GET /api/pagos`, `POST /api/pagos`, `PUT /api/pagos/{id}` |
| Grupos, Turnos Horarios e Inscripciones Fijas | `devgym` (`app/admin/clases`) + SQL v3 | `grupos`, `turnos`, `inscripciones_turno`, `alumnos` | `GET/POST/PUT/DELETE /api/grupos`, `GET/POST/PUT/DELETE /api/turnos`, `GET/POST/DELETE /api/turnos/{id}/inscripciones` |
| Asistencias (Check-in por turno y listado) | `devgym` (`app/admin/asistencias`, `registrar-asistencia-dialog.tsx`) | `asistencias`, `turnos`, `alumnos`, `membresias`, `movimientos_credito` | `GET /api/asistencias`, `POST /api/asistencias`, `DELETE /api/asistencias/{id}` |
| Reservas de Turnos y Calendario Semanal (Feature Flag) | `devgym` (`app/client/book`, `app/reception/book`) | `reservas`, `turnos`, `grupos`, `asistencias`, `movimientos_credito` | `GET /api/turnos/disponibles`, `GET /api/reservas`, `POST /api/reservas`, `DELETE /api/reservas/{id}` |
| Libro de Movimientos de Crédito (Feature Flag) | `devgym` + SQL v3 (Decisión #5) | `movimientos_credito`, `membresias`, `alumnos` | `GET /api/alumnos/{id}/creditos`, `POST /api/alumnos/{id}/creditos/ajuste` |
| Catálogo de Ejercicios | `cross-fit-coach-app` (`lib/constants.ts`) + SQL v3 | `ejercicios` | `GET /api/ejercicios`, `POST /api/ejercicios`, `PUT /api/ejercicios/{id}`, `DELETE /api/ejercicios/{id}` |
| Planes de Entrenamiento, Tablero Kanban de Semanas, Días, Ejercicios Estructurados y Links | `cross-fit-coach-app` (`planes-view.tsx`, `kanban-board.tsx`) | `planes`, `semanas`, `dias`, `dia_ejercicios`, `links_dia` | `GET/POST/PUT/DELETE /api/planes`, `POST /api/planes/{id}/semanas`, `POST /api/semanas/{id}/duplicar`, `PUT/DELETE /api/semanas/{id}`, `PUT /api/dias/{id}`, `POST/PUT/DELETE /api/dias/{id}/ejercicios`, `POST/DELETE /api/dias/{id}/links` |
| Asignación de Planes a Alumnos | `cross-fit-coach-app` (`alumnos-view.tsx`) | `alumno_planes`, `planes`, `alumnos` | `GET /api/alumnos/{id}/planes`, `POST /api/alumnos/{id}/planes`, `PUT /api/alumno-planes/{id}`, `DELETE /api/alumno-planes/{id}` |
| Vista de Entrenamiento del Alumno, Marcar Día Completado y Registros por Ejercicio | `cross-fit-coach-app` (`workout-dashboard.tsx`) + SQL v3 | `alumno_planes`, `planes`, `semanas`, `dias`, `dia_ejercicios`, `links_dia`, `progreso_dias`, `registros_entrenamiento`, `rms`, `membresias` | `GET /api/mi-entrenamiento`, `POST /api/dias/{id}/progreso`, `DELETE /api/dias/{id}/progreso`, `GET/POST /api/registros-entrenamiento` |
| RMs del Alumno (Historial y Calculadora) | `cross-fit-coach-app` (`student-rms-view.tsx`) | `rms`, `ejercicios`, `alumnos` | `GET /api/rms`, `POST /api/rms`, `DELETE /api/rms/{id}` |
| Comentarios (Feedback Alumno $\leftrightarrow$ Coach por semana/día) | `cross-fit-coach-app` (`workout-dashboard.tsx`, `kanban-board.tsx`) | `comentarios`, `semanas`, `dias`, `alumnos`, `usuarios` | `GET /api/comentarios`, `POST /api/comentarios`, `DELETE /api/comentarios/{id}` |
| Perfil del Usuario / Alumno | Ambas (`client/profile`, `student-profile-view.tsx`, `coach-profile-view.tsx`) | `usuarios`, `alumnos` | `GET /api/perfil`, `PUT /api/perfil` |

---

## E. Brechas, Conflictos entre Aplicaciones y Valores Reales Confirmados

### E.1. Brechas entre las Vistas y el Esquema `gym_coach_multitenant_v3.sql`

1. **Módulo de Gastos / Salidas (`devgym/app/admin/salidas/page.tsx` y `components/salida-dialog.tsx`)**:
   - **Evidencia**: `devgym` tiene una pantalla completa de egresos (`/admin/salidas`), calculadora de liquidación de sueldos a instructores por clases dictadas (`/clases/instructor-stats`) y muestra `salidasHoy` y `balanceHoy` en el Dashboard (`app/admin/page.tsx:221-244`).
   - **Brecha**: El archivo `gym_coach_multitenant_v3.sql` **no tiene tabla de salidas/gastos**.
   - **Opciones para decisión del usuario**:
     - *Opción A (Recomendada para no alterar el SQL v3 ahora)*: Ocultar/desactivar el ítem "Gastos/Salidas" del menú en esta etapa y mostrar solo métricas de ingresos en el Dashboard, o posponer la tabla `salidas` para un milestone posterior.
     - *Opción B*: Agregar una tabla `salidas` (`id`, `tenant_id`, `usuario_id` nullable para coach, `monto DECIMAL(10,2)`, `tipo_salida`, `descripcion`, `fecha`, timestamps) en una migración complementaria.

2. **Categorías y Unidades de Medición en `ejercicios` y `rms` (`kg` vs `reps` vs `segundos`)**:
   - **Evidencia**: En `cross-fit-coach-app` (`lib/constants.ts:1-38`, `student-rms-view.tsx:100-156`, `workout-dashboard.tsx:446-646`), los RMs abarcan 4 categorías (`Fuerza`, `Weightlifting`, `Capacidades`, `Running`) y 3 unidades distintas: **kilogramos**, **repeticiones** y **segundos** (para `5km`, `1k` y ejercicios custom de tiempo). Además, el parser del dashboard necesita saber si un ejercicio es de peso, repeticiones o tiempo para calcular porcentajes o ritmos de carrera (RPE).
   - **Brecha**: En SQL v3, `ejercicios` solo tiene `(id, tenant_id, nombre, descripcion, video_url)` y `rms` tiene `(id, tenant_id, alumno_id, ejercicio_id, kg DECIMAL(6,2), reps INT, fecha)`. No hay columna `categoria` ni `unidad` (`kg` | `reps` | `segundos`) en `ejercicios`, ni `nota` en `rms`.
   - **Opciones**:
     - *Opción A (Sin tocar SQL v3)*: Seguir usando `rms.kg` para el valor numérico (`DECIMAL(6,2)` soporta hasta `9999.99` seg $\approx$ 166 min) y codificar la categoría/unidad en un prefijo/formato estructurado dentro de `ejercicios.descripcion` (ej. JSON `{"categoria":"Running","unidad":"segundos"}`), manteniendo catálogo base por seeder.
     - *Opción B (Limpia en DB)*: Agregar `categoria VARCHAR(40) DEFAULT 'Fuerza'` y `unidad VARCHAR(20) DEFAULT 'kg'` (`kg`, `reps`, `segundos`) a la tabla `ejercicios`.

3. **Metadatos Visuales de `planes` (`color`, `icono`) y `dias` (`calentamiento`)**:
   - **Evidencia**: `cross-fit-coach-app` usa `TipoPlan.color` (hex) e `icono` en las tarjetas de `/coach/planes` y `/coach/kanban` (`planes-view.tsx:26-38`), y `Dia.calentamiento` (`schema.prisma:79`).
   - **Brecha**: En SQL v3, `planes` tiene `(id, tenant_id, coach_id, nombre, descripcion, activo)` y `dias` tiene `(id, tenant_id, semana_id, numero_dia, nombre, titulo, contenido, es_descanso, notas)`.
   - **Propuesta**: No requiere alterar el esquema si en el front asignamos colores/iconos por paleta determinística (o guardamos metadata opcional), y el calentamiento ya se redacta como bloque `# WARM UP` / `# MOVILIDAD` dentro de `dias.contenido` (así lo hace hoy el editor Markdown de `kanban-board.tsx:479`).

4. **Campos adicionales de Socio (`dni`, `direccion`, `fechaNacimiento`), Instructor (`especialidades`, `biografia`) y Clase (`coach_id`, límites en minutos)**:
   - **Evidencia**: `devgym/components/socio-dialog.tsx:44-50` pide `dni`, `direccion`, `fechaNacimiento`. `instructor-dialog.tsx` pide `especialidades` y `biografia`. `clase-dialog.tsx:60-68` pide `id_coach`, `minutos_limite_reserva` y `minutos_limite_cancelacion` por clase.
   - **Brecha**: En SQL v3:
     - `usuarios` tiene `(nombre, apellido, email, telefono, avatar_url)` y `alumnos` tiene `(fecha_alta, estado, notas)`. No existen columnas `dni`, `direccion`, `fecha_nacimiento`.
     - `grupos` y `turnos` no tienen `coach_id` (la política de horas de cancelación es global por tenant en `tenants.configuracion.cancelacion_horas`, según Decisión #5).
   - **Propuesta**:
     - Para `alumnos`: usar `alumnos.notas` o simplificar el formulario de `SocioDialog` a los campos de SQL v3 (`nombre`, `apellido`, `email`, `telefono`, `avatar_url`, `fecha_alta`, `estado`, `notas`), salvo que quieras agregar `dni`, `direccion`, `fecha_nacimiento` a `alumnos`.
     - Para `turnos`/`grupos`: la anticipación de cancelación se rige por `tenants.configuracion.cancelacion_horas` (Decisión #5). Si se requiere asignar un coach específico a cada grupo/turno, habría que agregar `coach_id` nullable a `grupos` o `turnos`.

5. **Asistencia "Acceso Libre" vs `asistencias.turno_id NOT NULL`**:
   - **Evidencia**: `devgym/components/registrar-asistencia-dialog.tsx` permite registrar asistencia sin seleccionar clase (`"Acceso Libre"`).
   - **Brecha**: En SQL v3 (`L263`), `asistencias.turno_id` es `BIGINT UNSIGNED NOT NULL`.
   - **Propuesta**: Todo gimnasio con acceso libre tendrá un Grupo/Turno comodín ("Sala de Musculación / Libre") o bien se selecciona siempre un turno activo del día al registrar asistencia.

6. **Coach que entrena en su propio gimnasio (`coach-dashboard-view.tsx:109-124`)**:
   - **Evidencia**: En `cross-fit-coach-app`, el coach puede asignarse planes a sí mismo y ver su `<WorkoutDashboard />` en `/coach`.
   - **Adaptación a SQL v3**: En SQL v3 `alumno_planes`, `progreso_dias` y `rms` apuntan a `alumnos.id` (`alumno_id`). Como `alumnos.usuario_id` es `1:1` con `usuarios.id` independientemente del rol, si un `coach` o `admin` desea llevar su propio entrenamiento y RMs, simplemente se le crea su fila vinculada en `alumnos` (`usuario_id = coach.id`).

### E.2. Valores Reales Confirmados en el Código y Resolución de Conflictos

| Concepto | Valor en `devgym` | Valor en `cross-fit-coach-app` | Valor en `gym_coach_multitenant_v3.sql` (Fuente de Verdad) |
|---|---|---|---|
| **Roles de usuario** | `"gym_owner"`, `"gym_staff"`, `"gym_socio"` (mapeados en front a `"admin"`, `"reception"`, `"client"`) | `"coach"`, `"alumno"` | `usuarios.rol`: `'admin'`, `'coach'`, `'alumno'` (Decisión #2). *(Ver pregunta sobre recepción)*. |
| **Estado de alumno** | `"activo"`, `"inactivo"`, `"vencido"` | `"activo"`, `"pausado"`, `"inactivo"` | `alumnos.estado`: `'activo'`, `'inactivo'`, `'suspendido'` (`DEFAULT 'activo'`). |
| **Modalidad de membresía** | `"fecha"`, `"creditos"` (`plan-dialog.tsx:39`) | Cuota fija mensual en `User.cuota` | `tipos_membresia.modalidad`: `'mensual'`, `'creditos'` (Decisión #5). |
| **Estado de membresía** | `"activa"`, `"vencida"`, `"pendiente"`, `"cancelada"` | Calculado por `vencimiento < hoy` | `membresias.estado`: `'activa'`, `'vencida'`, `'cancelada'`, `'sin_creditos'`. |
| **Estado de pago** | Pagos siempre registrados como cobrados; saldo en `membresia.saldo_pendiente` | `"pagado"`, `"pendiente"`, `"vencido"` | `pagos.estado`: `'pagado'`, `'pendiente'`, `'anulado'`. |
| **Método de pago** | `"efectivo"`, `"tarjeta"`, `"transferencia"`, `"mercadopago"` | `"efectivo"`, `"transferencia"`, `"mercadopago"` | `pagos.metodo`: `VARCHAR(40)` — soporta los 4 valores: `'efectivo'`, `'transferencia'`, `'mercadopago'`, `'tarjeta'`. |
| **Estado de semana** | N/A | `"planificacion"`, `"en-curso"`, `"finalizado"` | `semanas.estado`: `'borrador'`, `'publicada'`, `'archivada'` (`DEFAULT 'borrador'`). En la UI del Kanban se mostrarán las etiquetas amigables mapeadas a estos 3 valores del SQL. |
| **Estado de reserva** | `"reservada"`, `"asistio"`, `"cancelada"`, `"ausente"` | N/A | `reservas.estado`: `'reservada'`, `'cancelada'`. El presente se materializa creando fila en `asistencias` (`origen = 'reserva'`). |
| **Unidad de `descanso` en ejercicios del día** | N/A | En `Dia` era un booleano (`Dia.descanso` $\rightarrow$ en SQL v3 es `dias.es_descanso TINYINT(1)`). | En `dia_ejercicios.descanso_seg` (`SQL v3 L369`): **segundos** (`INT UNSIGNED NULL`, ej. `90` = 90 segundos de pausa entre series). |
| **Autenticación actual** | Bearer token en `localStorage` (`auth_token`) contra `/login` | Comparación texto plano en Server Action y objeto JSON en `localStorage` (`crossfit-user`) | **Laravel Sanctum** (Personal Access Tokens Bearer) + validación de `usuario.tenant_id === tenant.id` en cada request. |
| **Nombre de usuario** | Un solo campo `nombre` | Un solo campo `nombre` | Dos campos en `usuarios`: `nombre` (`VARCHAR(100)`) y `apellido` (`VARCHAR(100) NULL`). |
