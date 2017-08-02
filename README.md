подключение к виртуалке с проброшеным портом ssh на локальный 2222:
ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no root@127.0.0.1 -p 2222


tarantool 1.8.1+
https://tarantool.org/en/doc/1.8/tutorials/sql_tutorial.html

https://github.com/tarantool/tarantool-php

bitrix connection:
'tarantool' =>
array (
'className' => '\\Bitrix\\Main\\DB\\TarantoolConnection',
'host' => 'localhost',
'database' => 'test',
'login' => 'guest',
'password' => '',
),

файлы драйвера базы:
bitrix/modules/main/lib/db

В консоли tarantool:

слушать:
box.cfg {listen=3301}

права:
box.schema.user.grant('guest', 'read,write,execute', 'universe')

таблицы:
box.sql.execute([[create table bt1(ID INTEGER PRIMARY KEY, NAME VARCHAR(255), XML_ID VARCHAR(255)]])
box.sql.execute([[insert into bt1 (ID, NAME, XML_ID) values (1, 'Test1', 'test-1')]])
