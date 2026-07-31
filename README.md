# Secure administrator artwork download

Copy these files into the plugin root:

- `src/Admin/ArtworkDownload.php`
- `src/Plugin.php` (replace the existing file)

Then run:

```powershell
composer dump-autoload -o
php -l src\Admin\ArtworkDownload.php
php -l src\Plugin.php
```

Test with an order containing an artwork upload:

1. Open **WooCommerce > Orders** and edit the order.
2. Find the product line item and click **Download artwork**.
3. Confirm the original filename downloads and the stored randomized filename is not exposed.
4. Open the download link in a private/incognito browser window and confirm access is denied or redirected to login.
5. Sign in as a user without WooCommerce-management permission and confirm the link is absent and direct access returns `403`.
6. Confirm the direct URL inside `wp-content/uploads/customer-artwork-upload-private/` remains blocked.

The controller verifies the WooCommerce management capability, a per-order-item nonce,
the order item, its order, and the protected file before streaming it.
