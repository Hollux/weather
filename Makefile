# Makefile pour projet Genshin (Symfony + Nuxt.js + Docker)
# ================================================================

PROJECT_NAME = meteo

DOCKER_COMPOSE = docker compose
DOCKER_EXEC = docker exec -it

SYMFONY_CONTAINER = $(PROJECT_NAME)_dev_back
FRONT_CONTAINER = $(PROJECT_NAME)_dev_front
PHP_FPM_CONTAINER = $(PROJECT_NAME)_dev_phpfpm
DB_CONTAINER = $(PROJECT_NAME)_dev_db

SYMFONY_CONTAINER_PROD = $(PROJECT_NAME)_prod_back
FRONT_CONTAINER_PROD = $(PROJECT_NAME)_prod_front
PHP_FPM_CONTAINER_PROD = $(PROJECT_NAME)_prod_phpfpm
DB_CONTAINER_PROD = $(PROJECT_NAME)_prod_db

# Couleurs pour l'affichage
RED = \033[0;31m
GREEN = \033[0;32m
YELLOW = \033[1;33m
BLUE = \033[0;34m
NC = \033[0m # No Color

.PHONY: help
help: ## Affiche l’aide "soft"
	@echo "$(GREEN)Commandes disponibles (mode soft) : $(NC)"
	@grep -E '^[A-Za-z0-9._-]+:.*## \[soft\]' $(MAKEFILE_LIST) \
	| sort \
	| awk -F ':.*## \\[soft\\] ' '{ gsub(/\[(DEV|PROD)\]/,"", $$2); printf "$(BLUE)%-20s$(NC) %s\n", $$1, $$2 }'

# Commande par défaut
.DEFAULT_GOAL := help

.PHONY: 01_help-dev
01_help-dev: ## [soft] Affiche uniquement les commandes de développement
	@echo "$(GREEN)Commandes disponibles en DÉVELOPPEMENT :$(NC)"
	@grep -E '^[A-Za-z0-9._-]+:.*## \[DEV\]' $(MAKEFILE_LIST) \
	| sort \
	| awk -F ':.*## \\[DEV\\] ' '{ printf "$(BLUE)%-25s$(NC) %s\n", $$1, $$2 }'

.PHONY: 02_help-prod
02_help-prod: ## [soft] Affiche uniquement les commandes de production
	@echo "$(GREEN)Commandes disponibles en PRODUCTION :$(NC)"
	@grep -E '^[A-Za-z0-9._-]+:.*## \[PROD\]' $(MAKEFILE_LIST) \
	| sort \
	| awk -F ':.*## \\[PROD\\] ' '{ printf "$(BLUE)%-25s$(NC) %s\n", $$1, $$2 }'


.PHONY: 03_help-full
03_help-full: ## [soft] Affiche l’aide complète
	@echo "$(GREEN)Commandes avancées :$(NC)"
	@grep -E '^[A-Za-z0-9._-]+:.*?## .*$$' $(MAKEFILE_LIST) \
	| sort \
	| sed -E 's/## \[(soft|full)\] /## /' \
	| awk 'BEGIN {FS = ":.*?## "}; {printf "$(BLUE)%-20s$(NC) %s\n", $$1, $$2}'


## Raccourci pour l’aide complète
.PHONY: help-dev
help-dev: 01_help-dev ## Affiche l’aide dev

.PHONY: help-prod
help-prod: 02_help-prod ## Affiche l’aide prod

.PHONY: help-full
help-full: 03_help-full ## Affiche l’aide complète


# ======================================
# Docker automatisation
# ======================================

.PHONY: docker-dev
docker-dev: ## [soft] [DEV] Lance l'environnement de développement Docker
	@echo "$(GREEN)Lancement de l'environnement de développement Docker...$(NC)"
# Vérifie si le fichier .env existe, sinon le crée à partir de .env.exemple
	@if [ ! -f .env ]; then \
		echo "Création du fichier .env à partir de .env.exemple"; \
		cp .env.exemple .env; \
		echo "Vous pouvez modifier .env avant de continuer"; \
		read -p "Appuyez sur Entrée pour continuer..."; \
	fi

	$(DOCKER_COMPOSE) build
	$(DOCKER_COMPOSE) up -d
	@echo "Installation des dépendances backend..."
	$(DOCKER_EXEC) ${SYMFONY_CONTAINER} composer install
	@echo "Installation des dépendances frontend..."
	$(DOCKER_EXEC) ${FRONT_CONTAINER} npm install

	# Copie des vendors du conteneur vers le dossier local
	# @echo "$(GREEN)Copie des vendors du conteneur vers le dossier local...$(NC)"
	# @mkdir -p ./back/vendor
	# docker cp $(SYMFONY_CONTAINER):/var/www/html/vendor ./back/vendor

	# Exécution des migrations Doctrine
	@echo "Exécution des migrations Doctrine..."
	$(DOCKER_EXEC) ${SYMFONY_CONTAINER} php bin/console doctrine:migrations:migrate --no-interaction

	# chargement des anciennes données
	@echo "Importation des anciennes données SQL..."
	$(DOCKER_EXEC) ${SYMFONY_CONTAINER} php bin/console app:import-sql old_datas.sql --batch-size=4000

	# Chargement des fixtures (pas encore en place)
	# @echo "Chargement des fixtures..."
	# $(DOCKER_EXEC) ${SYMFONY_CONTAINER} php bin/console doctrine:fixtures:load --no-interaction


# Docker PROD
.PHONY: docker-prod
docker-prod: ## [soft] [PROD] Lance l'environnement de production Docker
	@echo "$(GREEN)Lancement de l'environnement de production Docker...$(NC)"
	@if [ ! -f .env ]; then \
		echo "Création du fichier .env à partir de .env.exemple"; \
		cp .env.exemple .env; \
		echo "Vous pouvez modifier .env avant de continuer"; \
		read -p "Appuyez sur Entrée pour continuer..."; \
	fi

	$(DOCKER_COMPOSE) -f docker-compose.prod.yaml build

	$(DOCKER_COMPOSE) -f docker-compose.prod.yaml up -d

	@echo "Installation des dépendances backend (prod)..."
	$(DOCKER_COMPOSE) -f docker-compose.prod.yaml exec back_prod composer install --no-dev --optimize-autoloader


	@echo "Warmup cache Symfony prod..."
	$(DOCKER_COMPOSE) -f docker-compose.prod.yaml exec back_prod php bin/console cache:warmup --env=prod


	@echo "Exécution des migrations Doctrine prod..."
	$(DOCKER_EXEC) ${SYMFONY_CONTAINER_PROD} php bin/console doctrine:migrations:migrate --no-interaction --env=prod

	# chargement des anciennes données
	@echo "Importation des anciennes données SQL..."
	$(DOCKER_EXEC) ${SYMFONY_CONTAINER_PROD} php bin/console app:import-sql old_datas.sql --batch-size=4000


	@echo "Production Docker environment started."



# /Docker automatisation




# ================================================================
# DOCKER & INFRASTRUCTURE
# ================================================================

.PHONY: up
up: ## [soft] [DEV] Démarre tous les conteneurs Docker
	@echo "$(GREEN)Démarrage des conteneurs...$(NC)"
	$(DOCKER_COMPOSE) up -d

.PHONY: up-prod
up-prod: ## [PROD] Démarre tous les conteneurs Docker en production
	@echo "$(GREEN)Démarrage des conteneurs de production...$(NC)"
	$(DOCKER_COMPOSE) -f docker-compose.prod.yaml up -d

.PHONY: down
down: ## [soft] [DEV] Arrête tous les conteneurs Docker
	@echo "$(RED)Arrêt des conteneurs...$(NC)"
	$(DOCKER_COMPOSE) down

.PHONY: down-prod
down-prod: ## [PROD] Arrête tous les conteneurs Docker en production
	@echo "$(RED)Arrêt des conteneurs de production...$(NC)"
	$(DOCKER_COMPOSE) -f docker-compose.prod.yaml down

.PHONY: restart
restart: down up ## [soft] [DEV] Redémarre tous les conteneurs

.PHONY: restart-prod
restart-prod: down-prod up-prod ## [PROD] Redémarre tous les conteneurs

.PHONY: build
build: ## [soft] [DEV] Reconstruit tous les conteneurs Docker
	@echo "$(YELLOW)Reconstruction des conteneurs...$(NC)"
	$(DOCKER_COMPOSE) build --no-cache

.PHONY: build-prod
build-prod: ## [PROD] Reconstruit tous les conteneurs Docker en production
	@echo "$(YELLOW)Reconstruction des conteneurs de production...$(NC)"
	$(DOCKER_COMPOSE) -f docker-compose.prod.yaml build --no-cache

.PHONY: logs
logs: ## [soft] [DEV] Affiche les logs de tous les conteneurs
	$(DOCKER_COMPOSE) logs -f

.PHONY: logs-prod
logs-prod: ## [PROD] Affiche les logs de tous les conteneurs en production
	$(DOCKER_COMPOSE) -f docker-compose.prod.yaml logs -f

.PHONY: status
status: ## [soft] [DEV] Affiche le statut des conteneurs
	@echo "$(BLUE)Statut des conteneurs :$(NC)"
	$(DOCKER_COMPOSE) ps

.PHONY: status-prod
status-prod: ## [PROD] Affiche le statut des conteneurs en production
	@echo "$(BLUE)Statut des conteneurs de production :$(NC)"
	$(DOCKER_COMPOSE) -f docker-compose.prod.yaml ps

.PHONY: clean
clean: ## Nettoie les conteneurs, volumes et images inutilisés
	@echo "$(YELLOW)Nettoyage Docker...$(NC)"
	docker system prune -f
	docker volume prune -f

# ================================================================
# SYMFONY BACKEND
# ================================================================

.PHONY: sf-bash
sf-bash: ## [soft] [DEV] Accède au bash du conteneur Symfony
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) bash

.PHONY: sf-bash-prod
sf-bash-prod: ## [PROD] Accède au bash du conteneur Symfony de prod
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER_PROD) bash

.PHONY: sf-console
sf-console: ## [DEV] Accède à la console Symfony (ex: make symfony-console c="debug:router")
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) php bin/console $(c)

.PHONY: sf-console-prod
sf-console-prod: ## [PROD] Accède à la console Symfony de prod (ex: make symfony-console-prod c="debug:router")
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER_PROD) php bin/console $(c) --env=prod

.PHONY: sf-cache-clear
sf-cache-clear: ## [DEV] Vide le cache Symfony
	@echo "$(YELLOW)Vidage du cache Symfony...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) php bin/console cache:clear

.PHONY: sf-cache-clear-prod
sf-cache-clear-prod: ## [PROD] Vide le cache Symfony en production
	@echo "$(YELLOW)Vidage du cache Symfony (prod)...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER_PROD) php bin/console cache:clear --env=prod

.PHONY: sf-composer-install
sf-composer-install: ## [DEV] Installe les dépendances Composer
	@echo "$(BLUE)Installation des dépendances Composer...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) composer install

.PHONY: sf-composer-install-prod
sf-composer-install-prod: ## [PROD] Installe les dépendances Composer en production
	@echo "$(BLUE)Installation des dépendances Composer (prod)...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER_PROD) composer install --no-dev --optimize-autoloader

.PHONY: sf-composer-update
sf-composer-update: ## [DEV] Met à jour les dépendances Composer
	@echo "$(BLUE)Mise à jour des dépendances Composer...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) composer update

.PHONY: sf-composer-update-prod
sf-composer-update-prod: ## [PROD] Met à jour les dépendances Composer en production
	@echo "$(BLUE)Mise à jour des dépendances Composer (prod)...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER_PROD) composer update --no-dev --optimize-autoloader

.PHONY: sf-migration-make
sf-migration-make: ## [DEV] Crée une nouvelle migration (ex: make sf-migration-make name="AddUserTable")
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) php bin/console make:migration $(name)

