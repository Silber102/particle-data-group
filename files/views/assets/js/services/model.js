/**
 * Data models
 */
Apperyio.Entity = new Apperyio.EntityFactory({
    "Number": {
        "type": "number"
    },
    "Boolean": {
        "type": "boolean"
    },
    "String": {
        "type": "string"
    }
});
Apperyio.getModel = Apperyio.Entity.get.bind(Apperyio.Entity);

/**
 * Data storage
 */
Apperyio.storage = {};

/**
 * Push Notification specific data storage
 */
Apperyio.storage.pushNotificationToken = new $a.LocalStorage("pushNotificationToken", "String");
Apperyio.storage.pushNotificationDeviceID = new $a.LocalStorage("pushNotificationDeviceID", "String");
Apperyio.storage.deviceTimeZone = new $a.LocalStorage("deviceTimeZone", "String");