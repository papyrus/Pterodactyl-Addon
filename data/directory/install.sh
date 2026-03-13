#!/bin/bash
cd "{root}"
php artisan migrate --force --path=database/migrations/papyrus

# Register auto-protect event listener in EventServiceProvider
LISTENER_IMPORT="use Pterodactyl\\\BlueprintFramework\\\Extensions\\\papyrusprotection\\\Listeners\\\ServerInstalledListener;"
LISTENER_MAPPING="ServerInstalledEvent::class => [ServerInstalledNotification::class, ServerInstalledListener::class],"

ESP_FILE="app/Providers/EventServiceProvider.php"

# Add import if not already present
if ! grep -q "ServerInstalledListener" "$ESP_FILE"; then
  # Add the use statement after the existing ServerInstalledNotification import
  sed -i '/use Pterodactyl\\Notifications\\ServerInstalled as ServerInstalledNotification;/a '"$LISTENER_IMPORT" "$ESP_FILE"

  # Replace the existing listener mapping to include our listener
  sed -i "s|ServerInstalledEvent::class => \[ServerInstalledNotification::class\],|$LISTENER_MAPPING|" "$ESP_FILE"
fi

echo "Papyrus Protection installed. Configure at Admin → Extensions → Papyrus Protection."
