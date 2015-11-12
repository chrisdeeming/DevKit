<?php

class XenDevTools_Model_AdminTemplate extends XFCP_XenDevTools_Model_AdminTemplate
{
	/**
	 * Deletes the admin templates that belong to the specified add-on.
	 *
	 * @param string $addOnId
	 */
	public function deleteAdminTemplatesForAddOn($addOnId)
	{
		$db = $this->_getDb();

		$titles = $db->fetchPairs('
			SELECT template_id, title
			FROM xf_admin_template
			WHERE addon_id = ?
		', $addOnId);
		if (!$titles)
		{
			return;
		}

		XenDevTools_FileHandler::delete($titles, 'admin');

		return parent::deleteAdminTemplatesForAddOn($addOnId);
	}
}