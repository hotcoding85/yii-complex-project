<?php
/**
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\db;

use yii\base\InvalidConfigException;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;

/**
 * ActiveRecord is the base class for classes representing relational data in terms of objects.
 *
 * @include @yii/db/ActiveRecord.md
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0
 */
class ActiveRecord extends BaseActiveRecord
{
	/**
	 * The insert operation. This is mainly used when overriding [[transactions()]] to specify which operations are transactional.
	 */
	const OP_INSERT = 0x01;
	/**
	 * The update operation. This is mainly used when overriding [[transactions()]] to specify which operations are transactional.
	 */
	const OP_UPDATE = 0x02;
	/**
	 * The delete operation. This is mainly used when overriding [[transactions()]] to specify which operations are transactional.
	 */
	const OP_DELETE = 0x04;
	/**
	 * All three operations: insert, update, delete.
	 * This is a shortcut of the expression: OP_INSERT | OP_UPDATE | OP_DELETE.
	 */
	const OP_ALL = 0x07;

	/**
	 * Returns the database connection used by this AR class.
	 * By default, the "db" application component is used as the database connection.
	 * You may override this method if you want to use a different database connection.
	 * @return Connection the database connection used by this AR class.
	 */
	public static function getDb()
	{
		return \Yii::$app->getDb();
	}

	/**
	 * Creates an [[ActiveQuery]] instance with a given SQL statement.
	 *
	 * Note that because the SQL statement is already specified, calling additional
	 * query modification methods (such as `where()`, `order()`) on the created [[ActiveQuery]]
	 * instance will have no effect. However, calling `with()`, `asArray()` or `indexBy()` is
	 * still fine.
	 *
	 * Below is an example:
	 *
	 * ~~~
	 * $customers = Customer::findBySql('SELECT * FROM tbl_customer')->all();
	 * ~~~
	 *
	 * @param string $sql the SQL statement to be executed
	 * @param array $params parameters to be bound to the SQL statement during execution.
	 * @return ActiveQuery the newly created [[ActiveQuery]] instance
	 */
	public static function findBySql($sql, $params = [])
	{
		$query = static::createQuery();
		$query->sql = $sql;
		return $query->params($params);
	}

	/**
	 * Updates the whole table using the provided attribute values and conditions.
	 * For example, to change the status to be 1 for all customers whose status is 2:
	 *
	 * ~~~
	 * Customer::updateAll(['status' => 1], 'status = 2');
	 * ~~~
	 *
	 * @param array $attributes attribute values (name-value pairs) to be saved into the table
	 * @param string|array $condition the conditions that will be put in the WHERE part of the UPDATE SQL.
	 * Please refer to [[Query::where()]] on how to specify this parameter.
	 * @param array $params the parameters (name => value) to be bound to the query.
	 * @return integer the number of rows updated
	 */
	public static function updateAll($attributes, $condition = '', $params = [])
	{
		$command = static::getDb()->createCommand();
		$command->update(static::tableName(), $attributes, $condition, $params);
		return $command->execute();
	}

	/**
	 * Updates the whole table using the provided counter changes and conditions.
	 * For example, to increment all customers' age by 1,
	 *
	 * ~~~
	 * Customer::updateAllCounters(['age' => 1]);
	 * ~~~
	 *
	 * @param array $counters the counters to be updated (attribute name => increment value).
	 * Use negative values if you want to decrement the counters.
	 * @param string|array $condition the conditions that will be put in the WHERE part of the UPDATE SQL.
	 * Please refer to [[Query::where()]] on how to specify this parameter.
	 * @param array $params the parameters (name => value) to be bound to the query.
	 * Do not name the parameters as `:bp0`, `:bp1`, etc., because they are used internally by this method.
	 * @return integer the number of rows updated
	 */
	public static function updateAllCounters($counters, $condition = '', $params = [])
	{
		$n = 0;
		foreach ($counters as $name => $value) {
			$counters[$name] = new Expression("[[$name]]+:bp{$n}", [":bp{$n}" => $value]);
			$n++;
		}
		$command = static::getDb()->createCommand();
		$command->update(static::tableName(), $counters, $condition, $params);
		return $command->execute();
	}

	/**
	 * Deletes rows in the table using the provided conditions.
	 * WARNING: If you do not specify any condition, this method will delete ALL rows in the table.
	 *
	 * For example, to delete all customers whose status is 3:
	 *
	 * ~~~
	 * Customer::deleteAll('status = 3');
	 * ~~~
	 *
	 * @param string|array $condition the conditions that will be put in the WHERE part of the DELETE SQL.
	 * Please refer to [[Query::where()]] on how to specify this parameter.
	 * @param array $params the parameters (name => value) to be bound to the query.
	 * @return integer the number of rows deleted
	 */
	public static function deleteAll($condition = '', $params = [])
	{
		$command = static::getDb()->createCommand();
		$command->delete(static::tableName(), $condition, $params);
		return $command->execute();
	}

