Doctrine Database Driver for Contao
===================================

To use this database driver, change the driver in your `system/config/localconfig.php`:
```php
$GLOBALS['TL_CONFIG']['dbDriver'] = 'DoctrineMySQL';
```

Configure caching
-----------------

By default, the driver use an array cache (equivalent to contao).
But the caching can be configured with `$GLOBALS['TL_CONFIG']['dbCache']`, `$GLOBALS['TL_CONFIG']['dbCacheTTL']` and `$GLOBALS['TL_CONFIG']['dbCacheName']`.

`$GLOBALS['TL_CONFIG']['dbCache']` define the caching mechanism, possible values are:

<table>
<tbody>
<tr>
<th>apc</th>
<td>use apc cache</td>
</tr>
<tr>
<th>xcache</th>
<td>use xcache cache</td>
</tr>
<tr>
<th>memcache://&lt;host&gt;[:&lt;port&gt;]</th>
<td>use memcache cache on &lt;host&gt;:&lt;port&gt;</td>
</tr>
<tr>
<th>redis://&lt;host&gt;[:&lt;port&gt;]</th>
<td>use redis cache on &lt;host&gt;:&lt;port&gt;</td>
</tr>
<tr>
<th>redis://&lt;socket&gt;</th>
<td>use redis cache on &lt;socket&gt; file</td>
</tr>
<tr>
<th>array</th>
<td>use array cache</td>
</tr>
<tr>
<th>false</th>
<td>disable the cache</td>
</tr>
</tbody>
</table>

`$GLOBALS['TL_CONFIG']['dbCacheTTL']` is an integer value, that define the *time to live*
(default value is 1 second for backend and 15 second for frontend).

`$GLOBALS['TL_CONFIG']['dbCacheName']` is a string for uniq identify cache entries. This is useful if you have a shared cache like memcache
(default value is `md5(/absolute/path/to/bit3/contao-doctrine-dbal-driver/src/Contao/Doctrine/Driver/MySQL/Statement.php)`).

### Different caching in frontend and backend

You can add `_FE` or `_BE` to each cache config key, to define different caching in frontend and backend.
For example `$GLOBALS['TL_CONFIG']['dbCache_FE']` define the frontend caching mechanism
and `$GLOBALS['TL_CONFIG']['dbCacheTTL_BE']` define the backend caching time to live.

Accessing the doctrine dbal connection
--------------------------------------

If you have installed [bit3/contao-doctrine-dbal](https://github.com/bit3/contao-doctrine-dbal), you should use the dependency injection container:
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
