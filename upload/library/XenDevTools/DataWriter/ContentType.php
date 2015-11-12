<?php

class XenDevTools_DataWriter_ContentType extends XenForo_DataWriter
{
	/**
	 * Returns all xf_content_type fields
	 *
	 * @see library/XenForo/DataWriter/XenForo_DataWriter#_getFields()
	 */
	protected function _getFields()
	{
		return array('xf_content_type' => array(
			'content_type'	=> array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 25, 'requiredError' => 'please_enter_valid_name'),
			'addon_id'		=> array('type' => self::TYPE_STRING, 'maxLength' => 25, 'default' => ''),
			'fields'		=> array('type' => self::TYPE_SERIALIZED, 'default' => 'a:0:{}')
		));
	}

	/**
	 * Gets the actual existing data out of data that was passed in. See parent for explanation.
	 *
	 * @param mixed
	 *
	 * @return array|false
	 */
	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data, 'content_type'))
		{
			return false;
		}

		return array('xf_content_type' => $this->_getContentTypeModel()->getContentType($id));
	}

	/**
	 * Gets SQL condition to update the existing record.
	 *
	 * @return string
	 */
	protected function _getUpdateCondition($tableName)
	{
		return 'content_type = ' . $this->_db->quote($this->getExisting('content_type'));
	}

	/**
	 * Pre-save handler.
	 */
	protected function _preSave()
	{

	}

	/**
	 * Post-save handler.
	 */
	protected function _postSave()
	{
		$this->_db->update(
			'xf_content_type_field', array(
				'content_type' => $this->get('content_type')
			), $this->_getUpdateCondition('xf_content_type')
		);

		$this->_getContentTypeModel()->rebuildContentTypeCache();
	}

	/**
	 * Post-delete handler.
	 */
	protected function _postDelete()
	{
		$this->_db->delete(
			'xf_content_type_field', $this->_getUpdateCondition('xf_content_type')
		);

		$this->_getContentTypeModel()->rebuildContentTypeCache();
	}

	/**
	 * @return XenForo_Model_ContentType
	 */
	protected function _getContentTypeModel()
	{
		return $this->getModelFromCache('XenForo_Model_ContentType');
	}
}