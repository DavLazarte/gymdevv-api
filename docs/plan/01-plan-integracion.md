# Fase 1 – Plan de Integración Multi-Tenant (`devgym` + `cross-fit-coach-app` + `gymdevv-api`)

> **Regla rectora**: Este plan respeta estrictamente las 21 tablas de `gymdevv-api/docs/schema/gym_coach_multitenant_v3.sql` y las 8 decisiones arquitectónicas fijadas. No se modifica código en esta fase.

---

## 1. Mapa de Pantallas del Producto Unificado por Rol y Módulo / Feature Flag

### 1.1. Estructura de `tenants.modulos` y `tenants.configuracion`
Al iniciar la app, el frontend consulta `GET /api/tenant/config` y recibe la configuración del tenant resuelto por dominio (`X-Tenant-Host`):

```json
{
  "nombre": "Box CrossFit Norte",
  "dominio": "boxnorte.tuapp.com",
  "logo_url": "https://...",
  "color_primario": "#f97316",
  "modulos": {
    "membresias": true,
    "pagos": true,
    "grupos_turnos": true,
    "asistencias": true,
    "entrenamiento": true,
    "rms": true,
    "comentarios": true,
    "creditos": false,
    "reservas": false
  },
  "configuracion": {
    "agenda": "fijo",
    "consumo_credito": "asistir",
    "cancelacion_horas": 2,
    "bloquear_plan_si_vencido": true,
    "alias_pago": "box.norte.mp",
    "telefono": "+54 11 5555-0000",
    "direccion": "Av. Libertador 1234",
    "horarios_texto": "Lun a Vie 7:00 a 22:00, Sáb 9:00 a 13:00"
  }
}
```
> *Nota*: `creditos` y `reservas` vienen **apagados por defecto** (`false`) conforme a la Decisión #5.

### 1.2. Mapa de Pantallas por Rol (`admin`, `coach`, `alumno`)

| Ruta en `devgym` | Pantalla / Funcionalidad | Roles con Acceso | Módulo / Feature Flag Requerido | Origen / Adaptación |
|---|---|---|---|---|
| `/login` | Inicio de sesión con branding del tenant (logo, nombre y color primario) | Público | Base (`siempre activo`) | `devgym/app/login/page.tsx` + `components/login-form.tsx` |
| `/admin` | Dashboard General: KPIs de alumnos activos, ingresos del mes, vencimientos próximos, asistencias del día y últimos comentarios de entrenamiento | `admin`, `coach` | Base (widgets condicionales según `modulos.*`) | Unifica `devgym/app/admin/page.tsx` + `coach-dashboard-view.tsx` |
| `/admin/alumnos` | Listado paginado de alumnos, alta/edición, creación/reseteo de acceso (`password`), asignación rápida de membresía y planes | `admin`, `coach` | Base (`siempre activo`) | `devgym/app/admin/socios/page.tsx` + `SocioDialog` |
| `/admin/alumnos/[id]` | Ficha integral 360° del alumno con tabs: **Resumen y Membresía**, **Planes Asignados y Progreso**, **RMs y Registros**, **Asistencias y Créditos**, **Pagos**, **Comentarios** | `admin`, `coach` | Base (tabs según `modulos.*`) | Portado de `coach/alumnos-view.tsx` (modal) y `coach/alumnos/[id]/page.tsx` con UI de `devgym` |
| `/admin/staff` | Gestión de usuarios `coach` y `admin` del gimnasio | `admin` | Base (`siempre activo`) | Adaptado de `devgym/app/admin/instructores/page.tsx` sobre `usuarios` |
| `/admin/tipos-membresia` | Catálogo de Tipos de Membresía (`mensual` o `creditos`, precio, duración en días, cantidad de créditos) | `admin` | `modulos.membresias` | `devgym/app/admin/planes/page.tsx` + `PlanDialog` (renombrado para evitar colisión con Planes de Entrenamiento) |
| `/admin/membresias` | Gestión de membresías asignadas a alumnos, filtros por estado, renovación y cobro inicial integrado | `admin`, `coach` (lectura/cobro según policy) | `modulos.membresias` | `devgym/app/admin/membresias/page.tsx` + `MembresiaDialog` |
| `/admin/pagos` | Libro de cobros, filtros por método/estado/fecha, registro de pago y anulación | `admin` | `modulos.pagos` | `devgym/app/admin/pagos/page.tsx` + `PagoDialog` |
| `/admin/grupos` | Gestión de Grupos, Turnos por día de semana (`0..6`), cupos e Inscripciones Fijas de alumnos a cada turno | `admin`, `coach` | `modulos.grupos_turnos` | Adaptado de `devgym/app/admin/clases/page.tsx` + `ClaseDialog` + nuevo drawer de `inscripciones_turno` |
| `/admin/asistencias` | Check-in de asistencia por turno del día y listado histórico | `admin`, `coach` | `modulos.asistencias` | `devgym/app/admin/asistencias/page.tsx` + `RegistrarAsistenciaDialog` |
| `/admin/ejercicios` | Catálogo de ejercicios del gimnasio (nombre, descripción, `video_url`) | `admin`, `coach` | `modulos.entrenamiento` | Nueva vista con tabla/dialog `shadcn/ui` + seed de `cross-fit-coach-app/lib/constants.ts` |
| `/admin/entrenamiento` | Catálogo de Planes de Entrenamiento (tarjetas de planes, contador de semanas y alumnos asignados) | `admin`, `coach` | `modulos.entrenamiento` | Portado de `cross-fit-coach-app/components/coach/planes-view.tsx` |
| `/admin/entrenamiento/[planId]` | Tablero Kanban de Semanas (`borrador`, `publicada`, `archivada`) con drag & drop (`@dnd-kit`), duplicación de semana, editor de Días (Markdown libre + bloques estructurados `dia_ejercicios` + `links_dia`) y comentarios de semana | `admin`, `coach` | `modulos.entrenamiento` | Portado de `cross-fit-coach-app/components/coach/kanban-board.tsx` adaptado a `shadcn/ui` |
| `/admin/comentarios` | Bandeja de comentarios de alumnos por semana/día con respuesta rápida del coach | `admin`, `coach` | `modulos.comentarios` | Portado de `coach-dashboard-view.tsx` + `kanban-board.tsx` |
| `/admin/configuracion` | Configuración del gimnasio: logo, color primario, datos de contacto, `alias_pago`, configuración de agenda (`fijo`/`reserva`/`mixto`), política de créditos y módulos | `admin` | Base (`siempre activo`) | Unifica `devgym/app/admin/configuracion/page.tsx` + `coach/config-form.tsx` conectado a `PUT /api/tenant/config` |
| `/client` | Inicio del Alumno: resumen de membresía activa (vencimiento, créditos restantes, alerta de vencimiento con `alias_pago`), racha semanal, próximas clases y acceso rápido al WOD del día | `alumno` | Base | Unifica `devgym/app/client/page.tsx` + alertas de `workout-dashboard.tsx` |
| `/client/entrenamiento` | Planificación del Alumno: selector de plan y semana publicada, carrusel de días, parser de RM/porcentajes/running + tabla de `dia_ejercicios`, links/videos de YouTube, botón `"Marcar día completado"` (`progreso_dias`), carga de `registros_entrenamiento` y comentarios del día | `alumno` (y `coach`/`admin` en modo "Mi Entrenamiento") | `modulos.entrenamiento` | Portado de `cross-fit-coach-app/components/student/workout-dashboard.tsx` |
| `/client/rms` | Mis RMs y Calculadora de Porcentajes (categorías, historial por ejercicio, gráfica de evolución y tabla 105%–30%) | `alumno` | `modulos.rms` | Portado de `cross-fit-coach-app/components/student/student-rms-view.tsx` |
| `/client/reservas` | Calendario semanal para reservar/cancelar turnos de clases | `alumno` | `modulos.reservas === true` y `configuracion.agenda in ['reserva','mixto']` | `devgym/app/client/book/page.tsx` |
| `/client/asistencias` | Historial de asistencias al gimnasio, reservas y libro de movimientos de créditos | `alumno` | `modulos.asistencias` | `devgym/app/client/history/page.tsx` |
| `/client/pagos` | Estado de cuenta, membresías e historial de pagos del alumno | `alumno` | `modulos.pagos` | Unifica `devgym/app/client/payments/page.tsx` + `coach/app/alumno/[id]/pagos/page.tsx` |
| `/client/perfil` | Datos personales y cambio de contraseña | `alumno`, `coach`, `admin` | Base (`siempre activo`) | `devgym/app/client/profile/page.tsx` |

---

## 2. Catálogo de Endpoints REST (`gymdevv-api`)

### 2.1. Convenciones de la API
- **Prefijo**: `/api` (todos los endpoints excepto `/api/tenant/config` y `/api/auth/login` requieren `auth:sanctum`).
- **Headers obligatorios desde el cliente**:
  - `X-Tenant-Host: <hostname>` (resuelto por middleware `ResolveTenant` en **todas** las rutas).
  - `Authorization: Bearer <token>` (en rutas autenticadas; el middleware `EnsureTenantMatch` verifica `auth()->user()->tenant_id === app('tenant')->id`, devolviendo `403 Forbidden` si no coincide).
  - `Accept: application/json`.