.PHONY: sf-migration-make-prod
sf-migration-make-prod: ## [PROD] Crée une nouvelle migration en prod (ex: make sf-migration-make-prod name="AddUserTable")
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER_PROD) php bin/console make:migration $(name)

.PHONY: sf-migration-migrate
sf-migration-migrate: ## [DEV] Exécute les migrations
	@echo "$(BLUE)Exécution des migrations...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) php bin/console doctrine:migrations:migrate --no-interaction

.PHONY: sf-migration-migrate-prod
sf-migration-migrate-prod: ## [PROD] Exécute les migrations en prod
	@echo "$(BLUE)Exécution des migrations (prod)...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER_PROD) php bin/console doctrine:migrations:migrate --no-interaction --env=prod

.PHONY: sf-migration-rollback
sf-migration-rollback: ## [DEV] Rollback de la dernière migration
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) php bin/console doctrine:migrations:migrate prev --no-interaction

.PHONY: sf-migration-rollback-prod
sf-migration-rollback-prod: ## [PROD] Rollback de la dernière migration en prod
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER_PROD) php bin/console doctrine:migrations:migrate prev --no-interaction --env=prod

.PHONY: sf-database-create
sf-database-create: ## [DEV] Crée la base de données
	@echo "$(BLUE)Création de la base de données...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) php bin/console doctrine:database:create --if-not-exists

