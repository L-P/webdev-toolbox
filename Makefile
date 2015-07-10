COMPOSER_URL=https://getcomposer.org/composer.phar
COMPOSER=./composer.phar

all: vendor

vendor: $(COMPOSER)
	"$(COMPOSER)" install

$(COMPOSER):
	wget --no-use-server-timestamps -nv -O "$(COMPOSER)" "$(COMPOSER_URL)"
	chmod +x "$(COMPOSER)"