- **Formato de Respuesta Exitosa (API Resources de Laravel)**:
  - Recurso individual: `{ "data": { ... } }`
  - Colección paginada (`paginate(perPage)`):
    ```json
    {
      "data": [ ... ],
      "meta": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 20,
        "total": 94
      }
    }
    ```
- **Formato de Errores Estándar**:
  - `401 Unauthorized`: `{ "message": "Unauthenticated." }`
  - `403 Forbidden`: `{ "message": "No tienes permiso para realizar esta acción o el usuario no pertenece a este gimnasio." }`
  - `404 Not Found`: `{ "message": "Recurso no encontrado." }`
  - `422 Unprocessable Entity`: `{ "message": "Los datos proporcionados no son válidos.", "errors": { "email": ["El email ya está registrado en este gimnasio."] } }`

### 2.2. Catálogo por Módulo (16 Módulos)

#### 1) Módulo `auth`
| Método | Ruta | Request Body / Query | Response (`200`/`201`) | Roles | Tablas SQL v3 |
|---|---|---|---|---|---|
| `POST` | `/api/auth/login` | `{ email, password }` | `{ data: { token, usuario: UsuarioResource, alumno?: AlumnoResource } }` | Público (con `X-Tenant-Host`) | `tenants`, `usuarios`, `alumnos`, `personal_access_tokens` |
| `POST` | `/api/auth/logout` | - | `{ message: "Sesión cerrada" }` | `admin`, `coach`, `alumno` | `personal_access_tokens` |
| `GET` | `/api/auth/me` | - | `{ data: { usuario: UsuarioResource, alumno?: AlumnoResource, membresia_activa?: MembresiaResource } }` | `admin`, `coach`, `alumno` | `usuarios`, `alumnos`, `membresias` |
| `PUT` | `/api/perfil` | `{ nombre, apellido?, telefono?, avatar_url?, password_actual?, password? }` | `{ data: UsuarioResource }` | `admin`, `coach`, `alumno` | `usuarios` |

#### 2) Módulo `tenant/config`
| Método | Ruta | Request Body / Query | Response | Roles | Tablas SQL v3 |
|---|---|---|---|---|---|
| `GET` | `/api/tenant/config` | - | `{ data: { id, nombre, dominio, logo_url, color_primario, modulos, configuracion } }` | Público (con `X-Tenant-Host`) | `tenants` |
| `PUT` | `/api/tenant/config` | `{ nombre, logo_url?, color_primario, modulos?, configuracion }` | `{ data: TenantConfigResource }` | `admin` | `tenants` |
| `GET` | `/api/dashboard/stats` | `?mes=YYYY-MM` | `{ data: { alumnos_activos, ingresos_mes, ingresos_hoy, asistencias_hoy, membresias_por_vencer, membresias_vencidas, historial_ingresos_6m, comentarios_recientes } }` | `admin`, `coach` | `alumnos`, `membresias`, `pagos`, `asistencias`, `comentarios` |

#### 3) Módulo `alumnos` (y staff `usuarios`)
| Método | Ruta | Request Body / Query | Response | Roles | Tablas SQL v3 |
|---|---|---|---|---|---|
| `GET` | `/api/alumnos` | `?search=&estado=activo\|inactivo\|suspendido&page=&per_page=&all=` | Paginado de `AlumnoResource` (incluye `usuario`, `membresia_activa`, `saldo_creditos`, `planes_activos`) | `admin`, `coach` | `alumnos`, `usuarios`, `membresias`, `alumno_planes` |
| `POST` | `/api/alumnos` | `{ nombre, apellido?, email, telefono?, avatar_url?, fecha_alta?, estado?, notas?, crear_acceso?: bool, password?: string }` | `{ data: AlumnoResource }` (crea `usuarios` con `rol='alumno'` + `alumnos` en transacción) | `admin`, `coach` | `usuarios`, `alumnos` |
| `GET` | `/api/alumnos/{id}` | - | `{ data: AlumnoDetalleResource }` | `admin`, `coach` | `alumnos`, `usuarios`, `membresias`, `alumno_planes` |
| `PUT` | `/api/alumnos/{id}` | `{ nombre, apellido?, email, telefono?, avatar_url?, fecha_alta?, estado, notas? }` | `{ data: AlumnoResource }` | `admin`, `coach` | `usuarios`, `alumnos` |
| `DELETE` | `/api/alumnos/{id}` | - | `{ message }` (Soft delete en `alumnos` y `usuarios`) | `admin` | `alumnos`, `usuarios` |
| `POST` | `/api/alumnos/{id}/acceso` | `{ password }` | `{ data: AlumnoResource }` (habilita acceso o resetea contraseña hasheada con bcrypt) | `admin`, `coach` | `usuarios` |
| `GET` | `/api/usuarios` | `?rol=coach\|admin&search=` | `{ data: UsuarioResource[] }` | `admin` | `usuarios` |
| `POST` | `/api/usuarios` | `{ nombre, apellido?, email, telefono?, rol: 'coach'\|'admin', password, activo?: bool, crear_perfil_alumno?: bool }` | `{ data: UsuarioResource }` | `admin` | `usuarios`, `alumnos` |
| `PUT` | `/api/usuarios/{id}` | `{ nombre, apellido?, email, telefono?, rol?, password?, activo? }` | `{ data: UsuarioResource }` | `admin` | `usuarios` |
| `DELETE` | `/api/usuarios/{id}` | - | `{ message }` (Soft delete) | `admin` | `usuarios` |

#### 4) Módulo `membresías y tipos`
| Método | Ruta | Request Body / Query | Response | Roles | Tablas SQL v3 |
|---|---|---|---|---|---|
| `GET` | `/api/tipos-membresia` | `?activo=1` | `{ data: TipoMembresiaResource[] }` | `admin`, `coach`, `alumno` | `tipos_membresia` |
| `POST` | `/api/tipos-membresia` | `{ nombre, modalidad: 'mensual'\|'creditos', precio, duracion_dias?, creditos?, activo? }` | `{ data: TipoMembresiaResource }` | `admin` | `tipos_membresia` |
| `PUT` | `/api/tipos-membresia/{id}` | Igual a `POST` | `{ data: TipoMembresiaResource }` | `admin` | `tipos_membresia` |
| `DELETE` | `/api/tipos-membresia/{id}` | - | `{ message }` (o desactiva si tiene `membresias` asociadas por `ON DELETE RESTRICT`) | `admin` | `tipos_membresia` |
| `GET` | `/api/membresias` | `?alumno_id=&estado=activa\|vencida\|cancelada\|sin_creditos&search=&page=&per_page=` | Paginado de `MembresiaResource` | `admin`, `coach`, `alumno` (solo propias) | `membresias`, `tipos_membresia`, `alumnos` |
| `POST` | `/api/membresias` | `{ alumno_id, tipo_membresia_id, fecha_inicio, fecha_fin?, creditos_otorgados?, precio_pactado?, notas?, registrar_pago?: bool, metodo_pago?: string, monto_pago?: number }` | `{ data: MembresiaResource }` (Si `modalidad === 'creditos'`, inserta movimiento `+creditos_otorgados` con `motivo='alta_membresia'` en `movimientos_credito`; si `registrar_pago`, crea fila en `pagos`) | `admin`, `coach` | `membresias`, `tipos_membresia`, `movimientos_credito`, `pagos` |
| `GET` | `/api/membresias/{id}` | - | `{ data: MembresiaResource }` | `admin`, `coach`, `alumno` (propia) | `membresias` |
| `PUT` | `/api/membresias/{id}` | `{ fecha_inicio?, fecha_fin?, estado?, notas? }` | `{ data: MembresiaResource }` | `admin` | `membresias` |
| `DELETE` | `/api/membresias/{id}` | - | `{ message }` (`409` si tiene pagos/movimientos por `RESTRICT`, sugiriendo cambiar `estado='cancelada'`) | `admin` | `membresias` |

#### 5) Módulo `pagos`
| Método | Ruta | Request Body / Query | Response | Roles | Tablas SQL v3 |
|---|---|---|---|---|---|
| `GET` | `/api/pagos` | `?alumno_id=&membresia_id=&estado=pagado\|pendiente\|anulado&metodo=&fecha_desde=&fecha_hasta=&search=&page=&per_page=` | Paginado de `PagoResource` + `{ meta, totales: { recaudado, pendiente } }` | `admin`, `coach`, `alumno` (solo propios) | `pagos`, `membresias`, `alumnos`, `usuarios` |
| `POST` | `/api/pagos` | `{ alumno_id, membresia_id?, monto, fecha_pago, metodo, estado?: 'pagado'\|'pendiente', comprobante?, notas? }` | `{ data: PagoResource }` | `admin`, `coach` | `pagos`, `membresias` |
| `PUT` | `/api/pagos/{id}` | `{ estado: 'pagado'\|'pendiente'\|'anulado', metodo?, fecha_pago?, comprobante?, notas? }` | `{ data: PagoResource }` | `admin` | `pagos` |