.PHONY: sf-database-create-prod
sf-database-create-prod: ## [PROD] Crée la base de données en prod
	@echo "$(BLUE)Création de la base de données (prod)...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER_PROD) php bin/console doctrine:database:create --if-not-exists --env=prod

.PHONY: sf-database-drop
sf-database-drop: ## [DEV] Supprime la base de données
	@echo "$(RED)Suppression de la base de données...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) php bin/console doctrine:database:drop --force

.PHONY: sf-database-drop-prod
sf-database-drop-prod: ## [PROD] Supprime la base de données en prod
	@echo "$(RED)Suppression de la base de données (prod)...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER_PROD) php bin/console doctrine:database:drop --force --env=prod

.PHONY: sf-fixtures
sf-fixtures: ## [DEV] Charge les fixtures (si disponibles)
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) php bin/console doctrine:fixtures:load --no-interaction

.PHONY: sf-fixtures-prod
sf-fixtures-prod: ## [PROD] Charge les fixtures en prod (si disponible)
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER_PROD) php bin/console doctrine:fixtures:load --no-interaction --env=prod

.PHONY: sf-entity-make
sf-entity-make: ## [DEV] Crée une nouvelle entité (ex: make sf-entity-make name="User")
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) php bin/console make:entity $(name)

