# Yii-Permission

[![Build Status](https://github.com/php-casbin/yii-permission/actions/workflows/build.yml/badge.svg?branch=master)](https://github.com/php-casbin/yii-permission/actions/workflows/build.yml)
[![Coverage Status](https://coveralls.io/repos/github/php-casbin/yii-permission/badge.svg)](https://coveralls.io/github/php-casbin/yii-permission)
[![Latest Stable Version](https://poser.pugx.org/casbin/yii-permission/v/stable)](https://packagist.org/packages/casbin/yii-permission)
[![Total Downloads](https://poser.pugx.org/casbin/yii-permission/downloads)](https://packagist.org/packages/casbin/yii-permission)
[![License](https://poser.pugx.org/casbin/yii-permission/license)](https://packagist.org/packages/casbin/yii-permission)

An authorization library for the Yii 3.0 PHP Framework, based on [Casbin](https://github.com/php-casbin/php-casbin).

* [Installation](#installation)
  * [Getting Composer package](#getting-composer-package)
  * [Configuring application](#configuring-application)
  * [Database Migration](#database-migration)
* [Usage](#usage)
  * [Quick start](#quick-start)
  * [Using Enforcer Api](#using-enforcer-api)
  * [Using a middleware](#using-a-middleware)
    * [Basic Enforcer Middleware](#basic-enforcer-middleware)
    * [HTTP Request Middleware (RESTful is also supported)](#http-request-middleware--restful-is-also-supported-)
  * [Using Yii3 AccessChecker ($user->can())](#using-yii3-accesschecker-user-can)
* [Define your own model.conf](#define-your-own-modelconf)
* [Learning Casbin](#learning-casbin)

## Installation

### Getting Composer package

Require this package in the `composer.json` of your Yii 3.0 project.

> **Note**: This package requires a database driver implementation for `yiisoft/db` (such as `yiisoft/db-mysql`, `yiisoft/db-sqlite`, `yiisoft/db-pgsql`, etc.) in your application. Make sure your project has installed a database driver.
>
> If your project doesn't have a database driver yet, install one first (for example, SQLite or MySQL):
> ```bash
> composer require yiisoft/db-mysql # or yiisoft/db-sqlite or yiisoft/db-pgsql
> ```

```bash
composer require casbin/yii-permission
```

### Configuring application

Yii-Permission automatically registers its parameters and DI container definitions via `yiisoft/config`.

You can customize parameters in your project's `config/params.php`:

```php
return [
    'casbin/yii-permission' => [
        'model' => [
            // Available Settings: "file", "text"
            'config_type' => 'file',
            'config_file_path' => dirname(__DIR__) . '/config/casbin-basic-model.conf',
            'config_text' => '',
        ],
        'database' => [
            // Connection service ID in DI container, defaults to Yiisoft\Db\Connection\ConnectionInterface::class
            'connection' => null,
            'casbin_rules_table' => 'casbin_rule',
        ],
        'log' => [
            'enabled' => false,
            'logger' => null,
        ],
        'adapter' => \Yii\Permission\Adapter::class,
    ],
];
```

### Database Migration

`casbin/yii-permission` automatically registers its database migration path (`src/migrations`) via `yiisoft/config` under `"db-migration"`.

Run the Yii 3.0 database migration command to create the `casbin_rule` table (requires `yiisoft/db-migration` in your application):

```bash
composer require yiisoft/db-migration # if not installed yet
./yii migrate:up
```

> **Troubleshooting: ConnectionInterface Not Found**
>
> If running `./yii migrate:up` throws an exception:
> `No definition or class found or resolvable for "Yiisoft\Db\Connection\ConnectionInterface"`
>
> It means your Yii 3 application has not registered a default `ConnectionInterface` in the DI container yet. Ensure your application's DI container (e.g. `config/common/di/db.php`) defines `Yiisoft\Db\Connection\ConnectionInterface::class`.
>
> Alternatively, if your database connection service has a custom ID in your container, set it in `config/params.php`:
> ```php
> 'casbin/yii-permission' => [
>     'database' => [
>         'connection' => 'your_custom_db_service_id',
>     ],
> ],
> ```
>
> For more details, see the [Yii Database Documentation](https://github.com/yiisoft/db).

For custom or manual database setups, see the [Migration Class File](src/migrations/M240729000000CreateCasbinRuleTable.php) for the detailed `casbin_rule` table schema.

## Usage

### Quick start

In Yii 3.0, you can directly inject native `\Casbin\Enforcer` into your actions, controllers or services to get 100% IDE auto-completion and full type safety:

```php
use Casbin\Enforcer;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class Action
{
    public function __construct(
        private Enforcer $enforcer,
        private ResponseFactoryInterface $responseFactory
    ) {}

    public function __invoke(): ResponseInterface
    {
        // adds permissions to a user with full IDE autocomplete
        $this->enforcer->addPermissionForUser('eve', 'articles', 'read');

        // adds a role for a user
        $this->enforcer->addRoleForUser('eve', 'writer');

        // adds permissions to a policy
        $this->enforcer->addPolicy('writer', 'articles', 'edit');

        // checks permission
        if ($this->enforcer->enforce('eve', 'articles', 'edit')) {
            // permit eve to edit articles
            $response = $this->responseFactory->createResponse();
            $response->getBody()->write('<div>permit is: true</div>');
            return $response;
        } else {
            // deny the request
            $response = $this->responseFactory->createResponse();
            $response->getBody()->write('<div>permit is: false</div>');
            return $response;
        }
    }
}
```

### Using Enforcer Api

It provides a very rich API to facilitate various operations on the Policy:

Gets all roles:

```php
$enforcer->getAllRoles(); // ['writer', 'reader']
```

Gets all the authorization rules in the policy:

```php
$enforcer->getPolicy();
```

Gets the roles that a user has:

```php
$enforcer->getRolesForUser('eve'); // ['writer']
```

Gets the users that have a role:

```php
$enforcer->getUsersForRole('writer'); // ['eve']
```

Determines whether a user has a role:

```php
$enforcer->hasRoleForUser('eve', 'writer'); // true or false
```

Adds a role for a user:

```php
$enforcer->addRoleForUser('eve', 'writer');
```

Adds a permission for a user or role:

```php
// to user
$enforcer->addPermissionForUser('eve', 'articles', 'read');
// to role
$enforcer->addPermissionForUser('writer', 'articles', 'edit');
```

Deletes a role for a user:

```php
$enforcer->deleteRoleForUser('eve', 'writer');
```

Deletes all roles for a user:

```php
$enforcer->deleteRolesForUser('eve');
```

Deletes a role:

```php
$enforcer->deleteRole('writer');
```

Deletes a permission:

```php
$enforcer->deletePermission('articles', 'read'); // returns false if the permission does not exist (aka not affected).
```

Deletes a permission for a user or role:

```php
$enforcer->deletePermissionForUser('eve', 'articles', 'read');
```

Deletes permissions for a user or role:

```php
// to user
$enforcer->deletePermissionsForUser('eve');
// to role
$enforcer->deletePermissionsForUser('writer');
```

Gets permissions for a user or role:

```php
$enforcer->getPermissionsForUser('eve'); // return array
```

Determines whether a user has a permission:

```php
$enforcer->hasPermissionForUser('eve', 'articles', 'read');  // true or false
```

See [Casbin API](https://casbin.apache.org/docs/management-api) for more APIs.

### Using a middleware

`casbin/yii-permission` provides two PSR-15 middlewares for HTTP route access control in Yii 3.0 applications.

#### Basic Enforcer Middleware

`\Yii\Permission\Middleware\EnforcerMiddleware` is used to check explicit permission parameters (e.g. resource and action). It provides an immutable `withParams(array $params)` method returning a cloned instance for safe route-level parameter binding.

```php
use Yii\Permission\Middleware\EnforcerMiddleware;
use Yiisoft\Router\Route;

// Checks if current user has permission on 'articles' resource with 'read' action
Route::get('/articles')
    ->action([ArticleController::class, 'index'])
    ->middleware(
        fn (EnforcerMiddleware $middleware) => $middleware->withParams(['articles', 'read'])
    );
```

#### HTTP Request Middleware ( RESTful is also supported )

`\Yii\Permission\Middleware\RequestMiddleware` automatically extracts the request **Path** as the resource and HTTP **Method** as the action (`$enforcer->enforce($userId, $path, $method)`).

```php
use Yii\Permission\Middleware\RequestMiddleware;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

// Automatically checks permission based on Request Path & HTTP Method
Group::create('/api')
    ->middleware(RequestMiddleware::class)
    ->routes(
        Route::get('/posts')->action([PostController::class, 'index']),
        Route::post('/posts')->action([PostController::class, 'create'])
    );
```

> **Note**: Both middlewares automatically fetch the current logged-in user ID via `Yiisoft\User\CurrentUser::getId()`. If your project needs automatic logged-in user resolution, you can install the `yiisoft/user` package:
> ```bash
> composer require yiisoft/user
> ```
> If `CurrentUser` is not available or the user is a guest, it falls back to the `user_id` request attribute or `'guest'`.

### Using Yii3 AccessChecker ($user->can())

`casbin/yii-permission` provides `\Yii\Permission\AccessChecker` implementing `Yiisoft\Access\AccessCheckerInterface`.

#### 1. Register as `AccessCheckerInterface` in DI Container (`config/common/di/auth.php`)

Bind `AccessCheckerInterface` to `AccessChecker` so that `Yiisoft\User\CurrentUser` uses Casbin under the hood:

```php
use Yiisoft\Access\AccessCheckerInterface;
use Yii\Permission\AccessChecker;

return [
    AccessCheckerInterface::class => AccessChecker::class,
];
```

#### 2. Check Permission via `$user->can()` in Controllers

Once registered, you can use Yii 3.0's native `$user->can()` method directly:

```php
use Yiisoft\User\CurrentUser;

final readonly class PostController
{
    public function __construct(
        private CurrentUser $user
    ) {}

    public function update(): ResponseInterface
    {
        // 1. Passing resource and action as separate arguments (Recommended for Casbin)
        if ($this->user->can('articles', ['write'])) {
            // Permission granted
        }

        // 2. Or using comma-separated string format
        if ($this->user->can('articles,write')) {
            // Permission granted
        }

        // 3. Or checking a single permission string
        if ($this->user->can('updatePost')) {
            // Permission granted
        }
    }
}
```

## Define your own model.conf

You can customize your own model configuration file (e.g. `casbin-basic-model.conf`). For full syntax and pre-defined model examples, see [Casbin Supported Models](https://casbin.apache.org/docs/supported-models) and [PHP-Casbin Models](https://github.com/php-casbin/php-casbin#supported-models).

## Learning Casbin

You can find the full documentation of Casbin [on the website](https://casbin.apache.org/).

## License

This project is licensed under the [Apache-2.0 License](LICENSE).
