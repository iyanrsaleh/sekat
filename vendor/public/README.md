# Public (bootstrap backend)

Folder ini **bukan** frontend. Frontend ada di `framework/public/`.

Di sini hanya ada satu file: `index.php`, titik masuk seluruh backend.

## Alur

```text
framework/index.php
  └── require vendor/public/index.php
        ├── autoload (deps/autoload.php bila ada, atau fallback PSR-4)
        ├── baca vendor/config/app.php (timezone, debug)
        ├── ErrorHandler::register()
        ├── muat vendor/config/routes.php
        └── Router::dispatch(Request::capture())->send()
```

## Autoload

Bila `deps/autoload.php` ada (hasil `composer install`), file itu dipakai. Bila belum, dipakai autoloader cadangan:

```php
spl_autoload_register(...); // App\ → vendor/src/
```

Jadi project tetap jalan tanpa Composer.

## Mengubah bootstrap

Lakukan hanya bila perlu menambah layanan global, misalnya:

- konfigurasi tambahan sebelum router,
- pendaftaran middleware,
- pengaturan header keamanan.

Jangan menaruh logika fitur di sini; gunakan controller.

## Aturan

1. Jangan mencetak apa pun sebelum `Response::send()`.
2. Jangan menambah file entry lain; satu pintu sudah cukup.
3. Jangan mengakses `vendor/public/index.php` langsung dari browser; akses lewat `framework/index.php` atau URL bersih (`.htaccess`).