<?php
namespace Bitrix\Main\DB;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Diag;
use Bitrix\Main\Entity;

/**
 * Class TarantoolConnection
 *
 * Class for Tarantool database connections.
 * @package Bitrix\Main\DB
 */
class TarantoolConnection extends Connection
{
	protected $lastInsertedId;

	/**********************************************************
	 * SqlHelper
	 **********************************************************/

	/**
	 * @return \Bitrix\Main\Db\SqlHelper
	 */
	protected function createSqlHelper()
	{
		return new TarantoolSqlHelper($this);
	}

	/***********************************************************
	 * Connection and disconnection
	 ***********************************************************/

	/**
	 * Establishes a connection to the database.
	 * Includes php_interface/after_connect_d7.php on success.
	 * Throws exception on failure.
	 *
	 * @return void
	 * @throws \Bitrix\Main\DB\ConnectionException
	 */
	protected function connectInternal()
	{
		if ($this->isConnected)
			return;

		// TODO $this->login, $this->password, $this->database, host, port, ...
		$connection = new \Tarantool();

		if (!$connection->ping())
			throw new ConnectionException('Tarantool connect error', $this->getErrorMessage());

		$this->isConnected = true;
		$this->resource = $connection;

		$this->afterConnected();
	}

	/**
	 * Disconnects from the database.
	 * Does nothing if there was no connection established.
	 *
	 * @return void
	 */
	protected function disconnectInternal()
	{
		if (!$this->isConnected)
			return;

		$this->isConnected = false;
		$this->resource->disconnect();
	}

	/*********************************************************
	 * Query
	 *********************************************************/

	/**
	 * Executes a query against connected database.
	 * Rises SqlQueryException on any database error.
	 * <p>
	 * When object $trackerQuery passed then calls its startQuery and finishQuery
	 * methods before and after query execution.
	 *
	 * @param string                            $sql Sql query.
	 * @param array                             $binds Array of binds.
	 * @param \Bitrix\Main\Diag\SqlTrackerQuery $trackerQuery Debug collector object.
	 *
	 * @return resource
	 * @throws \Bitrix\Main\Db\SqlQueryException
	 */
	protected function queryInternal($sql, array $binds = null, \Bitrix\Main\Diag\SqlTrackerQuery $trackerQuery = null)
	{
		$this->connectInternal();

		if ($trackerQuery != null)
			$trackerQuery->startQuery($sql, $binds);

		// FIXME secure?
		//echo $sql;
		$result = $this->resource->evaluate('return box.sql.execute([['.$sql.']])');

		if (!$result)
		{
			if ($trackerQuery != null)
				$trackerQuery->finishQuery();

			throw new SqlQueryException("", $this->getErrorMessage($this->resource), $sql);
		}

		if ($trackerQuery != null)
		{
			$trackerQuery->finishQuery();
		}

		$this->lastQueryResult = $result;

		return $result;
	}

	/**
	 * Returns database depended result of the query.
	 *
	 * @param resource $result Result of internal query function.
	 * @param \Bitrix\Main\Diag\SqlTrackerQuery $trackerQuery Debug collector object.
	 *
	 * @return Result
	 */
	protected function createResult($result, \Bitrix\Main\Diag\SqlTrackerQuery $trackerQuery = null)
	{
		return new TarantoolResult($result, $this, $trackerQuery);
	}

	/**
	 * Executes a query to the database.
	 *
	 * - query($sql)
	 * - query($sql, $limit)
	 * - query($sql, $offset, $limit)
	 * - query($sql, $binds)
	 * - query($sql, $binds, $limit)
	 * - query($sql, $binds, $offset, $limit)
	 *
	 * @param string $sql Sql query.
	 * @param array $binds Array of binds.
	 * @param int $offset Offset of the first row to return, starting from 0.
	 * @param int $limit Limit rows count.
	 *
	 * @return Result
	 * @throws \Bitrix\Main\Db\SqlQueryException
	 */
	public function query($sql)
	{
		list($sql, $binds, $offset, $limit) = self::parseQueryFunctionArgs(func_get_args());

		return parent::query($sql, $binds, $offset, $limit);
	}

	/**
	 * Adds row to table and returns ID of the added row.
	 * <p>
	 * $identity parameter must be null when table does not have autoincrement column.
	 *
	 * @param string $tableName Name of the table for insertion of new row..
	 * @param array $data Array of columnName => Value pairs.
	 *
	 * @return integer
	 * @throws \Bitrix\Main\Db\SqlQueryException
	 */
	public function add($tableName, array $data)
	{
		if($identity !== null && !isset($data[$identity]))
			$data[$identity] = $this->getNextId("sq_".$tableName);

		$insert = $this->getSqlHelper()->prepareInsert($tableName, $data);

		$binds = $insert[2];

		$sql =
			"INSERT INTO ".$tableName."(".$insert[0].") ".
			"VALUES (".$insert[1].")";

		$this->queryExecute($sql, $binds);

		// FIXME last insert id
		$this->lastInsertedId = $data[0];

		return $data;
	}

