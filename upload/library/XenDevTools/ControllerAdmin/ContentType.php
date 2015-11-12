<?php

class XenDevTools_ControllerAdmin_ContentType extends XenForo_ControllerAdmin_Abstract
{
	public function actionIndex()
	{
		$contentTypeModel = $this->_getContentTypeModel();

		$contentTypes = $contentTypeModel->getAllContentTypes();
		$contentTypes = $contentTypeModel->prepareContentTypes($contentTypes);

		$contentTypeFields = $contentTypeModel->getAllContentTypeFields();

		$viewParams = array(
			'contentTypes' => $contentTypes,
			'contentTypeFields' => $contentTypeFields,
			'contentTypeFieldsCount' => count($contentTypeFields)
		);

		return $this->responseView('XenDevTools_ViewAdmin_Index', 'content_type_management_index', $viewParams);
	}

	public function actionAdd()
	{
		return $this->_getContentTypeAddEditResponse($this->_getDefaultContentType());
	}

	public function actionEdit()
	{
		$contentTypeId = $this->_input->filterSingle('content_type', XenForo_Input::STRING);
		$contentType = $this->_getContentTypeOrError($contentTypeId);

		return $this->_getContentTypeAddEditResponse($contentType);
	}

	protected function _getContentTypeAddEditResponse(array $contentType)
	{
		/** @var $addOnModel XenForo_Model_AddOn */
		$addOnModel = $this->getModelFromCache('XenForo_Model_AddOn');

		$viewParams = array(
			'contentType' => $contentType,
			'addOnOptions' => $addOnModel->getAddOnOptionsListIfAvailable(),
			'addOnSelected' => (!empty($contentType['addon_id']) ? $contentType['addon_id'] : $addOnModel->getDefaultAddOnId())
		);

		return $this->responseView('XenDevTools_ViewAdmin_Edit', 'content_type_management_content_type_edit', $viewParams);
	}

	public function actionSave()
	{
		$contentTypeId = $this->_input->filterSingle('content_type', XenForo_Input::STRING);
		$contentType = $this->_getContentTypeModel()->getContentType($contentTypeId);

		$contentTypeInput = $this->_input->filter(array(
			'new_content_type' => XenForo_Input::STRING,
			'addon_id' => XenForo_Input::STRING
		));

		if (!empty($contentTypeInput['new_content_type']))
		{
			$contentTypeInput['content_type'] = $contentTypeInput['new_content_type'];
			unset ($contentTypeInput['new_content_type']);
		}

		$contentTypeWriter = XenForo_DataWriter::create('XenDevTools_DataWriter_ContentType');

		if ($contentType)
		{
			$contentTypeWriter->setExistingData($contentType);
		}

		$contentTypeWriter->bulkSet($contentTypeInput);
		$contentTypeWriter->save();

		$redirectPhrase = new XenForo_Phrase('content_type_created_successfully');
		if ($contentType)
		{
			$redirectPhrase = new XenForo_Phrase('content_type_updated_successfully');
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('content-types'),
			$redirectPhrase
		);
	}

