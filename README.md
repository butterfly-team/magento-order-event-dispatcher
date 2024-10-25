
# Magento 2 Order Event Dispatcher Module

This Magento 2 module allows you to trigger order-related events for an existing order via a URL. This is useful when you manually create orders in the Magento database and want to trigger events for processing workflows such as inventory updates, email notifications, and more.

## Features
- Trigger Magento 2 order events for an existing order ID via a URL.
- Supports events: `sales_order_place_after`, `checkout_submit_all_after`, `sales_order_save_after`.
- Secured with a secret key to ensure only authorized requests can trigger the events.

## Install/update using Composer

Composer is a dependency management tool for PHP. It allows you to declare the libraries your project depends on, and it will manage the installation or update of these libraries.

### Prerequisites
Make sure Composer is properly installed in your environment. Learn more at https://getcomposer.org.

### Install/Update

1. In your Magento root directory, run the following command to add the module via Composer:
   ```bash
   composer require butterfly-team/magento-order-event-dispatcher
   ```
2. Then, update your Magento setup:
   ```bash
   bin/magento setup:upgrade
   bin/magento setup:di:compile
   bin/magento cache:flush
   ```

### Uninstalling the Module

To remove the module installed via Composer, use these commands:

1. In your Magento root directory:
   ```bash
   composer remove butterfly-team/magento-order-event-dispatcher
   ```

## Securing the Endpoint with a Secret Key

To secure the endpoint, configure a secret key in Magento’s `core_config_data` table:

1. **Add the secret key** in the Magento admin:
   - Go to **Stores > Configuration > Advanced > Developer**.
   - Set a value under the configuration path: `butterfly/order_event_dispatcher/secret_key`.

   Alternatively, add the key directly to the database:
   ```sql
   INSERT INTO core_config_data (scope, scope_id, path, value)
   VALUES ('default', 0, 'butterfly/order_event_dispatcher/secret_key', 'your_secret_key_here');
   ```

2. **Replace `your_secret_key_here`** with a secure, unique key.

## Usage

To trigger order events for an existing order, use the following URL structure in your browser or API client:

```
http://your-magento-site.com/order_event_dispatcher/index/dispatch?order_id=<ORDER_ID>&secret=your_secret_key_here
```

Replace `<ORDER_ID>` with the actual order ID in the Magento database and `your_secret_key_here` with your configured secret key. The module will respond with a JSON message indicating the success or failure of the event dispatch.

### Example

For an order with ID `1`, access:
```
http://your-magento-site.com/order_event_dispatcher/index/dispatch?order_id=1&secret=your_secret_key_here
```

## Events Triggered

- `sales_order_place_after`: Dispatched after an order is placed.
- `checkout_submit_all_after`: Dispatched after checkout submission.
- `sales_order_save_after`: Dispatched after an order is saved.

You can customize which events are triggered in the `Dispatch.php` controller file.

## Troubleshooting

- Ensure the module is enabled:
  ```bash
  php bin/magento module:status Butterfly_OrderEventDispatcher
  ```
- Check Magento logs (`var/log/system.log` and `var/log/exception.log`) for any errors.

## License
This module is open-source and free to use. Feel free to modify it to suit your needs.

