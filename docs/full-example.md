# Full Example — Invoice Audit

This example demonstrates the main Audit features in one small application:

* auditing an Invoice and its Lines
* requested changes
* reactive changes
* redacting a sensitive field
* ignoring a sensitive field
* linking reactive audit actions
* displaying the audit log

The example uses the following data model:

```text
Invoice
  |
  +-- Lines
```

Each line has a price and quantity. Its total is calculated automatically, and the invoice total is updated whenever a line changes.

## Tables

The application tables can be created with:

```sql
create table invoice (
    id int not null primary key auto_increment,
    ref varchar(255),
    total decimal(8,2),
    paypal_id varchar(255),
    paypal_token varchar(255)
);

create table line (
    id int not null primary key auto_increment,
    invoice_id int,
    item varchar(255),
    price decimal(8,2),
    qty int,
    total decimal(8,2)
);
```

Create the Audit table separately using the `audit_log.sql` schema supplied by the package.

## Invoice model

```php
class Invoice extends \Atk4\Data\Model
{
    public $table = 'invoice';

    protected function init(): void
    {
        parent::init();

        $this->hasMany('Lines', [
            'model' => [Line::class],
        ]);

        $this->addField('ref', [
            'type' => 'string',
        ]);

        $this->addField('total', [
            'type' => 'atk4_money',
            'default' => 0.00,
        ]);

        $this->addField('paypal_id', [
            'type' => 'string',
        ]);

        $this->addField('paypal_token', [
            'type' => 'string',
        ]);
    }

    public function adjustTotal($change): void
    {
        $this->save([
            'total' => $this->get('total') + $change,
        ]);
    }
}
```

## Line model

```php
class Line extends \Atk4\Data\Model
{
    public $table = 'line';

    protected function init(): void
    {
        parent::init();

        $this->hasOne('invoice_id', [
            'model' => [Invoice::class],
        ]);

        $this->addField('item', [
            'type' => 'string',
        ]);

        $this->addField('price', [
            'type' => 'atk4_money',
            'default' => 0.00,
        ]);

        $this->addField('qty', [
            'type' => 'integer',
            'default' => 0,
        ]);

        $this->addField('total', [
            'type' => 'atk4_money',
            'default' => 0.00,
        ]);

        $this->addHook('beforeSave', function ($m) {
            $m->set(
                'total',
                $m->get('price') * $m->get('qty')
            );
        });

        $this->addHook('afterSave', function ($m) {
            if ($m->isDirty('total')) {
                $change = $m->get('total')
                    - $m->getDirtyRef()['total'];

                $m->ref('invoice_id')->adjustTotal($change);
            }
        });
    }
}
```

When a line is saved:

1. `beforeSave` calculates its total.
2. `afterSave` calculates the difference.
3. The related Invoice is updated.

This gives us a simple example of reactive auditing.

## Enable Audit

Create one controller for the persistence.

The PayPal policy applies only to `Invoice`, so attach it specifically to that model:

```php
$audit = new \Atk4\Audit\AuditController($db);

$invoicePolicy = (new \Atk4\Audit\AuditPolicy())
    ->redactField('paypal_id')
    ->ignoreField('paypal_token');

$audit->setModelPolicy(
    Invoice::class,
    $invoicePolicy
);

$audit->observePersistence($db);
```

The policy means:

```text
paypal_id     -> audited, but values are [REDACTED]
paypal_token  -> completely ignored
```

No policy is applied to `Line`, so its fields are audited normally.

All models added to the persistence afterwards are automatically observed.

## Create an invoice

```php
$invoice = new Invoice($db);

$invoice->save([
    'ref' => 'inv1',
    'paypal_id' => 'PAYPAL-12345',
    'paypal_token' => 'very-secret-token',
]);
```

The create operation is audited.

Conceptually, its requested diff contains:

```json
{
    "ref": [null, "inv1"],
    "paypal_id": [null, "[REDACTED]"]
}
```

`paypal_token` is absent because it is ignored.

The actual PayPal ID and token are never stored in the audit diff.

## Add invoice lines

```php
$invoice->ref('Lines')->insert([
    'item' => 'Chair',
    'price' => 2.50,
    'qty' => 3,
]);

$invoice->ref('Lines')->insert([
    'item' => 'Desk',
    'price' => 10.20,
    'qty' => 1,
]);
```

The resulting invoice total is:

```text
17.70
```

Audit records the line creation and the reactive invoice update.

## Requested versus reactive changes

Now change the quantity of the first line:

```php
$line = $invoice->ref('Lines')->load(1);

$line->set('qty', 4);
$line->save();
```

The requested change is:

```json
{
    "qty": [3, 4]
}
```

The line total is recalculated from:

```text
7.50 -> 10.00
```

so the line audit entry contains a reactive change similar to:

```json
{
    "total": [7.5, 10]
}
```

The parent invoice is then updated:

```text
17.70 -> 20.20
```

That update gets its own audit entry, linked to the original line operation.

The resulting structure is conceptually:

```text
Audit #10
  Line #1
  requested:
      qty 3 -> 4
  reactive:
      total 7.50 -> 10.00

      |
      +-- Audit #11
          Invoice #1
          requested:
              total 17.70 -> 20.20
```

This relationship is recorded using `initiator_audit_log_id`.

## Update the PayPal information

The same invoice can now change its PayPal information:

```php
$invoice->set('paypal_id', 'PAYPAL-67890');
$invoice->set('paypal_token', 'another-secret-token');
$invoice->save();
```

The audit records the change to `paypal_id`, but only as:

```json
{
    "paypal_id": [
        "[REDACTED]",
        "[REDACTED]"
    ]
}
```

The `paypal_token` change is not present.

This demonstrates the difference between redaction and ignoring a field:

```text
redactField()
    The change is visible, the values are hidden.

ignoreField()
    The field is not present in the audit.
```

## Display the audit log

A global audit page can use `AuditGrid`:

```php
use Atk4\Audit\Model\AuditLog;
use Atk4\Audit\View\AuditGrid;

$logs = new AuditLog($db);

AuditGrid::addTo($app)
    ->setModel($logs);
```

For the history of one invoice:

```php
AuditGrid::addTo($app)
    ->setModel($invoice->ref('AuditLog'));
```

## Display one audit entry

Load an audit entity and pass it to `AuditDetail`:

```php
use Atk4\Audit\Model\AuditLog;
use Atk4\Audit\View\AuditDetail;

$log = (new AuditLog($db))->load($auditLogId);

AuditDetail::addTo($app)
    ->setEntity($log);
```

The detail view allows the user to inspect the requested and reactive changes and navigate between related audit actions.

## Complete setup

The essential Audit setup for this example is therefore just:

```php
$audit = new \Atk4\Audit\AuditController($db);

$invoicePolicy = (new \Atk4\Audit\AuditPolicy())
    ->redactField('paypal_id')
    ->ignoreField('paypal_token');

$audit->setModelPolicy(
    Invoice::class,
    $invoicePolicy
);

$audit->observePersistence($db);
```

After that, normal Invoice and Line operations are automatically audited, with the special PayPal rules applied only to `Invoice`.
