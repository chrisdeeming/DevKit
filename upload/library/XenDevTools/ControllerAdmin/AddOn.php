<?php

/**
 * Add-ons controller.
 *
 * @package XenForo_AddOns
 */
class XenDevTools_ControllerAdmin_AddOn extends XFCP_XenDevTools_ControllerAdmin_AddOn
{
	/**
	 * Lists all installed add-ons
	 * If add-on is selected, launch form to build it.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionBuild()
	{
		$addOnModel = $this->_getAddOnModel();

		$addOnId = $this->_input->filterSingle('addon_id', XenForo_Input::STRING);

		if ($addOnId)
		{
			$addOn = $addOnModel->getAddOnById($addOnId);

			$buildLocation = XenForo_Application::get('config')->internalDataPath . '/addons/';

			$addOn['addon_id_library'] = str_replace('_', '/', $addOn['addon_id']);
			$addOn['addon_id_js'] = strtolower(str_replace('_', '/', $addOn['addon_id']));
			$addOn['addon_id_style'] = 'default/' . strtolower(str_replace('_', '/', $addOn['addon_id']));

			$addOn['libraryDirExists'] = is_dir('library/' . $addOn['addon_id_library']);
			$addOn['jsDirExists'] = is_dir('js/' . $addOn['addon_id_js']);
			$addOn['styleDirExists'] = is_dir('styles/' . $addOn['addon_id_style']);

			$addOnBuilderCache = XenForo_Application::getSimpleCacheData('addOnBuilder');

			if (isset($addOnBuilderCache[$addOnId]))
			{
				$addOn['customFiles'] = $addOnBuilderCache[$addOnId];

				if (!is_array($addOn['customFiles']))
				{
					$addOn['customFiles'] = '';
				}
				else
				{
					$addOn['customFiles'] = implode("\n", $addOn['customFiles']);
				}
			}

			$viewParams = array(
				'addOn' => $addOn,
				'buildLocation' => $buildLocation,
				'canAccessDevelopment' => $addOnModel->canAccessAddOnDevelopmentAreas()
			);

			return $this->responseView('XenForo_ViewAdmin_AddOn_Build', 'addon_builder_overlay', $viewParams);
		}

		$addOns = $addOnModel->getAllAddOns();

		$viewParams = array(
			'addOns' => $addOns,
			'canAccessDevelopment' => $addOnModel->canAccessAddOnDevelopmentAreas()
		);

		return $this->responseView('XenForo_ViewAdmin_AddOn_Build', 'addon_builder_index', $viewParams);
	}

	/**
	 * Builds the previously selected add-on
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionBuilder()
	{
		$addOnId = $this->_input->filterSingle('addon_id', XenForo_Input::STRING);
		$addOn = $this->_getAddOnOrError($addOnId);

		$addOnModel = $this->_getAddOnModel();

		if ($this->isConfirmedPost())
		{
			$directories = $this->_input->filter(array(
				'library_check' => XenForo_Input::BOOLEAN,
				'js_check' => XenForo_Input::BOOLEAN,
				'styles_check' => XenForo_Input::BOOLEAN,
				'custom_files_check' => XenForo_Input::BOOLEAN,
				'build_location' => XenForo_Input::STRING,
				'library_dir' => XenForo_Input::STRING,
				'js_dir' => XenForo_Input::STRING,
				'styles_dir' => XenForo_Input::STRING,
				'custom_files' => XenForo_Input::STRING,
			));

			$buildLocation = $directories['build_location'] . $addOnId . '/' . $addOnId . '/upload/';

			if ($directories['library_check'])
			{
				$source = 'library/' . $directories['library_dir'];
				$destination = $buildLocation . 'library/' . $directories['library_dir'];

				$copyFiles = $addOnModel->addOnBuilderCopyFiles($source, $destination);

				if (!$copyFiles)
				{
					return $this->responseError(new XenForo_Phrase('could_not_copy_files_did_you_specify_correct_dir'));
				}
			}

			if ($directories['js_check'])
			{
				$source = 'js/' . $directories['js_dir'];
				$jsDestination = $buildLocation . 'js/' . $directories['js_dir'];

				$copyFiles = $addOnModel->addOnBuilderCopyFiles($source, $jsDestination);

				if (!$copyFiles)
				{
					return $this->responseError(new XenForo_Phrase('could_not_copy_files_did_you_specify_correct_dir'));
				}

				$minifyJs = $this->_input->filterSingle('js_min', XenForo_Input::BOOLEAN);
				if ($minifyJs)
				{
					$iterator = new \GlobIterator($jsDestination . '/*.js', FilesystemIterator::KEY_AS_FILENAME);
					$jsFiles = iterator_to_array($iterator);

					XenForo_Helper_File::createDirectory($jsDestination . DIRECTORY_SEPARATOR . 'min');

					$minify = new XenDevTools_Helper_Minify();
					foreach (array_keys($jsFiles) AS $jsFile)
					{
						$minify->addSource($jsDestination . DIRECTORY_SEPARATOR . $jsFile);
						$minify->setTarget($jsDestination . DIRECTORY_SEPARATOR . 'min' . DIRECTORY_SEPARATOR . $jsFile);
						$minify->exec();
					}
				}
			}

			if ($directories['styles_check'])
			{
				$source = 'styles/' . $directories['styles_dir'];
				$destination = $buildLocation . 'styles/' . $directories['styles_dir'];

				$copyFiles = $addOnModel->addOnBuilderCopyFiles($source, $destination);

				if (!$copyFiles)
				{
					return $this->responseError(new XenForo_Phrase('could_not_copy_files_did_you_specify_correct_dir'));
				}
			};

			$addOnBuilderCache = XenForo_Application::getSimpleCacheData('addOnBuilder');
			if ($directories['custom_files_check'])
			{
				$copiedFiles = array();

				$files = preg_split('/\s/', $directories['custom_files'], -1, PREG_SPLIT_NO_EMPTY);
				foreach ($files AS $file)
				{
					$destination = $buildLocation;

					$pathInfo = pathinfo($file);
					if (isset($pathInfo['dirname']))
					{
						$destination .= $pathInfo['dirname'] . '/';
					}

					$copyFiles = copy($file, $destination . $file);
					if ($copyFiles)
					{
						$copiedFiles[] = $file;
					}
				}

				if (!$addOnBuilderCache)
				{
					$addOnBuilderCache = array();
				}
				$addOnBuilderCache[$addOnId] = $copiedFiles;

				XenForo_Application::setSimpleCacheData('addOnBuilder', $addOnBuilderCache);
			}
			else
			{
				$addOnBuilderCache[$addOnId] = array();
				XenForo_Application::setSimpleCacheData('addOnBuilder', $addOnBuilderCache);
			}

			$destXml = $directories['build_location'] . $addOnId . '/' . $addOnId . '/addon-' . $addOnId . '.xml';

			$xmlObject = $this->_getAddOnModel()->getAddOnXml($addOn);
			$xmlObject->save($destXml);

			$source = $directories['build_location'] . $addOnId . '/' . $addOnId;
			$destination = $directories['build_location'] . $addOnId . '/' . $addOnId . '-' . $addOn['version_string'] . '.zip';

			/** @var $zip Zend_Filter_Compress_Zip */
			$zip = new Zend_Filter_Compress(array(
				'adapter' => 'Zip',
				'options' => array(
					'archive' => $destination
				)
			));

			if (!$zip->filter($source))
			{
				throw $this->getErrorOrNoPermissionResponseException(new XenForo_Phrase('addon_builder_cannot_build_zip_file'));
			}

			$download = $this->_input->filterSingle('download', XenForo_Input::STRING);
			if ($download)
			{
				$fileName = $addOnId . '-' . $addOn['version_string'] . '.zip';
				$viewParams = array(
					'filePath' => $destination,
					'fileName' => $fileName
				);

				$this->_routeMatch->setResponseType('raw');

				return $this->responseView(
					'XenDevTools_ViewAdmin_Download',
					'',
					$viewParams
				);
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('add-ons') . $this->getLastHash($addOnId),
				new XenForo_Phrase('your_addon_has_been_built')
			);
		}
	}

	/**
	 * Gets the add-on model object.
	 *
	 * @return XenForo_Model_AddOn
	 */
	protected function _getAddOnModel()
	{
		return $this->getModelFromCache('XenForo_Model_AddOn');
	}
}