#### 6) Módulo `grupos/turnos/inscripciones`
| Método | Ruta | Request Body / Query | Response | Roles | Tablas SQL v3 |
|---|---|---|---|---|---|
| `GET` | `/api/grupos` | `?activo=1` | `{ data: GrupoResource[] }` (incluye `turnos` y conteo de inscriptos) | `admin`, `coach`, `alumno` | `grupos`, `turnos`, `inscripciones_turno` |
| `POST` | `/api/grupos` | `{ nombre, descripcion?, cupo_default, activo?, turnos?: [{ dia_semana, hora_inicio, hora_fin, cupo? }] }` | `{ data: GrupoResource }` | `admin` | `grupos`, `turnos` |
| `PUT` | `/api/grupos/{id}` | `{ nombre, descripcion?, cupo_default, activo? }` | `{ data: GrupoResource }` | `admin` | `grupos` |
| `DELETE` | `/api/grupos/{id}` | - | `{ message }` | `admin` | `grupos` |
| `GET` | `/api/turnos` | `?grupo_id=&dia_semana=&activo=1` | `{ data: TurnoResource[] }` | `admin`, `coach`, `alumno` | `turnos`, `grupos` |
| `POST` | `/api/turnos` | `{ grupo_id, dia_semana, hora_inicio, hora_fin, cupo?, activo? }` | `{ data: TurnoResource }` | `admin` | `turnos` |
| `PUT` | `/api/turnos/{id}` | `{ dia_semana?, hora_inicio?, hora_fin?, cupo?, activo? }` | `{ data: TurnoResource }` | `admin` | `turnos` |
| `DELETE` | `/api/turnos/{id}` | - | `{ message }` | `admin` | `turnos` |
| `GET` | `/api/turnos/{id}/inscripciones` | - | `{ data: InscripcionTurnoResource[] }` | `admin`, `coach` | `inscripciones_turno`, `alumnos`, `usuarios` |
| `POST` | `/api/turnos/{id}/inscripciones` | `{ alumno_id, fecha_alta?, estado?: 'activa' }` | `{ data: InscripcionTurnoResource }` (valida cupo del turno/grupo) | `admin`, `coach` | `inscripciones_turno`, `turnos`, `grupos` |
| `DELETE` | `/api/inscripciones-turno/{id}` | - | `{ message }` (o actualiza `estado='baja'`) | `admin`, `coach` | `inscripciones_turno` |

#### 7) Módulo `asistencias`
| Método | Ruta | Request Body / Query | Response | Roles | Tablas SQL v3 |
|---|---|---|---|---|---|
| `GET` | `/api/asistencias` | `?alumno_id=&turno_id=&fecha_desde=&fecha_hasta=&page=&per_page=` | Paginado de `AsistenciaResource` | `admin`, `coach`, `alumno` (propias) | `asistencias`, `turnos`, `grupos`, `alumnos` |
| `POST` | `/api/asistencias` | `{ alumno_id, turno_id, fecha, presente?: bool, origen?: 'fijo'\|'reserva'\|'manual' }` | `{ data: AsistenciaResource }` (Verifica membresía vigente; si el tenant tiene `modulos.creditos` y `consumo_credito === 'asistir'` y no se descontó al reservar, registra `-1` en `movimientos_credito` con `motivo='asistencia'`) | `admin`, `coach` | `asistencias`, `membresias`, `movimientos_credito`, `reservas` |
| `DELETE` | `/api/asistencias/{id}` | - | `{ message }` | `admin`, `coach` | `asistencias` |

#### 8) Módulo `reservas` (Detrás de Feature Flag `modulos.reservas`)
| Método | Ruta | Request Body / Query | Response | Roles | Tablas SQL v3 |
|---|---|---|---|---|---|
| `GET` | `/api/turnos/disponibles` | `?fecha_desde=YYYY-MM-DD&fecha_hasta=YYYY-MM-DD` | `{ data: TurnoInstanciaResource[] }` (calcula por fecha y `dia_semana`: `cupo_efectivo`, `inscriptos_fijos`, `reservas_activas`, `cupos_disponibles`, `reservado_por_mi`) | `admin`, `coach`, `alumno` | `turnos`, `grupos`, `inscripciones_turno`, `reservas` |
| `GET` | `/api/reservas` | `?alumno_id=&fecha_desde=&fecha_hasta=&estado=reservada\|cancelada` | `{ data: ReservaResource[] }` | `admin`, `coach`, `alumno` (propias) | `reservas`, `turnos`, `grupos`, `alumnos` |
| `POST` | `/api/reservas` | `{ turno_id, fecha, alumno_id? }` | `{ data: ReservaResource }` (Valida cupo disponible, membresía activa y si `consumo_credito === 'reservar'` descuenta `-1` en `movimientos_credito` con `motivo='reserva'`) | `admin`, `coach`, `alumno` | `reservas`, `turnos`, `inscripciones_turno`, `membresias`, `movimientos_credito` |
| `DELETE` | `/api/reservas/{id}` | - | `{ data: ReservaResource }` (Cambia `estado='cancelada'`, `cancelada_at=now()`; si se cancela con más horas que `tenants.configuracion.cancelacion_horas` y había consumido crédito al reservar, inserta `+1` en `movimientos_credito` con `motivo='devolucion_cancelacion'`) | `admin`, `coach`, `alumno` (propia) | `reservas`, `movimientos_credito`, `tenants` |

#### 9) Módulo `créditos` (Detrás de Feature Flag `modulos.creditos`)
| Método | Ruta | Request Body / Query | Response | Roles | Tablas SQL v3 |
|---|---|---|---|---|---|
| `GET` | `/api/alumnos/{id}/creditos` | `?membresia_id=&page=&per_page=` | `{ data: MovimientoCreditoResource[], meta, saldo_actual: number }` (calculado con `SUM(cantidad)`) | `admin`, `coach`, `alumno` (propio) | `movimientos_credito`, `membresias` |
| `POST` | `/api/alumnos/{id}/creditos/ajuste` | `{ membresia_id?, cantidad: int, notas: string }` | `{ data: MovimientoCreditoResource, saldo_actual: number }` (inserta movimiento con `motivo='ajuste_manual'`) | `admin` | `movimientos_credito`, `membresias` |

#### 10) Módulo `ejercicios`
| Método | Ruta | Request Body / Query | Response | Roles | Tablas SQL v3 |
|---|---|---|---|---|---|
| `GET` | `/api/ejercicios` | `?search=` | `{ data: EjercicioResource[] }` | `admin`, `coach`, `alumno` | `ejercicios` |
| `POST` | `/api/ejercicios` | `{ nombre, descripcion?, video_url? }` | `{ data: EjercicioResource }` | `admin`, `coach` | `ejercicios` |
| `PUT` | `/api/ejercicios/{id}` | `{ nombre, descripcion?, video_url? }` | `{ data: EjercicioResource }` | `admin`, `coach` | `ejercicios` |
| `DELETE` | `/api/ejercicios/{id}` | - | `{ message }` (`409` si está en uso en `rms` o `dia_ejercicios`) | `admin`, `coach` | `ejercicios` |

#### 11) Módulo `planes/semanas/días/ejercicios del día/links`
| Método | Ruta | Request Body / Query | Response | Roles | Tablas SQL v3 |
|---|---|---|---|---|---|
| `GET` | `/api/planes` | `?activo=1&coach_id=` | `{ data: PlanResource[] }` (con conteo de `semanas` y `alumnos_activos`) | `admin`, `coach` | `planes`, `semanas`, `alumno_planes` |
| `POST` | `/api/planes` | `{ nombre, descripcion?, coach_id?, activo? }` | `{ data: PlanResource }` | `admin`, `coach` | `planes` |
| `GET` | `/api/planes/{id}` | - | `{ data: PlanDetalleResource }` (incluye `semanas` ordenadas por `numero`, cada una con sus `dias`, `dia_ejercicios` y `links_dia`) | `admin`, `coach` | `planes`, `semanas`, `dias`, `dia_ejercicios`, `links_dia` |
| `PUT` | `/api/planes/{id}` | `{ nombre, descripcion?, coach_id?, activo? }` | `{ data: PlanResource }` | `admin`, `coach` | `planes` |
| `DELETE` | `/api/planes/{id}` | - | `{ message }` | `admin`, `coach` | `planes` |
| `POST` | `/api/planes/{id}/semanas` | `{ titulo?, numero?, fecha_inicio?, notas?, estado?: 'borrador'\|'publicada'\|'archivada', crear_dias_default?: bool }` | `{ data: SemanaResource }` (si `crear_dias_default !== false`, crea los 6 días `Lunes`..`Sábado`, `numero_dia` 1..6, con `Miércoles` `es_descanso=true`) | `admin`, `coach` | `semanas`, `dias` |
| `POST` | `/api/semanas/{id}/duplicar` | `{ titulo?, fecha_inicio? }` | `{ data: SemanaResource }` (clona la semana con `numero = max + 1`, `estado = 'borrador'`, más sus `dias`, `dia_ejercicios` y `links_dia`) | `admin`, `coach` | `semanas`, `dias`, `dia_ejercicios`, `links_dia` |
| `PUT` | `/api/semanas/{id}` | `{ numero?, titulo?, fecha_inicio?, estado?, notas? }` | `{ data: SemanaResource }` (usado también por el drag & drop del Kanban) | `admin`, `coach` | `semanas` |
| `DELETE` | `/api/semanas/{id}` | - | `{ message }` | `admin`, `coach` | `semanas` |
| `PUT` | `/api/dias/{id}` | `{ nombre?, titulo?, contenido?, es_descanso?, notas? }` | `{ data: DiaResource }` | `admin`, `coach` | `dias` |
| `POST` | `/api/dias/{id}/ejercicios` | `{ ejercicio_id, bloque?, orden?, series?, repeticiones?, peso_sugerido?, porcentaje_rm?, rpe?, descanso_seg?, notas? }` | `{ data: DiaEjercicioResource }` | `admin`, `coach` | `dia_ejercicios` |
| `PUT` | `/api/dia-ejercicios/{id}` | Igual a `POST` | `{ data: DiaEjercicioResource }` | `admin`, `coach` | `dia_ejercicios` |
| `DELETE` | `/api/dia-ejercicios/{id}` | - | `{ message }` | `admin`, `coach` | `dia_ejercicios` |
| `POST` | `/api/dias/{id}/links` | `{ titulo?, url }` | `{ data: LinkDiaResource }` | `admin`, `coach` | `links_dia` |
| `DELETE` | `/api/links-dia/{id}` | - | `{ message }` | `admin`, `coach` | `links_dia` |

