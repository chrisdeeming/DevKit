<?php

class XenDevTools_Listener
{
	protected static $_templateDevToolsPhrase = null;

	protected static $_templateNames = array();
	protected static $_templateManModel = null;
	protected static $_templateModel = null;

	public static function navigationTabs(array &$extraTabs, $selectedTabId)
	{
		if (XenForo_Visitor::getInstance()->isSuperAdmin())
		{
			$extraTabs['xendevtools'] = array(
				'title' => new XenForo_Phrase('xendevtools_development'),
				'href' => XenForo_Link::buildPublicLink('full:dev'),
				'position' => 'end',
				'linksTemplate' => 'xendevtools_tab_links'
			);
		}
	}

	public static function extendAddOnController($class, array &$extend)
	{
		$extend[] = 'XenDevTools_ControllerAdmin_AddOn';
	}

	public static function extendLanguageController($class, array &$extend)
	{
		$extend[] = 'XenDevTools_ControllerAdmin_Language';
	}

	public static function extendPhraseController($class, array &$extend)
	{
		$extend[] = 'XenDevTools_ControllerAdmin_Phrase';
	}

	public static function extendAddOnDataWriter($class, array &$extend)
	{
		$extend[] = 'XenDevTools_DataWriter_AddOn';
	}

	public static function extendAdminTemplateDataWriter($class, array &$extend)
	{
		$extend[] = 'XenDevTools_DataWriter_AdminTemplate';
	}

	public static function extendTemplateDataWriter($class, array &$extend)
	{
		$extend[] = 'XenDevTools_DataWriter_Template';
	}

	public static function extendAddOnModel($class, array &$extend)
	{
		$extend[] = 'XenDevTools_Model_AddOn';
	}

	public static function extendAdminTemplateModel($class, array &$extend)
	{
		$extend[] = 'XenDevTools_Model_AdminTemplate';
	}

	public static function extendContentTypeModel($class, array &$extend)
	{
		$extend[] = 'XenDevTools_Model_ContentType';
	}

	public static function extendPhraseModel($class, array &$extend)
	{
		$extend[] = 'XenDevTools_Model_Phrase';
	}

	public static function templateCreate(&$templateName, array &$params, XenForo_Template_Abstract $template)
	{
		if (XenForo_Visitor::getInstance()->isSuperAdmin())
		{
			if (self::$_templateDevToolsPhrase === null)
			{
				$debugMode = XenForo_Application::debugMode();
				$defaultAddOn = XenForo_Application::get('config')->development->default_addon;

				$debug = $debugMode ? 'debug_on_' : 'debug_off_';
				$addOn = $defaultAddOn ? 'default_addon_on' : 'default_addon_off';

				$phraseKey = 'xendevtools_' . $debug . $addOn;

				self::$_templateDevToolsPhrase = new XenForo_Phrase($phraseKey, array('addon' => $defaultAddOn));
			}

			if (!isset($params['debugModePhrase']))
			{
				$params['debugModePhrase'] = self::$_templateDevToolsPhrase;
			}
		}
	}

