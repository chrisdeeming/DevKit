<?php

class XenDevTools_DataWriter_ContentTypeField extends XenForo_DataWriter
{
	/**
	 * Returns all xf_content_type fields
	 *
	 * @see library/XenForo/DataWriter/XenForo_DataWriter#_getFields()
	 */
	protected function _getFields()
	{
		return array('xf_content_type_field' => array(
			'content_type'		=> array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 25, 'requiredError' => 'content_type_please_enter_valid_name'),
			'field_name'		=> array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50, 'requiredError' => 'content_type_please_enter_valid_field_name'),
			'field_value'		=> array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 75, 'requiredError' => 'content_type_please_enter_valid_field_value')
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
		if (!is_array($data))
		{
			return false;
		}
		else if (isset($data['content_type'], $data['field_name']))
		{
			$contentType = $data['content_type'];
			$fieldName = $data['field_name'];
		}
		else
		{
			return false;
		}

		return array('xf_content_type_field' => $this->_getContentTypeModel()->getContentTypeFieldFromQuery($contentType, $fieldName));
	}

	/**
	 * Gets SQL condition to update the existing record.
	 *
	 * @return string
	 */
	protected function _getUpdateCondition($tableName)
	{
		return 'content_type = ' . $this->_db->quote($this->getExisting('content_type')) .
			'AND field_name = ' . $this->_db->quote($this->getExisting('field_name'));
	}

	/**
	 * Pre-save handler.
	 */
	protected function _preSave()
	{
		if ($this->isChanged('field_name'))
		{
			if (!$name = $this->get('field_name'))
			{
				$this->error(new XenForo_Phrase('content_type_please_enter_valid_field_name'), 'field_name');
			}
		}

		if ($this->isChanged('field_value'))
		{
			if ($class = $this->get('field_value'))
			{
				if (!XenForo_Application::autoload($class))
				{
					$this->error(new XenForo_Phrase('content_type_field_value_must_be_a_valid_class_that_already_exists'), 'field_value');
				}
			}
		}
	}

	/**
	 * Post-save handler.
	 */
	protected function _postSave()
	{
		$this->_getContentTypeModel()->rebuildContentTypeCache();
	}

	/**
	 * Post-delete handler.
	 */
	protected function _postDelete()
	{
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