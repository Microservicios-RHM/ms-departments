.RECIPEPREFIX := >
.DEFAULT_GOAL := ayuda

COMPOSE := docker compose -f ../rhm-database-infrastructure/docker-compose.yml

ayuda: ## Muestra los comandos disponibles
> @grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

init: ## Crea el .env central, construye y levanta todo el reto
> @test -f ../rhm-database-infrastructure/.env || cp ../rhm-database-infrastructure/.env.example ../rhm-database-infrastructure/.env
> $(COMPOSE) up -d --build

up: ## Levanta los cuatro contenedores
> $(COMPOSE) up -d --build

down: ## Detiene los contenedores y conserva datos
> $(COMPOSE) down

reset: ## Detiene y elimina los datos persistidos
> $(COMPOSE) down -v

logs: ## Sigue los logs de todos los servicios
> $(COMPOSE) logs -f

ps: ## Muestra estado y health checks
> $(COMPOSE) ps

salud: ## Consulta los dos health checks
> curl -fsS http://localhost:8080/health
> curl -fsS http://localhost:8081/health

.PHONY: ayuda init up down reset logs ps salud