	// FIXME get next id
	/**
	 * Gets next value from the database sequence.
	 * <p>
	 * Sequence name may contain only A-Z,a-z,0-9 and _ characters.
	 *
	 * @param string $name Name of the sequence.
	 *
	 * @return null|string
	 * @throws \Bitrix\Main\ArgumentNullException
	 */
	public function getNextId($name = "")
	{
		$name = preg_replace("/[^A-Za-z0-9_]+/i", "", $name);
		$name = trim($name);

		if($name == '')
			throw new \Bitrix\Main\ArgumentNullException("name");

		$sql = "SELECT ".$this->getSqlHelper()->quote($name).".NEXTVAL FROM DUAL";

		$result = $this->query($sql);
		if ($row = $result->fetch())
		{
			return array_shift($row);
		}

		return null;
	}

	/**
	 * @return integer
	 */
	public function getInsertedId()
	{
		return $this->lastInsertedId;
	}

	/**
	 * Returns affected rows count from last executed query.
	 *
	 * @return integer
	 */
	public function getAffectedRowsCount()
	{
		return count($this->lastQueryResult);
	}

	/**
	 * Checks if a table exists.
	 *
	 * @param string $tableName The table name.
	 *
	 * @return boolean
	 */
	public function isTableExists($tableName)
	{
		if (empty($tableName))
			return false;
		$r = $this->resource->evaluate('return box.schema.'.$tableName);
		return empty($r) || $r[0] === null;
	}

	/**
	 * Checks if an index exists.
	 * Actual columns in the index may differ from requested.
	 * $columns may present an "prefix" of actual index columns.
	 *
	 * @param string $tableName A table name.
	 * @param array  $columns An array of columns in the index.
	 *
	 * @return boolean
	 * @throws \Bitrix\Main\Db\SqlQueryException
	 */
	public function isIndexExists($tableName, array $columns)
	{
		return $this->getIndexName($tableName, $columns) !== null;
	}

	// TODO get index name
	/**
	 * Returns the name of an index.
	 *
	 * @param string $tableName A table name.
	 * @param array $columns An array of columns in the index.
	 * @param bool $strict The flag indicating that the columns in the index must exactly match the columns in the $arColumns parameter.
	 *
	 * @return string|null Name of the index or null if the index doesn't exist.
	 * @throws \Bitrix\Main\Db\SqlQueryException
	 */
	public function getIndexName($tableName, array $columns, $strict = false)
	{
		if (!is_array($columns) || empty($columns))
			return null;
		/*
		$isFunc = false;
		$indexes = array();

		$result = $this->query("SELECT * FROM USER_IND_COLUMNS WHERE TABLE_NAME = upper('".$this->getSqlHelper()->forSql($tableName)."')");
		while ($ar = $result->fetch())
		{
			$indexes[$ar["INDEX_NAME"]][$ar["COLUMN_POSITION"] - 1] = $ar["COLUMN_NAME"];
			if (strncmp($ar["COLUMN_NAME"], "SYS_NC", 6) === 0)
			{
				$isFunc = true;
			}
		}

		if ($isFunc)
		{
			$result = $this->query("SELECT * FROM USER_IND_EXPRESSIONS WHERE TABLE_NAME = upper('".$this->getSqlHelper()->forSql($tableName)."')");
			while ($ar = $result->fetch())
			{
				$indexes[$ar["INDEX_NAME"]][$ar["COLUMN_POSITION"] - 1] = $ar["COLUMN_EXPRESSION"];
			}
		}

		$columnsList = implode(",", $columns);
		foreach ($indexes as $indexName => $indexColumns)
		{
			ksort($indexColumns);
			$indexColumnList = implode(",", $indexColumns);
			if ($strict)
			{
				if ($indexColumnList === $columnsList)
					return $indexName;
			}
			else
			{
				if (substr($indexColumnList, 0, strlen($columnsList)) === $columnsList)
					return $indexName;
			}
		}
		*/
		return null;
	}

	/**
	 * Returns fields objects according to the columns of a table.
	 * Table must exists.
	 *
	 * @param string $tableName The table name.
	 *
	 * @return Entity\ScalarField[] An array of objects with columns information.
	 * @throws \Bitrix\Main\Db\SqlQueryException
	 */
	public function getTableFields($tableName)
	{
		if (!isset($this->tableColumnsCache[$tableName]))
		{
			$this->connectInternal();

			// FIXME ROWNUM
			$query = $this->queryInternal("SELECT * FROM ".$this->getSqlHelper()->quote($tableName)." WHERE ROWNUM = 0");

			$result = $this->createResult($query);

			$this->tableColumnsCache[$tableName] = $result->getFields();
		}
		return $this->tableColumnsCache[$tableName];
	}

