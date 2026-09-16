# System-wide Audit Usage

In order for Audit to work, it requires 2 objects. `AuditController` and `AuditLog` model. You can extend both of those classes if you wish to redefine any internal behaviours.

The default example creates a new controller and attach model or multiple models one by one to it, but normally you would want to re-use single instance of controller and automatically audit all changes in DB.

```php
$audit = new \Atk4\Audit\AuditController($db);
```

If you wish to link model with this controller manually, you can do so by calling `addModel()`:

``` php
$invoices = new Invoice($db);
$audit->addModel($invoices);
```

Of course setting controllers up like that will take you a lot of effort and add room for error, so instead I recommend you to automatically apply controller for all models through a hook inside `$db`:

``` php
$audit = new \Atk4\Audit\AuditController($db);
$audit->observePersistence($db);
```

## Implications of re-using controller

When one model modifies another model inside a hook, as long as the same controller is used, it is considered as a nested action. For example, if you used individual controllers for `InvoiceLine` and `Invoice` models, even if `InvoiceLine` changed `Invoice` reactively those would be stored as independent log entries. If you re-use same audit controller object, then audit-log for `Invoice` will have it's field `initiator_audit_log_id` pointing to the log entry that recorded change in `InvoiceLine`. This can help you to link various actions together.
