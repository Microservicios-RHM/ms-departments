# Microservicio de departamentos

Servicio HTTP para crear y consultar departamentos. Está construido con PHP 8.3, Apache y MySQL.
Sus datos pertenecen exclusivamente a este servicio; otros microservicios los consultan mediante
su API HTTP, nunca mediante acceso directo a MySQL.

## Ejecución local

La plataforma se orquesta desde el repositorio hermano `rhm-database-infrastructure`. Allí se
inician el contenedor de MySQL y este servicio:

```bash
cd ../rhm-database-infrastructure
docker compose up --build
```

La URL pública es `http://localhost:8080/departamentos`; el API Gateway enruta la solicitud al
puerto interno de este servicio.

Para ejecutar PHP fuera de Docker se requieren PHP 8.3 y Composer:

```bash
composer install
```

La configuración se toma de las variables `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`,
`DB_PASSWORD` y `DB_CHARSET`. En Docker son suministradas por el Compose central.

## API

| Método | Ruta | Resultado |
|---|---|---|
| `POST` | `/departamentos` | Crea un departamento (`201 Created`) |
| `GET` | `/departamentos` | Lista departamentos (`200 OK`) |
| `GET` | `/departamentos/{id}` | Consulta un departamento (`200` o `404`) |
| `GET` | `/health` | Confirma que el proceso está activo |
| `GET` | `/departamentos/docs` | Swagger UI |
| `GET` | `/departamentos/openapi.json` | Contrato OpenAPI 3.1 |

Montadas bajo `/departamentos` (no en la raíz) para poder vivir detrás del API Gateway sin
reescritura de rutas: el Gateway ya proxea `/departamentos/*` preservando la ruta, así que quedan
alcanzables en `http://localhost:8080/departamentos/docs` sin ningún cambio en `api-gateway`.

Ejemplo de creación:

```bash
curl -i -X POST http://localhost:8080/departamentos \
  -H "Content-Type: application/json" \
  -d '{"id":"IT","nombre":"Tecnología","descripcion":"Departamento de TI"}'
```

Las respuestas de negocio usan el contrato común:

```json
{
  "success": true,
  "message": "Departamento registrado correctamente",
  "data": {
    "id": "IT",
    "nombre": "Tecnología",
    "descripcion": "Departamento de TI"
  }
}
```

Los errores incluyen `error.code`, `error.status`, `error.path` y `error.timestamp`. Los clientes
deben usar `error.code`, no el texto de `message`, para tomar decisiones.

### Autenticación en Swagger (Reto 5)

Desde el Reto 5 todas las peticiones exigen un token, que valida el API Gateway. El documento
OpenAPI declara el esquema de seguridad `BearerAuth`, así que la página de Swagger muestra el
botón **Authorize**: se pega ahí el `accessToken` que devuelve `POST /auth/login` y el token viaja
en cada petición de prueba.

Es solo documentación. Este servicio no verifica la firma — de eso se encarga el Gateway, una sola
vez para todo el ecosistema. Sin esta declaración el botón no existiría y cualquier "Try it out"
respondería `401` sin forma de autenticarse.

## Persistencia y evolución del esquema

El esquema inicial está versionado en `database/init/01_schema.sql`. MySQL lo ejecuta desde
`/docker-entrypoint-initdb.d` únicamente cuando crea un volumen vacío. La tabla
`departamentos` tiene `id` como clave primaria, por lo cual no admite identificadores repetidos.

El script inicial no se debe modificar para actualizar una base que ya contiene datos. Cada cambio
posterior debe incorporarse como una migración incremental y versionada, aplicable sin eliminar el
volumen ni perder información existente.

## Estructura

```text
src/
├── Domain/          Entidad y contrato del repositorio
├── Application/     Caso de uso y reglas de negocio
├── Infrastructure/  MySQL, HTTP y documento OpenAPI
└── Shared/          Excepciones y mensajes reutilizables
```

La capa de aplicación depende de `DepartmentRepository`; la implementación `MySqlDepartmentRepository`
queda en infraestructura. Esto evita que las reglas de negocio dependan de PDO o de detalles de MySQL.

## Docker

Este repositorio contiene el `Dockerfile` de la imagen de Departamentos, pero no un Compose propio.
El único Compose autorizado está en `../rhm-database-infrastructure`, donde se definen la red,
el volumen, las credenciales y el orden de arranque de toda la plataforma.
