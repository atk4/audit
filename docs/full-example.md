#  Example - Invoice Totals

This is a full demonstration of a basic system designed with Agile Data and Audit extension. The purpose of this system is to store list of invoices, where each invoice could contain multiple lines.

## Setting up tables

There are 2 basic tables at play. Invoices:

| id   | ref  | total |
| ---- | ---- | ----- |
| 1    | inv1 | 17.70 |

and invoice contains lines:

| id   | invoice_id | item  | price | qty  | total |
| ---- | ---------- | ----- | ----- | ---- | ----- |
| 1    | 1          | Chair | 2.50  | 3    | 7.50  |
| 2    | 1          | Desk  | 10.20 | 1    | 10.20 |

The following SQL schema can be used to create above table:

``` sql
create table invoice (id int not null primary key auto_increment, ref varchar(255), total decimal(8,2));
create table line (id int not null primary key auto_increment, invoice_id int, item varchar(255), price decimal(8,2), qty int, total decimal(8,2));
```

## Setting up Models

``` php
class Invoice extends \Atk4\Data\Model
{
    public $table = 'invoice';
    protected function init(): void
    {
        parent::init();

        $this->hasMany('Lines', ['model' => [Line::class]]);
        $this->addField('ref', ['type' => 'string']);
        $this->addField('total', ['type' => 'atk4_money', 'default' => 0.00]);
    }

    public function adjustTotal($change): void
    {
        $this->save(['total' => $this->get('total') + $change]);
    }
}
```

The Invoice model defines all fields and types as well as reference to invoice line model. A new method adjustTotal will be used to increment/decrement invoice total when invoice lines are added or updated.

Next - the Line model:

``` php
class Line extends \Atk4\Data\Model {
    public $table = 'line';

    protected function init(): void
    {
        parent::init();

        $this->hasOne('invoice_id', ['model' => [Invoice::class]]);

        $this->addField('item', ['type' => 'string']);
        $this->addField('price', ['type' => 'atk4_money', 'default' => 0.00]);
        $this->addField('qty', ['type' => 'integer', 'default' => 0]);
        $this->addField('total', ['type' => 'atk4_money', 'default' => 0.00]);

        $this->addHook('beforeSave', function($m) {
            $m->set('total', $m->get('price') * $m->get('qty'));
        });

        $this->addHook('afterSave', function($m) {
            if ($m->isDirty('total')) {
                $change = $m->get('total') - $m->getDirtyRef()['total'];

                $this->ref('invoice_id')->adjustTotal($change);
            }
        });
    }
}
```

The model is rather trivial except for the 2 hooks it contains. `beforeSave` model will re-calculate the total based on price and quantity multiplication. After model is saved, if `total` field was affected, then we will calculate the difference and request update from our relevant invoice.

## Test-code

``` php
$m = new Invoice($this->db);
$m->save(['ref'=>'inv1']);
$this->assertEquals(0, $m->get('total'));

$m->ref('Lines')->insert(['item'=>'Chair', 'price'=>2.50, 'qty'=>3]);
$m->ref('Lines')->insert(['item'=>'Desk', 'price'=>10.20, 'qty'=>1]);

// output
echo 'invoices = '.json_encode($m->export())."\n";
echo 'lines = '.json_encode($m->ref('Lines')->export())."\n";
```

Run to get this output (formatted):

``` json
invoices = [
   {
      "id":1,
      "ref":"inv1",
      "total":17.7
   }
]

lines = [
   {
      "id":1,
      "invoice_id":"1",
      "item":"Chair",
      "price":2.5,
      "qty":3,
      "total":7.5
   },
   {
      "id":2,
      "invoice_id":"1",
      "item":"Desk",
      "price":10.2,
      "qty":1,
      "total":10.2
   }
]
```

## Add AuditLog

So far everything is working perfectly, but there is no audit yet! To enable audit, we need to execute the following:

``` php
$audit = new \Atk4\Audit\AuditController($this->db);
$audit->observePersistence($this->db);
```

followed by our "test-code" once again. The result is the same, but this time an `audit_log` table was populated.

## Explanation of AuditLog Entries

Let's look inside each audit record individually.

``` php
$m->save(['ref'=>'inv1']);
```

