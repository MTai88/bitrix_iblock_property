## Bitrix custom iblock property type

### 1. `MTai\IBlockProperty\EListByUser` - Link to elements (drop-down list) filtered by current user Id
In the property options, enter the property code of the linked iblock with user id column on which filtering will be performed.
By default, filtering will be performed by the CREATED_BY column of the authorized user Id.

## Notes
- ```/local/php_interface/autoload.php``` - Class autoloader
- ```/local/php_interface/event_handler.php``` - Event handler to add custom iblock property