#### 12) Módulo `asignación de planes`
| Método | Ruta | Request Body / Query | Response | Roles | Tablas SQL v3 |
|---|---|---|---|---|---|
| `GET` | `/api/alumnos/{id}/planes` | `?activo=1` | `{ data: AlumnoPlanResource[] }` | `admin`, `coach`, `alumno` (propio) | `alumno_planes`, `planes` |
| `POST` | `/api/alumnos/{id}/planes` | `{ plan_id, fecha_inicio, fecha_fin?, activo?: true }` | `{ data: AlumnoPlanResource }` | `admin`, `coach` | `alumno_planes` |
| `PUT` | `/api/alumno-planes/{id}` | `{ fecha_inicio?, fecha_fin?, activo }` | `{ data: AlumnoPlanResource }` | `admin`, `coach` | `alumno_planes` |
| `DELETE` | `/api/alumno-planes/{id}` | - | `{ message }` | `admin`, `coach` | `alumno_planes` |

#### 13) Módulo `progreso de días` (y vista unificada del alumno)
| Método | Ruta | Request Body / Query | Response | Roles | Tablas SQL v3 |
|---|---|---|---|---|---|
| `GET` | `/api/mi-entrenamiento` | `?plan_id=&semana_id=` | `{ data: { bloqueado_por_cuota: bool, membresia_activa, planes_asignados, semana_actual: SemanaDetalleResource, progreso_dias_ids: number[], rms_alumno: RmResource[] } }` (Solo devuelve semanas con `estado = 'publicada'`; si `semanas.fecha_inicio` es `NULL`, resuelve la semana relativa a `alumno_planes.fecha_inicio`) | `alumno`, `coach`, `admin` | `alumno_planes`, `planes`, `semanas`, `dias`, `dia_ejercicios`, `links_dia`, `progreso_dias`, `rms`, `membresias` |
| `POST` | `/api/dias/{id}/progreso` | `{ notas_alumno? }` | `{ data: ProgresoDiaResource }` (`updateOrCreate` en `progreso_dias` para `(alumno_id, dia_id)` con `completado=1`, `completado_at=now()`) | `alumno`, `coach`, `admin` | `progreso_dias` |
| `DELETE` | `/api/dias/{id}/progreso` | - | `{ message }` (marca `completado=0`, `completado_at=null` o elimina el registro) | `alumno`, `coach`, `admin` | `progreso_dias` |

#### 14) Módulo `registros de entrenamiento`
| Método | Ruta | Request Body / Query | Response | Roles | Tablas SQL v3 |
|---|---|---|---|---|---|
| `GET` | `/api/registros-entrenamiento` | `?alumno_id=&dia_ejercicio_id=&ejercicio_id=&fecha_desde=&fecha_hasta=` | `{ data: RegistroEntrenamientoResource[] }` | `admin`, `coach`, `alumno` (propios) | `registros_entrenamiento`, `dia_ejercicios`, `ejercicios` |
| `POST` | `/api/registros-entrenamiento` | `{ dia_ejercicio_id, fecha, series_hechas?, repeticiones?, peso_usado?, rpe_real?, notas? }` | `{ data: RegistroEntrenamientoResource }` | `alumno`, `coach`, `admin` | `registros_entrenamiento` |
| `DELETE` | `/api/registros-entrenamiento/{id}` | - | `{ message }` | `alumno`, `coach`, `admin` | `registros_entrenamiento` |

#### 15) Módulo `RM`
| Método | Ruta | Request Body / Query | Response | Roles | Tablas SQL v3 |
|---|---|---|---|---|---|
| `GET` | `/api/rms` | `?alumno_id=&ejercicio_id=` | `{ data: RmResource[] }` (incluye `ejercicio` y cálculo de 1RM estimado Epley `kg * (1 + reps/30)` cuando `reps > 1`) | `admin`, `coach`, `alumno` (propios) | `rms`, `ejercicios` |
| `POST` | `/api/rms` | `{ alumno_id?, ejercicio_id?, nombre_ejercicio_custom?, kg, reps?: 1, fecha }` | `{ data: RmResource }` (Si el alumno envía un ejercicio personalizado no existente en `ejercicios`, lo busca o crea en `ejercicios` del tenant) | `admin`, `coach`, `alumno` | `rms`, `ejercicios` |
| `DELETE` | `/api/rms/{id}` | - | `{ message }` | `admin`, `coach`, `alumno` (propio) | `rms` |

#### 16) Módulo `comentarios`
| Método | Ruta | Request Body / Query | Response | Roles | Tablas SQL v3 |
|---|---|---|---|---|---|
| `GET` | `/api/comentarios` | `?alumno_id=&semana_id=&dia_id=&page=&per_page=` | Paginado de `ComentarioResource` (incluye `autor: { id, nombre, apellido, rol, avatar_url }`) | `admin`, `coach`, `alumno` (solo los de su `alumno_id`) | `comentarios`, `usuarios`, `alumnos` |
| `POST` | `/api/comentarios` | `{ alumno_id?, semana_id?, dia_id?, texto }` | `{ data: ComentarioResource }` (`autor_id = auth()->id()`; si es `alumno`, fuerza `alumno_id` al propio) | `admin`, `coach`, `alumno` | `comentarios` |
| `DELETE` | `/api/comentarios/{id}` | - | `{ message }` (solo autor o `admin`/`coach`) | `admin`, `coach`, `alumno` | `comentarios` |

---

## 3. Plan de Backend (`gymdevv-api`)

### 3.1. Orden de Migraciones Laravel (Derivadas de `gym_coach_multitenant_v3.sql`)
Se eliminará la migración por defecto `0001_01_01_000000_create_users_table.php` (reemplazándola por `usuarios` + `password_reset_tokens` + `sessions`) y se ejecutarán en orden estricto de dependencias de claves foráneas:

