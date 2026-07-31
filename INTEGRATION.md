# Protected artwork storage integration

Copy the three PHP classes into the matching paths under your plugin's `src/` directory.

In `src/Plugin.php`, add these imports:

```php
use DakaKiki\CustomerArtworkUpload\Storage\LocalStorage;
use DakaKiki\CustomerArtworkUpload\WooCommerce\CartArtwork;
```

Immediately after `ProductUploadField::register();`, register cart/order persistence:

```php
$cart_artwork = new CartArtwork( new LocalStorage() );
$cart_artwork->register();
```

The resulting section should be:

```php
ProductSettings::register();
ProductUploadField::register();

$cart_artwork = new CartArtwork( new LocalStorage() );
$cart_artwork->register();
```

Regenerate Composer's optimized autoloader and lint:

```powershell
composer dump-autoload -o
php -l src\Storage\StorageInterface.php
php -l src\Storage\LocalStorage.php
php -l src\WooCommerce\CartArtwork.php
php -l src\Plugin.php
```

## Tests

1. Upload an allowed file and add the product to the cart.
2. Confirm the cart displays the safe original filename beside `Artwork`.
3. Confirm a randomized file exists in `wp-content/uploads/customer-artwork-upload-private/`.
4. Confirm the directory also contains `index.php`, `.htaccess`, and `web.config`.
5. Try opening the randomized file URL directly. Apache/WAMP should return `403 Forbidden`.
6. Complete a test checkout and inspect the order item metadata. It should contain the four private `_cau_artwork_*` values.
7. Add the same product twice with two different files. WooCommerce should create two cart lines.

Do not delete the test order or stored files yet. Cleanup and the authorized download controller are separate milestones.
