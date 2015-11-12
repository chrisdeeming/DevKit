<?php

class XenDevTools_Option_TemplateFiles
{
	/**
	 * Writes uncompiled templates to the file system.
	 *
	 * @param boolean $option
	 * @param XenForo_DataWriter $dw
	 * @param string $fieldName
	 *
	 * @return boolean
	 */
	public static function verifyOption(&$option, XenForo_DataWriter $dw, $fieldName)
	{
		if ($dw->isInsert())
		{
			return true; // don't need to do anything
		}

		if ($option)
		{
			XenForo_Model::create('XenDevTools_Model_Template')->writeTemplateFiles(false, false);
		}
		else
		{
			XenForo_Model::create('XenDevTools_Model_Template')->deleteTemplateFiles();
		}

		return true;
	}
}