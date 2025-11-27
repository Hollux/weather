# Makefile pour projet Genshin (Symfony + Nuxt.js + Docker)
# ================================================================

# Variables
DOCKER_COMPOSE = docker-compose
DOCKER_EXEC = docker exec -it

PROJECT_NAME = meteo_dev
SYMFONY_CONTAINER = $(PROJECT_NAME)_back
FRONT_CONTAINER = $(PROJECT_NAME)_front
PHP_FPM_CONTAINER = $(PROJECT_NAME)_phpfpm
DB_CONTAINER = $(PROJECT_NAME)_db

PROJECT_NAME_PROD = meteo_prod
SYMFONY_CONTAINER_PROD = $(PROJECT_NAME_PROD)_back
FRONT_CONTAINER_PROD = $(PROJECT_NAME_PROD)_front
PHP_FPM_CONTAINER_PROD = $(PROJECT_NAME_PROD)_phpfpm
DB_CONTAINER_PROD = $(PROJECT_NAME_PROD)_db

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
	| awk -F ':.*## \\[soft\\] ' '{ printf "$(BLUE)%-20s$(NC) %s\n", $$1, $$2 }'


.PHONY: help-full
01_help-full: ## [soft] Affiche l’aide complète
	@echo "$(GREEN)Commandes avancées :$(NC)"
	@grep -E '^[A-Za-z0-9._-]+:.*?## .*$$' $(MAKEFILE_LIST) \
	| sort \
	| sed -E 's/## \[(soft|full)\] /## /' \
	| awk 'BEGIN {FS = ":.*?## "}; {printf "$(BLUE)%-20s$(NC) %s\n", $$1, $$2}'



# ======================================
# Docker automatisation
# ======================================

.PHONY: docker-dev
docker-dev: ## [soft] Lance l'environnement de développement Docker
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

	# Exécution des migrations Doctrine
	@echo "Exécution des migrations Doctrine..."
	$(DOCKER_EXEC) ${SYMFONY_CONTAINER} php bin/console doctrine:migrations:migrate --no-interaction

	# Chargement des fixtures (pas encore en place)
	#@echo "Chargement des fixtures..."
	#$(DOCKER_EXEC) ${SYMFONY_CONTAINER} php bin/console doctrine:fixtures:load --no-interaction

	# chargement des anciennes données
	$(DOCKER_EXEC) ${SYMFONY_CONTAINER} php bin/console app:import-sql old_datas.sql --batch-size=2000


# Docker PROD
.PHONY: docker-prod
docker-prod: ## [soft] Lance l'environnement de production Docker
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
	$(DOCKER_COMPOSE) -f docker-compose.prod.yaml exec back composer install --no-dev --optimize-autoloader
	@echo "Warmup cache Symfony prod..."
	$(DOCKER_COMPOSE) -f docker-compose.prod.yaml exec back php bin/console cache:warmup --env=prod

	@echo "Exécution des migrations Doctrine prod..."
	$(DOCKER_COMPOSE) -f docker-compose.prod.yaml exec back php bin/console doctrine:migrations:migrate --no-interaction --env=prod

	@echo "Production Docker environment started."



# /Docker automatisation




# ================================================================
# DOCKER & INFRASTRUCTURE
# ================================================================

.PHONY: up
up: ## [soft] Démarre tous les conteneurs Docker
	@echo "$(GREEN)Démarrage des conteneurs...$(NC)"
	$(DOCKER_COMPOSE) up -d

.PHONY: down
down: ## [soft] Arrête tous les conteneurs Docker
	@echo "$(RED)Arrêt des conteneurs...$(NC)"
	$(DOCKER_COMPOSE) down

.PHONY: down-prod
down-prod: ## [soft] Arrête tous les conteneurs Docker en production
	@echo "$(RED)Arrêt des conteneurs de production...$(NC)"
	$(DOCKER_COMPOSE) -f docker-compose.prod.yaml down

.PHONY: restart
restart: down up ## [soft] Redémarre tous les conteneurs

.PHONY: build
build: ## [soft] Reconstruit tous les conteneurs Docker
	@echo "$(YELLOW)Reconstruction des conteneurs...$(NC)"
	$(DOCKER_COMPOSE) build --no-cache

