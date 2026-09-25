# Audit Policies

`AuditPolicy` controls which models and fields are audited and how their values are stored.

There are three field modes:

* audit the actual value
* ignore the field completely
* redact the value

There are also two model modes:

* audit the model
* ignore the model completely

## Ignoring and redacting fields

Use `ignoreField()` when a field should not appear in the audit at all.

Use `redactField()` when the field change should still be visible, but its values must not be stored.

```php
$policy = new \Atk4\Audit\AuditPolicy();

$policy->ignoreField('paypal_token');
$policy->redactField('paypal_id');
```

With the policy above:

```text
paypal_id:
    old value -> [REDACTED]
    new value -> [REDACTED]

paypal_token:
    not present in the audit
```

This distinction is useful when you want to know that a sensitive field changed without storing the sensitive value itself.

## Ignoring a model

Use `ignoreModel()` to exclude a model completely:

```php
$policy = new \Atk4\Audit\AuditPolicy();

$policy->ignoreModel(\App\Model\TemporaryData::class);
```

The model will not be registered for auditing.

To ignore a database table, ignore the ATK Data model that represents that table.

## Default and model-specific policies

A default policy can be applied to all audited models:

```php
$defaultPolicy = new \Atk4\Audit\AuditPolicy();
$defaultPolicy->redactField('email');

$audit = new \Atk4\Audit\AuditController($db);
$audit->setDefaultPolicy($defaultPolicy);

$audit->setModelPolicy(
    \App\Model\Payment::class,
    (new \Atk4\Audit\AuditPolicy())
        ->redactField('card_number')
        ->ignoreField('internal_note')
);

$audit->observePersistence($db);
```

The default policy is used unless a model has its own policy.

Configure policies before models are registered with the controller, for example before calling `observePersistence()`.

For explicit model registration, a policy can also be passed directly:

```php
$audit->addModel(
    new \App\Model\Payment($db),
    $policy
);
```

## Password fields

`PasswordField` values are automatically redacted.

There is therefore normally no need to add a separate `redactField()` rule for password fields.

## Fields that are not audited

Fields marked by ATK Data as:

```text
neverPersist
neverSave
readOnly
```

are ignored automatically.

## Policy precedence

For a field, an explicit `ignoreField()` rule takes precedence over `redactField()`.

Password fields are redacted automatically unless they are explicitly ignored.

The policy works with the field's short name, so rules such as:

```php
$policy->redactField('email');
```

apply to fields named `email`.