	/**
	 * Creates an [[ActiveQuery]] instance.
	 *
	 * This method is called by [[find()]], [[findBySql()]] to start a SELECT query.
	 * You may override this method to return a customized query (e.g. `CustomerQuery` specified
	 * written for querying `Customer` purpose.)
	 *
	 * You may also define default conditions that should apply to all queries unless overridden:
	 *
	 * ```php
	 * public static function createQuery()
	 * {
	 *     return parent::createQuery()->where(['deleted' => false]);
	 * }
	 * ```
	 *
	 * Note that all queries should use [[Query::andWhere()]] and [[Query::orWhere()]] to keep the
	 * default condition. Using [[Query::where()]] will override the default condition.
	 *
	 * @return ActiveQuery the newly created [[ActiveQuery]] instance.
	 */
	public static function createQuery()
	{
		return new ActiveQuery(['modelClass' => get_called_class()]);
	}

	/**
	 * Declares the name of the database table associated with this AR class.
	 * By default this method returns the class name as the table name by calling [[Inflector::camel2id()]]
	 * with prefix [[Connection::tablePrefix]]. For example if [[Connection::tablePrefix]] is 'tbl_',
	 * 'Customer' becomes 'tbl_customer', and 'OrderItem' becomes 'tbl_order_item'. You may override this method
	 * if the table is not named after this convention.
	 * @return string the table name
	 */
	public static function tableName()
	{
		return '{{%' . Inflector::camel2id(StringHelper::basename(get_called_class()), '_') . '}}';
	}

	/**
	 * Returns the schema information of the DB table associated with this AR class.
	 * @return TableSchema the schema information of the DB table associated with this AR class.
	 * @throws InvalidConfigException if the table for the AR class does not exist.
	 */
	public static function getTableSchema()
	{
		$schema = static::getDb()->getTableSchema(static::tableName());
		if ($schema !== null) {
			return $schema;
		} else {
			throw new InvalidConfigException("The table does not exist: " . static::tableName());
		}
	}

	/**
	 * Returns the primary key name(s) for this AR class.
	 * The default implementation will return the primary key(s) as declared
	 * in the DB table that is associated with this AR class.
	 *
	 * If the DB table does not declare any primary key, you should override
	 * this method to return the attributes that you want to use as primary keys
	 * for this AR class.
	 *
	 * Note that an array should be returned even for a table with single primary key.
	 *
	 * @return string[] the primary keys of the associated database table.
	 */
	public static function primaryKey()
	{
		return static::getTableSchema()->primaryKey;
	}

	/**
	 * Returns the list of all attribute names of the model.
	 * The default implementation will return all column names of the table associated with this AR class.
	 * @return array list of attribute names.
	 */
	public function attributes()
	{
		return array_keys(static::getTableSchema()->columns);
	}

	/**
	 * Declares which DB operations should be performed within a transaction in different scenarios.
	 * The supported DB operations are: [[OP_INSERT]], [[OP_UPDATE]] and [[OP_DELETE]],
	 * which correspond to the [[insert()]], [[update()]] and [[delete()]] methods, respectively.
	 * By default, these methods are NOT enclosed in a DB transaction.
	 *
	 * In some scenarios, to ensure data consistency, you may want to enclose some or all of them
	 * in transactions. You can do so by overriding this method and returning the operations
	 * that need to be transactional. For example,
	 *
	 * ~~~
	 * return [
	 *     'admin' => self::OP_INSERT,
	 *     'api' => self::OP_INSERT | self::OP_UPDATE | self::OP_DELETE,
	 *     // the above is equivalent to the following:
	 *     // 'api' => self::OP_ALL,
	 *
	 * ];
	 * ~~~
	 *
	 * The above declaration specifies that in the "admin" scenario, the insert operation ([[insert()]])
	 * should be done in a transaction; and in the "api" scenario, all the operations should be done
	 * in a transaction.
	 *
	 * @return array the declarations of transactional operations. The array keys are scenarios names,
	 * and the array values are the corresponding transaction operations.
	 */
	public function transactions()
	{
		return [];
	}

	/**
	 * Creates an [[ActiveRelation]] instance.
	 * This method is called by [[hasOne()]] and [[hasMany()]] to create a relation instance.
	 * You may override this method to return a customized relation.
	 * @param array $config the configuration passed to the ActiveRelation class.
	 * @return ActiveRelation the newly created [[ActiveRelation]] instance.
	 */
	public static function createRelation($config = [])
	{
		return new ActiveRelation($config);
	}