1. `0001_01_01_000001_create_cache_table.php` *(existente)*
2. `0001_01_01_000002_create_jobs_table.php` *(existente)*
3. `2026_10_11_222321_create_personal_access_tokens_table.php` *(existente)*
4. `2026_10_12_000001_create_tenants_table.php` $\rightarrow$ tabla `tenants`
5. `2026_10_12_000002_create_usuarios_table.php` $\rightarrow$ tabla `usuarios` (FK `tenant_id`, `UNIQUE(tenant_id, email)`, `softDeletes`), más `password_reset_tokens` y `sessions`
6. `2026_10_12_000003_create_alumnos_table.php` $\rightarrow$ tabla `alumnos` (FK `tenant_id`, FK `usuario_id` unique, `softDeletes`)
7. `2026_10_12_000004_create_tipos_membresia_table.php` $\rightarrow$ tabla `tipos_membresia` (FK `tenant_id`)
8. `2026_10_12_000005_create_membresias_table.php` $\rightarrow$ tabla `membresias` (FK `tenant_id`, FK `alumno_id` RESTRICT, FK `tipo_membresia_id` RESTRICT)
9. `2026_10_12_000006_create_pagos_table.php` $\rightarrow$ tabla `pagos` (FK `tenant_id`, FK `alumno_id` RESTRICT, FK `membresia_id` SET NULL)
10. `2026_10_12_000007_create_grupos_table.php` $\rightarrow$ tabla `grupos` (FK `tenant_id`)
11. `2026_10_12_000008_create_turnos_table.php` $\rightarrow$ tabla `turnos` (FK `tenant_id`, FK `grupo_id` CASCADE)
12. `2026_10_12_000009_create_inscripciones_turno_table.php` $\rightarrow$ tabla `inscripciones_turno` (FK `tenant_id`, FK `turno_id` CASCADE, FK `alumno_id` CASCADE, `UNIQUE(turno_id, alumno_id)`)
13. `2026_10_12_000010_create_asistencias_table.php` $\rightarrow$ tabla `asistencias` (FK `tenant_id`, FK `turno_id` RESTRICT, FK `alumno_id` RESTRICT, `UNIQUE(turno_id, alumno_id, fecha)`)
14. `2026_10_12_000011_create_reservas_table.php` $\rightarrow$ tabla `reservas` (FK `tenant_id`, FK `turno_id` RESTRICT, FK `alumno_id` CASCADE, `UNIQUE(turno_id, alumno_id, fecha)`)
15. `2026_10_12_000012_create_movimientos_credito_table.php` $\rightarrow$ tabla `movimientos_credito` (FK `tenant_id`, FK `alumno_id` RESTRICT, FK `membresia_id` RESTRICT, FK `reserva_id` SET NULL, FK `asistencia_id` SET NULL)
16. `2026_10_12_000013_create_ejercicios_table.php` $\rightarrow$ tabla `ejercicios` (FK `tenant_id`, `UNIQUE(tenant_id, nombre)`)
17. `2026_10_12_000014_create_planes_table.php` $\rightarrow$ tabla `planes` (FK `tenant_id`, FK `coach_id` SET NULL $\rightarrow$ `usuarios.id`)
18. `2026_10_12_000015_create_semanas_table.php` $\rightarrow$ tabla `semanas` (FK `tenant_id`, FK `plan_id` CASCADE, `UNIQUE(plan_id, numero)`)
19. `2026_10_12_000016_create_dias_table.php` $\rightarrow$ tabla `dias` (FK `tenant_id`, FK `semana_id` CASCADE, `UNIQUE(semana_id, numero_dia)`)
20. `2026_10_12_000017_create_dia_ejercicios_table.php` $\rightarrow$ tabla `dia_ejercicios` (FK `tenant_id`, FK `dia_id` CASCADE, FK `ejercicio_id` RESTRICT)
21. `2026_10_12_000018_create_links_dia_table.php` $\rightarrow$ tabla `links_dia` (FK `tenant_id`, FK `dia_id` CASCADE)
22. `2026_10_12_000019_create_alumno_planes_table.php` $\rightarrow$ tabla `alumno_planes` (FK `tenant_id`, FK `alumno_id` CASCADE, FK `plan_id` CASCADE)
23. `2026_10_12_000020_create_progreso_dias_table.php` $\rightarrow$ tabla `progreso_dias` (FK `tenant_id`, FK `alumno_id` CASCADE, FK `dia_id` CASCADE, `UNIQUE(alumno_id, dia_id)`)
24. `2026_10_12_000021_create_registros_entrenamiento_table.php` $\rightarrow$ tabla `registros_entrenamiento` (FK `tenant_id`, FK `alumno_id` CASCADE, FK `dia_ejercicio_id` CASCADE)
25. `2026_10_12_000022_create_rms_table.php` $\rightarrow$ tabla `rms` (FK `tenant_id`, FK `alumno_id` CASCADE, FK `ejercicio_id` RESTRICT)
26. `2026_10_12_000023_create_comentarios_table.php` $\rightarrow$ tabla `comentarios` (FK `tenant_id`, FK `alumno_id` CASCADE, FK `autor_id` CASCADE $\rightarrow$ `usuarios.id`, FK `semana_id` CASCADE, FK `dia_id` CASCADE)

### 3.2. Arquitectura Multi-Tenant (Middleware, Servicio y Trait)
1. **`App\Services\TenantContext`** (registrado como `scoped` en `AppServiceProvider`):
   - Almacena la instancia `Tenant` activa del request o job en curso (`setTenant(Tenant $t)`, `getTenant(): ?Tenant`, `id(): ?int`).
2. **`App\Http\Middleware\ResolveTenant`**:
   - Lee el header `X-Tenant-Host` (quitando puerto si viniera, ej. `localhost:3000` $\rightarrow$ `localhost`).
   - Busca `Tenant::where('dominio', $host)->where('activo', true)->first()`.
   - Si no existe o está inactivo, retorna `404 JSON` (`{ "message": "Gimnasio no encontrado o inactivo para el dominio indicado." }`).
   - Si existe, lo inyecta en `TenantContext`.
