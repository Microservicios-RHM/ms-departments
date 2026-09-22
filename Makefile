# =============================================================================
#  Atajos del equipo. Ejecute "make ayuda" para ver los comandos disponibles.
#  Se usa .RECIPEPREFIX para no depender de tabulaciones.
# =============================================================================
.RECIPEPREFIX := >
.DEFAULT_GOAL := ayuda

COMPOSE := docker compose
API     := departamentos-api
DB      := departamentos-db

ayuda: ## Muestra esta ayuda
> @grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

init: ## Prepara el entorno por primera vez (.env + build + up)
> @test -f .env || cp .env.example .env
> $(COMPOSE) build
> $(COMPOSE) up -d
> @echo "Servicio disponible en http://localhost:$$(grep ^APP_PORT .env | cut -d= -f2)"

up: ## Levanta los contenedores
> $(COMPOSE) up -d

down: ## Detiene los contenedores (conserva los datos)
> $(COMPOSE) down

reset: ## Detiene y BORRA el volumen de datos (re-ejecuta los scripts de init)
> $(COMPOSE) down -v

build: ## Reconstruye la imagen de la API
> $(COMPOSE) build --no-cache

logs: ## Muestra los logs en vivo
> $(COMPOSE) logs -f

sh: ## Abre una terminal dentro del contenedor de la API
> $(COMPOSE) exec $(API) bash

db: ## Abre el cliente MySQL dentro del contenedor de la base de datos
> $(COMPOSE) exec $(DB) sh -c 'mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"'

install: ## Instala/actualiza las dependencias de Composer dentro del contenedor
> $(COMPOSE) run --rm --entrypoint composer $(API) install

migrate: ## Aplica las migraciones pendientes de database/migrations
> ./scripts/migrate.sh

test: ## Ejecuta las pruebas unitarias
> $(COMPOSE) exec $(API) ./vendor/bin/phpunit

.PHONY: ayuda init up down reset build logs sh db install migrate test salud