	/**
	 * @inheritdoc
	 */
	public static function create($row)
	{
		$record = static::instantiate($row);
		$columns = array_flip($record->attributes());
		$schema = static::getTableSchema();
		foreach ($row as $name => $value) {
			if (isset($columns[$name])) {
				$record->setAttribute($name, $schema->getColumn($name)->typecast($value));
			} else {
				$record->$name = $value;
			}
		}
		$record->setOldAttributes($record->getAttributes());
		return $record;
	}

	/**
	 * Inserts a row into the associated database table using the attribute values of this record.
	 *
	 * This method performs the following steps in order:
	 *
	 * 1. call [[beforeValidate()]] when `$runValidation` is true. If validation
	 *    fails, it will skip the rest of the steps;
	 * 2. call [[afterValidate()]] when `$runValidation` is true.
	 * 3. call [[beforeSave()]]. If the method returns false, it will skip the
	 *    rest of the steps;
	 * 4. insert the record into database. If this fails, it will skip the rest of the steps;
	 * 5. call [[afterSave()]];
	 *
	 * In the above step 1, 2, 3 and 5, events [[EVENT_BEFORE_VALIDATE]],
	 * [[EVENT_BEFORE_INSERT]], [[EVENT_AFTER_INSERT]] and [[EVENT_AFTER_VALIDATE]]
	 * will be raised by the corresponding methods.
	 *
	 * Only the [[dirtyAttributes|changed attribute values]] will be inserted into database.
	 *
	 * If the table's primary key is auto-incremental and is null during insertion,
	 * it will be populated with the actual value after insertion.
	 *
	 * For example, to insert a customer record:
	 *
	 * ~~~
	 * $customer = new Customer;
	 * $customer->name = $name;
	 * $customer->email = $email;
	 * $customer->insert();
	 * ~~~
	 *
	 * @param boolean $runValidation whether to perform validation before saving the record.
	 * If the validation fails, the record will not be inserted into the database.
	 * @param array $attributes list of attributes that need to be saved. Defaults to null,
	 * meaning all attributes that are loaded from DB will be saved.
	 * @return boolean whether the attributes are valid and the record is inserted successfully.
	 * @throws \Exception in case insert failed.
	 */
	public function insert($runValidation = true, $attributes = null)
	{
		if ($runValidation && !$this->validate($attributes)) {
			return false;
		}
		$db = static::getDb();
		if ($this->isTransactional(self::OP_INSERT) && $db->getTransaction() === null) {
			$transaction = $db->beginTransaction();
			try {
				$result = $this->insertInternal($attributes);
				if ($result === false) {
					$transaction->rollback();
				} else {
					$transaction->commit();
				}
			} catch (\Exception $e) {
				$transaction->rollback();
				throw $e;
			}
		} else {
			$result = $this->insertInternal($attributes);
		}
		return $result;
	}

	/**
	 * @see ActiveRecord::insert()
	 */
	private function insertInternal($attributes = null)
	{
		if (!$this->beforeSave(true)) {
			return false;
		}
		$values = $this->getDirtyAttributes($attributes);
		if (empty($values)) {
			foreach ($this->getPrimaryKey(true) as $key => $value) {
				$values[$key] = $value;
			}
		}
		$db = static::getDb();
		$command = $db->createCommand()->insert($this->tableName(), $values);
		if (!$command->execute()) {
			return false;
		}
		$table = $this->getTableSchema();
		if ($table->sequenceName !== null) {
			foreach ($table->primaryKey as $name) {
				if ($this->getAttribute($name) === null) {
					$id = $db->getLastInsertID($table->sequenceName);
					$this->setAttribute($name, $id);
					$this->setOldAttribute($name, $id);
					break;
				}
			}
		}
		foreach ($values as $name => $value) {
			$this->setOldAttribute($name, $value);
		}
		$this->afterSave(true);
		return true;
	}

