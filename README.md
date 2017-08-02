ПО:

- Tarantool 1.8.1+ (https://tarantool.org/en/doc/1.8/tutorials/sql_tutorial.html)
- PECL PHP driver for Tarantool (https://github.com/tarantool/tarantool-php)

Файлы драйвера базы (tarantool*.php) должны быть расположены в bitrix/modules/main/lib/db.

В .settings.php нужно подключение в БД:
```
'tarantool' => array(
  'className' => '\\Bitrix\\Main\\DB\\TarantoolConnection',
  'host' => 'localhost',
  'database' => 'test',
  'login' => 'guest',
  'password' => '',
),
```

В консоли tarantool:

слушать:
```
box.cfg {listen=3301}
```

права:
```
box.schema.user.grant('guest', 'read,write,execute', 'universe')
```

таблицы:
```
box.sql.execute([[create table bt1(ID INTEGER PRIMARY KEY, NAME VARCHAR(255), XML_ID VARCHAR(255)]])
box.sql.execute([[insert into bt1 (ID, NAME, XML_ID) values (1, 'Test1', 'test-1')]])
box.sql.execute([[insert into bt1 (ID, NAME, XML_ID) values (2, 'Test2', 'test-2')]])
```
