<?php

declare(strict_types=1);

return [

    /*
    | Every permission in the app, as module => actions. A permission's name is
    | "{module}.{action}", for example "users.update". Modules made with
    | `php artisan make:module` are listed in modules.json and added on top.
    | After changing this list, run `php artisan permissions:sync`.
    */
    'modules' => [
        'users' => ['view', 'create', 'update', 'delete', 'restore', 'impersonate'],
        'roles' => ['view', 'create', 'update', 'delete'],
        'settings' => ['update'],
        'activity' => ['view'],
    ],

    // New permissions go to the admin role automatically, except these.
    'not_for_admins' => ['users.impersonate'],
];
