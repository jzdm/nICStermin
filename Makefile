install:
	@echo "Requirements:"
	@echo "- Composer"
	@echo "- Node"
	@echo "- WP-CLI"
	@echo "- Docker & Docker Compose"

build:
	rm -rf dist
	mkdir -p dist/nICStermin
	
	# Copy all files
	rsync -a --exclude '.git*' \
			 --exclude 'allowed_signers' \
			 --exclude '.DS_Store' \
			 --exclude 'node_modules' \
			 --exclude 'dist' \
			 --exclude 'vendor' \
			 --exclude 'docker' \
			 --exclude 'cache/calendars/*' \
			 --exclude 'Makefile' \
			 --exclude '*.pot' \
			 --exclude '*.po' \
			 --exclude 'package.json' --exclude 'package-lock.json' \
			 --exclude 'tailwind.config.js' \
			 --exclude '*.code-workspace' \
			 --exclude 'test.php' \
			 . dist/nICStermin
	
	# compile CSS
	npx tailwindcss -i ./public/css/input.css -o ./dist/nICStermin/public/css/styles.css --minify
	rm dist/nICStermin/public/css/input.css
	
	# load compose dependencies
	cd dist/nICStermin && composer install --no-ansi --no-dev --no-interaction --no-plugins --no-progress --no-scripts --optimize-autoloader
	rm dist/nICStermin/composer.json
	rm dist/nICStermin/composer.lock

zip:
	cd dist/ && zip -r nICStermin-vX.zip nICStermin

serve:
	cd docker && docker compose up -d
	npx tailwindcss -i ./public/css/input.css -o ./public/css/styles.css --watch

i18n:
	wp i18n make-pot . ./languages/nicstermin.pot --domain="nicstermin" --slug="nicstermin" --exclude="docker,cache,languages,*.json,*.lock,*.config.js"

i18n-compile:
	msgfmt -o ./languages/nicstermin-de_DE.mo ./languages/nicstermin-de_DE.po