3. **`App\Http\Middleware\EnsureUserBelongsToTenant`**:
   - Se ejecuta en todas las rutas con `auth:sanctum`.
   - Verifica `$request->user()->tenant_id === app(TenantContext::class)->id()` y `$request->user()->activo === true`.
   - Si falla, retorna **`403 Forbidden`** (Decisión #1).
4. **`App\Models\Concerns\BelongsToTenant` (Trait)**:
   - En `bootBelongsToTenant()`:
     - Añade un `TenantScope` (`Illuminate\Database\Eloquent\Scope`) que aplica `$builder->where($model->getTable() . '.tenant_id', app(TenantContext::class)->id())`.
     - En el evento `creating`: asigna automáticamente `$model->tenant_id = app(TenantContext::class)->id()` si no fue provisto.
   - Se aplica a las **20 tablas de negocio** (`Usuario`, `Alumno`, `TipoMembresia`, `Membresia`, `Pago`, `Grupo`, `Turno`, `InscripcionTurno`, `Asistencia`, `Reserva`, `MovimientoCredito`, `Ejercicio`, `Plan`, `Semana`, `Dia`, `DiaEjercicio`, `LinkDia`, `AlumnoPlan`, `ProgresoDia`, `RegistroEntrenamiento`, `Rm`, `Comentario`).

### 3.3. Modelos Eloquent y Enums PHP 8.3 (`app/Enums/*`)
- **Enums tipados (`string`)**:
  - `RolUsuario`: `Admin = 'admin'`, `Coach = 'coach'`, `Alumno = 'alumno'`
  - `EstadoAlumno`: `Activo = 'activo'`, `Inactivo = 'inactivo'`, `Suspendido = 'suspendido'`
  - `ModalidadMembresia`: `Mensual = 'mensual'`, `Creditos = 'creditos'`
  - `EstadoMembresia`: `Activa = 'activa'`, `Vencida = 'vencida'`, `Cancelada = 'cancelada'`, `SinCreditos = 'sin_creditos'`
  - `EstadoPago`: `Pagado = 'pagado'`, `Pendiente = 'pendiente'`, `Anulado = 'anulado'`
  - `MetodoPago`: `Efectivo = 'efectivo'`, `Transferencia = 'transferencia'`, `MercadoPago = 'mercadopago'`, `Tarjeta = 'tarjeta'`
  - `EstadoInscripcionTurno`: `Activa = 'activa'`, `Pausada = 'pausada'`, `Baja = 'baja'`
  - `OrigenAsistencia`: `Fijo = 'fijo'`, `Reserva = 'reserva'`, `Manual = 'manual'`
  - `EstadoReserva`: `Reservada = 'reservada'`, `Cancelada = 'cancelada'`
  - `MotivoMovimientoCredito`: `AltaMembresia = 'alta_membresia'`, `Reserva = 'reserva'`, `Asistencia = 'asistencia'`, `DevolucionCancelacion = 'devolucion_cancelacion'`, `AjusteManual = 'ajuste_manual'`, `Vencimiento = 'vencimiento'`
  - `EstadoSemana`: `Borrador = 'borrador'`, `Publicada = 'publicada'`, `Archivada = 'archivada'`
- **Modelo `App\Models\Usuario`** (reemplaza `User`):
  - `protected $table = 'usuarios';`
  - Usa `HasApiTokens` (Sanctum), `SoftDeletes`, `BelongsToTenant`, `Notifiable`.
  - `password` es `nullable` y casteado a `'hashed'`.
  - Relaciones: `alumno(): HasOne`, `planesCreados(): HasMany`, `comentariosEscritos(): HasMany`.
  - actualizar `config/auth.php` para que el provider `users` apunte a `App\Models\Usuario::class`.

### 3.4. Policies por Rol (`app/Policies/*`) y Form Requests
- **`TenantPolicy` / `UsuarioPolicy`**: Solo `admin` puede editar configuración del tenant, crear/eliminar staff (`coach`/`admin`) y ajustar créditos manualmente.
- **`AlumnoPolicy`**: `admin` y `coach` pueden listar, crear y editar alumnos; solo `admin` puede eliminar (`softDelete`). Un `alumno` solo puede ver su propio perfil.
- **`MembresiaPolicy` y `PagoPolicy`**: `admin` tiene control total; `coach` puede listar y registrar membresías/pagos; `alumno` solo puede consultar sus propias membresías y pagos (`alumno_id === $user->alumno?->id`).
- **`PlanPolicy`, `SemanaPolicy`, `DiaPolicy`, `EjercicioPolicy`**: `admin` y `coach` pueden crear, editar, duplicar y eliminar. `alumno` solo tiene lectura sobre los planes asignados en `alumno_planes` y únicamente para semanas con `estado === 'publicada'`.
- **`ProgresoDiaPolicy`, `RegistroEntrenamientoPolicy`, `RmPolicy`, `ComentarioPolicy`**: `admin` y `coach` pueden ver/crear para cualquier alumno de su tenant; `alumno` solo puede crear/borrar sobre su propio `alumno_id`.
- **Validación en Form Requests con Unicidad por Tenant**:
  - Ejemplo en `StoreAlumnoRequest` / `StoreUsuarioRequest`:
    `Rule::unique('usuarios', 'email')->where('tenant_id', app(TenantContext::class)->id())`.
  - Validación de pertenencia al tenant en todas las claves foráneas (`Rule::exists('planes', 'id')->where('tenant_id', $tenantId)`).

---

## 4. Plan de Frontend (`devgym` Unificado)

### 4.1. Cliente de API Centralizado (`devgym/lib/api.ts` y Proxy)
1. **Inyección de `X-Tenant-Host`**:
   - En `devgym/lib/api.ts`, `getHeaders()` agregará siempre:
     `headers["X-Tenant-Host"] = typeof window !== "undefined" ? window.location.hostname : (process.env.NEXT_PUBLIC_DEFAULT_TENANT_HOST || "localhost");`
   - En `devgym/app/api/proxy/[...path]/route.ts`, al reenviar headers al backend Laravel, se asegurará de conservar `x-tenant-host` o tomar `request.headers.get("host")?.split(":")[0]`.
2. **Eliminación de Server Actions y Mocks**:
   - Ningún componente importará `@/lib/mock-data` ni `@/lib/mock-admin-data`.
   - Todas las vistas portadas de `cross-fit-coach-app` reemplazarán sus llamadas a `lib/actions.ts` por métodos tipados en `devgym/lib/api.ts`.
3. **Optimización de Sesión (`AuthContext` + `useRequireRole`)**:
   - `AuthContext` hidratará el usuario con `GET /api/auth/me` una única vez al montar la aplicación y `useRequireRole` leerá directamente del contexto en lugar de disparar un `GET /user` extra en cada cambio de página.

### 4.2. Tema Dinámico por Tenant y Menú por Módulos (`TenantProvider`)
1. Se creará `contexts/TenantContext.tsx` que al iniciar la app ejecuta `api.getTenantConfig()` (`GET /api/tenant/config`).
2. **Aplicación dinámica del tema (`color_primario`)**:
   - Inyecta en `document.documentElement.style` las variables `--primary`, `--ring` y `--sidebar-primary` a partir de `tenant.color_primario` (en CSS moderno / Tailwind v4 se puede asignar directamente el valor hex o convertirlo a `oklch`), además de actualizar el título de la pestaña y el logo en `AdminSidebar` y `AppHeader`.
3. **Menú lateral y navegación móvil condicional**:
   - `components/admin-sidebar.tsx` y `components/mobile-nav.tsx` filtrarán sus ítems según:
     - `user.rol` (`admin`, `coach`, `alumno`).
     - `tenant.modulos` (ej. si `modulos.entrenamiento === false`, oculta Planes, Ejercicios y RMs; si `modulos.reservas === false`, oculta Reservas).

### 4.3. Reutilización de Componentes de `devgym` y Portado desde `cross-fit-coach-app`
- **Se reutilizan de `devgym`**:
  - Todo el sistema de diseño `components/ui/*` (`shadcn/ui`), `AdminLayout`, `AdminSidebar`, `AppHeader`, `MobileNav`, `SocioDialog` (adaptado a `nombre` + `apellido`), `PlanDialog` (para `tipos_membresia`), `MembresiaDialog`, `PagoDialog`, `RegistrarAsistenciaDialog`, `ClassCard`.
- **Se portan desde `cross-fit-coach-app` hacia `devgym/components/entrenamiento/*`**:
  1. `planes-view.tsx` $\rightarrow$ `app/admin/entrenamiento/page.tsx`: usando `Card`, `Dialog`, `Badge` de `devgym`.
  2. `kanban-board.tsx` $\rightarrow$ `app/admin/entrenamiento/[planId]/page.tsx`: se instala `@dnd-kit/core`, `@dnd-kit/sortable` y `@dnd-kit/utilities` en `devgym/package.json`. Se adapta el modal de edición de día para soportar **tanto el editor Markdown libre (`dias.contenido`) como la grilla opcional de ejercicios estructurados (`dia_ejercicios`)** (Decisión #6).
  3. `workout-dashboard.tsx` $\rightarrow$ `components/entrenamiento/workout-dashboard.tsx` (usado en `/client/entrenamiento` y en el tab "Mi Entrenamiento" del coach): conserva intacto el parser inteligente de porcentajes de RM, rangos, predictor de running por distancia/RPE y embeds de YouTube, alimentado por `GET /api/mi-entrenamiento`.
  4. `student-rms-view.tsx` $\rightarrow$ `components/entrenamiento/rms-view.tsx` (usado en `/client/rms` y dentro de la ficha `/admin/alumnos/[id]`): adaptado a los componentes `Tabs`, `Card` y `Select` de `devgym`.

---

## 5. Plan de Migración de Datos (Solo Plan – No Ejecutar hasta M7)

### 5.1. Estrategia General y Tabla Temporal de Mapeo de IDs
Se construirá un comando Artisan idempotente (`php artisan migration:import-legacy {--tenant=} {--source-sqlite=} {--source-gym-db=}`) que corra dentro de una transacción por tenant y mantenga en memoria (y en un archivo JSON de auditoría) un diccionario de mapeo `cuid_o_id_viejo -> nuevo_bigint_id`.

### 5.2. Reglas de Transformación y Mapeo Detalladas

| Origen | Destino en SQL v3 | Regla de Transformación Exacta |
|---|---|---|
| **IDs `cuid` (Coach SQLite)** / **IDs int (`devgym`)** | `BIGINT UNSIGNED AUTO_INCREMENT` | Cada registro insertado en MySQL genera un `id` numérico incremental; el script guarda `$map['users'][$oldCuid] = $newUsuarioId` (y análogo para `alumnos`, `planes`, `semanas`, `dias`, `ejercicios`) para resolver todas las FKs hijas sin pérdida referencial. |
| **Nombre completo** (`User.nombre` en Coach / `Socio.nombre` en `devgym`) | `usuarios.nombre` + `usuarios.apellido` | Se divide por el primer espacio o último bloque según convención: `parts = explode(' ', trim($fullName), 2)` $\rightarrow$ `nombre = $parts[0]`, `apellido = $parts[1] ?? null`. |
| **Passwords de `User` (Coach SQLite)** | `usuarios.password` | En `cross-fit-coach-app` están en texto plano (`"alumno123"`, etc.). Al migrar, si no empieza con `$2y$`, se aplica `Hash::make($plainPassword)`. Si el socio de `devgym` no tenía usuario, `usuarios.password = NULL` (Decisión #2). |
| **Estados de `User` (Coach)** / **`Socio` (`devgym`)** | `alumnos.estado` | `"activo"` $\rightarrow$ `'activo'`; `"pausado"` (Coach) $\rightarrow$ `'suspendido'`; `"inactivo"` $\rightarrow$ `'inactivo'`; `"vencido"` (`devgym`) $\rightarrow$ `alumnos.estado = 'activo'` (el estado vencido pasa a `membresias.estado = 'vencida'`). |
| **`membresias.tipo` (texto) / `servicios` (`devgym`)** y **`User.cuota` (Coach)** | `tipos_membresia` + `membresias` | 1) En `devgym`, cada servicio de tipo plan se migra a `tipos_membresia` (`modalidad = creditos > 0 ? 'creditos' : 'mensual'`). Si existen membresías con texto libre en `tipo`, se agrupa por `(nombre_normalizado, precio)` y se crea el `TipoMembresia` correspondiente.<br>2) En `cross-fit-coach-app`, por cada valor distinto de `User.cuota > 0` se crea un `TipoMembresia` (`nombre: "Plan Mensual $" . $cuota`, `modalidad: 'mensual'`, `precio: $cuota`, `duracion_dias: 30`) y se crea una fila en `membresias` para el alumno con `fecha_fin = User.vencimiento`, `fecha_inicio = Carbon::parse($vencimiento)->subDays(30)`, `precio_pactado = User.cuota`, y `estado = ($vencimiento >= today) ? 'activa' : 'vencida'`. |
| **`Pago` (Coach SQLite)** | `pagos` | Se vincula al `alumno_id` migrado y a su `membresia_id` correspondiente. Mapeo de estado: `"pagado"` $\rightarrow$ `'pagado'`, `"pendiente"` o `"vencido"` $\rightarrow$ `'pendiente'`. |
| **`TipoPlan`, `Semana`, `Dia`, `Link` (Coach)** + **`planes` / `plan_ejercicios` (Gym)** | `planes` $\rightarrow$ `semanas` $\rightarrow$ `dias` $\rightarrow$ (`contenido` + `dia_ejercicios` + `links_dia`) | 1) Cada `TipoPlan` del coach se inserta en `planes` (`activo = 1`).<br>2) Cada `Semana` se inserta en `semanas` mapeando `estado`: `"planificacion"` $\rightarrow$ `'borrador'`, `"en-curso"` $\rightarrow$ `'publicada'`, `"finalizado"` $\rightarrow$ `'archivada'`. `fecha_inicio` vacío se guarda como `NULL`.<br>3) Cada `Dia` se inserta en `dias` convirtiendo `orden (0..5)` a `numero_dia (1..6)` (`orden + 1`), `nombre = Dia.dia`, `es_descanso = Dia.descanso`, y si `Dia.calentamiento` tiene texto, se antepone como `# CALENTAMIENTO\n{calentamiento}\n\n{contenido}` en `dias.contenido`.<br>4) Cada `Link` se inserta en `links_dia`.<br>5) Los planes estructurados por serie/repetición del gym antiguo (`planes` + `plan_ejercicios`) se crean como un `Plan` con una `Semana` (`numero = 1`, `estado = 'publicada'`, `fecha_inicio = NULL`) y sus ejercicios se vuelcan en `dia_ejercicios` convirtiendo el descanso a segundos en `descanso_seg`. |
| **`AlumnoPlan` (Coach)** | `alumno_planes` | Se inserta en `alumno_planes` con `alumno_id`, `plan_id`, `fecha_inicio = alumno.fecha_alta`, `activo = 1`. |
| **`Asistencia` (Coach SQLite)** | `progreso_dias` | En la app del coach, `Asistencia(userId, diaId, fecha, completado)` representa haber completado un día de entrenamiento (no el ingreso físico a un turno del gimnasio). Por lo tanto, se migra íntegramente a **`progreso_dias`** (`alumno_id`, `dia_id`, `completado = 1`, `completado_at = fecha`). |
| **`RM` (Coach SQLite)** | `ejercicios` + `rms` | Por cada nombre único de `RM.ejercicio`, se crea (si no existe) el registro en `ejercicios` del tenant y luego se inserta la fila en `rms` (`alumno_id`, `ejercicio_id`, `kg`, `reps`, `fecha`). |
| **`Comentario` (Coach SQLite)** | `comentarios` | Se resuelve `alumno_id` desde `Comentario.userId` y `autor_id` desde `autorRol === 'coach' ? coachUsuarioId : alumnoUsuarioId`, preservando `semana_id`, `dia_id`, `texto` y `created_at = fecha`. |