	public function actionDelete()
	{
		$contentTypeId = $this->_input->filterSingle('content_type', XenForo_Input::STRING);
		$contentType = $this->_getContentTypeOrError($contentTypeId);

		if ($this->isConfirmedPost())
		{
			$contentTypeWriter = XenForo_DataWriter::create('XenDevTools_DataWriter_ContentType');

			$contentTypeWriter->setExistingData($contentType);
			$contentTypeWriter->delete();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('content-types'),
				new XenForo_Phrase('content_type_deleted_successfully')
			);
		}
		else
		{
			$viewParams = array(
				'contentType' => $contentType
			);

			return $this->responseView('XenDevTools_ViewAdmin_Delete', 'content_type_management_content_type_delete', $viewParams);
		}
	}

	public function actionRebuild()
	{
		$this->_getContentTypeModel()->rebuildContentTypeCache();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('content-types')
		);
	}

	public function actionAddField()
	{
		$contentTypeId = $this->_input->filterSingle('content_type', XenForo_Input::STRING);
		$contentType = $this->_getContentTypeOrError($contentTypeId);

		$contentTypeField = $this->_getDefaultContentTypeField();

		return $this->_getContentTypeFieldAddEditResponse($contentType, $contentTypeField);
	}

	public function actionEditField()
	{
		$contentTypeId = $this->_input->filterSingle('content_type', XenForo_Input::STRING);
		$contentType = $this->_getContentTypeOrError($contentTypeId);

		$contentTypeFieldName = $this->_input->filterSingle('field', XenForo_Input::STRING);
		$contentTypeField = $this->_getContentTypeFieldOrError($contentTypeId, $contentTypeFieldName);

		return $this->_getContentTypeFieldAddEditResponse($contentType, $contentTypeField);
	}

	protected function _getContentTypeFieldAddEditResponse(array $contentType, array $contentTypeField)
	{
		$viewParams = array(
			'contentType' => $contentType,
			'contentTypeField' => $contentTypeField,
			'contentTypeFieldNames' => $this->_getContentTypeModel()->getContentTypeFieldNames()
		);

		return $this->responseView(
			'XenDevTools_ViewAdmin_Field_Edit',
			'content_type_management_content_type_field_edit',
			$viewParams
		);
	}

	public function actionSaveField()
	{
		$contentTypeId = $this->_input->filterSingle('content_type', XenForo_Input::STRING);
		$contentType = $this->_getContentTypeOrError($contentTypeId);

		$contentTypeFieldInput = $this->_input->filter(array(
			'content_type' => XenForo_Input::STRING,
			'field_name' => XenForo_Input::STRING,
			'field_value' => XenForo_Input::STRING
		));

		$contentTypeFieldWriter = XenForo_DataWriter::create('XenDevTools_DataWriter_ContentTypeField');

		$contentTypeFieldName = $this->_input->filterSingle('field', XenForo_Input::STRING);
		if ($contentTypeFieldName)
		{
			$contentTypeField = $this->_getContentTypeFieldOrError($contentTypeId, $contentTypeFieldName);
			$contentTypeFieldWriter->setExistingData($contentTypeField);
		}

		$contentTypeFieldWriter->bulkSet($contentTypeFieldInput);
		$contentTypeFieldWriter->save();

		$redirectPhrase = new XenForo_Phrase('content_type_field_created_successfully');
		if ($contentType)
		{
			$redirectPhrase = new XenForo_Phrase('content_type_field_updated_successfully');
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('content-types'),
			$redirectPhrase
		);
	}

	public function actionDeleteField()
	{
		$contentTypeId = $this->_input->filterSingle('content_type', XenForo_Input::STRING);
		$contentType = $this->_getContentTypeOrError($contentTypeId);

		$contentTypeFieldName = $this->_input->filterSingle('field', XenForo_Input::STRING);
		$contentTypeField = $this->_getContentTypeFieldOrError($contentTypeId, $contentTypeFieldName);

		if ($this->isConfirmedPost())
		{
			$contentTypeFieldWriter = XenForo_DataWriter::create('XenDevTools_DataWriter_ContentTypeField');

			$contentTypeFieldWriter->setExistingData($contentTypeField);
			$contentTypeFieldWriter->delete();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('content-types'),
				new XenForo_Phrase('content_type_field_deleted_successfully')
			);
		}
		else
		{
			$viewParams = array(
				'contentType' => $contentType,
				'contentTypeField' => $contentTypeField
			);

			return $this->responseView('XenDevTools_ViewAdmin_DeleteField', 'content_type_management_content_type_field_delete', $viewParams);
		}
	}

	public function actionGenerateSql()
	{
		$contentTypeId = $this->_input->filterSingle('content_type', XenForo_Input::STRING);
		$contentType = $this->_getContentTypeOrError($contentTypeId);

		$contentType['sqlCode'] = $this->_generateSqlCode($contentType);

		$contentTypeFields = @unserialize($contentType['fields']);
		if ($contentTypeFields)
		{
			$contentTypeFields['sqlCode'] = $this->_generateSqlCode($contentTypeFields, true, $contentType['content_type']);
		}

		$viewParams = array(
			'contentType' => $contentType,
			'contentTypeFields' => $contentTypeFields,
			'sqlCode' => $contentType['sqlCode'] . ";\n\n" . $contentTypeFields['sqlCode'] .';'
		);

		return $this->responseView('XenDevTools_ViewAdmin_DeleteField', 'content_type_management_generate_sql', $viewParams);
	}

	protected function _generateSqlCode(array $data, $isFields = false, $contentType = '')
	{
		$sql = '';
		$tab = '	';
		$line = "\n";

		if ($isFields)
		{
			$sqlPrefix = "INSERT IGNORE INTO xf_content_type_field{$line}{$tab}(content_type, field_name, field_value){$line}VALUES{$line}{$tab}";

			$fieldCount = count($data);

			$sqlFields = '';
			$i = 0;
			foreach ($data AS $fieldName => $fieldValue)
			{
				$i++;

				$sqlFields .= "('{$contentType}', '{$fieldName}', '{$fieldValue}')";
				if ($i < $fieldCount)
				{
					$sqlFields .= ",{$line}{$tab}";
				}
			}

			$sql = $sqlPrefix . $sqlFields;
		}
		else
		{
			$sql = "INSERT IGNORE INTO xf_content_type{$line}{$tab}(content_type, addon_id, fields){$line}VALUES{$line}{$tab}('{$data['content_type']}', '{$data['addon_id']}', '')";
		}

		return $sql;
	}

	protected function _getDefaultContentType()
	{
		return array(
			'content_type' => '',
			'addon_id' => '',
			'fields' => array()
		);
	}

	protected function _getDefaultContentTypeField()
	{
		return array(
			'content_type' => '',
			'field_name' => '',
			'field_value' => ''
		);
	}

	protected function _getContentTypeOrError($contentTypeId)
	{
		$contentType = $this->_getContentTypeModel()->getContentType($contentTypeId);
		if (!$contentType)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_content_type_could_not_be_found'), 404));
		}

		return $contentType;
	}

	protected function _getContentTypeFieldOrError($contentTypeId, $contentTypeFieldName)
	{
		$contentTypeField = $this->_getContentTypeModel()->getContentTypeFieldFromQuery($contentTypeId, $contentTypeFieldName);
		if (!$contentTypeField)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_content_type_field_could_not_be_found'), 404));
		}

		return $contentTypeField;
	}

	/**
	 * @return XenForo_Model_ContentType
	 */
	protected function _getContentTypeModel()
	{
		return $this->getModelFromCache('XenForo_Model_ContentType');
	}
}