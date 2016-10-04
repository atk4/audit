#  Example - Invoice Totals

This is a full demonstration of a basic system designed with Agile Data and Audit extension. The purpose of this system is to store list of invoices, where each invocie could contain multiple lines.

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
class Invoice extends \atk4\data\Model
{
    public $table = 'invoice';
    function init()
    {
        parent::init();

        $this->hasMany('Lines', new Line());
        $this->addField('ref', ['type' => 'string']);
        $this->addField('total', ['type' => 'money', 'default' => 0.00]);
    }

    function adjustTotal($change)
    {
        if ($this->audit_log_controller) {
            $this->audit_log_controller->custom_fields = [
                'action'=>'total_adjusted',
                'descr'=>'Changing total by '.$change
            ];
        }
        $this['total'] += $change;
        $this->save();
    }
}
```

The Invoice model defines all fields and types as well as reference to invoice line model. A new method adjustTotal will be used to increment/decrement invoice total when invoice lines are added or updated.

Notice how I'm creating a custom `log` entry when adjustTotal() is executed. Next - the Line model:

``` php
class Line extends \atk4\data\Model {
    public $table = 'line';

    function init()
    {
        parent::init();

        $this->hasOne('invoice_id', new Invoice());

        $this->addField('item', ['type' => 'string']);
        $this->addField('price', ['type' => 'money', 'default' => 0.00]);
        $this->addField('qty', ['type' => 'integer', 'default' => 0]);
        $this->addField('total', ['type' => 'money', 'default' => 0.00]);

        $this->addHook('beforeSave', function($m) {
            $m['total'] = $m['price'] * $m['qty'];
        });

        $this->addHook('afterSave', function($m) {
            if ($m->isDirty('total')) {
                $change = $m['total'] - $m->dirty['total'];

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
$this->assertEquals(0, $m['total']);

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
$audit = new \atk4\audit\Controller();

$this->db->addHook('afterAdd', function($owner, $element) use($audit) {
    if ($element instanceof \atk4\data\Model) {
        if (isset($element->no_audit) && $element->no_audit) {
            // Whitelisting this model, won't audit
            return;
        }

        $audit->setUp($element);
    }
});
```

followed by our "test-code" once again. The result is the same, but this time an audit_log table was populated. 

## Explanation of AuditLog Entries

Let's look inside each audit record individually.

``` php
$m->save(['ref'=>'inv1']);
```

| Field                  | Value                           | Description                              |
| ---------------------- | ------------------------------- | ---------------------------------------- |
| id                     | 1                               | If you use relational database for storing Audit Log, the ID will increment, but that's not a requirement. |
| initiator_audit_log_id | NULL                            | This action was triggered directly.      |
| action                 | create                          | New record was created                   |
| model                  | Invoice                         |                                          |
| model_id               | 1                               |                                          |
| ts                     | 2016-10-04 00:49:55             | Timestamp always uses UTC as per Agile Data implementation. |
| time_taken             | 0.001196                        | AuditLog actually tracks how long many seconds this operation took. This can be disabled. |
| request_diff           | {"ref":[null,"inv1"]}           | SQL stores value in JSON but it's converted into PHP array on load/save. |
| reactive_diff          | {"id":1,"ref":"inv1","total":0} | For create operations contains all fields. |
| descr                  | create ref=inv1                 | Human-readable field                     |
| is_reverted            | 0                               | Will be set to 1 if you execute `undo()` |
| revert_audit_log_id    | null                            | When reverted, will point to revert log. |

This record corresponds to us creating initial model. Next we were adding invoice line, which was reflected in the audit_log. 

``` php
$m->ref('Lines')->insert(['item'=>'Chair', 'price'=>2.50, 'qty'=>3]);
```

| Field                  | Value                                    | Description                              |
| ---------------------- | ---------------------------------------- | ---------------------------------------- |
| id                     | 2                                        |                                          |
| initiator_audit_log_id | NULL                                     | Also a manually created record           |
| action                 | create                                   | New line added through insert()          |
| model                  | Line                                     |                                          |
| model_id               | 1                                        |                                          |
| ts                     | 2016-10-04 00:49:55                      | May be same, so this field can't be used for ordering. |
| time_taken             | 0.005522                                 | This action took longer (because of related operation) |
| request_diff           | {"item":[null,"Chair"],"price":[0,2.5],"qty":[0,3]} | SQL stores value in JSON but it's converted into PHP array on load/save. |
| reactive_diff          | {"id":null,"invoice_id":"1", "item":"Chair","price":2.5, "qty":3,"total":7.5} | All values being stored, including calculated. |
| descr                  | create item=Chair, price=2.5, qty=3      | Human-readable field                     |
| is_reverted            | 0                                        |                                          |
| revert_audit_log_id    | null                                     |                                          |

The next entry is reactive and was caused beacuse of the call to `adjustTotal` with a subsequential `save()`

| Field                  | Value                 | Description                              |
| ---------------------- | --------------------- | ---------------------------------------- |
| id                     | 3                     |                                          |
| initiator_audit_log_id | 2                     | Reactive change, caused by previous record. |
| action                 | total_adjusted        | We have manually specified this          |
| model                  | Invoice               |                                          |
| model_id               | 1                     |                                          |
| ts                     | 2016-10-04 00:49:55   |                                          |
| time_taken             | 0.000367              |                                          |
| request_diff           | {"total":[0,7.5]}     | Total was the only field changed         |
| reactive_diff          | NULL                  | When identical to request_diff, this will store NULL to save space |
| descr                  | Changing total by 7.5 | Human-readable field, specified by us    |
| is_reverted            | 0                     |                                          |
| revert_audit_log_id    | null                  |                                          |

Next line is similar to the above:

``` php
$m->ref('Lines')->insert(['item'=>'Desk', 'price'=>10.20, 'qty'=>1]);
```

| Field                  | Value                                    | Description |
| ---------------------- | ---------------------------------------- | ----------- |
| id                     | 4                                        |             |
| initiator_audit_log_id | NULL                                     |             |
| action                 | create                                   |             |
| model                  | Line                                     |             |
| model_id               | 1                                        |             |
| ts                     | 2016-10-04 00:49:55                      |             |
| time_taken             | 0.004329                                 |             |
| request_diff           | {"item":[null,"Desk"],"price":[0,10.2],"qty":[0,1]} |             |
| reactive_diff          | {"id":null,"invoice_id":"1","item":"Desk","price":10.2,"qty":1,"total":10.2} |             |
| descr                  | create item=Desk, price=10.2, qty=1      |             |
| is_reverted            | 0                                        |             |
| revert_audit_log_id    | null                                     |             |

| Field                  | Value                  | Description                           |
| ---------------------- | ---------------------- | ------------------------------------- |
| id                     | 5                      |                                       |
| initiator_audit_log_id | 4                      |                                       |
| action                 | total_adjusted         |                                       |
| model                  | Invoice                |                                       |
| model_id               | 1                      |                                       |
| ts                     | 2016-10-04 00:49:55    |                                       |
| time_taken             | 0.000367               |                                       |
| request_diff           | {"total":[7.5,17.7]}   | Total was the only field changed      |
| reactive_diff          | NULL                   |                                       |
| descr                  | Changing total by 10.2 | Human-readable field, specified by us |
| is_reverted            | 0                      |                                       |
| revert_audit_log_id    | null                   |                                       |

## Now we can Undo things.

To keep things safe, `undo()` will not work recursively. Let's load our Audit record and call undo() manually on record (4).

``` php
$a = $this->db->add(clone $audit->audit_model);
$a->load(4)->undo();
```

 Looking at the records now, the above operation has removed `line.id=2`, but the total for the Invoice was not updated. The reason is because our model for the Line did not contain `afterDelete()` hook to properly react to deleted records.

## Remaining Touches

Attempt to undo action 5 will end in failure:

``` php
$a->load(4)->undo();

// Method is not defined for this object: 
// atk4\\audit\\model\\AuditLog
// undo_total_adjusted
```

If we wanted to undo this operation, we would have to create our own "undo" handler inside AuditLog explaining how it should be done. We don't really want anything to be done, so we can define a blank method in our code above.

``` php
// after this line
$audit = new \atk4\audit\Controller();
// add this line
$audit->audit_model->addMethod('undo_total_adjusted', function() {} );
```

Now if you attempt to undo operation 5, it will successfully mark operation as "un-done". Next lets deal with the problem of totals not being re-calculated on record deletion. To address add the following inside Line::init():

``` php
$this->addHook('afterDelete', function($m) {
    $this->ref('invoice_id')->adjustTotal(-$m['total']);
});
```

After this modification adding, deleting and "undo" operations will perform correctly. You will also be able to `undo()` log with id=2 which corresponds to addition of first invoice line. As a final modification, let's make sure that invoice deletion would also delete it's lines. Add the following code to Invoice model:

``` php
$this->addHook('beforeDelete', function($m) {
    $m->ref('Lines', ['no_adjust'=>true])->each('delete');
});
```

The reason I'm passing `no_adjust` here is because I don't want Lines to do unnecessary changes by adjusting total of Invoice that is about to be deleted. We need to listen for this property inside `Line` model:

``` php
class Line extends \atk4\data\Model {
    public $table = 'line';

    // add this line
    public $no_adjust = false;
  
      function init()
      {
        parent::init();

        $this->hasOne('invoice_id', new Invoice());

        $this->addField('item', ['type' => 'string']);
        $this->addField('price', ['type' => 'money', 'default' => 0.00]);
        $this->addField('qty', ['type' => 'integer', 'default' => 0]);
        $this->addField('total', ['type' => 'money', 'default' => 0.00]);

        // add this line
        if ($this->no_adjust) return;

        // rest remains as-is..
        $this->addHook(........
```

## Final run-through

Executing our test code again:

``` php
$a = $this->db->add(clone $audit->audit_model);
$a->load(1);
$a->undo();

echo 'invoices = '.json_encode($m->export())."\n";
echo 'lines = '.json_encode($m->ref('Lines')->export())."\n";
```

The `AuditLog.id=1` corresponds to opeartion for adding new Invoice. `undo()` on this operation will delete invoice that will also affect invocie lines. Let's look at the full audit log again:

| id   | initiator | action         | model   | model_id | revert | revert_id |
| ---- | --------- | -------------- | ------- | -------- | ------ | --------- |
| 1    |           | create         | Invoice | 1        | 1      |           |
| 2    |           | create         | Line    | 1        |        |           |
| 3    | 2         | total_adjusted | Invoice | 1        |        |           |
| 4    |           | create         | Line    | 2        |        |           |
| 5    | 4         | total_adjusted | Invoice | 1        |        |           |
| 6    |           | undo create    | Invoice | 1        |        | 1         |
| 7    | 6         | delete         | Line    | 1        |        |           |
| 8    | 6         | delete         | Line    | 2        |        |           |

I have ommitted details from AuditLog, but the outline above is clean, easy to read and easy to vizualize for the user and very logical.