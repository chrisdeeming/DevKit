<?php

class XenDevTools_Install
{
	protected static $_db = null;
	
	protected static function _canBeInstalled(&$error)
	{
		if (XenForo_Application::$versionId < 1020070)
		{
			$error = 'This add-on requires XenForo 1.2.0 or higher.';
			return false;
		}

		return true;
	}

	public static function installer($addOn)
	{
		if (!self::_canBeInstalled($error))
		{
			throw new XenForo_Exception($error, true);
		}

		self::stepAlters();
	}

	public static function stepAlters()
	{
		$db = self::_getDb();

		foreach (self::_getAlters() AS $alterSql)
		{
			try
			{
				$db->query($alterSql);
			}
			catch (Zend_Db_Exception $e) {}
		}
	}

	protected static function _getAlters()
	{
		$alters = array();
		
		$alters[] = "
			ALTER TABLE xf_admin_template
				ADD COLUMN last_edit_date INT(10) UNSIGNED DEFAULT 0  NOT NULL
		";

		return $alters;
	}

	/**
	 * @return Zend_Db_Adapter_Abstract
	 */
	protected static function _getDb()
	{
		if (!self::$_db)
		{
			self::$_db = XenForo_Application::getDb();
		}

		return self::$_db;
	}
}