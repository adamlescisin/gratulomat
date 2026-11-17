#!/bin/sh
# Plánovač Laravelu pro cron v hPanelu (Hostinger v příkazu cronu nepovoluje znaky jako > nebo &&).
# Cron v hPanelu, každou minutu (* * * * *), typ Custom:
#   /bin/sh /home/u603009705/domains/gratulomat.cz/current/bin/cron.sh
# Chyby se zapisují do storage/logs/cron-error.log (sdílená složka, přežije nasazení).
cd "$(dirname "$0")/.." || exit 1
exec /opt/alt/php83/usr/bin/php artisan schedule:run > /dev/null 2>> storage/logs/cron-error.log
