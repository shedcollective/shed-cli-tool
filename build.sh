composer --no-interaction --optimize-autoloader --no-dev install
box build
chmod +x dist/shed.phar
mv dist/shed.phar dist/shed
