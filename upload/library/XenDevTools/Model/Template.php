<?php

class XenDevTools_Model_Template extends XenForo_Model
{
	/**
	 * Writes out the complete set of (uncompiled) template files to the file system
	 *
	 * @param boolean Enable the templateFiles option after completion.
	 * @param boolean Manipulate the option values to ensure failsafe operation.
	 */
	public function writeTemplateFiles($enable = false, $handleOptions = true)
	{
		if ($handleOptions && XenForo_Application::get('options')->templateManFiles)
		{
			$this->getModelFromCache('XenForo_Model_Option')->updateOptions(array('templateManFiles' => 0));
		}

		$this->deleteTemplateFiles();

		$templates = $this->_getDb()->query('SELECT * FROM xf_template WHERE style_id = 0');
		while ($template = $templates->fetch())
		{
			$template['template'] = $this->getModelFromCache('XenForo_Model_Template')->replaceIncludesWithLinkRel($template['template']);
			XenDevTools_FileHandler::save($template['title'], $template);
		}

		$adminTemplates = $this->_getDb()->query('SELECT * FROM xf_admin_template');
		while ($adminTemplate = $adminTemplates->fetch())
		{
			$adminTemplate['template'] = $this->getModelFromCache('XenForo_Model_Template')->replaceIncludesWithLinkRel($adminTemplate['template']);
			XenDevTools_FileHandler::save($adminTemplate['title'], $adminTemplate, 'admin');
		}

		if ($handleOptions && $enable)
		{
			$this->getModelFromCache('XenForo_Model_Option')->updateOptions(array('templateManFiles' => 1));
		}
	}

	public function getTemplates()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_template
			WHERE style_id = 0
			ORDER BY last_edit_date DESC
		', 'title');
	}

	public function getAdminTemplates()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_admin_template
			ORDER BY last_edit_date DESC
		', 'title');
	}

	public function deleteTemplateFilesForAddOn($addOnId)
	{
		$db = $this->_getDb();

		$titles = $db->fetchPairs('
			SELECT template_id, title
			FROM xf_template
			WHERE style_id = 0
				AND addon_id = ?
		', $addOnId);
		if (!$titles)
		{
			return;
		}

		XenDevTools_FileHandler::delete($titles);
	}

	public function deleteTemplates(array $templates, $type = 'public')
	{
		$count = 0;
		foreach ($templates AS $template)
		{
			$count++;

			if ($count >= 5)
			{
				break;
			}

			switch ($type)
			{
				case 'public':

					$templateDw = XenForo_DataWriter::create('XenForo_DataWriter_Template', XenForo_DataWriter::ERROR_SILENT);
					break;

				case 'admin':

					$templateDw = XenForo_DataWriter::create('XenForo_DataWriter_AdminTemplate', XenForo_DataWriter::ERROR_SILENT);
					break;
			}

			if ($templateDw->setExistingData($template['template_id']))
			{
				$templateDw->delete();
			}
		}
	}

	public function createTemplates(array $templates)
	{
		/** @var $addOnModel XenForo_Model_AddOn */
		$addOnModel = $this->getModelFromCache('XenForo_Model_AddOn');
		$addOnId = $addOnModel->getDefaultAddOnId();
		$addOn = false;
		if ($addOnId)
		{
			$addOn = $addOnModel->getAddOnById($addOnId);
		}

		$count = 0;
		foreach ($templates AS $templateName => $template)
		{
			$count++;

			if ($count >= 5)
			{
				break;
			}

			$templateDw = XenForo_DataWriter::create('XenForo_DataWriter_Template');

			$templateData = array(
				'title' => $templateName,
				'style_id' => 0,
				'template' => $template['template'],
				'last_edit_date' => $template['last_edit_date']
			);

			if ($addOn)
			{
				$templateData = $templateData + array(
					'addon_id' => $addOn['addon_id'],
					'version_id' => $addOn['version_id'],
					'version_string' => $addOn['version_string']
				);
			}

			$templateDw->bulkSet($templateData);
			$templateDw->save();
		}
	}

	public function createAdminTemplates(array $templates)
	{
		/** @var $addOnModel XenForo_Model_AddOn */
		$addOnModel = $this->getModelFromCache('XenForo_Model_AddOn');
		$addOnId = $addOnModel->getDefaultAddOnId();
		$addOn = false;
		if ($addOnId)
		{
			$addOn = $addOnModel->getAddOnById($addOnId);
		}

		$count = 0;
		foreach ($templates AS $templateName => $template)
		{
			$count++;

			if ($count >= 5)
			{
				break;
			}

			$templateDw = XenForo_DataWriter::create('XenForo_DataWriter_AdminTemplate');

			$templateData = array(
				'title' => $templateName,
				'template' => $template['template'],
				'last_edit_date' => $template['last_edit_date']
			);

			if ($addOn)
			{
				$templateData['addon_id'] = $addOn['addon_id'];
			}

			$templateDw->bulkSet($templateData);
			$templateDw->save();
		}
	}

	public function updateTemplates(array $templates)
	{
		$db = $this->_getDb();

		/** @var $templateModel XenForo_Model_Template */
		$templateModel = $this->getModelFromCache('XenForo_Model_Template');

		$count = 0;
		foreach ($templates AS $templateName => $template)
		{
			$count++;

			if ($count >= 5)
			{
				break;
			}

			try
			{
				$db->update('xf_template', $template, 'title = ' . $db->quote($templateName));
				$templateModel->reparseTemplate($template['template_id']);
			}
			catch (Zend_Db_Exception $e) { continue; }
		}
	}

	public function updateAdminTemplates(array $templates)
	{
		$db = $this->_getDb();

		/** @var $templateModel XenForo_Model_AdminTemplate */
		$templateModel = $this->getModelFromCache('XenForo_Model_AdminTemplate');

		$count = 0;
		foreach ($templates AS $templateName => $template)
		{
			$count++;

			if ($count >= 20)
			{
				break;
			}

			try
			{
				$db->update('xf_admin_template', $template, 'title = ' . $db->quote($templateName));
				$templateModel->reparseTemplate($template['template_id']);
			}
			catch (Zend_Db_Exception $e) { continue; }
		}
	}

	public function getFileNamesFromTemplateNames($templateNames = null, $templateIds = null)
	{
		return XenDevTools_FileHandler::getFileNamesFromTemplateNames($templateNames, $templateIds);
	}

	public function getFileNameFromTemplateName($templateName)
	{
		return $files = glob(XenDevTools_FileHandler::get($templateName, '*'));
	}

	public function replaceIncludesWithLinkRel($templateText)
	{
		return XenForo_Model_Template::replaceIncludesWithLinkRel($templateText);
	}

	/**
	 * Deletes the file versions of all templates
	 */
	public function deleteTemplateFiles()
	{
		XenDevTools_FileHandler::delete(null);
		XenDevTools_FileHandler::delete(null, 'admin');
	}
}