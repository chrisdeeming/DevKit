<?php

class XenDevTools_DataWriter_AddOn extends XFCP_XenDevTools_DataWriter_AddOn
{
	/**
	 * Post-delete handler.
	 */
	protected function _postDelete()
	{
		if (XenForo_Application::get('options')->templateManFiles)
		{
			$this->getModelFromCache('XenDevTools_Model_Template')->deleteTemplateFilesForAddOn($this->get('addon_id'));
		}

		parent::_postDelete();
	}
}