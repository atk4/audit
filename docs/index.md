
# Agile Audit Extension

Audit Extension provides a system-wide controller that can be added into any of your or 3rd
party models. Once Added audit will keep track of all record changes. Changes will be stored
into a separate model and will be sufficient to answer the following questions at any later time.

## Enable Audit for your Models

Any model in your system regardless of your database choice can be audited. You can enlist
specific models by adding controller manually or perform a system-wide audit.

``` php
use \atk4\audit;

class User extends \atk4\data\Model {
    public $table = 'user';
  
    function init() {
        parent::init();
      
        $this->addField('name');
        $this->addField('email');
        $this->addField('password');
      
        // The following lines enable full audit:
        $this->add(new audit\Controller(
            new audit\model\AuditLog()
        )); 
    }
}
```

You have a choice. You may use `model\AuditLog` that will automatically store information about the enviroment. Alternatively you can supply your own model, which you can extend from `model\AuditLog`.

## System-wide Audit

More often you would want all of your models to be automatically audited. The next snippet demonstrates how to implement numerous things:

-   I will be automatically auditing all my models
-   If possible, I'll record currently logged user
-   I do not want to store certain fields and certain types

Here is the relevant usage code. I start by defining my own Audit model:

```php
class Audit extends \atk4\audit\model\Audit
{
    public $no_audit = true;
  
    public $exclude_field_values = ['password'];
    public $exclude_field_types = ['encrypted'];
  
    function getExtraData()
    {
        $extra = [];
      
        // If we have App context, record currently logged user
        if (isset($this->app->user)) {
            $extra['user'] = $this->app->user;
        }

        return $extra;
    }
}
```

To continue I need to add a handler into persistence which will automatically attach itself to all initialized models:

``` php
$audit = new \atk4\audit\Controller(new Audit());

$db->addHook('afterAdd', function($owner, $element) {
    if ($element instanceof \atk4\data\Model) {
        if (isset($element->no_audit) && $element->no_audit) {
            // Whitelisting this model, won't audit
            break;
        }
      
        $element->add($audit); // re-using same object to save resources
    }
});
```

You can refer to full class documentation if you want to further extend Audit behaviours.

## Requested vs Reactive actions

Agile Data incorporates rich volume of logic that allow you to make a lot of decision across the system when even a smallest change is requested. For example assuming you have the following structure:

-   Invoice
    -   `addFields(['total_net', 'total_vat', 'total_gross'], ['type' => 'money']);`
    -   `hasMany('Line')`
        -   `addField('qty', ['type => 'int'])`
        -   `addField('vat_rate', ['type' => 'float'])`
        -   `addFields(['price', 'vat', 'net', 'gross'], ['type' => 'money']);`

Your `afterSave` hooks will automatically recalculate and update `Invoice` whenever you change the `Line`. Additionally, changing `qty` will trigger change in `vat`, `net` and `gross`.

Looking at he following code:

``` php
$m = new Invoice($db);
$m->load(1);
$m['qty']++;
$m->save();
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

The default way for Audit Log is to store only "reactive" changes, however you can enable storing of both "requested" and "reactivte":

``` php
$audit = new \atk4\audit\Controller([
    new Audit(),
    ['requested_log' => true, 'reactive_log' => true, 'link' => true]
);
```

The option for `nested` will also associate changes inside `Invoice` with the requested changes of `Line`. If you need to customise settings on per-model basis, you should create individual controllers.

## How values are stored?

Audit Extension uses array persistence to prepare values for storage inside JSON. If you need to tweak how values are stored exactly, you shourd refer to documentation on [typecasting](http://agile-data.readthedocs.io/en/develop/persistence.html?highlight=typecasting#type-converting).

System is storing using business-domain field names. If "net" has an actual field of "sql_net", then audit will store "net". Additionally 

## Undo and Replay features

Agile Audit Extension perform a strict type auditing which can be quite useful for automation. Certain actions can be "undone" or "replayed" (Redo).

Those action can be performed on the `Audit` model after you load the record:

``` php
$audit->load(20);
$audit->replay();
```

Assuming that audit log with ID=20 corresponds to the `qty` modifications as I was explaining above, the replay will perform the following:

-   start transaction
-   load model for `Line`
-   perform modification of `qty`
-   save value of `qty`
-   track changed fields in `Line` and `Invoice`
-   assert to make sure all the same reactive changes happened
-   commit

Similarly you can also call `undo()`, which will:

-   Reverse all `new` / `old` values for original Audit event and all related ones
-   Attempts to apply the replay()

Both `Undo` and `Replay` functionality can bypass the verification steps or can actually enforce `Reactive` changes to be used. Those modes are less safe but if that's what you want you can try it.

Finally, Replay feature can also override `id` of the original model. In this scenario changes will be re-applied to a different record. 

### When Undo and Replay are useful?

Undo can be offered to a user as an option. Because implementation of `Undo` especially in transaction-supporting database is pretty safe, you can execute multiple `Undo` actions effectively allowing you to walk between revisions of your persistence.

``` php
$audit = new Audit($db);
$audit->addCondition('user_id', $this->app->user->id);
$audit->addCondition('date', '>', $unroll_to_date);
$audit->setOrder('id desc');
$audit->each('undo');
```

Applying `Replay` on the range of entries makes a pretty effective multi-record update technique.

```php
$invoices = $client->ref('Invoice'); // references multiple invoices
$invoices->add($audit);

// record action
$invoices->loadAny();
$invoices->save( $new_data );

$audit->last_action->applyOnOthers($invoices);
```

Finally, replay can be used in creating unit tests. If you have enabled Audit Log for your application and have already performed some actions, you can generate  `PHPUnit`-compatible code through Admin Audit Page.

## Admin Page

Audit Extension comes with [Agile UI](https://github.com/atk4/ui) based page that contains a handy management console where you can browse all the recent events, convert them into unit-tests, undo or re-apply some of those. Additionally selecting an event will also show you all the "Reactive" actions that have been done.

![data-audit-1-console](images/data-audit-1-console.png)

## Download and Install

Audit Extension is currently in Beta. You need to contact us if you wish to get early access.
