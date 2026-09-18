
# Agile Audit Extension

Audit Extension provides a mechanism to store all changes that happen during persistance of your model. This extension is designed to be extensive and flexible. Use Audit if you need to track changes performed by your users in great detail.

Audit also supports some additional features such as ability to record actions that have **failed** to execute (due to validation) along with the error, log **custom** actions and few more. Audit records which **field values** were changed inside a model before executing `save()` and which fields were changed **reactively** (through other hooks or automatic calculations) and will track and link reactive modifications to **multiple models**.

Huge focus on extensibility allow you to **customise** name of log table, change field names, database **engine** (e.g. store in CSV file, API or Cloud Database), **switch off** certain features, customise **human-readable** log entries and add additional information about **user**, **session** or **environment**.

(See also - [Full Example](full-example.md))

## Enabling Audit Log

To enable extension for your model, create audit controller object and attach model to it:

``` php
$audit = new \Atk4\Audit\AuditController($db);
$users = new Model\User($db);
$audit->addModel($model);
```

For a basic usage you will also need to create `audit_log` table by importing `audit_log.sql` file. The audit-log is automatically populated when you perform an operation with the model next time:

``` php
$user = $users->load(1);
$user->set('name', 'Ken'); // was Vinny before
$user->save();
```

The following new record will be stored inside `audit_log` table:

``` json
{
   "id":1,
   "initiator_audit_log_id":null,
   "action":"update",
   "model":"Atk4\\Audit\\Tests\\User",
   "model_id":"1",
   "start_time_ms":1789549702238,
   "start_time":"2026-09-16 09:08:22.238",
   "duration_ms":35,
   "request_diff":{
      "name":[
         "Vinny",
         "Ken"
      ]
   },
   "reactive_diff":{},
   "session_info":{"ip":"::1"},
   "descr":"update #1 (Vinny): name=Vinny"
}
```

Here are some more advanced topics:

-   [Enable AuditLog for all your Models](system-wide.md)
-   [Configure which fields are logged](policies.md)

## Working With the Log Entries

Your model contains reference to AuditLog model. Let's see how many times the above record have been modified in the past:

``` php
echo $users->load(1)->ref('AuditLog')->action('count')->getOne();  // 1
```

You can also use it to access records individually or just access last record:

``` php
$users->load(1)->ref('AuditLog')->loadLast(); // access last action
```



## Requested and Reactive field changes

AuditLog extension records fields that were `dirty` before execution of save() operation. Sometimes you would have a logic inside your model hooks that can change more records or even change other models. For example, if you change `InvoiceLine` amount it might want to update amount of `Inovice` too.

Agile Data incorporates rich volume of logic that allow you to make a lot of decision across the system when even a smallest change is requested. For example, assuming you have the following structure:

-   Invoice
    -   `addFields(['total_net', 'total_vat', 'total_gross'], ['type' => 'atk4_money']);`
    -   `hasMany('Line')`
        -   `addField('qty', ['type => 'int'])`
        -   `addField('vat_rate', ['type' => 'float'])`
        -   `addFields(['price', 'vat', 'net', 'gross'], ['type' => 'atk4_money']);`

Your `afterSave` hooks will automatically recalculate and update `Invoice` whenever you change the `Line`. Additionally, changing `qty` will trigger change in `vat`, `net` and `gross`.

Now check the following code:

``` php
$invoice = (new Invoice($db))->load(1);
$invoice->set('qty', $invoice->get('qty') + 1);
$invoice->save();
```

Only a single field falls into "requested" change, which is `qty`. The original value and a new value will be stored in JSON:

``` json
{"qty": [5, 6]}
```

However due to hooks, many other values have also been updated. For the Line the "reactive" changes are:

``` json
{"net": [50, 60], "vat": [11.5, 13.8], "gross": [51.5, 63.8]}
```

Then you have some "reactive" changes for the `Invoice` model too:

``` php
{"total_net": [100, 110], "total_vat": [23.0, 25.3], "total_gross": [123.0, 135.3]}
```

## How values are stored?

Audit Extension uses JSON field type to store this info in DB. You can change or improve that by extending audit controller class.

System is storing using business-domain field names. If "net" has an actual field of "sql_net", then audit will store "net".

## Admin Page

Audit Extension comes with [Agile UI](https://github.com/atk4/ui) based page that contains a handy management console where you can browse all the recent events. Additionally selecting an event will also show you all the "Reactive" actions that have been done.

![data-audit-1-console](images/data-audit-1-console.png)

## Download and Install

Audit Extension is currently in Beta.