---

## 6. Milestones de Implementación (M0 a M7)

> **Evaluación del orden propuesto**: El orden `M0 -> M7` que propusiste es excelente y minimiza el riesgo porque primero asegura el aislamiento multi-tenant (`M0`–`M1`), luego alcanza paridad con la operación administrativa actual de `devgym` (`M2`–`M3`), después incorpora el corazón del coach (`M4`–`M5`), encapsula créditos/reservas bajo feature flag (`M6`) y cierra con migración real y hardening (`M7`). No requiere reordenamiento.

### M0 – Base Multi-Tenant + Auth + Migraciones
- **Alcance**: Configuración de MySQL 8 en `gymdevv-api`, creación de las 23 migraciones del esquema v3, modelos Eloquent con relaciones, enums PHP, `TenantContext`, middleware `ResolveTenant` + `EnsureUserBelongsToTenant`, trait `BelongsToTenant`, autenticación con Sanctum y seeder inicial de 2 tenants demo.
- **Endpoints incluidos**: `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/auth/me`, `GET /api/tenant/config`.
- **Pantallas incluidas**: Ninguna todavía (verificación vía tests automatizados y cliente HTTP).
- **Criterio de aceptación E2E**:
  1. `php artisan migrate:fresh --seed` crea las 21 tablas de negocio sin errores en MySQL 8.
  2. Login con un usuario del Tenant A enviando `X-Tenant-Host: tenant-a.local` devuelve `200` + token; usar ese mismo token enviando `X-Tenant-Host: tenant-b.local` en `GET /api/auth/me` devuelve `403 Forbidden`.

### M1 – Tenant Config + Shell del Front con Tema y Menú por Módulos
- **Alcance**: Adaptación de `devgym/lib/api.ts` y `/api/proxy` para enviar `X-Tenant-Host`, creación de `TenantContext` en `devgym`, aplicación de variables CSS dinámicas (`color_primario`, logo, nombre), unificación de roles (`admin`, `coach`, `alumno`) en `AuthContext`/`useRequireRole`, menú dinámico según `tenants.modulos` y pantalla `/admin/configuracion` conectada al backend.
- **Endpoints incluidos**: `GET /api/tenant/config`, `PUT /api/tenant/config`, `PUT /api/perfil`.
- **Pantallas incluidas**: `/login`, Layout `/admin/*`, Layout `/client/*`, `/admin/configuracion`, `/client/perfil`.
- **Criterio de aceptación E2E**:
  1. Al abrir `devgym` en dos hosts distintos (o cambiando el host configurado), la pantalla de login y el sidebar cambian automáticamente de nombre, logo, color primario y opciones de menú según `tenants.modulos`.
  2. El admin edita el color primario, `alias_pago` y módulos en `/admin/configuracion`, guarda (`PUT /api/tenant/config`) y al recargar se refleja inmediatamente.

### M2 – Alumnos + Tipos de Membresía + Membresías + Pagos (Paridad Operativa `devgym`)
- **Alcance**: ABM completo de alumnos (con creación/reseteo de cuenta de usuario), staff (`coach`/`admin`), catálogo de `tipos_membresia`, asignación de `membresias` con cobro opcional en el acto, registro de `pagos` y KPIs financieros del dashboard.
- **Endpoints incluidos**: Todos los de los módulos `alumnos`, `usuarios`, `membresías y tipos`, `pagos` y `GET /api/dashboard/stats`.
- **Pantallas incluidas**: `/admin`, `/admin/alumnos`, `/admin/staff`, `/admin/tipos-membresia`, `/admin/membresias`, `/admin/pagos`, `/client`, `/client/pagos`.
- **Criterio de aceptación E2E**:
  1. Un admin da de alta un alumno sin contraseña (`password = null`), le asigna una membresía mensual con cobro en efectivo desde `MembresiaDialog`, y el cobro impacta en `/admin/pagos` y en los KPIs de `/admin`.
  2. Luego le habilita acceso al alumno (`POST /api/alumnos/{id}/acceso`), el alumno inicia sesión y ve su membresía vigente y su pago en `/client` y `/client/pagos`.

### M3 – Grupos, Turnos, Inscripciones Fijas y Asistencias
- **Alcance**: ABM de `grupos` y `turnos` por día de semana, asignación de alumnos a turnos fijos (`inscripciones_turno`) validando cupo, y registro/listado de `asistencias` diarias.
- **Endpoints incluidos**: Todos los de los módulos `grupos/turnos/inscripciones` y `asistencias`.
- **Pantallas incluidas**: `/admin/grupos`, `/admin/asistencias`, `/client/asistencias`.
- **Criterio de aceptación E2E**:
  1. El admin crea el grupo "CrossFit 19hs" con turnos Lunes, Miércoles y Viernes (cupo 15), inscribe a un alumno en `inscripciones_turno`, registra su asistencia del día en `/admin/asistencias` y el alumno ve la asistencia reflejada en su historial y en su racha semanal en `/client`.

### M4 – Ejercicios + Planes + Semanas + Días (Núcleo del Coach)
- **Alcance**: Catálogo de `ejercicios` (con seeder de los ejercicios de CrossFit/Running), ABM de `planes`, tablero Kanban de `semanas` (`borrador`, `publicada`, `archivada`) con `@dnd-kit`, duplicación de semanas, editor de `dias` (contenido Markdown + bloques estructurados `dia_ejercicios` + `links_dia`).
- **Endpoints incluidos**: Todos los de los módulos `ejercicios` y `planes/semanas/días/ejercicios del día/links`.
- **Pantallas incluidas**: `/admin/ejercicios`, `/admin/entrenamiento`, `/admin/entrenamiento/[planId]`.
- **Criterio de aceptación E2E**:
  1. Un coach crea un plan, agrega la Semana 1 (se crean automáticamente los 6 días), edita el Lunes agregando texto con porcentajes (`Back Squat 5x5 al 80%`), un bloque en `dia_ejercicios` y un link de YouTube, duplica la semana a Semana 2 y la arrastra a la columna `Publicada` (`estado = 'publicada'`).

### M5 – Asignación de Planes + Vista del Alumno + Progreso + RM + Comentarios + Registros
- **Alcance**: Asignación de planes a alumnos (`alumno_planes`), ficha 360° del alumno (`/admin/alumnos/[id]`), vista de entrenamiento del alumno (`/client/entrenamiento`) con parser de RM/Running, bloqueo opcional por cuota vencida, marcado de día completado (`progreso_dias`), carga de resultados por ejercicio (`registros_entrenamiento`), calculadora e historial de `rms`, y sistema de `comentarios` por semana/día.
- **Endpoints incluidos**: Todos los de los módulos `asignación de planes`, `progreso de días`, `registros de entrenamiento`, `RM` y `comentarios`.
- **Pantallas incluidas**: `/admin/alumnos/[id]`, `/admin/comentarios`, `/client/entrenamiento`, `/client/rms`.
- **Criterio de aceptación E2E**:
  1. Un alumno con RM de `Back Squat = 100 kg` y `1k = 4:00 (240s)` abre `/client/entrenamiento`: ve únicamente las semanas `publicadas` de su plan, el parser le muestra automáticamente `con 80kg` en `Back Squat 80%` y los tiempos proyectados de carrera, marca el día como completado, deja un comentario al coach y el coach lo ve en `/admin/comentarios` y en la ficha del alumno.

