<?php
namespace My;

use Bitrix\Main,
	Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

class TestTable extends Main\Entity\DataManager
{
	public static function getConnectionName()
	{
		return 'tarantool';
	}

	public static function getTableName()
	{
		return 'bt1';
	}

	public static function getMap()
	{
		return array(
			'ID' => array(
				'data_type' => 'integer',
				'primary' => true,
				'autocomplete' => true,
				'title' => Loc::getMessage('TEST_ENTITY_ID_FIELD'),
			),
			'UF_NAME' => array(
				'data_type' => 'text',
				'title' => Loc::getMessage('TEST_ENTITY_UF_NAME_FIELD'),
			),
			'XML_ID' => array(
				'data_type' => 'text',
				'title' => Loc::getMessage('TEST_ENTITY_UF_XML_ID_FIELD'),
			),
		);
	}
}