.PHONY: sf-entity-make-prod
sf-entity-make-prod: ## [PROD] Crée une nouvelle entité en prod (ex: make sf-entity-make-prod name="User")
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER_PROD) php bin/console make:entity $(name)

.PHONY: sf-controller-make
sf-controller-make: ## [DEV] Crée un nouveau contrôleur (ex: make sf-controller-make name="UserController")
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) php bin/console make:controller $(name)

.PHONY: sf-controller-make-prod
sf-controller-make-prod: ## [PROD] Crée un nouveau contrôleur en prod (ex: make sf-controller-make-prod name="UserController")
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER_PROD) php bin/console make:controller $(name)

.PHONY: sf-permissions
sf-permissions: ## [DEV] Corrige les permissions Symfony
	@echo "$(YELLOW)Correction des permissions...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) chown -R www-data:www-data /var/www/html/var
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) chmod -R 755 /var/www/html/var

.PHONY: sf-permissions-prod
sf-permissions-prod: ## [PROD] Corrige les permissions Symfony en prod
	@echo "$(YELLOW)Correction des permissions (prod)...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER_PROD) chown -R www-data:www-data /var/www/html/var
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER_PROD) chmod -R 755 /var/www/html/var

# SFONY USER MANAGEMENT
.PHONY: sf-create-user
sf-create-user: ## [DEV] Crée un nouvel utilisateur (ex: make sf-create-user email="
	@echo "$(YELLOW) Création d'un nouvel utilisateur...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) php bin/console app:create-user

.PHONY: sf-create-user-prod
sf-create-user-prod: ## [PROD] Crée un nouvel utilisateur en prod
	@echo "$(YELLOW)Création d'un nouvel utilisateur (prod)...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER_PROD) php bin/console app:create-user --env=prod



.PHONY: sf-import-files
sf-import-files: ## [DEV] Importe des fichiers de données (ex: make sf-import-files path="./data")
	@echo "$(YELLOW)Importation des fichiers de données...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) php bin/console app:import-files /app/datas --batch-size 2000

.PHONY: sf-import-files-prod
sf-import-files-prod: ## [PROD] Importe des fichiers de données en prod (ex: make sf-import-files-prod path="./data")
	@echo "$(YELLOW)Importation des fichiers de données (prod)...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER_PROD) php bin/console app:import-files /app/datas --batch-size 2000 --env=prod

.PHONY: sf-weather-collect
sf-weather-collect: ## [DEV] Lance manuellement la collecte OpenWeatherMap (sinon auto toutes les 5 min via le service cron)
	@echo "$(YELLOW)Collecte OpenWeatherMap...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) php bin/console app:weather:collect

.PHONY: sf-weather-collect-prod
sf-weather-collect-prod: ## [PROD] Lance manuellement la collecte OpenWeatherMap
	@echo "$(YELLOW)Collecte OpenWeatherMap (prod)...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER_PROD) php bin/console app:weather:collect --env=prod

.PHONY: sf-weather-aggregate
sf-weather-aggregate: ## [DEV] Lance manuellement l'agrégation journalière (sinon auto à 00h30 via le service cron)
	@echo "$(YELLOW)Agrégation journalière...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) php bin/console app:weather:aggregate

