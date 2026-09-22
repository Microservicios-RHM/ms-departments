-- =============================================================================
--  Esquema inicial del servicio de departamentos.
--  Este archivo se ejecuta automáticamente la primera vez que se crea el
--  volumen departamentos-datos (montado en /docker-entrypoint-initdb.d).
--  Todo el equipo parte del mismo esquema versionado en git.
-- =============================================================================

CREATE TABLE IF NOT EXISTS departamentos (
    id          VARCHAR(50)   NOT NULL,
    nombre      VARCHAR(100)  NOT NULL,
    descripcion TEXT          NOT NULL,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
