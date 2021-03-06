Описание
========

Эксперименты по использованию Tarantool (https://tarantool.org/) в Битрикс (https://www.1c-bitrix.ru/).

На данный момент реализован самый базовый функционал выборки:
```
$res = \My\TestTable::getList();
while ($row = $res->fetch())
{
	print_r($row);
}
```

todo:
- результат с полями, а не индексами
- проверка/реализация выборок с фильтрами, сортировками, группировками, объединениями и т.д. (см. документацию по возможностям sql в tarantool)
- проверка/реализация вставки, обновления, удаления записей
- проверка существования таблицы/поля
- и др. и пр. возможности драйвера базы и sql в tarantool

Установка и использование
=========================

ПО
--

- Tarantool 1.8.1+ (https://tarantool.org/en/doc/1.8/tutorials/sql_tutorial.html)
- PECL PHP driver for Tarantool (https://github.com/tarantool/tarantool-php)

Файлы драйвера базы (tarantool*.php) должны быть расположены в bitrix/modules/main/lib/db.

Подготовка/настройка
--------------------

В bitrix/.settings.php нужно подключение в БД:
```
'tarantool' => array(
  'className' => '\\Bitrix\\Main\\DB\\TarantoolConnection',
  'host' => 'localhost',
  'database' => 'test',
  'login' => 'guest',
  'password' => '',
),
```

Тестовая модель (\My\TestTable, расширение Entity\DataManager) в model.php должна быть подключена в своем модуле или в init.php.

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

Прочее
------

- _db.php - консольные эксперименты
- test.php - веб-эксперименты
