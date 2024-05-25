# Yii-Permission

[![Build Status](https://github.com/php-casbin/yii-permission/actions/workflows/build.yml/badge.svg?branch=master)](https://github.com/php-casbin/yii-permission/actions/workflows/build.yml)
[![Coverage Status](https://coveralls.io/repos/github/php-casbin/yii-permission/badge.svg)](https://coveralls.io/github/php-casbin/yii-permission)
[![Latest Stable Version](https://poser.pugx.org/casbin/yii-permission/v/stable)](https://packagist.org/packages/casbin/yii-permission)
[![Total Downloads](https://poser.pugx.org/casbin/yii-permission/downloads)](https://packagist.org/packages/casbin/yii-permission)
[![License](https://poser.pugx.org/casbin/yii-permission/license)](https://packagist.org/packages/casbin/yii-permission)

Use [Casbin](https://github.com/php-casbin/php-casbin) in Yii 2.0 PHP Framework.

## Installation

### Getting Composer package

Require this package in the `composer.json` of your Yii 2.0 project. This will download the package.

```
composer require casbin/yii-permission
```

### Configuring application

To use this extension, you have to configure the `Casbin` class in your application configuration:

```php
return [
    //....
    'components' => [
        'permission' => [
            'class' => \yii\permission\Permission::class,
            
            /*
             * Casbin model setting.
             */
            'model' => [
                // Available Settings: "file", "text"
                'config_type' => 'file',
                'config_file_path' => '/path/to/casbin-model.conf',
                'config_text' => '',
            ],

            // Casbin adapter .
            'adapter' => \yii\permission\Adapter::class,

            /*
             * Casbin database setting.
             */
            'database' => [
                // Database connection for following tables.
                'connection' => '',
                // CasbinRule tables and model.
                'casbin_rules_table' => '{{%casbin_rule}}',
            ],
        ],
    ]
];
```


## Usage

### Quick start

Once installed you can do stuff like this:

```php

$permission = \Yii::$app->permission;

// adds permissions to a user
$permission->addPermissionForUser('eve', 'articles', 'read');
// adds a role for a user.
$permission->addRoleForUser('eve', 'writer');
// adds permissions to a rule
$permission->addPolicy('writer', 'articles','edit');

```

You can check if a user has a permission like this:

```php
// to check if a user has permission
if ($permission->enforce("eve", "articles", "edit")) {
    // permit eve to edit articles
} else {
    // deny the request, show an error
}

```

### Using Enforcer Api

It provides a very rich api to facilitate various operations on the Policy:

Gets all roles:

```php
$permission->getAllRoles(); // ['writer', 'reader']
```

Gets all the authorization rules in the policy.:

```php
$permission->getPolicy();
```

Gets the roles that a user has.

```php
$permission->getRolesForUser('eve'); // ['writer']
```

Gets the users that has a role.

```php
$permission->getUsersForRole('writer'); // ['eve']
```

Determines whether a user has a role.

```php
$permission->hasRoleForUser('eve', 'writer'); // true or false
```

Adds a role for a user.

```php
$permission->addRoleForUser('eve', 'writer');
```

Adds a permission for a user or role.

```php
// to user
$permission->addPermissionForUser('eve', 'articles', 'read');
// to role
$permission->addPermissionForUser('writer', 'articles','edit');
```

Deletes a role for a user.

```php
$permission->deleteRoleForUser('eve', 'writer');
```

Deletes all roles for a user.

```php
$permission->deleteRolesForUser('eve');
```

Deletes a role.

```php
$permission->deleteRole('writer');
```

Deletes a permission.

```php
$permission->deletePermission('articles', 'read'); // returns false if the permission does not exist (aka not affected).
```

Deletes a permission for a user or role.

```php
$permission->deletePermissionForUser('eve', 'articles', 'read');
```

Deletes permissions for a user or role.

```php
// to user
$permission->deletePermissionsForUser('eve');
// to role
$permission->deletePermissionsForUser('writer');
```

Gets permissions for a user or role.

```php
$permission->getPermissionsForUser('eve'); // return array
```

Determines whether a user has a permission.

```php
$permission->hasPermissionForUser('eve', 'articles', 'read');  // true or false
```

See [Casbin API](https://casbin.org/docs/en/management-api) for more APIs.

## Define your own model.conf

[Supported models](https://github.com/php-casbin/php-casbin#supported-models).

## Learning Casbin

You can find the full documentation of Casbin [on the website](https://casbin.org/).
