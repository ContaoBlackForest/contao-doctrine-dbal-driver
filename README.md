Doctrine Database Driver for Contao
===================================

To use this database driver, change the driver in your `system/config/localconfig.php`:
```php
$GLOBALS['TL_CONFIG']['dbDriver'] = 'DoctrineMySql';
```

Accessing the doctrine dbal connection
--------------------------------------

Our preferred way is to use the dependency injection container:
```php
class MyClass
{
	public function myFunc()
	{
		global $container;
		/** @var \Doctrine\DBAL\Connection $connection */
		$connection = $container['doctrine.connection.default'];

		$connection->query('...');
	}
}
```

Alternatively you can get the connection from the database instance:
```php
class MyClass
{
	public function myFunc()
	{
		/** @var \Doctrine\DBAL\Connection $connection */
		$connection = Database::getInstance()->getConnection();
	}
}
```
