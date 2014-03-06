<?php

class XenDevTools_Install
{
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
	}
}