# Abandoned artwork cleanup

Install the files under `src/` using their exact names, regenerate Composer's optimized autoloader, and lint each changed PHP file.

Pending uploads receive a private `.cau-pending` marker. Successful order creation removes the marker. WordPress cron runs twice daily and deletes pending artwork older than 48 hours.

The grace period can be changed in seconds:

```php
add_filter( 'cau_abandoned_upload_grace_period', function () {
    return 72 * HOUR_IN_SECONDS;
} );
```

## Test checklist

1. Upload artwork and add it to the cart without checking out. Confirm the artwork and its `.cau-pending` marker exist.
2. Complete checkout. Confirm the artwork remains and its marker disappears.
3. Confirm a failed-payment order also removes the marker and retains the artwork.
4. For a safe local test, temporarily set the grace-period filter to `HOUR_IN_SECONDS`, age a marker beyond one hour, and run the `cau_cleanup_abandoned_artwork` cron event.
5. Confirm the abandoned artwork and marker are deleted, while ordered artwork remains.