	/**
	 * Saves the changes to this active record into the associated database table.
	 *
	 * This method performs the following steps in order:
	 *
	 * 1. call [[beforeValidate()]] when `$runValidation` is true. If validation
	 *    fails, it will skip the rest of the steps;
	 * 2. call [[afterValidate()]] when `$runValidation` is true.
	 * 3. call [[beforeSave()]]. If the method returns false, it will skip the
	 *    rest of the steps;
	 * 4. save the record into database. If this fails, it will skip the rest of the steps;
	 * 5. call [[afterSave()]];
	 *
	 * In the above step 1, 2, 3 and 5, events [[EVENT_BEFORE_VALIDATE]],
	 * [[EVENT_BEFORE_UPDATE]], [[EVENT_AFTER_UPDATE]] and [[EVENT_AFTER_VALIDATE]]
	 * will be raised by the corresponding methods.
	 *
	 * Only the [[dirtyAttributes|changed attribute values]] will be saved into database.
	 *
	 * For example, to update a customer record:
	 *
	 * ~~~
	 * $customer = Customer::find($id);
	 * $customer->name = $name;
	 * $customer->email = $email;
	 * $customer->update();
	 * ~~~
	 *
	 * Note that it is possible the update does not affect any row in the table.
	 * In this case, this method will return 0. For this reason, you should use the following
	 * code to check if update() is successful or not:
	 *
	 * ~~~
	 * if ($this->update() !== false) {
	 *     // update successful
	 * } else {
	 *     // update failed
	 * }
	 * ~~~
	 *
	 * @param boolean $runValidation whether to perform validation before saving the record.
	 * If the validation fails, the record will not be inserted into the database.
	 * @param array $attributes list of attributes that need to be saved. Defaults to null,
	 * meaning all attributes that are loaded from DB will be saved.
	 * @return integer|boolean the number of rows affected, or false if validation fails
	 * or [[beforeSave()]] stops the updating process.
	 * @throws StaleObjectException if [[optimisticLock|optimistic locking]] is enabled and the data
	 * being updated is outdated.
	 * @throws \Exception in case update failed.
	 */
	public function update($runValidation = true, $attributes = null)
	{
		if ($runValidation && !$this->validate($attributes)) {
			return false;
		}
		$db = static::getDb();
		if ($this->isTransactional(self::OP_UPDATE) && $db->getTransaction() === null) {
			$transaction = $db->beginTransaction();
			try {
				$result = $this->updateInternal($attributes);
				if ($result === false) {
					$transaction->rollback();
				} else {
					$transaction->commit();
				}
			} catch (\Exception $e) {
				$transaction->rollback();
				throw $e;
			}
		} else {
			$result = $this->updateInternal($attributes);
		}
		return $result;
	}

	/**
	 * Deletes the table row corresponding to this active record.
	 *
	 * This method performs the following steps in order:
	 *
	 * 1. call [[beforeDelete()]]. If the method returns false, it will skip the
	 *    rest of the steps;
	 * 2. delete the record from the database;
	 * 3. call [[afterDelete()]].
	 *
	 * In the above step 1 and 3, events named [[EVENT_BEFORE_DELETE]] and [[EVENT_AFTER_DELETE]]
	 * will be raised by the corresponding methods.
	 *
	 * @return integer|boolean the number of rows deleted, or false if the deletion is unsuccessful for some reason.
	 * Note that it is possible the number of rows deleted is 0, even though the deletion execution is successful.
	 * @throws StaleObjectException if [[optimisticLock|optimistic locking]] is enabled and the data
	 * being deleted is outdated.
	 * @throws \Exception in case delete failed.
	 */
	public function delete()
	{
		$db = static::getDb();
		$transaction = $this->isTransactional(self::OP_DELETE) && $db->getTransaction() === null ? $db->beginTransaction() : null;
		try {
			$result = false;
			if ($this->beforeDelete()) {
				// we do not check the return value of deleteAll() because it's possible
				// the record is already deleted in the database and thus the method will return 0
				$condition = $this->getOldPrimaryKey(true);
				$lock = $this->optimisticLock();
				if ($lock !== null) {
					$condition[$lock] = $this->$lock;
				}
				$result = $this->deleteAll($condition);
				if ($lock !== null && !$result) {
					throw new StaleObjectException('The object being deleted is outdated.');
				}
				$this->setOldAttributes(null);
				$this->afterDelete();
			}
			if ($transaction !== null) {
				if ($result === false) {
					$transaction->rollback();
				} else {
					$transaction->commit();
				}
			}
		} catch (\Exception $e) {
			if ($transaction !== null) {
				$transaction->rollback();
			}
			throw $e;
		}
		return $result;
	}

	/**
	 * Returns a value indicating whether the given active record is the same as the current one.
	 * The comparison is made by comparing the table names and the primary key values of the two active records.
	 * If one of the records [[isNewRecord|is new]] they are also considered not equal.
	 * @param ActiveRecord $record record to compare to
	 * @return boolean whether the two active records refer to the same row in the same database table.
	 */
	public function equals($record)
	{
		if ($this->isNewRecord || $record->isNewRecord) {
			return false;
		}
		return $this->tableName() === $record->tableName() && $this->getPrimaryKey() === $record->getPrimaryKey();
	}

	/**
	 * Returns a value indicating whether the specified operation is transactional in the current [[scenario]].
	 * @param integer $operation the operation to check. Possible values are [[OP_INSERT]], [[OP_UPDATE]] and [[OP_DELETE]].
	 * @return boolean whether the specified operation is transactional in the current [[scenario]].
	 */
	public function isTransactional($operation)
	{
		$scenario = $this->getScenario();
		$transactions = $this->transactions();
		return isset($transactions[$scenario]) && ($transactions[$scenario] & $operation);
	}
}
