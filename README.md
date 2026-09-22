# Reto 2: empleados y departamentos

Orquestación del sistema de onboarding con dos microservicios independientes:

| Componente | Tecnología | Persistencia | Puerto host |
|---|---|---|---:|
| `empleados-service` | Node.js 24 + TypeScript | PostgreSQL 17 | 8080 |
| `departamentos-service` | PHP 8.3 + Apache | MySQL 8.4 | 8081 |
| `database-empleados` | PostgreSQL 17 | `rhm-employees-data` | 5433 |
| `database-departamentos` | MySQL 8.4 | `rhm-departments-data` | 3307 |

Los repositorios `ms-departments` y `ms-employees` deben estar como carpetas hermanas de
`rhm-database-infrastructure`. El único Compose del sistema está en esa carpeta. Ejecútelo allí:

```bash
cd ../rhm-database-infrastructure
cp .env.example .env
docker compose up --build
```

El archivo `.env` central es opcional porque Compose incluye valores de desarrollo seguros por defecto,
pero permite personalizar puertos y credenciales sin modificar el YAML. No debe subirse a Git.

Para detener conservando los datos:

```bash
cd ../rhm-database-infrastructure && docker compose down
```

Para reiniciar desde cero, eliminando definitivamente ambas bases:

```bash
cd ../rhm-database-infrastructure && docker compose down -v
cd ../rhm-database-infrastructure && docker compose up --build
```

## Endpoints

### Departamentos (`http://localhost:8081`)

| Método | Ruta | Resultado |
|---|---|---|
| `POST` | `/departamentos` | Crea un departamento (`201`) |
| `GET` | `/departamentos` | Lista los departamentos (`200`) |
| `GET` | `/departamentos/{id}` | Consulta uno (`200` o `404`) |
| `GET` | `/health` | Estado del proceso |
| `GET` | `/docs` | Swagger UI |
| `GET` | `/openapi.json` | Documento OpenAPI 3.1 |

Ejemplo:

```bash
curl -X POST http://localhost:8081/departamentos \
  -H "Content-Type: application/json" \
  -d '{"id":"IT","nombre":"Tecnología","descripcion":"Departamento de TI"}'
```

Todas las respuestas de negocio tienen el contrato común:

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

### Empleados (`http://localhost:8080`)

| Método | Ruta | Resultado |
|---|---|---|
| `POST` | `/empleados` | Crea un empleado (`201`) |
| `GET` | `/empleados` | Lista los empleados (`200`) |
| `GET` | `/empleados/{id}` | Consulta uno (`200` o `404`) |
| `GET` | `/health` | Estado del proceso |
| `GET` | `/docs` | Swagger UI |

Al registrar, empleados valida en orden el email, el número de empleado y la existencia del
departamento. La tercera validación se hace por HTTP contra `departamentos-service`; empleados
nunca accede a la base MySQL.

## Decisiones técnicas

### Motor por servicio

Se eligió PostgreSQL para empleados y MySQL para departamentos. Esto demuestra persistencia
políglota y mantiene la autonomía de cada servicio. El beneficio es poder elegir la tecnología por
contexto; el costo es operar, monitorear y respaldar dos motores diferentes.

### Creación y evolución del esquema

Departamentos usa `database/init/01_schema.sql`, montado en
`/docker-entrypoint-initdb.d`. Es explícito y reproducible para este reto, pero solo se ejecuta al
crear un volumen vacío. Un cambio posterior exige una migración incremental; no se debe borrar
un volumen productivo para actualizar el esquema. Empleados conserva su ejecutor de migraciones
versionadas al iniciar.

### Unicidad

Se realiza una consulta previa para entregar un mensaje claro y también se conservan restricciones
`UNIQUE` en la base de empleados. La restricción es la garantía real ante dos peticiones
concurrentes; el repositorio traduce la violación a una respuesta `400`.

### Fallo del servicio de departamentos

La llamada usa timeout de 2 segundos y hasta tres intentos con espera exponencial de 1 y 2
segundos entre intentos. Si todos fallan, empleados rechaza el registro con `503 Service
Unavailable`. No se guarda un empleado pendiente porque el modelo de este reto no define dicho
estado y hacerlo permitiría referencias no validadas.

## Arranque ordenado y persistencia

Las bases tienen health checks nativos. Cada aplicación usa
`depends_on: condition: service_healthy`; empleados espera además a que departamentos esté sano.
Los reintentos siguen siendo necesarios porque el health check solo ordena el arranque y no cubre
fallos posteriores.

Comprobación:

```bash
cd ../rhm-database-infrastructure && docker compose ps
cd ../rhm-database-infrastructure && docker compose down
cd ../rhm-database-infrastructure && docker compose up -d
curl http://localhost:8080/empleados/E001
```

Después de `down`, los datos deben seguir disponibles. Después de `down -v`, deben desaparecer.
