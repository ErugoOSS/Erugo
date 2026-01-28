SAIL=./vendor/bin/sail

.PHONY: bootstrap up down reset logs test

bootstrap:
	@bash ./scripts/bootstrap.sh

up:
	$(SAIL) up -d

down:
	$(SAIL) down

reset:
	$(SAIL) artisan migrate:fresh --seed

logs:
	$(SAIL) logs -f

test:
	$(SAIL) artisan test
