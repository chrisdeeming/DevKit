<?php

class XenDevTools_ControllerHelper_Tools extends XenForo_ControllerHelper_Abstract
{
	public function safeUnserialize($serializedData, $returnArray = true)
	{
		$unserializedData = @unserialize($serializedData);
		if (!is_array($unserializedData))
		{
			if ($returnArray)
			{
				$unserializedData = array();
			}
		}

		return $unserializedData;
	}

	public function safeJsonDecode($encodedData, $returnArray = true)
	{
		$decodedData = @json_decode($encodedData, true);
		if (!is_array($decodedData))
		{
			if ($returnArray)
			{
				$decodedData = array();
			}
		}

		return $decodedData;
	}

	public function convertTimestamp($timestamp)
	{
		if (!is_int($timestamp))
		{
			$timestamp = 0;
		}
		return XenForo_Locale::dateTime($timestamp, 'absolute');
	}

	public function executeQuery($query, $fetchMode, $key)
	{
		if ($fetchMode == 'fetchAllKeyed')
		{
			if (!$key)
			{
				return array();
			}

			try
			{
				$model = new XenForo_Model_User();
				return $model->$fetchMode($query, $key);
			}
			catch (Zend_Db_Exception $e)
			{
				$phrase = new XenForo_Phrase('xendevtools_verify_valid_query');
				return $phrase->render();
			}
		}
		elseif ($fetchMode != 'query')
		{
			try
			{
				$db = XenForo_Application::getDb();
				$query = $db->$fetchMode($query);

				return $query;
			}
			catch (Zend_Db_Exception $e)
			{
				$phrase = new XenForo_Phrase('xendevtools_verify_valid_query');
				return $phrase->render();
			}
		}

		try
		{
			$db = XenForo_Application::getDb();
			$query = $db->query($query);

			if ($fetchMode == 'query')
			{
				$phrase = new XenForo_Phrase('xendevtools_affects_x_rows', array('rows' => $query->rowCount()));
				return $phrase->render();
			}
			else
			{
				return $query;
			}
		}
		catch (Zend_Db_Exception $e)
		{
			$phrase = new XenForo_Phrase('xendevtools_verify_valid_query');
			return $phrase->render();
		}
	}

	public function prepareDataForDisplay($data)
	{
		$dataDump = Zend_Debug::dump($data, null, false);

		return $dataDump;
	}
}