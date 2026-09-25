# Agile Audit Extension

Agile Audit records changes made through ATK Data models in a dedicated audit log. It records creates, updates and deletes, together with the fields that were requested to change and changes that happened reactively through model hooks.

Audit is managed by `AuditController`. Audit records are stored by `Model\AuditLog`.

## Installation

Install the package with Composer:

```bash
composer require atk4/audit
```

Create the `audit_log` table using the `audit_log.sql` schema included with the package.

For applications that also use the Audit UI, make sure ATK UI is available as well.

## Basic usage

Create one `AuditController` for the persistence and add the models you want to audit:

```php
$audit = new \Atk4\Audit\AuditController($db);

$users = new \App\Model\User($db);

$audit->addModel($users);
```

Now normal model operations are audited:

```php
$user = $users->load(1);
$user->set('name', 'Ken');
$user->save();
```

An audit record is created in `audit_log`.

The important fields are the action, model, record ID, user information, and the requested/reactive differences.

For example, an update may contain:

```json
{
    "request_diff": {
        "name": ["Vinny", "Ken"]
    },
    "reactive_diff": {}
}
```

## Auditing all models

For most applications it is more convenient to use one controller for the whole persistence:

```php
$audit = new \Atk4\Audit\AuditController($db);
$audit->observePersistence($db);
```

Call `observePersistence()` while setting up the persistence, before models that should be audited are added to it.

Models added afterwards are registered automatically.

Using one controller is also important when one model changes another model reactively. Those changes can then be linked together in the audit log.

For a small application that only needs to audit selected models, `addModel()` is sufficient.

## Working with audit entries

Audited models receive an `AuditLog` reference:

```php
$user = $users->load(1);

$history = $user->ref('AuditLog');
```

Count the history:

```php
$count = $user->ref('AuditLog')
    ->action('count')
    ->getOne();
```

Load the most recent entry:

```php
$lastAudit = $user->ref('AuditLog')->loadLast();
```

The same reference can be used as the data source for an Audit UI grid.

## Requested and reactive changes

Audit separates changes requested by the original operation from changes caused by hooks or other model logic.

For example, changing a line quantity can recalculate the line total and then update the parent invoice:

```text
Line.qty
    |
    v
Line.total
    |
    v
Invoice.total
```

The line operation can therefore contain:

```json
{
    "request_diff": {
        "qty": [5, 6]
    },
    "reactive_diff": {
        "total": [50, 60]
    }
}
```

The reactive update to `Invoice` is stored as another audit record linked to the original action.

See [full-example.md](full-example.md) for a complete example.

## Policies

Use `AuditPolicy` when some models or fields should not be stored normally.

For example:

```php
$policy = new \Atk4\Audit\AuditPolicy();

$policy->ignoreField('internal_note');
$policy->redactField('email');

$audit = new \Atk4\Audit\AuditController($db);
$audit->setDefaultPolicy($policy);
$audit->observePersistence($db);
```

See [policies.md](policies.md).

## Audit UI

The package provides:

* `AuditGrid` for browsing audit entries
* `AuditDetail` for inspecting one audit entry

See [ui.md](ui.md).

## Custom audit log entries

A model registered with Audit gets an `auditLog()` method that can be used to create a custom audit entry:

```php
$user->auditLog('Imported from CRM', [
    'source' => 'crm',
]);
```

The custom data is stored with the audit entry.

## Customisation

The controller can associate audit records with the current application user:

```php
$audit->setUserId($currentUserId);
```

You can also shorten stored model names:

```php
$audit->setRootNamespace('App\\Model\\');
```

This turns:

```text
App\Model\Invoice
```

into:

```text
Invoice
```

## Important limitation

Audit hooks are attached to ATK Data models.

Changes made directly with SQL or through another database connection do not pass through those model hooks and are therefore not automatically audited.

## Example

A complete Invoice/Line example showing policies, requested changes and reactive changes is available in [full-example.md](full-example.md).
