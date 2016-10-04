# System-wide Audit Usage

In order for Audit to work, it requires 2 objects. Controller and AuditLog model. You can extend both of those classes if you wish to redefine any interna behvaiours. 

The default example creates a new controller and inserts it during model init, but normally you would want to re-use single instance of controller.

```php
$audit_controller = new \atk4\data\audit\Controller();
```

If you wish to link model with this controller manually, you can do so by calling `setUp()`:

``` php
$invoice = new Invoice($db);
$audit_controller->setUp($invoice);
```

Of course setting controllers up like that will take you a lot of effort and add room for error, so instead I recommend you to automatically apply controller for all models through a hook inside `$db`:

``` php
$audit = new \atk4\audit\Controller();

$db->addHook('afterAdd', function($owner, $element) use($audit) {
    if ($element instanceof \atk4\data\Model) {
        if (isset($element->no_audit) && $element->no_audit) {
            // Whitelisting this model, won't audit
            break;
        }
        $audit->setUp($element);
    }
});
```

## Implications of re-using controller

When one model modifies another model inside a hook, as long as the same controller is used, it is considered as a nested action. For example if you used individual controllers for `InvoiceLine` and `Invoice` models, even if `InvoiceLine` changed `Invoice` reactively those would be stored as independent log entries. If you re-use same audit controller object, then audit-log for `Invoice` will have it's field `initiator_audit_log_id` pointing to the log entry that recorded change in `InvoiceLine`. This can help you to link various actions together.