| Field                  | Value                           | Description                              |
| ---------------------- | ------------------------------- | ---------------------------------------- |
| id                     | 1                               | If you use relational database for storing Audit Log, the ID will increment, but that's not a requirement. |
| model                  | Invoice                         |                                          |
| model_id               | 1                               |                                          |
| start_time_ms          | 1789549702238                   | Timestamp in miliseconds |
| duration_ms            | 35                              | AuditLog actually tracks how long many miliseconds this operation took. |
| action                 | create                          | New record was created                   |
| request_diff           | {"ref":[null,"inv1"]}           | SQL stores value in JSON but it's converted into PHP array on load/save. |
| reactive_diff          | {"id":1,"ref":"inv1",total":0}  | For create operations contains all fields. |
| initiator_audit_log_id | NULL                            | This action was triggered directly.      |
| descr                  | create #1: ref=inv1             | Human-readable field                     |

This record corresponds to us creating initial model. Next we were adding invoice line, which was reflected in the audit_log.

``` php
$m->ref('Lines')->insert(['item'=>'Chair', 'price'=>2.50, 'qty'=>3]);
```

| Field                  | Value                                    | Description                              |
| ---------------------- | ---------------------------------------- | ---------------------------------------- |
| id                     | 2                                        |                                          |
| model                  | Line                                     |                                          |
| model_id               | 1                                        |                                          |
| start_time_ms          | 1789549702250                            |                                          |
| duration_ms            | 50                                       | This action took longer (because of related operation) |
| action                 | create                                   | New line added through insert()          |
| request_diff           | {"item":[null,"Chair"],"price":[0,2.5],"qty":[0,3]} | SQL stores value in JSON but it's converted into PHP array on load/save. |
| reactive_diff          | {"id":1,"invoice_id":"1","item":"Chair","price":2.5, "qty":3,"total":7.5} | All values being stored, including calculated. |
| initiator_audit_log_id | NULL                                     | Also a manually created record           |
| descr                  | create #1: item=Chair, price=2.5, qty=3  | Human-readable field                     |

The next entry is reactive and was caused beacuse of the call to `adjustTotal` with a subsequential `save()`

| Field                  | Value                 | Description                              |
| ---------------------- | --------------------- | ---------------------------------------- |
| id                     | 3                     |                                          |
| model                  | Invoice               |                                          |
| model_id               | 1                     |                                          |
| ts                     | 1789549702262         |                                          |
| time_taken             | 42                    |                                          |
| action                 | update                | Updates invoice model                    |
| request_diff           | {"total":[0,7.5]}     | Total was the only field changed         |
| reactive_diff          | NULL                  |                                          |
| initiator_audit_log_id | 2                     | Reactive change, caused by previous record. |
| descr                  | update #1: total=7.5  |                                          |

Next line is similar to the above:

``` php
$m->ref('Lines')->insert(['item'=>'Desk', 'price'=>10.20, 'qty'=>1]);
```

| Field                  | Value                                    | Description                              |
| ---------------------- | ---------------------------------------- | ---------------------------------------- |
| id                     | 4                                        |                                          |
| model                  | Line                                     |                                          |
| model_id               | 1                                        |                                          |
| start_time_ms          | 1789549702270                            |                                          |
| duration_ms            | 47                                       |                                          |
| action                 | create                                   |                                          |
| request_diff           | {"item":[null,"Desk"],"price":[0,10.2],"qty":[0,1]} |                               |
| reactive_diff          | {"id":2,"invoice_id":"1","item":"Desk","price":10.2, "qty":1,"total":10.2} |        |
| initiator_audit_log_id | NULL                                     |                                          |
| descr                  | create #2: item=Desk, price=10.2, qty=1  |                                          |

| Field                  | Value                 | Description                              |
| ---------------------- | --------------------- | ---------------------------------------- |
| id                     | 5                     |                                          |
| model                  | Invoice               |                                          |
| model_id               | 1                     |                                          |
| ts                     | 1789549702292         |                                          |
| time_taken             | 28                    |                                          |
| action                 | update                |                                          |
| request_diff           | {"total":[7.5,10.2]}  |                                          |
| reactive_diff          | NULL                  |                                          |
| initiator_audit_log_id | 4                     |                                          |
| descr                  | update #1: total=10.2 |                                          |

## Final run-through

Let's look at the full audit log again:

| id   | initiator | action         | model   | model_id |
| ---- | --------- | -------------- | ------- | -------- |
| 1    |           | create         | Invoice | 1        |
| 2    |           | create         | Line    | 1        |
| 3    | 2         | update         | Invoice | 1        |
| 4    |           | create         | Line    | 2        |
| 5    | 4         | update         | Invoice | 1        |

I have ommitted details from AuditLog, but the outline above is clean, easy to read and easy to vizualize for the user and very logical.