### M6 – Créditos + Reservas (Bajo Feature Flag)
- **Alcance**: Libro mayor de créditos (`movimientos_credito`) en el alta de membresías por créditos, descuento automático al reservar o al asistir según `tenants.configuracion.consumo_credito`, devolución automática al cancelar dentro de `cancelacion_horas`, ajustes manuales y calendario semanal de reservas del alumno.
- **Endpoints incluidos**: Todos los de los módulos `reservas` y `créditos`.
- **Pantallas incluidas**: `/client/reservas`, pestaña de créditos en `/admin/alumnos/[id]` y `/client/asistencias`.
- **Criterio de aceptación E2E**:
  1. En un tenant con `modulos.creditos = true`, `modulos.reservas = true` y `consumo_credito = 'reservar'`, al asignarle un pack de 8 clases a un alumno se crea el movimiento `+8` (`alta_membresia`). El alumno reserva un turno (`-1`, saldo `7`), lo cancela con antelación suficiente (`+1`, saldo `8`) y todo queda trazado en `movimientos_credito`.

### M7 – Migración de Datos + Hardening Final
- **Alcance**: Desarrollo y prueba del comando de migración de datos reales desde SQLite (`cross-fit-coach-app/dev.db`) y base de `devgym`, pruebas exhaustivas de aislamiento multi-tenant (incluyendo colas y caché), limpieza de archivos muertos/mocks y auditoría de performance (índices y eager loading).
- **Criterio de aceptación E2E**:
  1. Ejecución del importador en entorno de staging migrando el 100% de usuarios, alumnos, planes, semanas, días, RMs, asistencias (`progreso_dias`) y pagos, verificando conteos exactos y navegación real con cuentas migradas.

---

## 7. Estrategia de Pruebas (`tests/Feature/*`)

1. **Tests de Aislamiento Estricto entre Tenants (`TenantIsolationTest`)**:
   - **Consultas y Escrituras HTTP**: Crear datos en `Tenant A` y `Tenant B` (alumnos, membresías, pagos, planes, RMs). Autenticar como `admin` de `Tenant A` y verificar que:
     - `GET /api/alumnos` solo retorna registros de `Tenant A` (`count` exacto).
     - `GET /api/alumnos/{id_de_tenant_B}` retorna `404 Not Found` (gracias al global scope `BelongsToTenant`).
     - Enviar un token válido de `Tenant A` con header `X-Tenant-Host: tenant-b.test` retorna `403 Forbidden` en cualquier endpoint.
     - Intentar forzar `"tenant_id": $tenantB->id` en el payload de `POST /api/alumnos` o `POST /api/planes` ignora el valor del body y asigna el `tenant_id` de `TenantContext`.
   - **Jobs y Colas (`TenantAwareJobTest`)**:
     - Configurar un middleware de Queue o serialización en base job que adjunte `tenant_id` al despachar el job y restaure `TenantContext::setTenant(...)` antes de ejecutar `handle()`, testeando que un job encolado durante un request de `Tenant A` opere exclusivamente sobre registros de `Tenant A`.
   - **Claves de Caché (`TenantCacheTest`)**:
     - Prefijar cualquier uso de caché de negocio con `tenant_{id}_...` y verificar que dos tenants consultando métricas cacheadas nunca reciban datos cruzados.

2. **Tests de Policies por Rol (`RoleAuthorizationTest`)**:
   - Matriz automatizada probando cada grupo de endpoints con usuarios de rol `admin`, `coach` y `alumno`:
     - `alumno` no puede crear planes, ni ver alumnos ajenos, ni editar configuración del tenant (`403`).
     - `alumno` no puede ver semanas en estado `'borrador'` ni `'archivada'`.
     - `coach` puede gestionar planes, semanas, días, RMs y asistencias, pero no puede eliminar alumnos ni modificar `PUT /api/tenant/config` (`403`).

3. **Seeders con 2 Tenants Demo (`DatabaseSeeder` + `DemoTenantsSeeder`)**:
   - **Tenant 1 (`"Box CrossFit Central"`, dominio `crossfit.localhost`)**: Color `#f97316`, enfoque entrenamiento + membresía mensual (`agenda: "fijo"`), con 1 admin, 1 coach, 10 alumnos, catálogo de ejercicios CrossFit/Running, 2 planes con semanas publicadas y en borrador, RMs cargados y comentarios.
   - **Tenant 2 (`"FitStudio Palermo"`, dominio `fitstudio.localhost`)**: Color `#10b981`, enfoque turnos + créditos + reservas activas (`agenda: "mixto"`, `creditos: true`, `reservas: true`), con 1 admin, 2 coaches, 10 alumnos con packs de créditos, grupos, turnos y reservas.

---

## 8. Riesgos y Preguntas Abiertas (Lista Única Priorizada)

1. **[ALTA – Esquema vs Vistas] Módulo de Gastos / Salidas (`devgym/app/admin/salidas/page.tsx`)**:
   - `devgym` tiene implementado el ABM de gastos/salidas del gimnasio y el cálculo de pago a instructores por clases dictadas, pero `gym_coach_multitenant_v3.sql` no incluye tabla de `salidas`.
   - **Decisión requerida**: ¿Ocultamos/desactivamos la sección de Salidas/Gastos del frontend en este lanzamiento para respetar el SQL v3 sin cambios, o agregamos una tabla `salidas`?
2. **[ALTA – Esquema vs Vistas] Unidades y Categorías de Ejercicios para el Parser de RM y Running**:
   - El parser de `workout-dashboard.tsx` y la vista de RMs (`student-rms-view.tsx`) distinguen entre **Peso (`kg`)**, **Capacidad (`reps`)** y **Running/Tiempo (`segundos`)**, agrupados en 4 categorías (`Fuerza`, `Weightlifting`, `Capacidades`, `Running`). En SQL v3 `ejercicios` solo tiene `(nombre, descripcion, video_url)`.
   - **Decisión requerida**: ¿Agregamos las columnas `categoria VARCHAR(40)` y `unidad VARCHAR(20) DEFAULT 'kg'` (`kg` | `reps` | `segundos`) a la tabla `ejercicios`, o preferís no tocar el SQL v3 y guardar esa metadata en un catálogo/convención dentro de `descripcion`?
3. **[MEDIA – Roles y Pantallas] Destino de las pantallas `/reception/*` de `devgym`**:
   - La Decisión #2 establece exactamente 3 roles (`admin`, `coach`, `alumno`), mientras que `devgym` tenía una sección `/reception/*` (toda con datos mock) pensada para un rol `gym_staff`.
   - **Decisión requerida**: ¿Eliminamos las rutas `/reception/*` y concentramos el check-in de asistencia y búsqueda de socios en `/admin/asistencias` y `/admin/alumnos` (accesibles tanto por `admin` como por `coach`)?
4. **[MEDIA – Regla de Negocio] Bloqueo del Plan de Entrenamiento cuando la Membresía está Vencida**:
   - En `cross-fit-coach-app` (`workout-dashboard.tsx:155-165`), si la cuota del alumno venció (`daysLeft < 0`), el alumno no puede ver su entrenamiento (`"Plan Bloqueado"`).
   - **Decisión requerida**: ¿Mantenemos este bloqueo activo para todos los gimnasios o lo parametrizamos como un flag booleano `bloquear_plan_si_vencido` dentro de `tenants.configuracion` (activo por defecto)?
5. **[MEDIA – Esquema vs Vistas] Asistencia en "Acceso Libre" vs `asistencias.turno_id NOT NULL`**:
   - En SQL v3 `asistencias.turno_id` es obligatorio (`NOT NULL`), mientras que `devgym` permitía registrar asistencias sin turno ("Acceso Libre").
   - **Decisión requerida**: ¿Creamos por defecto un Grupo/Turno comodín ("Horario Libre / Sala") en cada tenant para imputar esas asistencias sin alterar la restricción `NOT NULL` del SQL v3?
6. **[BAJA – Esquema vs Vistas] Campos extra de Socio (`dni`, `direccion`, `fecha_nacimiento`) y Coach en Grupos/Turnos**:
   - `devgym` pide `dni`, `direccion` y `fechaNacimiento` en el alta de socio, y permite asignar un instructor a cada clase (`id_coach`). Ninguno de esos campos está en `alumnos` ni en `grupos`/`turnos` del SQL v3.
   - **Decisión requerida**: ¿Simplificamos los formularios de `devgym` para que pidan únicamente los campos presentes en SQL v3 (`nombre`, `apellido`, `email`, `telefono`, `avatar_url`, `notas`), o querés sumar alguno de esos campos al esquema?