	public static function initDependencies(XenForo_Dependencies_Abstract $dependencies, array $data)
	{
		if (XenForo_Application::get('options')->templateManFiles && ($dependencies instanceof XenForo_Dependencies_Public))
		{
			$files = glob(XenForo_Application::getInstance()->getRootDir() . DIRECTORY_SEPARATOR . 'templates/public/0/*', GLOB_NOSORT);
			clearstatcache();
			array_multisort(array_map('filemtime', $files), SORT_NUMERIC, SORT_DESC, $files);

			$filesKeyed = array();
			foreach ($files AS $file)
			{
				$pathParts = pathinfo($file);

				$templateName = $pathParts['filename'];
				if ($pathParts['extension'] == 'css')
				{
					$templateName .= ".$pathParts[extension]";
				}

				$filesKeyed[$templateName] = array(
					'last_edit_date' => filemtime($file),
					'template' => file_get_contents($file)
				);
			}

			$templateManModel = self::_getTemplateManModel();
			$templateModel = self::_getTemplateModel();

			$templates = $templateManModel->getTemplates();

			$templatesToDelete = array_diff_key($templates, $filesKeyed);
			$templateManModel->deleteTemplates($templatesToDelete);

			$templatesToUpdate = array();
			$templatesToCreate = array();

			foreach ($filesKeyed AS $templateName => $template)
			{
				$createTemplate = false;
				$updateTemplate = false;

				if (!isset($templates[$templateName]))
				{
					$createTemplate = true;
				}
				else
				{
					if ($template['last_edit_date'] > $templates[$templateName]['last_edit_date'])
					{
						$updateTemplate = true;
						$template['template_id'] = $templates[$templateName]['template_id'];
					}
				}

				if ($createTemplate || $updateTemplate)
				{
					$template['template'] = $templateModel->replaceLinkRelWithIncludes($template['template']);

					if ($createTemplate)
					{
						$templatesToCreate[$templateName] = $template;
					}
					elseif ($updateTemplate)
					{
						$templatesToUpdate[$templateName] = $template;
					}
				}
			}

			$templateManModel->updateTemplates($templatesToUpdate);
			$templateManModel->createTemplates($templatesToCreate);
		}

		if (XenForo_Application::get('options')->templateManFiles && ($dependencies instanceof XenForo_Dependencies_Admin))
		{
			$files = glob(XenForo_Application::getInstance()->getRootDir() . DIRECTORY_SEPARATOR . 'templates/admin/*', GLOB_NOSORT);
			array_multisort(array_map('filemtime', $files), SORT_NUMERIC, SORT_DESC, $files);

			$filesKeyed = array();
			foreach ($files AS $file)
			{
				$pathParts = pathinfo($file);

				$templateName = $pathParts['filename'];
				if ($pathParts['extension'] == 'css')
				{
					$templateName .= ".$pathParts[extension]";
				}

				$filesKeyed[$templateName] = array(
					'last_edit_date' => filemtime($file),
					'template' => file_get_contents($file)
				);
			}

			$templateManModel = self::_getTemplateManModel();
			$templateModel = self::_getTemplateModel();

			$templates = $templateManModel->getAdminTemplates();

			$templatesToDelete = array_diff_key($templates, $filesKeyed);
			$templateManModel->deleteTemplates($templatesToDelete, 'admin');

			$templatesToUpdate = array();
			$templatesToCreate = array();

			foreach ($filesKeyed AS $templateName => $template)
			{
				$createTemplate = false;
				$updateTemplate = false;

				if (!isset($templates[$templateName]))
				{
					$createTemplate = true;
				}
				else
				{
					if ($template['last_edit_date'] > $templates[$templateName]['last_edit_date'])
					{
						$updateTemplate = true;
						$template['template_id'] = $templates[$templateName]['template_id'];
					}
				}

				if ($createTemplate || $updateTemplate)
				{
					$template['template'] = $templateModel->replaceLinkRelWithIncludes($template['template']);

					if ($createTemplate)
					{
						$templatesToCreate[$templateName] = $template;
					}
					elseif ($updateTemplate)
					{
						$templatesToUpdate[$templateName] = $template;
					}
				}
			}

			$templateManModel->updateAdminTemplates($templatesToUpdate);
			$templateManModel->createAdminTemplates($templatesToCreate);
		}
	}

	/**
	 * @return XenDevTools_Model_Template
	 */
	protected static function _getTemplateManModel()
	{
		if (self::$_templateManModel === null)
		{
			self::$_templateManModel = XenForo_Model::create('XenDevTools_Model_Template');
		}

		return self::$_templateManModel;
	}

	/**
	 * @return XenForo_Model_Template
	 */
	protected static function _getTemplateModel()
	{
		if (self::$_templateModel === null)
		{
			self::$_templateModel = XenForo_Model::create('XenForo_Model_Template');
		}

		return self::$_templateModel;
	}
}

