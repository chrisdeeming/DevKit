<?php

class XenDevTools_DataWriter_Template extends XFCP_XenDevTools_DataWriter_Template
{
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

			XenDevTools_FileHandler::save($this->get('title'), $template);
		}
	}

	/**
	 * Post-delete handler.
	 */
	protected function _postDelete()
	{
		if (XenForo_Application::get('options')->templateManFiles)
		{
			XenDevTools_FileHandler::delete($this->get('title'));
		}

		parent::_postDelete();
	}
}