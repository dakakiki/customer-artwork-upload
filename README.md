# Artwork lifecycle cleanup

Copy these files into the plugin root, preserving their paths:

- `src/WooCommerce/ArtworkCleanup.php`
- `src/Plugin.php`

The supplied `Plugin.php` assumes the existing `ArtworkDownload` constructor
accepts the shared `StorageInterface` instance, as implemented in the previous
secure-download milestone.

## Validate

```powershell
composer dump-autoload -o
php -l src\WooCommerce\ArtworkCleanup.php
php -l src\Plugin.php
```

## Test

Use a fresh test order containing an artwork upload and note the randomized
stored filename before each test.

1. Cancel the order: the stored file must remain.
2. Refund the order: the stored file must remain.
3. Move the order to Trash: the stored file must remain and downloads must work
   after restoring the order.
4. Permanently delete the order from Trash: the stored artwork file must be
   removed.
5. Permanently delete an order without artwork: deletion must complete without
   an error.

Run the permanent-deletion test once with HPOS enabled. If your site supports
switching to legacy order storage in a test environment, repeat it there too.
