# Building features

This guide builds real features on top of Pundasyon, step by step. Every snippet comes from code that was run against this project and passed `composer check` (Pint, Larastan level 8 and the full test suite).

With Sail, put `./vendor/bin/sail` in front of each `php artisan`, `composer` and `php` command.

- [The rules every feature follows](#the-rules-every-feature-follows)
- [Tutorial 1: a CRUD module, then making it yours](#tutorial-1-a-crud-module-then-making-it-yours)
- [Tutorial 2: records people own](#tutorial-2-records-people-own)
- [Tutorial 3: a feature without a module](#tutorial-3-a-feature-without-a-module)
- [Recipes](#recipes): a setting, a scheduled job, a queued email, API version 2
- [Before you open a pull request](#before-you-open-a-pull-request)

## The rules every feature follows

1. **A permission per action.** Every action someone can take is a permission named `{module}.{action}`, such as `products.discontinue`. Action names use lowercase letters and `_`.
2. **Policies decide.** Screens and API endpoints ask the policy (`$user->can('discontinue', $product)`). They never check roles by name.
3. **Rules live in one place.** Business logic goes in a class under `app/Actions`. The admin screen and the API controller both call it.
4. **Tests come with it.** Each feature has Pest tests for what it allows, what it refuses, and who can see it.

Super admins pass every permission check. Admins get every new permission automatically, except those listed in `not_for_admins` in `config/permissions.php`.

## Tutorial 1: a CRUD module, then making it yours

You're building a small catalogue: categories and products.

### 1. Generate the modules

```bash
php artisan make:module Category --fields="name:string:unique" --soft-deletes=false
php artisan make:module Product --fields="name:string,sku:string:unique,price:decimal,stock:integer,is_active:boolean,category_id:foreignId"
php artisan migrate
php artisan test --filter="Product|Category"
```

Make `Category` first: `category_id:foreignId` needs the `Category` model to exist.

Sign in at `/app`. **Categories** and **Products** are now in the menu, with list, search, filters, create, view, edit and delete. The API has `/api/v1/categories` and `/api/v1/products`, and they're listed at `/docs/api`. [Adding a module](modules.md) lists every file that was written.

From here the code is yours. The next steps change it.

### 2. Group the menu

In `app/Filament/Resources/Products/ProductResource.php`:

```php
use UnitEnum;

protected static string|UnitEnum|null $navigationGroup = 'Catalogue';

protected static ?int $navigationSort = 1;
```

Do the same in `CategoryResource.php` with `$navigationSort = 2`.

### 3. Tighten validation

The generator allows any decimal, including negative prices. Change the price rule to `'min:0'` in both `app/Http/Requests/Api/V1/StoreProductRequest.php` and `UpdateProductRequest.php`, then add the same limit to the admin form in `app/Filament/Resources/Products/Schemas/ProductForm.php`:

```php
TextInput::make('price')->label('Price')->required()->numeric()->step('0.01')->minValue(0),
```

API rules and form rules are separate on purpose: the API is used without the form. Change both.

### 4. Add a custom action: "Discontinue"

Discontinuing takes a product off sale and sets its stock to zero. It needs its own permission, because not everyone who edits products should do it.

**Permission.** Add it in `config/permissions.php`, then sync:

```php
'modules' => [
    // ...
    'products' => ['discontinue'],
],
```

```bash
php artisan permissions:sync
```

Actions listed here are added to the module's generated ones, so `products` now has `view`, `create`, `update`, `delete`, `restore` and `discontinue`. Admins get it straight away. To give it to another role, tick **Products: discontinue** on the role's page.

**Rule.** `app/Actions/Products/DiscontinueProduct.php`:

```php
<?php

declare(strict_types=1);

namespace App\Actions\Products;

use App\Models\Product;

/**
 * Takes a product off sale. Used by the admin screen and the API, so the rule
 * lives in one place.
 */
final class DiscontinueProduct
{
    public function __invoke(Product $product): void
    {
        $product->update(['is_active' => false, 'stock' => 0]);

        activity('products')->performedOn($product)->event('discontinued')->log('Discontinued product');
    }
}
```

The update is already in the activity log, because the model logs its changes. The extra `activity()` call records *why* it changed.

**Policy.** In `app/Policies/ProductPolicy.php`:

```php
/** Only products still on sale can be discontinued. */
public function discontinue(User $user, Product $product): bool
{
    return $user->can('products.discontinue') && $product->is_active;
}
```

**Admin screen.** In `app/Filament/Resources/Products/Tables/ProductsTable.php`, add the action to the row's action group:

```php
use App\Actions\Products\DiscontinueProduct;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

ActionGroup::make([
    Action::make('discontinue')
        ->icon(Heroicon::OutlinedArchiveBox)
        ->color('warning')
        ->requiresConfirmation()
        ->modalDescription('It comes off sale and its stock is set to zero.')
        ->visible(fn (Product $record): bool => Auth::user()?->can('discontinue', $record) ?? false)
        ->action(fn (Product $record) => app(DiscontinueProduct::class)($record))
        ->successNotificationTitle('Discontinued'),
    DeleteAction::make(),
    // ...
]),
```

Filament checks `visible()` again when the action runs, so hiding the button also blocks the action.

**API.** Add the route to `routes/api/modules/products.php`:

```php
Route::post('products/{product}/discontinue', [ProductController::class, 'discontinue'])->name('products.discontinue');
```

And the method to `app/Http/Controllers/Api/V1/ProductController.php`:

```php
use App\Actions\Products\DiscontinueProduct;

/**
 * Discontinue a product: it comes off sale and its stock is set to zero.
 */
public function discontinue(Product $product, DiscontinueProduct $discontinue): ProductResource
{
    Gate::authorize('discontinue', $product);
    $discontinue($product);

    return new ProductResource($product->load(['category']));
}
```

The doc comment becomes the endpoint's description at `/docs/api`. A token needs the `products.discontinue` ability to call it. Tokens made without a list of abilities get all of the user's permissions.

### 5. Add a dashboard widget

`app/Filament/Widgets/ProductStats.php` appears on the dashboard automatically:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

final class ProductStats extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::user()?->can('products.view') ?? false;
    }

    protected function getStats(): array
    {
        $onSale = Product::query()->where('is_active', true);

        return [
            Stat::make('Products on sale', number_format((clone $onSale)->count())),
            Stat::make('Out of stock', number_format((clone $onSale)->where('stock', 0)->count())),
            Stat::make('Stock value', number_format((float) (clone $onSale)->sum(Product::query()->raw('price * stock')), 2)),
        ];
    }
}
```

`canView()` keeps the numbers away from people who can't see products.

### 6. Test it

`tests/Feature/Modules/ProductDiscontinueTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Widgets\ProductStats;
use App\Models\Product;
use Filament\Actions\Testing\TestAction;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

it('lets admins discontinue a product, and logs it', function (): void {
    $this->actingAs(userWithRole(SystemRole::Admin));
    $product = Product::factory()->create(['is_active' => true, 'stock' => 12]);

    livewire(ListProducts::class)->callAction(TestAction::make('discontinue')->table($product));

    expect($product->fresh())->is_active->toBeFalse()->stock->toBe(0)
        ->and(Activity::query()->where('event', 'discontinued')->exists())->toBeTrue();
});

it('hides the action for products already off sale', function (): void {
    $this->actingAs(userWithRole(SystemRole::Admin));
    $offSale = Product::factory()->create(['is_active' => false]);

    livewire(ListProducts::class)->assertActionHidden(TestAction::make('discontinue')->table($offSale));
});

it('discontinues through the API with the right ability only', function (): void {
    $admin = userWithRole(SystemRole::Admin);
    $product = Product::factory()->create(['is_active' => true]);

    asApiUser($admin, ['products.view'])->postJson("/api/v1/products/{$product->id}/discontinue")->assertForbidden();
    asApiUser($admin)->postJson("/api/v1/products/{$product->id}/discontinue")->assertOk()->assertJsonPath('data.is_active', false);
});

it('refuses a negative price', function (): void {
    asApiUser(userWithRole(SystemRole::Admin))->postJson('/api/v1/products', [...productApiData(), 'price' => -1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('price');
});

it('shows product numbers on the dashboard', function (): void {
    $this->actingAs(userWithRole(SystemRole::Admin));
    Product::factory()->create(['is_active' => true, 'stock' => 0]);

    livewire(ProductStats::class)->assertSee('Out of stock');
});

it('refuses the action to people without the permission, even if called directly', function (): void {
    $this->actingAs(userWithRole(SystemRole::User)->givePermissionTo('products.view'));
    $product = Product::factory()->create(['is_active' => true]);

    livewire(ListProducts::class)
        ->call('mountAction', 'discontinue', [], ['table' => true, 'recordKey' => (string) $product->getKey()])
        ->call('callMountedAction');

    expect($product->fresh()->is_active)->toBeTrue();
});
```

Helpers from `tests/Pest.php`:

| Helper | Does |
|---|---|
| `seedAccess()` | Creates every permission and the built-in roles. |
| `userWithRole(SystemRole::Admin)` | A user with a built-in role. It calls `seedAccess()` for you. |
| `asApiUser($user, ['products.view'])` | Sends the next requests with a real API token. Leave out the list to get every permission the user has. |

`productApiData()` comes from the generated `ProductApiTest.php`. It returns valid input made by the factory.

```bash
php artisan test --filter=Product
composer check
```

## Tutorial 2: records people own

Generated modules are shared: anyone with `notes.view` sees every note. Here each person sees and changes only their own notes, and people with a new `notes.view_all` permission see everyone's.

```bash
php artisan make:module Note --fields="title:string,body:text,user_id:foreignId"
php artisan migrate
```

**Permission.** In `config/permissions.php`, then `php artisan permissions:sync`:

```php
'notes' => ['view_all'],
```

**One query rule.** Add a scope to `app/Models/Note.php`. The admin screen and the API both use it:

```php
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Notes the user may see: their own, or every note with notes.view_all.
 *
 * @param  Builder<self>  $query
 */
#[Scope]
protected function visibleTo(Builder $query, ?User $user): void
{
    if ($user?->can('notes.view_all') !== true) {
        $query->where('user_id', $user?->id);
    }
}
```

With no user, the query matches nothing.

**Policy.** In `app/Policies/NotePolicy.php`, require ownership for `view`, `update`, `delete` and `restore`:

```php
public function update(User $user, Note $note): bool
{
    return $user->can('notes.update') && $this->owns($user, $note);
}

/** People see and change their own notes; notes.view_all opens everyone's. */
private function owns(User $user, Note $note): bool
{
    return $note->user_id === $user->id || $user->can('notes.view_all');
}
```

**Admin list and pages.** In `app/Filament/Resources/Notes/NoteResource.php`, scope both queries. The second one means opening someone else's note by URL gives a 404:

```php
use Illuminate\Support\Facades\Auth;

/**
 * Only the notes the signed-in person may see.
 *
 * @return Builder<Note>
 */
public static function getEloquentQuery(): Builder
{
    /** @var Builder<Note> $query */
    $query = parent::getEloquentQuery()->with(['user']);

    return $query->visibleTo(Auth::user());
}

/**
 * @return Builder<Note>
 */
public static function getRecordRouteBindingEloquentQuery(): Builder
{
    /** @var Builder<Note> $query */
    $query = parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);

    return $query->visibleTo(Auth::user());
}
```

**The owner is whoever writes it.** Remove the `Select::make('user_id')` line from `Schemas/NoteForm.php`. Then set the owner in `Pages/CreateNote.php`:

```php
use Illuminate\Support\Facades\Auth;

/**
 * The note belongs to whoever writes it.
 *
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
protected function mutateFormDataBeforeCreate(array $data): array
{
    return [...$data, 'user_id' => Auth::id()];
}
```

**API.** Remove the `user_id` rule from `StoreNoteRequest` and `UpdateNoteRequest`, so clients can't choose an owner. Then, in `NoteController`:

```php
// index
$notes = QueryBuilder::for(Note::query()->visibleTo($request->user()))

// store
$note = Note::query()->create([...$request->validated(), 'user_id' => $request->user()?->id]);
```

`show`, `update`, `destroy` and `restore` already ask the policy, so they refuse other people's notes with a 403.

**Tests.** Remove `'user_id' => $model->user_id,` from the form and API data helpers in the generated `NoteAdminTest.php` and `NoteApiTest.php`. Then add `tests/Feature/Modules/NoteOwnershipTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Filament\Resources\Notes\Pages\CreateNote;
use App\Filament\Resources\Notes\Pages\EditNote;
use App\Filament\Resources\Notes\Pages\ListNotes;
use App\Models\Note;
use App\Models\User;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

/** A role that can work with notes, but not everyone's. */
function noteWriter(): User
{
    $user = userWithRole(SystemRole::User);
    Role::findOrCreate('writer', 'web')->syncPermissions(['notes.view', 'notes.create', 'notes.update', 'notes.delete']);

    return $user->assignRole('writer');
}

it('lists only your own notes', function (): void {
    $me = noteWriter();
    $mine = Note::factory()->for($me)->create();
    $theirs = Note::factory()->create();

    $this->actingAs($me);

    livewire(ListNotes::class)->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$theirs]);
});

it('makes you the owner of the notes you write', function (): void {
    $me = noteWriter();
    $this->actingAs($me);

    livewire(CreateNote::class)->fillForm(['title' => 'Call back', 'body' => 'Tomorrow'])->call('create')->assertHasNoFormErrors();

    expect(Note::query()->sole()->user_id)->toBe($me->id);
});

it("keeps you out of other people's notes", function (): void {
    $this->actingAs(noteWriter());
    $theirs = Note::factory()->create();

    $this->get(EditNote::getUrl(['record' => $theirs]))->assertNotFound();
});

it('scopes the API the same way', function (): void {
    $me = noteWriter();
    Note::factory()->for($me)->create();
    $theirs = Note::factory()->create();

    asApiUser($me)->getJson('/api/v1/notes')->assertOk()->assertJsonCount(1, 'data');
    asApiUser($me)->getJson("/api/v1/notes/{$theirs->id}")->assertForbidden();
    asApiUser($me)->postJson('/api/v1/notes', ['title' => 'Mine', 'body' => 'x', 'user_id' => $theirs->user_id])
        ->assertCreated()
        ->assertJsonPath('data.user_id', $me->id);
});

it('shows every note to people with notes.view_all', function (): void {
    $this->actingAs(userWithRole(SystemRole::Admin));
    $notes = Note::factory()->count(2)->create();

    livewire(ListNotes::class)->assertCanSeeTableRecords($notes);
});
```

Call `userWithRole()` before creating a role: it's what creates the permissions.

## Tutorial 3: a feature without a module

Not every feature is a table of records. This one adds an **Export CSV** button to the Users list.

**Permission.** Add `export` to `users` in `config/permissions.php`, then `php artisan permissions:sync`:

```php
'users' => ['view', 'create', 'update', 'delete', 'restore', 'impersonate', 'export'],
```

**Rule.** `app/Actions/Users/ExportUsers.php`:

```php
<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use SplFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads every user as a CSV file. Rows are streamed in chunks, so a large
 * user list does not have to fit in memory.
 */
final class ExportUsers
{
    public function __invoke(): StreamedResponse
    {
        activity('access')->event('exported')->log('Exported users');

        return response()->streamDownload(function (): void {
            $out = new SplFileObject('php://output', 'w');
            $out->fputcsv(['Name', 'Email', 'Roles', 'Status', 'Joined']);

            User::query()->with('roles')->orderBy('id')->chunk(500, function ($users) use ($out): void {
                foreach ($users as $user) {
                    $out->fputcsv(array_map(self::safe(...), [
                        $user->name,
                        $user->email,
                        $user->roles->pluck('name')->join(', '),
                        $user->status->value,
                        $user->created_at?->toDateString() ?? '',
                    ]));
                }
            });
        }, 'users-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Stops spreadsheet apps from running a cell as a formula (CSV injection). */
    private static function safe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
```

People choose their own names, so a name like `=HYPERLINK(...)` would run as a formula when the file is opened. `safe()` stops that.

**Button.** In `app/Filament/Resources/Users/Pages/ListUsers.php`:

```php
use App\Actions\Users\ExportUsers;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

protected function getHeaderActions(): array
{
    return [
        Action::make('export')
            ->label('Export CSV')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->visible(fn (): bool => Auth::user()?->can('users.export') ?? false)
            ->action(fn () => app(ExportUsers::class)()),
        CreateAction::make(),
    ];
}
```

**Tests.** `tests/Feature/ExportUsersTest.php`:

```php
<?php

declare(strict_types=1);

use App\Actions\Users\ExportUsers;
use App\Enums\SystemRole;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

it('lets admins download users as CSV', function (): void {
    $this->actingAs(userWithRole(SystemRole::Admin));

    livewire(ListUsers::class)->callAction('export')->assertFileDownloaded('users-'.now()->format('Y-m-d').'.csv');
});

it('writes one row per user, and defuses formulas', function (): void {
    seedAccess();
    User::factory()->create(['name' => '=HYPERLINK("http://evil.test")', 'email' => 'x@example.com']);

    ob_start();
    app(ExportUsers::class)()->sendContent();
    $csv = (string) ob_get_clean();

    expect($csv)->toContain('Name,Email,Roles,Status,Joined')
        ->toContain("\"'=HYPERLINK(\"\"http://evil.test\"\")\"");
});

it('hides the export from people without users.export', function (): void {
    seedAccess();
    Role::findOrCreate('viewer', 'web')->syncPermissions(['users.view']);
    $this->actingAs(User::factory()->create()->assignRole('viewer'));

    livewire(ListUsers::class)->assertActionHidden('export');
});
```

For very large exports, or exports emailed when they're ready, use Filament's built-in `ExportAction`. It runs in the queue and needs its own migrations.

## Recipes

These three recipes build one feature together: a daily email to admins listing products that are low on stock.

### A setting admins can change

Settings use [spatie/laravel-settings](https://github.com/spatie/laravel-settings). Each value is a typed property, stored in the database.

1. Add the property to `app/Settings/GeneralSettings.php`:

   ```php
   /** Products at or below this stock count are reported as low. */
   public int $low_stock_threshold;
   ```

2. Give it a starting value in a settings migration, `database/settings/2026_11_08_000000_add_low_stock_threshold.php`:

   ```php
   <?php

   declare(strict_types=1);

   use Spatie\LaravelSettings\Migrations\SettingsMigration;

   return new class extends SettingsMigration
   {
       public function up(): void
       {
           $this->migrator->add('general.low_stock_threshold', 5);
       }
   };
   ```

3. Add the field to `app/Filament/Pages/Settings/ManageGeneral.php`:

   ```php
   Section::make('Catalogue')->schema([
       TextInput::make('low_stock_threshold')
           ->label('Low stock at')
           ->helperText('Admins get a daily email listing products at or below this stock count.')
           ->integer()
           ->minValue(0)
           ->required(),
   ]),
   ```

4. Run `php artisan migrate`. Read the value anywhere with `app(GeneralSettings::class)->low_stock_threshold`, or by type-hinting `GeneralSettings`.

Changes are logged in the activity log. Secrets belong in a settings class with `encrypted()`, like `MailSettings`.

### A queued email

`app/Notifications/LowStockReport.php`:

```php
<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The daily list of products running low. Queued, so the scheduler is not
 * held up by the mail server.
 */
final class LowStockReport extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Product>  $products
     */
    public function __construct(public Collection $products) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(trans_choice(':count product is low on stock|:count products are low on stock', $this->products->count()))
            ->line('These products are at or below the low-stock level:');

        foreach ($this->products as $product) {
            $message->line("{$product->name} ({$product->sku}): {$product->stock} left");
        }

        return $message->action('Open products', url('/app/products'));
    }
}
```

`ShouldQueue` sends it from the `queue` container. Locally, the email shows up in Mailpit at <http://localhost:8031>.

### A scheduled job

`app/Console/Commands/ReportLowStock.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\User;
use App\Notifications\LowStockReport;
use App\Settings\GeneralSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Emails everyone who can update products when products on sale run low.
 * Scheduled daily in routes/console.php.
 */
#[Signature('products:report-low-stock')]
#[Description('Email a list of products that are low on stock')]
final class ReportLowStock extends Command
{
    public function handle(GeneralSettings $settings): int
    {
        $low = Product::query()
            ->where('is_active', true)
            ->where('stock', '<=', $settings->low_stock_threshold)
            ->orderBy('stock')
            ->get();

        if ($low->isEmpty()) {
            $this->info('Nothing is low on stock.');

            return self::SUCCESS;
        }

        $recipients = User::permission('products.update')->get();
        Notification::send($recipients, new LowStockReport($low));

        $this->info("Reported {$low->count()} products to {$recipients->count()} people.");

        return self::SUCCESS;
    }
}
```

Schedule it in `routes/console.php`:

```php
// Emails a list of products that are low on stock.
Schedule::command('products:report-low-stock')->dailyAt('07:00');
```

The `scheduler` container runs the schedule. Run the command once by hand with `php artisan products:report-low-stock`, and see every scheduled task with `php artisan schedule:list`. Times are in UTC. The [deployment guide](deployment.md) covers the cron line for servers.

`User::permission('products.update')` finds people by permission, not by role, so it keeps working when you add roles.

Tests, in `tests/Feature/LowStockReportTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Filament\Pages\Settings\ManageGeneral;
use App\Models\Product;
use App\Notifications\LowStockReport;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

it('emails admins the products at or below the threshold', function (): void {
    Notification::fake();
    $admin = userWithRole(SystemRole::Admin);
    $member = userWithRole(SystemRole::User);
    $low = Product::factory()->create(['is_active' => true, 'stock' => 2]);
    Product::factory()->create(['is_active' => true, 'stock' => 50]);
    Product::factory()->create(['is_active' => false, 'stock' => 0]);

    $this->artisan('products:report-low-stock')->assertSuccessful();

    Notification::assertSentTo($admin, LowStockReport::class, fn (LowStockReport $n): bool => $n->products->modelKeys() === [$low->id]);
    Notification::assertNotSentTo($member, LowStockReport::class);
});

it('sends nothing when stock is fine', function (): void {
    Notification::fake();
    userWithRole(SystemRole::Admin);
    Product::factory()->create(['is_active' => true, 'stock' => 50]);

    $this->artisan('products:report-low-stock')->expectsOutput('Nothing is low on stock.');

    Notification::assertNothingSent();
});

it('lets admins change the threshold', function (): void {
    $this->actingAs(userWithRole(SystemRole::Admin));

    livewire(ManageGeneral::class)->fillForm(['low_stock_threshold' => 10])->call('save')->assertHasNoFormErrors();

    expect(app(GeneralSettings::class)->low_stock_threshold)->toBe(10);
});

it('is scheduled daily', function (): void {
    $this->artisan('schedule:list')->expectsOutputToContain('products:report-low-stock');
});
```

### API version 2

Change v1's JSON and every client that uses it breaks. Put the change in v2 instead, and leave v1 as it is. Version 2 only needs the endpoints that changed.

Here, v2 sends prices as whole cents. Add `app/Http/Resources/V2/ProductResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\V2;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Version 2 sends the price as a whole number of cents, so clients never
 * round a decimal string.
 *
 * @mixin Product
 */
final class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'price_cents' => (int) round((float) $this->price * 100),
            'stock' => $this->stock,
            'is_active' => $this->is_active,
            'category_id' => $this->category_id,
        ];
    }
}
```

Add `app/Http/Controllers/Api/V2/ProductController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Controller;
use App\Http\Resources\V2\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class ProductController extends Controller
{
    /**
     * List products, with prices in cents.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Product::class);

        return ProductResource::collection(Product::query()->latest()->paginate(UserController::perPage($request)));
    }
}
```

Then add the v2 group at the end of `routes/api.php`, with the same middleware as v1:

```php
use App\Http\Controllers\Api\V2;

/*
| Version 2: only what changed. Everything else stays on v1.
*/
Route::prefix('v2')->name('api.v2.')->middleware(['auth:sanctum', EnsureApiUserIsActive::class, 'throttle:api'])->group(function (): void {
    Route::get('products', [V2\ProductController::class, 'index'])->name('products.index');
});
```

The same tokens work on both versions. Test that v1 hasn't changed:

```php
it('sends prices in cents on v2 and leaves v1 alone', function (): void {
    $admin = userWithRole(SystemRole::Admin);
    Product::factory()->create(['price' => '12.50']);

    asApiUser($admin)->getJson('/api/v2/products')->assertOk()->assertJsonPath('data.0.price_cents', 1250);
    asApiUser($admin)->getJson('/api/v1/products')->assertOk()->assertJsonPath('data.0.price', '12.50');
});
```

Tell clients when v1 will be removed, and record it in `CHANGELOG.md`.

## Before you open a pull request

- [ ] Each new action has a permission in `config/permissions.php`, and you ran `php artisan permissions:sync`.
- [ ] Screens and endpoints ask the policy. Nothing checks a role by name.
- [ ] Business rules are in `app/Actions`, not repeated in the screen and the controller.
- [ ] Records people own are scoped in the list query, the record query and the policy.
- [ ] API input is validated in a form request. Fields clients mustn't set, like an owner, aren't in its rules.
- [ ] Tests cover what's allowed, what's refused, and who can see it.
- [ ] `composer check` passes. If you changed `stubs/module`, `composer check:generator` passes too.
- [ ] `CHANGELOG.md` says what changed for people using the app.