.PHONY: logs
logs: ## [soft] Affiche les logs de tous les conteneurs
	$(DOCKER_COMPOSE) logs -f

.PHONY: status
status: ## [soft] Affiche le statut des conteneurs
	@echo "$(BLUE)Statut des conteneurs :$(NC)"
	$(DOCKER_COMPOSE) ps

.PHONY: clean
clean: ## Nettoie les conteneurs, volumes et images inutilisés
	@echo "$(YELLOW)Nettoyage Docker...$(NC)"
	docker system prune -f
	docker volume prune -f

# ================================================================
# SYMFONY BACKEND
# ================================================================

.PHONY: sf-bash
sf-bash: ## [soft] Accède au bash du conteneur Symfony
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) bash

.PHONY: sf-console
sf-console: ## Accède à la console Symfony (ex: make symfony-console c="debug:router")
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) php bin/console $(c)

.PHONY: sf-cache-clear
sf-cache-clear: ## Vide le cache Symfony
	@echo "$(YELLOW)Vidage du cache Symfony...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) php bin/console cache:clear

.PHONY: sf-composer-install
sf-composer-install: ## Installe les dépendances Composer
	@echo "$(BLUE)Installation des dépendances Composer...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) composer install

.PHONY: sf-composer-update
sf-composer-update: ## Met à jour les dépendances Composer
	@echo "$(BLUE)Mise à jour des dépendances Composer...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) composer update

.PHONY: sf-migration-make
sf-migration-make: ## Crée une nouvelle migration (ex: make sf-migration-make name="AddUserTable")
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) php bin/console make:migration $(name)

.PHONY: sf-migration-migrate
sf-migration-migrate: ## Exécute les migrations
	@echo "$(BLUE)Exécution des migrations...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) php bin/console doctrine:migrations:migrate --no-interaction

.PHONY: sf-migration-rollback
sf-migration-rollback: ## Rollback de la dernière migration
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) php bin/console doctrine:migrations:migrate prev --no-interaction

.PHONY: sf-database-create
sf-database-create: ## Crée la base de données
	@echo "$(BLUE)Création de la base de données...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) php bin/console doctrine:database:create --if-not-exists

.PHONY: sf-database-drop
sf-database-drop: ## Supprime la base de données
	@echo "$(RED)Suppression de la base de données...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) php bin/console doctrine:database:drop --force

.PHONY: sf-fixtures
sf-fixtures: ## Charge les fixtures (si disponibles)
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) php bin/console doctrine:fixtures:load --no-interaction

.PHONY: sf-entity-make
sf-entity-make: ## Crée une nouvelle entité (ex: make sf-entity-make name="User")
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) php bin/console make:entity $(name)

.PHONY: sf-controller-make
sf-controller-make: ## Crée un nouveau contrôleur (ex: make sf-controller-make name="UserController")
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) php bin/console make:controller $(name)

.PHONY: sf-permissions
sf-permissions: ## Corrige les permissions Symfony
	@echo "$(YELLOW)Correction des permissions...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) chown -R www-data:www-data /var/www/html/var
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) chmod -R 755 /var/www/html/var

# SFONY USER MANAGEMENT
.PHONY: sf-create-user
sf-create-user: ## Crée un nouvel utilisateur (ex: make sf-create-user email="
	@echo "$(YELLOW) Création d'un nouvel utilisateur...$(NC)"
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) php bin/console app:create-user

# ================================================================
# FRONTEND NUXT.JS
# ================================================================

.PHONY: front-bash
front-bash: ## Accède au bash du conteneur Frontend
	$(DOCKER_EXEC) $(FRONT_CONTAINER) bash

.PHONY: front-npm-install
front-npm-install: ## Installe les dépendances npm
	@echo "$(BLUE)Installation des dépendances npm...$(NC)"
	$(DOCKER_EXEC) $(FRONT_CONTAINER) npm install

.PHONY: front-npm-update
front-npm-update: ## Met à jour les dépendances npm
	@echo "$(BLUE)Mise à jour des dépendances npm...$(NC)"
	$(DOCKER_EXEC) $(FRONT_CONTAINER) npm update