.PHONY: sf-weather-aggregate-prod
sf-weather-aggregate-prod: ## [PROD] Lance manuellement l'agrégation journalière
	@echo "$(YELLOW)Agrégation journalière (prod)...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER_PROD) php bin/console app:weather:aggregate --env=prod

.PHONY: cron-logs
cron-logs: ## [DEV] Affiche les logs du planificateur (jobs cron)
	$(DOCKER_COMPOSE) logs -f cron

# ================================================================
# FRONTEND NUXT.JS
# ================================================================

.PHONY: front-bash
front-bash: ## [DEV] Accède au bash du conteneur Frontend
	$(DOCKER_EXEC) $(FRONT_CONTAINER) bash

.PHONY: front-bash-prod
front-bash-prod: ## [PROD] Accède au bash du conteneur Frontend (prod)
	$(DOCKER_EXEC) $(FRONT_CONTAINER_PROD) bash

.PHONY: front-npm-install
front-npm-install: ## [DEV] Installe les dépendances npm
	@echo "$(BLUE)Installation des dépendances npm...$(NC)"
	$(DOCKER_EXEC) $(FRONT_CONTAINER) npm install

.PHONY: front-npm-install-prod
front-npm-install-prod: ## [PROD] Installe les dépendances npm en prod
	@echo "$(BLUE)Installation des dépendances npm (prod)...$(NC)"
	$(DOCKER_EXEC) $(FRONT_CONTAINER_PROD) npm install

.PHONY: front-npm-update
front-npm-update: ## [DEV] Met à jour les dépendances npm
	@echo "$(BLUE)Mise à jour des dépendances npm...$(NC)"
	$(DOCKER_EXEC) $(FRONT_CONTAINER) npm update

.PHONY: front-npm-update-prod
front-npm-update-prod: ## [PROD] Met à jour les dépendances npm en prod
	@echo "$(BLUE)Mise à jour des dépendances npm (prod)...$(NC)"
	$(DOCKER_EXEC) $(FRONT_CONTAINER_PROD) npm update

.PHONY: front-dev
front-dev: ## [DEV] Lance le serveur de développement Nuxt
	$(DOCKER_EXEC) $(FRONT_CONTAINER) npm run dev

.PHONY: front-build
front-build: ## [DEV] Build le frontend pour la production
	@echo "$(BLUE)Build du frontend...$(NC)"
	$(DOCKER_EXEC) $(FRONT_CONTAINER) npm run build

.PHONY: front-build-prod
front-build-prod: ## [PROD] Build le frontend pour la production (prod)
	@echo "$(BLUE)Build du frontend (prod)...$(NC)"
	$(DOCKER_EXEC) $(FRONT_CONTAINER_PROD) npm run build

.PHONY: front-lint
front-lint: ## [DEV] Lance le linter sur le frontend (si configuré)
	$(DOCKER_EXEC) $(FRONT_CONTAINER) npm run lint

.PHONY: front-lint-prod
front-lint-prod: ## [PROD] Lance le linter sur le frontend en prod (si configuré)
	$(DOCKER_EXEC) $(FRONT_CONTAINER_PROD) npm run lint

# ================================================================
# BASE DE DONNÉES
# ================================================================

.PHONY: db-bash
db-bash: ## [DEV] Accède au bash du conteneur MySQL
	$(DOCKER_EXEC) $(DB_CONTAINER) bash

.PHONY: db-bash-prod
db-bash-prod: ## [PROD] Accède au bash du conteneur MySQL (prod)
	$(DOCKER_EXEC) $(DB_CONTAINER_PROD) bash

.PHONY: db-mysql
db-mysql: ## [DEV] Accède à MySQL en ligne de commande
	$(DOCKER_EXEC) $(DB_CONTAINER) mysql -u root -p

.PHONY: db-mysql-prod
db-mysql-prod: ## [PROD] Accède à MySQL en ligne de commande (prod)
	$(DOCKER_EXEC) $(DB_CONTAINER_PROD) mysql -u root -p

.PHONY: db-dump
db-dump: ## [DEV] Sauvegarde la base de données (ex: make db-dump file="backup.sql")
	@echo "$(BLUE)Sauvegarde de la base de données...$(NC)"
	$(DOCKER_EXEC) $(DB_CONTAINER) mysqldump -u root -p --all-databases > $(file)

.PHONY: db-dump-prod
db-dump-prod: ## [PROD] Sauvegarde la base de données en prod (ex: make db-dump-prod file="backup.sql")
	@echo "$(BLUE)Sauvegarde de la base de données (prod)...$(NC)"
	$(DOCKER_EXEC) $(DB_CONTAINER_PROD) mysqldump -u root -p --all-databases > $(file)

