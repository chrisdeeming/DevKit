<?php

class XenDevTools_DataWriter_AdminTemplate extends XFCP_XenDevTools_DataWriter_AdminTemplate
{
	protected function _getFields()
	{
		$parent = parent::_getFields();

		$parent['xf_admin_template']['last_edit_date'] = array('type' => self::TYPE_UINT, 'default' => XenForo_Application::$time);

		return $parent;
	}

	protected function _preSave()
	{
		if ($this->isInsert())
		{
			$this->set('last_edit_date', XenForo_Application::$time);
		}
	}

	/**
	 * Post-save handler.
	 */
	protected function _postSave()
	{
		parent::_postSave();

		if (XenForo_Application::get('options')->templateManFiles)
		{
			$template = $this->getMergedData();
			$template['template'] = $this->getModelFromCache('XenForo_Model_Template')->replaceIncludesWithLinkRel($template['template']);

			XenDevTools_FileHandler::save($this->get('title'), $template, 'admin');
		}

		$db = $this->_db;
		$db->update('xf_admin_template', array('last_edit_date' => XenForo_Application::$time), 'template_id = ' . $db->quote($this->get('template_id')));
	}

	/**
	 * Post-delete handler.
	 */
	protected function _postDelete()
	{
		if (XenForo_Application::get('options')->templateManFiles)
		{
			XenDevTools_FileHandler::delete($this->get('title'), 'admin');
		}

		parent::_postDelete();
	}
}