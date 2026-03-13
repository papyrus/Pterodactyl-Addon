#!/bin/bash
cd "{root}"
echo "WARNING: Active proxies will NOT be auto-deleted from Papyrus."
echo "Disable protection on all servers first, or clean up at dash.papyrus.vip"

# Unregister auto-protect event listener from EventServiceProvider
ESP_FILE="app/Providers/EventServiceProvider.php"

if grep -q "ServerInstalledListener" "$ESP_FILE"; then
  # Remove the import line
  sed -i '/use Pterodactyl\\BlueprintFramework\\Extensions\\papyrusprotection\\Listeners\\ServerInstalledListener;/d' "$ESP_FILE"

  # Restore the original listener mapping (remove our listener from the array)
  sed -i "s|ServerInstalledEvent::class => \[ServerInstalledNotification::class, ServerInstalledListener::class\],|ServerInstalledEvent::class => [ServerInstalledNotification::class],|" "$ESP_FILE"
fi

php artisan migrate:rollback --path=database/migrations/papyrus --force
echo "Papyrus Protection removed."
