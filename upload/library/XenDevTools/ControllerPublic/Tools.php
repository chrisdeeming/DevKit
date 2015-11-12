<?php

class XenDevTools_ControllerPublic_Tools extends XenForo_ControllerPublic_Abstract
{
	protected function _preDispatch($action)
	{
		$visitor = XenForo_Visitor::getInstance();
		if (!$visitor->isSuperAdmin())
		{
			throw new XenForo_Exception('xendevtools_super_admin_required', true);
		}
	}

	public function actionIndex()
	{
		$viewParams = array();

		return $this->responseView(
			'XenDevTools_ViewPublic_Index',
			'xendevtools_dev_index',
			$viewParams
		);
	}

	public function actionProcess()
	{
		$toolsHelper = $this->_getToolsHelper();

		$tool = $this->_input->filterSingle('tool', XenForo_Input::STRING);

		switch ($tool)
		{
			case 'unserialize':

				$string = $this->_input->filterSingle($tool, XenForo_Input::STRING);
				$result = $toolsHelper->safeUnserialize($string);

				break;

			case 'json_decode':

				$string = $this->_input->filterSingle($tool, XenForo_Input::STRING);
				$result = $toolsHelper->safeJsonDecode($string);

				break;

			case 'timestamp_convert':

				$timestamp = $this->_input->filterSingle($tool, XenForo_Input::UINT);
				$result = $toolsHelper->convertTimestamp($timestamp);

				break;

			case 'query':

				$query = $this->_input->filterSingle($tool, XenForo_Input::STRING);
				$fetchMode = '';
				$key = '';

				$format = $this->_input->filterSingle('format', XenForo_Input::STRING);
				if ($format)
				{
					$type = $this->_input->filterSingle('fetch_type', XenForo_Input::STRING);

					$fetchMode = 'fetch' . utf8_ucwords($type);
					if ($fetchMode == 'fetchAllKeyed')
					{
						$key = $this->_input->filterSingle('key', XenForo_Input::STRING);
					}
				}
				else
				{
					$fetchMode = 'query';
				}

				$result = $toolsHelper->executeQuery($query, $fetchMode, $key);

				break;
		}

		$viewParams = array(
			'result' => $toolsHelper->prepareDataForDisplay($result)
		);

		if ($this->_noRedirect())
		{
			return $this->responseView(
				'XenDevTools_ViewPublic_Processed',
				'xendevtools_processed',
				$viewParams
			);
		}
	}

	public function actionSwitch()
	{
		if ($this->isConfirmedPost())
		{
			$debugTrue = '$config[\'debug\'] = true;';
			$debugFalse = '$config[\'debug\'] = false;';

			$debugMode = $this->_input->filterSingle('debug_mode', XenForo_Input::BOOLEAN);
			if ($debugMode)
			{
				$configFile = file_get_contents('library/config.php');
				try
				{
					if (strpos($configFile, $debugFalse))
					{
						$configFile = str_replace($debugFalse, $debugTrue, $configFile);
					}
					elseif (!strpos($configFile, $debugTrue) && !strpos($configFile, $debugFalse))
					{
						$configFile .= "\n\n$debugTrue";
					}
					file_put_contents('library/config.php', $configFile);
				}
				catch (Exception $e) {}
			}
			else
			{
				$configFile = file_get_contents('library/config.php');
				try
				{
					if (strpos($configFile, $debugTrue))
					{
						$configFile = str_replace($debugTrue, '', $configFile);
					}
					file_put_contents('library/config.php', $configFile);
				}
				catch (Exception $e) {}
			}

			$addOnId = $this->_input->filterSingle('addon_id', XenForo_Input::STRING);
			if ($addOnId)
			{
				try
				{
					$configFile = file_get_contents('library/config.php');
					if ($addOn = XenForo_Application::get('config')->development->default_addon)
					{
						$configFile = str_replace($addOn, $addOnId, $configFile);
					}
					else
					{
						$configFile .= "\n\n" . '$config[\'development\'][\'default_addon\'] = \'' . $addOnId . '\';';
					}
					file_put_contents('library/config.php', $configFile);
				}
				catch (Exception $e) {}
			}
			else
			{
				try
				{
					$configFile = file_get_contents('library/config.php');
					if ($addOn = XenForo_Application::get('config')->development->default_addon)
					{
						$configFile = str_replace('$config[\'development\'][\'default_addon\'] = \'' . $addOn . '\';', '', $configFile);
					}
					file_put_contents('library/config.php', $configFile);
				}
				catch (Exception $e) {}
			}

			if (function_exists('accelerator_reset'))
			{
				accelerator_reset();
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->getDynamicRedirect()
			);
		}

		$viewParams = array(
			'debugMode' => XenForo_Application::debugMode(),
			'defaultAddOn' => XenForo_Application::get('config')->development->default_addon,
			'addOnOptions' => XenForo_Model::create('XenForo_Model_AddOn')->getAddOnOptionsList()
		);

		return $this->responseView(
			'XenDevTools_ViewPublic_Switch',
			'xendevtools_switch',
			$viewParams
		);
	}

	public function actionSwitchComplete()
	{
		$redirect = $this->_input->filterSingle('redirect', XenForo_Input::STRING);

		return $this->responseView(
			'XenDevTools_ViewPublic_SwitchComplete',
			'xendevtools_switch_complete',
			array('redirect' => $redirect)
		);
	}

	/**
	 * @return XenDevTools_ControllerHelper_Tools
	 */
	protected function _getToolsHelper()
	{
		return $this->getHelper('XenDevTools_ControllerHelper_Tools');
	}
}