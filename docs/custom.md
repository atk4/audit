
# Custom Events

There are three ways to add custom events inside audit log.

## Custom action based on change

In this approach, we will register a `beforeSave` hook that will examine the nature of our change and if certain condition is met, will customise the log message.

``` php
$this->addField('gender', ['enum' => ['M','F']]);
$this->addHook('beforeSave', function($m) {
    if ($m->isDirty('gender')) {
        $m->audit_log['action'] = 'genderbending';
    }
});
```

Property `audit_log` is only available while model is being saved and will point to a model object. After operation completes, the property is removed.

During the save, however, we can use this to change values. Another important point is that when `audit_log` is initially being set-up it was already saved, so that model will have a real `id` set. If no additional changes are done to the `$m` model or it's `audit_log` model, then there won't be any need to perform secondary save. 

## Setting action before action starts

The method described above will only work during the modifications hook of the model. What about situations when you want to perform custom action from outside the model? In this case you should set a property for controller:

``` php
$m->load(2); // surname=Shatwell
$m->audit_log_controller->custom_action = 'married';
$m['surname'] = 'Shira';
$m->save();
```

In this example a person is being married, so the surname have to be changed. But instead of using default action, we can set it to `married` through `custom_action` property.

After the next audit_log operation is completed, the custom_action will be emptied and the next operation will have default action set.

## Pushing custom actions

In this final scenario you would want to record action when something happened with the model without actually modifying the model itself. For that AuditLog controller have added a handy method `log()` for you right inside your method:

``` php
$m->load(2);
$m->log('inspection', ['value' => $m['test_value']]);
$m->unload();
```

In this case the nothing has happened with the model, but we are still recording information about it with a custom action `inspection`. Additionally we are populating `requested_diff` field with second argument array passed into log() method.

## Custom AuditLog Model

Sometimes you would want to have your own model. Although you could create `AuditLog` model from scratch, you would be using some of the useful features, so I recommend you to extend the existing class:

``` php
class CustomLog extends \atk4\audit\model\AuditLog {
    function init(){
        parent::init();
        $this->addField('custom_data', ['type' => 'struct']);
    }
}
```

This will extend AuditLog by adding additional field definition which you can use for storing your own information in. Type `struct` allow you to store arrays too.

## Using custom descriptions

Field `descr` inside a model stores a human-readable value. If you have a different criteria for "human readable", then you can re-define description in your audit model. To do so, define method `getDescr` inside your AuditLog model:

``` php
class CustomLog extends \atk4\audit\model\AuditLog {
    function getDescr(){
        return count($this['request_diff']).' fields magically change';
    }
}
```

This will populate `descr` with text like *"2 fields magically change".*