.PHONY: db-restore
db-restore: ## [DEV] Restaure la base de données (ex: make db-restore file="backup.sql")
	@echo "$(BLUE)Restauration de la base de données...$(NC)"
	$(DOCKER_EXEC) -i $(DB_CONTAINER) mysql -u root -p < $(file)

.PHONY: db-restore-prod
db-restore-prod: ## [PROD] Restaure la base de données en prod (ex: make db-restore-prod file="backup.sql")
	@echo "$(BLUE)Restauration de la base de données (prod)...$(NC)"
	$(DOCKER_EXEC) -i $(DB_CONTAINER_PROD) mysql -u root -p < $(file)

# ================================================================
# DÉVELOPPEMENT & TESTS
# ================================================================

.PHONY: test
test: ## [soft] [DEV] Lance les tests Symfony (si configurés)
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) php bin/phpunit

.PHONY: test-prod
test-prod: ## [PROD] Lance les tests Symfony en prod (si configurés)
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER_PROD) php bin/phpunit

.PHONY: fix-permissions
fix-permissions: ## [DEV] Corrige les permissions des fichiers
	@echo "$(YELLOW)Correction des permissions...$(NC)"
	sudo chown -R $(USER):$(USER) .
	sudo chmod -R 755 .

.PHONY: reset-project
reset-project: ## [DEV] Remet à zéro le projet (ATTENTION: supprime les données)
	@echo "$(RED)Remise à zéro du projet...$(NC)"
	@read -p "Êtes-vous sûr ? [y/N] " -n 1 -r; \
	if [[ $$REPLY =~ ^[Yy]$$ ]]; then \
		make down; \
		docker volume rm genshin_db_data 2>/dev/null || true; \
		make build; \
		make up; \
		sleep 10; \
		make sf-database-create; \
		make sf-migration-migrate; \
	fi

.PHONY: init-project
init-project: ## [DEV] Initialise le projet pour la première fois
	@echo "$(GREEN)Initialisation du projet...$(NC)"
	make build
	make up
	sleep 15
	make sf-composer-install
	make front-npm-install
	make sf-database-create
	make sf-migration-migrate
	make sf-permissions
	@echo "$(GREEN)Projet initialisé avec succès !$(NC)"
	@echo "$(BLUE)Frontend disponible sur: http://localhost:3000$(NC)"
	@echo "$(BLUE)Backend Symfony disponible sur: http://localhost:8000$(NC)"
	@echo "$(BLUE)PhpMyAdmin disponible sur: http://localhost:8080$(NC)"

# ================================================================
# PHP-FPM
# ================================================================

.PHONY: phpfpm-bash
phpfpm-bash: ## [DEV] Accède au bash du conteneur PHP-FPM
	$(DOCKER_EXEC) $(PHP_FPM_CONTAINER) bash

.PHONY: phpfpm-bash-prod
phpfpm-bash-prod: ## [PROD] Accède au bash du conteneur PHP-FPM (prod)
	$(DOCKER_EXEC) $(PHP_FPM_CONTAINER_PROD) bash

# ================================================================
# MONITORING & DEBUG
# ================================================================

.PHONY: logs-symfony
logs-symfony: ## [DEV] Affiche les logs du conteneur Symfony
	$(DOCKER_COMPOSE) logs -f back

.PHONY: logs-front
logs-front: ## [DEV] Affiche les logs du conteneur Frontend
	$(DOCKER_COMPOSE) logs -f front

.PHONY: logs-db
logs-db: ## [DEV] Affiche les logs du conteneur MySQL
	$(DOCKER_COMPOSE) logs -f db

.PHONY: info
info: ## Affiche les informations du projet
	@echo "$(GREEN)=== Informations du projet Meteo ===$(NC)"
	@echo "$(BLUE)Frontend (Nuxt.js):$(NC) http://localhost:3000"
	@echo "$(BLUE)Backend (Symfony):$(NC) http://localhost:8000"
	@echo "$(BLUE)PhpMyAdmin:$(NC) http://localhost:8080"
	@echo "$(BLUE)Base de données:$(NC) localhost:3306"
	@echo ""
	@echo "$(YELLOW)Conteneurs actifs :$(NC)"
	@$(DOCKER_COMPOSE) ps

# ===============================================================
# Déploiement du front en prod sur un serveur OVH via lftp
# ===============================================================