.PHONY: front-dev
front-dev: ## Lance le serveur de développement Nuxt
	$(DOCKER_EXEC) $(FRONT_CONTAINER) npm run dev

.PHONY: front-build
front-build: ## Build le frontend pour la production
	@echo "$(BLUE)Build du frontend...$(NC)"
	$(DOCKER_EXEC) $(FRONT_CONTAINER) npm run build

.PHONY: front-lint
front-lint: ## Lance le linter sur le frontend (si configuré)
	$(DOCKER_EXEC) $(FRONT_CONTAINER) npm run lint

# ================================================================
# BASE DE DONNÉES
# ================================================================

.PHONY: db-bash
db-bash: ## Accède au bash du conteneur MySQL
	$(DOCKER_EXEC) $(DB_CONTAINER) bash

.PHONY: db-mysql
db-mysql: ## Accède à MySQL en ligne de commande
	$(DOCKER_EXEC) $(DB_CONTAINER) mysql -u root -p

.PHONY: db-dump
db-dump: ## Sauvegarde la base de données (ex: make db-dump file="backup.sql")
	@echo "$(BLUE)Sauvegarde de la base de données...$(NC)"
	$(DOCKER_EXEC) $(DB_CONTAINER) mysqldump -u root -p --all-databases > $(file)

.PHONY: db-restore
db-restore: ## Restaure la base de données (ex: make db-restore file="backup.sql")
	@echo "$(BLUE)Restauration de la base de données...$(NC)"
	$(DOCKER_EXEC) -i $(DB_CONTAINER) mysql -u root -p < $(file)

# ================================================================
# DÉVELOPPEMENT & TESTS
# ================================================================

.PHONY: test
test: ## [soft] Lance les tests Symfony (si configurés)
	$(DOCKER_EXEC) $(SYMFONY_CONTAINER) php bin/phpunit

.PHONY: fix-permissions
fix-permissions: ## Corrige les permissions des fichiers
	@echo "$(YELLOW)Correction des permissions...$(NC)"
	sudo chown -R $(USER):$(USER) .
	sudo chmod -R 755 .

.PHONY: reset-project
reset-project: ## Remet à zéro le projet (ATTENTION: supprime les données)
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
init-project: ## Initialise le projet pour la première fois
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
phpfpm-bash: ## Accède au bash du conteneur PHP-FPM
	$(DOCKER_EXEC) $(PHP_FPM_CONTAINER) bash

# ================================================================
# MONITORING & DEBUG
# ================================================================

.PHONY: logs-symfony
logs-symfony: ## Affiche les logs du conteneur Symfony
	$(DOCKER_COMPOSE) logs -f back

.PHONY: logs-front
logs-front: ## Affiche les logs du conteneur Frontend
	$(DOCKER_COMPOSE) logs -f front

.PHONY: logs-db
logs-db: ## Affiche les logs du conteneur MySQL
	$(DOCKER_COMPOSE) logs -f db

.PHONY: info
info: ## Affiche les informations du projet
	@echo "$(GREEN)=== Informations du projet Genshin ===$(NC)"
	@echo "$(BLUE)Frontend (Nuxt.js):$(NC) http://localhost:3000"
	@echo "$(BLUE)Backend (Symfony):$(NC) http://localhost:8000"
	@echo "$(BLUE)PhpMyAdmin:$(NC) http://localhost:8080"
	@echo "$(BLUE)Base de données:$(NC) localhost:3306"
	@echo ""
	@echo "$(YELLOW)Conteneurs actifs :$(NC)"
	@$(DOCKER_COMPOSE) ps

# Commande par défaut
.DEFAULT_GOAL := help

# ===============================================================
# Déploiement du front en prod sur un serveur OVH via lftp
# ===============================================================
.PHONY: deploy
deploy: ## Déploie le front et le back en production sur le serveur OVH
	@echo "$(GREEN)Déploiement du front et du back en prod sur un serveur OVH via lftp...$(NC)"
	./scripts/front_deploy.sh
	@echo "$(GREEN)Déploiement terminé avec succès.$(NC)"