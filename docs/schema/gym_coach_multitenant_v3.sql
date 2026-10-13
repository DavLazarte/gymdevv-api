-- =====================================================================
-- Gimnasio + Coach  |  Multi-tenant (una DB, tenant_id en todo)
-- MySQL 8 / InnoDB / utf8mb4
-- Base: DBML real de la app del gimnasio + esquema de la app del coach.
-- v3: membresías mensuales Y por créditos; agenda por turnos fijos y/o reservas.
-- Las tablas propias de Laravel (personal_access_tokens, jobs, cache,
-- sessions, migrations) las crea Laravel, no van acá.
-- Estados/roles como VARCHAR + enum de PHP.
-- En Laravel: Usuario con  protected $table = 'usuarios';
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- TENANTS
-- ---------------------------------------------------------------------
CREATE TABLE tenants (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre          VARCHAR(150) NOT NULL,
  slug            VARCHAR(80)  NOT NULL,
  dominio         VARCHAR(190) NOT NULL,              -- host que resuelve el tenant
  logo_url        VARCHAR(500) NULL,
  color_primario  VARCHAR(9)   NOT NULL DEFAULT '#f97316',
  modulos         JSON NULL,                          -- {"turnos":true,"planificacion":true,"cobros":true}
  configuracion   JSON NULL,                          -- {"agenda":"fijo|reserva|mixto","consumo_credito":"reserva|asistencia","cancelacion_horas":2}
  activo          TINYINT(1)   NOT NULL DEFAULT 1,
  created_at      TIMESTAMP NULL,
  updated_at      TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY tenants_slug_unique (slug),
  UNIQUE KEY tenants_dominio_unique (dominio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- USUARIOS  (gym: nombre/apellido/telefono/rol  +  coach: password/avatar)
-- password NULL = alumno cargado por el gimnasio sin acceso a la app
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id       BIGINT UNSIGNED NOT NULL,
  nombre          VARCHAR(100) NOT NULL,
  apellido        VARCHAR(100) NULL,
  email           VARCHAR(190) NOT NULL,
  telefono        VARCHAR(40)  NULL,
  password        VARCHAR(255) NULL,
  rol             VARCHAR(20)  NOT NULL,              -- admin | coach | alumno
  avatar_url      VARCHAR(500) NULL,
  activo          TINYINT(1)   NOT NULL DEFAULT 1,
  remember_token  VARCHAR(100) NULL,
  created_at      TIMESTAMP NULL,
  updated_at      TIMESTAMP NULL,
  deleted_at      TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY usuarios_tenant_email_unique (tenant_id, email),
  KEY usuarios_tenant_rol_index (tenant_id, rol),
  CONSTRAINT usuarios_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alumnos (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id   BIGINT UNSIGNED NOT NULL,
  usuario_id  BIGINT UNSIGNED NOT NULL,
  fecha_alta  DATE NULL,
  estado      VARCHAR(20) NOT NULL DEFAULT 'activo',  -- activo | inactivo | suspendido
  created_at  TIMESTAMP NULL,
  updated_at  TIMESTAMP NULL,
  deleted_at  TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY alumnos_usuario_unique (usuario_id),
  KEY alumnos_tenant_estado_index (tenant_id, estado),
  CONSTRAINT alumnos_tenant_fk  FOREIGN KEY (tenant_id)  REFERENCES tenants (id)  ON DELETE RESTRICT,
  CONSTRAINT alumnos_usuario_fk FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MEMBRESÍAS Y COBROS
-- =====================================================================

-- Catálogo: reemplaza membresias.tipo (texto libre)
-- modalidad mensual  : vale por periodo (duracion_dias); clases_por_semana = tope opcional
-- modalidad creditos : pack de N usos (creditos); duracion_dias = vigencia del pack (NULL = no vence)
CREATE TABLE tipos_membresia (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id      BIGINT UNSIGNED NOT NULL,
  nombre         VARCHAR(100) NOT NULL,
  precio         DECIMAL(12,2) NOT NULL,
  modalidad      VARCHAR(20) NOT NULL DEFAULT 'mensual',   -- mensual | creditos
  creditos       SMALLINT UNSIGNED NULL,
  clases_por_semana TINYINT UNSIGNED NULL,
  duracion_dias  SMALLINT UNSIGNED NULL DEFAULT 30,
  activo         TINYINT(1) NOT NULL DEFAULT 1,
  created_at     TIMESTAMP NULL,
  updated_at     TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY tipos_membresia_tenant_nombre_unique (tenant_id, nombre),
  CONSTRAINT tipos_membresia_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- cuota = precio pactado con este alumno (reemplaza User.cuota del coach)
-- fecha_fin = fuente de verdad del vencimiento (reemplaza User.vencimiento del coach)
CREATE TABLE membresias (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id           BIGINT UNSIGNED NOT NULL,
  alumno_id           BIGINT UNSIGNED NOT NULL,
  tipo_membresia_id   BIGINT UNSIGNED NOT NULL,
  cuota               DECIMAL(12,2) NOT NULL,
  fecha_inicio        DATE NOT NULL,
  fecha_fin           DATE NULL,                           -- NULL = pack de créditos sin vencimiento
  estado              VARCHAR(20) NOT NULL DEFAULT 'activa', -- activa | vencida | pausada | cancelada
  created_at          TIMESTAMP NULL,
  updated_at          TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY membresias_tenant_fin_index (tenant_id, fecha_fin),
  KEY membresias_alumno_index (alumno_id),
  CONSTRAINT membresias_tenant_fk FOREIGN KEY (tenant_id)         REFERENCES tenants (id)         ON DELETE RESTRICT,
  CONSTRAINT membresias_alumno_fk FOREIGN KEY (alumno_id)         REFERENCES alumnos (id)         ON DELETE RESTRICT,
  CONSTRAINT membresias_tipo_fk   FOREIGN KEY (tipo_membresia_id) REFERENCES tipos_membresia (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gym: fecha, vencimiento, estado, monto  +  coach: metodo, nota
CREATE TABLE pagos (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id     BIGINT UNSIGNED NOT NULL,
  alumno_id     BIGINT UNSIGNED NOT NULL,
  membresia_id  BIGINT UNSIGNED NULL,
  monto         DECIMAL(12,2) NOT NULL,
  fecha         DATE NOT NULL,
  vencimiento   DATE NULL,                             -- fecha límite de ese cobro
  estado        VARCHAR(20) NOT NULL,                  -- pagado | pendiente | anulado
  metodo        VARCHAR(30) NULL,                      -- efectivo | transferencia | tarjeta | mercadopago
  nota          VARCHAR(255) NULL,
  created_at    TIMESTAMP NULL,
  updated_at    TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY pagos_tenant_fecha_index (tenant_id, fecha),
  KEY pagos_alumno_index (alumno_id),
  CONSTRAINT pagos_tenant_fk    FOREIGN KEY (tenant_id)    REFERENCES tenants (id)    ON DELETE RESTRICT,
  CONSTRAINT pagos_alumno_fk    FOREIGN KEY (alumno_id)    REFERENCES alumnos (id)    ON DELETE RESTRICT,
  CONSTRAINT pagos_membresia_fk FOREIGN KEY (membresia_id) REFERENCES membresias (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- GRUPOS, TURNOS, RESERVAS, ASISTENCIA Y CRÉDITOS
-- =====================================================================

CREATE TABLE grupos (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id  BIGINT UNSIGNED NOT NULL,
  nombre     VARCHAR(100) NOT NULL,
  capacidad  SMALLINT UNSIGNED NOT NULL,
  estado     VARCHAR(20) NOT NULL DEFAULT 'activo',
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY grupos_tenant_index (tenant_id),
  CONSTRAINT grupos_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE turnos (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id    BIGINT UNSIGNED NOT NULL,
  grupo_id     BIGINT UNSIGNED NOT NULL,
  dia_semana   TINYINT UNSIGNED NOT NULL,              -- 1 = lunes ... 7 = domingo
  hora_inicio  TIME NOT NULL,
  hora_fin     TIME NOT NULL,
  created_at   TIMESTAMP NULL,
  updated_at   TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY turnos_tenant_dia_index (tenant_id, dia_semana, hora_inicio),
  KEY turnos_grupo_index (grupo_id),
  CONSTRAINT turnos_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
  CONSTRAINT turnos_grupo_fk  FOREIGN KEY (grupo_id)  REFERENCES grupos (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inscripción fija de un alumno a un turno (con historial por fecha_alta/fecha_baja)
CREATE TABLE alumnos_turnos (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id   BIGINT UNSIGNED NOT NULL,
  alumno_id   BIGINT UNSIGNED NOT NULL,
  turno_id    BIGINT UNSIGNED NOT NULL,
  fecha_alta  DATE NOT NULL,
  fecha_baja  DATE NULL,
  estado      VARCHAR(20) NOT NULL DEFAULT 'activo',
  created_at  TIMESTAMP NULL,
  updated_at  TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY alumnos_turnos_turno_estado_index (turno_id, estado),
  KEY alumnos_turnos_alumno_index (alumno_id),
  KEY alumnos_turnos_tenant_index (tenant_id),
  CONSTRAINT alumnos_turnos_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
  CONSTRAINT alumnos_turnos_alumno_fk FOREIGN KEY (alumno_id) REFERENCES alumnos (id) ON DELETE CASCADE,
  CONSTRAINT alumnos_turnos_turno_fk  FOREIGN KEY (turno_id)  REFERENCES turnos (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reserva puntual de un alumno a un turno en una fecha (agenda reserva o mixto).
-- Con agenda fijo no se usa. membresia_id = con qué membresía/pack se reservó.
CREATE TABLE reservas (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id     BIGINT UNSIGNED NOT NULL,
  turno_id      BIGINT UNSIGNED NOT NULL,
  alumno_id     BIGINT UNSIGNED NOT NULL,
  membresia_id  BIGINT UNSIGNED NULL,
  fecha         DATE NOT NULL,
  estado        VARCHAR(20) NOT NULL DEFAULT 'reservada',  -- reservada | cancelada
  cancelada_en  DATETIME NULL,
  created_at    TIMESTAMP NULL,
  updated_at    TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY reservas_turno_alumno_fecha_unique (turno_id, alumno_id, fecha),
  KEY reservas_tenant_fecha_index (tenant_id, fecha),
  KEY reservas_alumno_index (alumno_id),
  CONSTRAINT reservas_tenant_fk    FOREIGN KEY (tenant_id)    REFERENCES tenants (id)    ON DELETE RESTRICT,
  CONSTRAINT reservas_turno_fk     FOREIGN KEY (turno_id)     REFERENCES turnos (id)     ON DELETE RESTRICT,
  CONSTRAINT reservas_alumno_fk    FOREIGN KEY (alumno_id)    REFERENCES alumnos (id)    ON DELETE RESTRICT,
  CONSTRAINT reservas_membresia_fk FOREIGN KEY (membresia_id) REFERENCES membresias (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Asistencia real al gimnasio (un registro por alumno, turno y fecha).
-- Sirve para turnos fijos y para reservas: siempre es el único lugar que dice quién vino.
CREATE TABLE asistencias (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id   BIGINT UNSIGNED NOT NULL,
  alumno_id   BIGINT UNSIGNED NOT NULL,
  turno_id    BIGINT UNSIGNED NOT NULL,
  fecha       DATE NOT NULL,
  estado      VARCHAR(20) NOT NULL,                    -- presente | ausente | justificado
  reserva_id  BIGINT UNSIGNED NULL,
  membresia_id BIGINT UNSIGNED NULL,                   -- membresía/pack con la que se usó
  created_at  TIMESTAMP NULL,
  updated_at  TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY asistencias_alumno_turno_fecha_unique (alumno_id, turno_id, fecha),
  KEY asistencias_tenant_fecha_index (tenant_id, fecha),
  CONSTRAINT asistencias_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
  CONSTRAINT asistencias_alumno_fk FOREIGN KEY (alumno_id) REFERENCES alumnos (id) ON DELETE RESTRICT,
  CONSTRAINT asistencias_turno_fk  FOREIGN KEY (turno_id)  REFERENCES turnos (id)  ON DELETE RESTRICT,
  CONSTRAINT asistencias_reserva_fk    FOREIGN KEY (reserva_id)   REFERENCES reservas (id)   ON DELETE SET NULL,
  CONSTRAINT asistencias_membresia_fk  FOREIGN KEY (membresia_id) REFERENCES membresias (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Libro de créditos: nunca se edita un saldo, se agregan movimientos.
-- Saldo de una membresía = SUM(cantidad) por membresia_id, vigente si
-- membresias.fecha_fin IS NULL OR fecha_fin >= CURDATE() (no hace falta job de vencimiento).
-- cantidad: + carga / devolucion, - consumo (ajuste puede ser ambos).
CREATE TABLE movimientos_credito (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id      BIGINT UNSIGNED NOT NULL,
  alumno_id      BIGINT UNSIGNED NOT NULL,
  membresia_id   BIGINT UNSIGNED NOT NULL,
  cantidad       SMALLINT NOT NULL,
  motivo         VARCHAR(20) NOT NULL,                 -- carga | consumo | devolucion | ajuste
  reserva_id     BIGINT UNSIGNED NULL,
  asistencia_id  BIGINT UNSIGNED NULL,
  usuario_id     BIGINT UNSIGNED NULL,                 -- quién lo registró
  nota           VARCHAR(255) NULL,
  created_at     TIMESTAMP NULL,
  updated_at     TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY movimientos_credito_membresia_index (membresia_id),
  KEY movimientos_credito_tenant_fecha_index (tenant_id, created_at),
  CONSTRAINT movimientos_credito_tenant_fk     FOREIGN KEY (tenant_id)     REFERENCES tenants (id)     ON DELETE RESTRICT,
  CONSTRAINT movimientos_credito_alumno_fk     FOREIGN KEY (alumno_id)     REFERENCES alumnos (id)     ON DELETE RESTRICT,
  CONSTRAINT movimientos_credito_membresia_fk  FOREIGN KEY (membresia_id)  REFERENCES membresias (id)  ON DELETE RESTRICT,
  CONSTRAINT movimientos_credito_reserva_fk    FOREIGN KEY (reserva_id)    REFERENCES reservas (id)    ON DELETE SET NULL,
  CONSTRAINT movimientos_credito_asistencia_fk FOREIGN KEY (asistencia_id) REFERENCES asistencias (id) ON DELETE SET NULL,
  CONSTRAINT movimientos_credito_usuario_fk    FOREIGN KEY (usuario_id)    REFERENCES usuarios (id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- ENTRENAMIENTO (unifica planes del gym + TipoPlan/Semana/Dia del coach)
-- planes -> semanas -> dias -> (contenido libre y/o dia_ejercicios)
-- =====================================================================

CREATE TABLE ejercicios (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id    BIGINT UNSIGNED NOT NULL,
  nombre       VARCHAR(120) NOT NULL,
  descripcion  TEXT NULL,
  video_url    VARCHAR(500) NULL,
  created_at   TIMESTAMP NULL,
  updated_at   TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY ejercicios_tenant_nombre_unique (tenant_id, nombre),
  CONSTRAINT ejercicios_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gym: planes(nombre, tipo, descripcion, estado)  +  coach: TipoPlan(color, icono)
CREATE TABLE planes (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id    BIGINT UNSIGNED NOT NULL,
  coach_id     BIGINT UNSIGNED NULL,                   -- usuarios.rol = coach (opcional)
  nombre       VARCHAR(120) NOT NULL,
  tipo         VARCHAR(50)  NULL,
  descripcion  TEXT NULL,
  color        VARCHAR(9)   NOT NULL DEFAULT '#f97316',
  icono        VARCHAR(40)  NOT NULL DEFAULT 'dumbbell',
  estado       VARCHAR(20)  NOT NULL DEFAULT 'activo',
  created_at   TIMESTAMP NULL,
  updated_at   TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY planes_tenant_estado_index (tenant_id, estado),
  CONSTRAINT planes_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id)  ON DELETE RESTRICT,
  CONSTRAINT planes_coach_fk  FOREIGN KEY (coach_id)  REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- fecha_inicio NULL = plan relativo (se calcula desde alumno_planes.fecha_inicio)
-- fecha_inicio con valor = plan calendario (misma fecha para todos, como el coach hoy)
CREATE TABLE semanas (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id     BIGINT UNSIGNED NOT NULL,
  plan_id       BIGINT UNSIGNED NOT NULL,
  titulo        VARCHAR(150) NOT NULL,
  numero        SMALLINT UNSIGNED NOT NULL,
  estado        VARCHAR(20) NOT NULL DEFAULT 'borrador', -- borrador | publicada | archivada
  fecha_inicio  DATE NULL,
  created_at    TIMESTAMP NULL,
  updated_at    TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY semanas_plan_numero_unique (plan_id, numero),
  KEY semanas_tenant_index (tenant_id),
  CONSTRAINT semanas_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
  CONSTRAINT semanas_plan_fk   FOREIGN KEY (plan_id)   REFERENCES planes (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dias (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id   BIGINT UNSIGNED NOT NULL,
  semana_id   BIGINT UNSIGNED NOT NULL,
  nombre      VARCHAR(30) NOT NULL,
  descanso    TINYINT(1) NOT NULL DEFAULT 0,
  contenido   MEDIUMTEXT NULL,                         -- texto libre del coach
  orden       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NULL,
  updated_at  TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY dias_semana_orden_index (semana_id, orden),
  KEY dias_tenant_index (tenant_id),
  CONSTRAINT dias_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
  CONSTRAINT dias_semana_fk FOREIGN KEY (semana_id) REFERENCES semanas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Antes plan_ejercicios (plan + dia int). Ahora cuelga del día concreto.
CREATE TABLE dia_ejercicios (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id     BIGINT UNSIGNED NOT NULL,
  dia_id        BIGINT UNSIGNED NOT NULL,
  ejercicio_id  BIGINT UNSIGNED NOT NULL,
  orden         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  series        SMALLINT UNSIGNED NULL,
  repeticiones  VARCHAR(30) NULL,                      -- "8-10", "al fallo"
  peso          DECIMAL(6,2) NULL,
  descanso      SMALLINT UNSIGNED NULL,                -- revisar unidad en la app actual (seg?)
  created_at    TIMESTAMP NULL,
  updated_at    TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY dia_ejercicios_dia_orden_index (dia_id, orden),
  KEY dia_ejercicios_tenant_index (tenant_id),
  CONSTRAINT dia_ejercicios_tenant_fk    FOREIGN KEY (tenant_id)    REFERENCES tenants (id)    ON DELETE RESTRICT,
  CONSTRAINT dia_ejercicios_dia_fk       FOREIGN KEY (dia_id)       REFERENCES dias (id)       ON DELETE CASCADE,
  CONSTRAINT dia_ejercicios_ejercicio_fk FOREIGN KEY (ejercicio_id) REFERENCES ejercicios (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE links (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id   BIGINT UNSIGNED NOT NULL,
  dia_id      BIGINT UNSIGNED NOT NULL,
  titulo      VARCHAR(150) NOT NULL,
  url         VARCHAR(500) NOT NULL,
  created_at  TIMESTAMP NULL,
  updated_at  TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY links_dia_index (dia_id),
  KEY links_tenant_index (tenant_id),
  CONSTRAINT links_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
  CONSTRAINT links_dia_fk    FOREIGN KEY (dia_id)    REFERENCES dias (id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- gym: alumno_planes(fecha_inicio, fecha_fin, estado)  +  coach: AlumnoPlan
-- Sin unique (alumno, plan): con fechas, un alumno puede repetir un plan.
CREATE TABLE alumno_planes (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id     BIGINT UNSIGNED NOT NULL,
  alumno_id     BIGINT UNSIGNED NOT NULL,
  plan_id       BIGINT UNSIGNED NOT NULL,
  fecha_inicio  DATE NOT NULL,
  fecha_fin     DATE NULL,
  estado        VARCHAR(20) NOT NULL DEFAULT 'activo',
  created_at    TIMESTAMP NULL,
  updated_at    TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY alumno_planes_alumno_estado_index (alumno_id, estado),
  KEY alumno_planes_plan_index (plan_id),
  KEY alumno_planes_tenant_index (tenant_id),
  CONSTRAINT alumno_planes_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
  CONSTRAINT alumno_planes_alumno_fk FOREIGN KEY (alumno_id) REFERENCES alumnos (id) ON DELETE CASCADE,
  CONSTRAINT alumno_planes_plan_fk   FOREIGN KEY (plan_id)   REFERENCES planes (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- PROGRESO DEL ALUMNO
-- =====================================================================

-- Lo que el alumno hizo en un ejercicio. ejercicio_id se guarda aparte para que
-- el historial sobreviva si el coach edita o borra el ejercicio del día.
CREATE TABLE registros_entrenamiento (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id          BIGINT UNSIGNED NOT NULL,
  alumno_id          BIGINT UNSIGNED NOT NULL,
  ejercicio_id       BIGINT UNSIGNED NOT NULL,
  dia_ejercicio_id   BIGINT UNSIGNED NULL,
  fecha              DATE NOT NULL,
  peso               DECIMAL(6,2) NULL,
  repeticiones       SMALLINT UNSIGNED NULL,
  observaciones      TEXT NULL,
  created_at         TIMESTAMP NULL,
  updated_at         TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY registros_alumno_ejercicio_fecha_index (alumno_id, ejercicio_id, fecha),
  KEY registros_tenant_index (tenant_id),
  CONSTRAINT registros_tenant_fk        FOREIGN KEY (tenant_id)        REFERENCES tenants (id)        ON DELETE RESTRICT,
  CONSTRAINT registros_alumno_fk        FOREIGN KEY (alumno_id)        REFERENCES alumnos (id)        ON DELETE CASCADE,
  CONSTRAINT registros_ejercicio_fk     FOREIGN KEY (ejercicio_id)     REFERENCES ejercicios (id)     ON DELETE RESTRICT,
  CONSTRAINT registros_dia_ejercicio_fk FOREIGN KEY (dia_ejercicio_id) REFERENCES dia_ejercicios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historial de RM (el actual = el último por alumno y ejercicio)
CREATE TABLE rms (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id     BIGINT UNSIGNED NOT NULL,
  alumno_id     BIGINT UNSIGNED NOT NULL,
  ejercicio_id  BIGINT UNSIGNED NOT NULL,
  kg            DECIMAL(6,2) NOT NULL,
  fecha         DATE NOT NULL,
  created_at    TIMESTAMP NULL,
  updated_at    TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY rms_alumno_ejercicio_fecha_index (alumno_id, ejercicio_id, fecha),
  KEY rms_tenant_index (tenant_id),
  CONSTRAINT rms_tenant_fk    FOREIGN KEY (tenant_id)    REFERENCES tenants (id)    ON DELETE RESTRICT,
  CONSTRAINT rms_alumno_fk    FOREIGN KEY (alumno_id)    REFERENCES alumnos (id)    ON DELETE CASCADE,
  CONSTRAINT rms_ejercicio_fk FOREIGN KEY (ejercicio_id) REFERENCES ejercicios (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Antes "Asistencia" del coach (el alumno marca el día como hecho).
-- Existe la fila = completado. Para desmarcar, se borra.
CREATE TABLE progreso_dias (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id      BIGINT UNSIGNED NOT NULL,
  alumno_id      BIGINT UNSIGNED NOT NULL,
  dia_id         BIGINT UNSIGNED NOT NULL,
  completado_en  DATETIME NOT NULL,
  created_at     TIMESTAMP NULL,
  updated_at     TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY progreso_dias_alumno_dia_unique (alumno_id, dia_id),
  KEY progreso_dias_tenant_index (tenant_id),
  CONSTRAINT progreso_dias_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id)  ON DELETE RESTRICT,
  CONSTRAINT progreso_dias_alumno_fk FOREIGN KEY (alumno_id) REFERENCES alumnos (id)  ON DELETE CASCADE,
  CONSTRAINT progreso_dias_dia_fk    FOREIGN KEY (dia_id)    REFERENCES dias (id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hilo por alumno y día; el rol del autor sale de usuarios.rol
CREATE TABLE comentarios (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id         BIGINT UNSIGNED NOT NULL,
  alumno_id         BIGINT UNSIGNED NOT NULL,
  dia_id            BIGINT UNSIGNED NOT NULL,
  autor_usuario_id  BIGINT UNSIGNED NULL,
  texto             TEXT NOT NULL,
  created_at        TIMESTAMP NULL,
  updated_at        TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY comentarios_hilo_index (alumno_id, dia_id, created_at),
  KEY comentarios_tenant_index (tenant_id),
  CONSTRAINT comentarios_tenant_fk FOREIGN KEY (tenant_id)        REFERENCES tenants (id)  ON DELETE RESTRICT,
  CONSTRAINT comentarios_alumno_fk FOREIGN KEY (alumno_id)        REFERENCES alumnos (id)  ON DELETE CASCADE,
  CONSTRAINT comentarios_dia_fk    FOREIGN KEY (dia_id)           REFERENCES dias (id)     ON DELETE CASCADE,
  CONSTRAINT comentarios_autor_fk  FOREIGN KEY (autor_usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;