# Adding a module

A module is one kind of record your app manages: products, students, invoices. `make:module` writes everything for it, following the same rules as the rest of the code.

```bash
php artisan make:module Product --fields="name:string,sku:string:unique,price:decimal,stock:integer,is_active:boolean,released_on:date:nullable,category_id:foreignId"
php artisan migrate
php artisan test --filter=Product
```

With Sail, put `./vendor/bin/sail` in front of each command.

## What you get

| File | What it does |
|---|---|
| `app/Models/Product.php` | Fillable fields, casts, relations, activity logging, soft deletes |
| `database/migrations/..._create_products_table.php` | The table |
| `database/factories/ProductFactory.php` | Believable fake records, used by tests and the seeder |
| `database/seeders/ProductSeeder.php` | 10 sample records (`php artisan db:seed --class=ProductSeeder`; `--demo` installs run it too) |
| `app/Policies/ProductPolicy.php` | Who can do what, using `products.view/create/update/delete/restore` |
| `app/Filament/Resources/Products/...` | Admin list (search, sorting, filters), create, view, edit and delete |
| `app/Http/Controllers/Api/V1/ProductController.php` | `GET/POST /api/v1/products`, `GET/PATCH/DELETE /api/v1/products/{id}` and restore |
| `app/Http/Requests/Api/V1/StoreProductRequest.php`, `UpdateProductRequest.php` | API validation |
| `app/Http/Resources/V1/ProductResource.php` | The API's JSON for a product |
| `routes/api/modules/products.php` | The API routes, loaded automatically |
| `tests/Feature/Modules/ProductAdminTest.php`, `ProductApiTest.php` | Pest tests for the screens and the API |

The permissions are created and given to the admin role straight away. The new screens and endpoints show up without any other changes, including in the API docs.

## Fields

Write each field as `name:type`, separated by commas. Names are lowercase `snake_case`; `id`, `created_at`, `updated_at` and `deleted_at` are added for you.

| Type | Column | Admin field | Notes |
|---|---|---|---|
| `string` | `string` | text input | The first string field is used as the record's name. |
| `text` | `text` | text area | |
| `integer` | `integer` | number | |
| `decimal` | `decimal(12, 2)` | number | Money and measurements. |
| `boolean` | `boolean`, default false | toggle | Filterable yes/no. |
| `date` | `date` | date picker | |
| `datetime` | `dateTime` | date and time picker | |
| `json` | `json` | key-value editor | |
| `foreignId` | `foreignId`, constrained | searchable select | Name must end in `_id`; the related model must already exist. |

Add options after the type: `:nullable` (can be empty) and `:unique` (no two records can share it), for example `notes:text:nullable` or `sku:string:unique`.

## Options

| Option | Effect |
|---|---|
| `--no-api` | No API controller, requests, resource, routes or API tests. |
| `--no-admin` | No admin screens or admin tests. |
| `--no-tests` | No tests. |
| `--soft-deletes=false` | Deleting removes the record for good, and there's no restore. |

## Removing a module

```bash
php artisan remove:module Product
```

It lists the files, asks for confirmation, then deletes them and the `products.*` permissions. The table and its records stay unless you confirm deleting them too, or pass `--drop-table`. `--force` skips the questions.

Modules are tracked in `modules.json`. Don't edit it by hand unless you also move or delete the files it lists.

## Changing what modules look like

The templates are in `stubs/module`. Edit them to change every module you make from then on. Placeholders look like `{{ Model }}`; blocks like `{{#softDeletes}}...{{/softDeletes}}` only appear when soft deletes are on.

After changing a template, run `composer check:generator`. It makes two linked sample modules with every field type, runs Pint, Larastan and their tests, and removes them. CI runs it on every push.

## After generating

Generated code is yours. Typical next steps:

- Add a navigation group: `protected static string|UnitEnum|null $navigationGroup = 'Catalogue';` in the resource.
- Add a dashboard widget: create a class in `app/Filament/Widgets` with a `canView()` that checks `products.view`.
- Add business rules in an action class under `app/Actions` and call it from the admin page and the API controller.