	/**
	 * @param string $tableName Name of the new table.
	 * @param \Bitrix\Main\Entity\ScalarField[] $fields Array with columns descriptions.
	 * @param string[] $primary Array with primary key column names.
	 * @param string[] $autoincrement Which columns will be auto incremented ones.
	 *
	 * @return void
	 * @throws \Bitrix\Main\ArgumentException
	 * @throws \Bitrix\Main\Db\SqlQueryException
	 */
	public function createTable($tableName, $fields, $primary = array(), $autoincrement = array())
	{
		$sql = 'CREATE TABLE '.$this->getSqlHelper()->quote($tableName).' (';
		$sqlFields = array();

		foreach ($fields as $columnName => $field)
		{
			if (!($field instanceof Entity\ScalarField))
			{
				throw new ArgumentException(sprintf(
					'Field `%s` should be an Entity\ScalarField instance', $columnName
				));
			}

			$realColumnName = $field->getColumnName();

			$sqlFields[] = $this->getSqlHelper()->quote($realColumnName)
				. ' ' . $this->getSqlHelper()->getColumnTypeByField($field)
				. ' ' . (in_array($columnName, $primary, true) ? 'NOT NULL' : 'NULL')
			;
		}

		$sql .= join(', ', $sqlFields);

		if (!empty($primary))
		{
			foreach ($primary as &$primaryColumn)
			{
				$realColumnName = $fields[$primaryColumn]->getColumnName();
				$primaryColumn = $this->getSqlHelper()->quote($realColumnName);
			}

			$sql .= ', PRIMARY KEY('.join(', ', $primary).')';
		}

		$sql .= ')';

		$this->query($sql);

		// autoincrement field
		if (!empty($autoincrement))
		{
			foreach ($autoincrement as $autoincrementColumn)
			{
				$autoincrementColumn = $fields[$autoincrementColumn]->getColumnName();

				if ($autoincrementColumn == 'ID')
				{
					// old-school hack
					$aiName = $tableName;
				}
				else
				{
					$aiName = $tableName.'_'.$autoincrementColumn;
				}

			}	
		}
	}

	/**
	 * Renames the table. Renamed table must exists and new name must not be occupied by any database object.
	 *
	 * @param string $currentName Old name of the table.
	 * @param string $newName New name of the table.
	 *
	 * @return void
	 * @throws \Bitrix\Main\Db\SqlQueryException
	 */
	public function renameTable($currentName, $newName)
	{
		$this->query('RENAME '.$this->getSqlHelper()->quote($currentName).' TO '.$this->getSqlHelper()->quote($newName));
	}	

	/**
	 * Drops the table.
	 *
	 * @param string $tableName Name of the table to be dropped.
	 *
	 * @return void
	 * @throws \Bitrix\Main\Db\SqlQueryException
	 */
	public function dropTable($tableName)
	{
		$this->query('DROP TABLE '.$this->getSqlHelper()->quote($tableName).' CASCADE CONSTRAINTS');
	}

	/*********************************************************
	 * Transaction
	 *********************************************************/

	/**
	 * Starts new database transaction.
	 *
	 * @return void
	 * @throws \Bitrix\Main\Db\SqlQueryException
	 */
	public function startTransaction()
	{
	}

	/**
	 * Commits started database transaction.
	 *
	 * @return void
	 * @throws \Bitrix\Main\Db\SqlQueryException
	 */
	public function commitTransaction()
	{
	}

	/**
	 * Rollbacks started database transaction.
	 *
	 * @return void
	 * @throws \Bitrix\Main\Db\SqlQueryException
	 */
	public function rollbackTransaction()
	{
	}

	/*********************************************************
	 * Type, version, cache, etc.
	 *********************************************************/

	/**
	 * Returns database type.
	 * <ul>
	 * <li> oracle
	 * </ul>
	 *
	 * @return string
	 * @see \Bitrix\Main\DB\Connection::getType
	 */
	public function getType()
	{
		return "tarantool";
	}

	// FIXME versions
	/**
	 * Returns connected database version.
	 * Version presented in array of two elements.
	 * - First (with index 0) is database version.
	 * - Second (with index 1) is true when light/express version of database is used.
	 *
	 * @return array
	 * @throws \Bitrix\Main\Db\SqlQueryException
	 */
	public function getVersion()
	{
		if ($this->version == null)
		{
			$this->version = '1';
		}

		return array($this->version, $this->versionExpress);
	}

	/**
	 * Returns error message of last failed database operation.
	 *
	 * @param resource $resource Connection or query result resource.
	 * @return string
	 */
	protected function getErrorMessage($resource = null)
	{
		// TODO errors
		$error = ['code' => 'code', 'message' => 'message'];

		$result = sprintf("[%s] %s", $error["code"], $error["message"]);
		if (!empty($error["sqltext"]))
			$result .= sprintf(" (%s)", $error["sqltext"]);

		return $result;
	}
}
