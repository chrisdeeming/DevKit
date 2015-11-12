<?php

/**
 * Add-ons controller.
 *
 * @package XenForo_AddOns
 */
class XenDevTools_ControllerAdmin_AddOn extends XFCP_XenDevTools_ControllerAdmin_AddOn
{
	public function actionInstallUpgrade()
	{
		$type = $this->_input->filterSingle('type', XenForo_Input::STRING);

		$downloadPending = false;
		if ($type == 'xenforo')
		{
			$downloadPending = XenForo_Application::getSession()->get('xenforoDownloadPending');
		}

		$viewParams = array(
			'type' => $type ? $type : 'file',
			'fileTypes' => $this->_getAddOnModel()->getFileTypes(),
			'loadUpgradeOverlay' => $downloadPending
		);

		return $this->responseView(
			'XenDevTools_ViewAdmin_InstallUpgrade_Index',
			'xendevtools_install_upgrade',
			$viewParams
		);
	}

	public function actionUpgradePending()
	{
		$clear = $this->_input->filterSingle('clear', XenForo_Input::BOOLEAN);
		if ($clear)
		{
			XenForo_Application::getSession()->remove('xenforoDownloadPending');

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->getDynamicRedirect()
			);
		}

		$downloadPending = XenForo_Application::getSession()->get('xenforoDownloadPending');
		if ($this->isConfirmedPost())
		{
			$downloadLink = 'https://xenforo.com/customers/download?l=' . $downloadPending['license_key'] . '&d=xenforo';

			$client = XenForo_Helper_Http::getClient($downloadLink);
			$client->setCookieJar($downloadPending['cookie_jar']);

			$request = $client->request();
			$dom = new Zend_Dom_Query($request->getBody());

			$downloadPage = $dom->query('.customer_download');
			if ($downloadPage->count())
			{
				$postData = array(
					'download_version_id' => $downloadPending['download_id'],
					'options[upgradePackage]' => 1,
					'agree' => 1,
					'l' => $downloadPending['license_key'],
					'd' => 'xenforo'
				);

				@ini_set('max_execution_time', 0);

				$client->setUri('https://xenforo.com/customers/download');
				$client->setParameterPost($postData);

				$baseDir = XenForo_Helper_File::getInternalDataPath();
				$extractTo = $baseDir . '/addons/_install/xenforo';

				XenForo_Helper_File::createDirectory($extractTo);
				XenForo_Helper_File::makeWritableByFtpUser($extractTo);

				$tempName = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
				$fp = fopen($tempName, 'w');

				fwrite($fp, $client->request('POST')->getRawBody());
				fclose($fp);

				$filesToProcess = array();
				$filesToProcess['zip'][] = array(
					'tmp_name' => $tempName,
					'name' => $downloadPending['version_string'] . '.zip'
				);

				$addOnModel = $this->_getAddOnModel();

				$processedZipFiles = $addOnModel->processZipFiles($filesToProcess['zip']);
				$addOnModel->installXenForo($processedZipFiles);

				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					'install'
				);
			}
		}

		$viewParams = array(
			'downloadDetails' => $downloadPending
		);

		return $this->responseView(
			'XenDevTools_ViewAdmin_InstallUpgrade_UpgradePending',
			'xendevtools_install_upgrade_xenforo_pending',
			$viewParams
		);
	}

	public function actionDoInstallUpgrade()
	{
		$this->_assertPostOnly();

		$addOnModel = $this->_getAddOnModel();

		$type = $this->_input->filterSingle('type', XenForo_Input::STRING);
		if (!$type)
		{
			$type = 'file';
		}

		switch ($type)
		{
			case 'file':

				$filesToProcess = array();

				$fileTransfer = new Zend_File_Transfer_Adapter_Http();
				if ($fileTransfer->isUploaded('from_file'))
				{
					foreach ($fileTransfer->getFileInfo() AS $fileInfo)
					{
						$filesToProcess[XenForo_Helper_File::getFileExtension($fileInfo['name'])][] = $fileInfo;
					}
				}

				break;

			case 'server':

				$serverPaths = $this->_input->filterSingle('server_paths', XenForo_Input::STRING);
				$serverPaths = preg_split('/\r?\n/', trim($serverPaths));

				$urls = array();
				$local = array();

				foreach ($serverPaths AS $path)
				{
					if (Zend_Uri::check($path))
					{
						$urls[] = $path;
					}
					else
					{
						$local[] = $path;
					}
				}

				foreach ($local AS $fileName)
				{
					$fileInfo = $addOnModel->getFileInfoFromFile($fileName);

					if ($fileInfo)
					{
						$filesToProcess[XenForo_Helper_File::getFileExtension($fileName)][] = $fileInfo;
					}
				}

				if ($urls)
				{
					$username = $this->_input->filterSingle('login', XenForo_Input::STRING);
					$password = $this->_input->filterSingle('password', XenForo_Input::STRING);

					if (!$username || !$password)
					{
						return $this->responseError(new XenForo_Phrase('login_to_xenforo_has_failed'));
					}

					$client = XenForo_Helper_Http::getClient('https://xenforo.com/community');
					$client->setCookieJar();

					$dom = new Zend_Dom_Query();

					foreach ($urls AS $url)
					{
						$client->setUri($url);

						$request = $client->request('GET');
						if ($request->isError())
						{
							continue;
						}

						$dom->setDocument($request->getBody());

						$baseHref = $addOnModel->getFromDom($dom, 'base', 'href');

						$loggedIn = $addOnModel->getFromDom($dom, 'html .LoggedIn');
						if (!$loggedIn)
						{
							$loginUrl = $baseHref . 'index.php?login/login';

							$client->setUri($loginUrl);

							$client->setParameterPost(array('login' => $username, 'password' => $password, 'redirect' => $url));
							$login = $client->request('POST');

							$dom->setDocument($login->getBody());

							$loggedIn = $addOnModel->getFromDom($dom, 'html .LoggedIn');
							if (!$loggedIn)
							{
								continue;
							}
						}

						$downloadUrl = $addOnModel->getFromDom($dom, '.downloadButton a', 'href');
						if (!$downloadUrl)
						{
							continue;
						}

						$resourceTitle = $addOnModel->getFromDom($dom, 'h1');
						$resourceTitle = $resourceTitle->current()->nodeValue;

						$client->setUri($baseHref . $downloadUrl);

						$tempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
						$fileContents = $client->request('GET')->getBody();

						$fp = fopen($tempFile, 'w');

						fwrite($fp, $fileContents);
						fclose($fp);

						$fileType = $addOnModel->getFileType($fileContents);
						$safeTitle = XenForo_Link::getTitleForUrl($resourceTitle);

						$filePath = sprintf('%s/addons/_install/%s/%s.%s',
							XenForo_Helper_File::getInternalDataPath(),
							$safeTitle, $safeTitle, $fileType
						);

						XenForo_Helper_File::createDirectory(dirname($filePath));
						XenForo_Helper_File::safeRename($tempFile, $filePath);

						$fileInfo = $addOnModel->getFileInfoFromFile($filePath);
						$fileInfo['url'] = $url;
						$filesToProcess[$fileType][] = $fileInfo;
					}
				}

				break;

			case 'xenforo':

				// Disabling this for now.
				break;
				$unsupportedString = ' (Unsupported)';

				$username = $this->_input->filterSingle('xenforo_username', XenForo_Input::STRING);
				$password = $this->_input->filterSingle('xenforo_password', XenForo_Input::STRING);
				$licenseKey = $this->_input->filterSingle('xenforo_license', XenForo_Input::STRING);
				$credentials = $username && $password && $licenseKey;

				if (!$credentials)
				{
					throw $this->getErrorOrNoPermissionResponseException(new XenForo_Phrase('xendevtools_please_enter_all_details'));
				}

				$saveLicense = $this->_input->filterSingle('save_license', XenForo_Input::BOOLEAN);
				if ($credentials && $saveLicense)
				{
					$addOnModel->saveCredentials($username, $password, $licenseKey);
				}

				$loggedIn = false;

				$downloadLink = 'https://xenforo.com/customers/download?l=' . $licenseKey . '&d=xenforo';

				$client = XenForo_Helper_Http::getClient($downloadLink);
				$client->setCookieJar();

				$request = $client->request();
				$dom = new Zend_Dom_Query($request->getBody());

				$downloadPage = $dom->query('.customer_download');
				if ($downloadPage->count())
				{
					$loggedIn = true;
				}

				if (!$loggedIn)
				{
					$client->setUri('https://xenforo.com/customers/login');

					$client->setParameterPost(array(
						'email' => $username,
						'password' => $password,
						'redirect' => $downloadLink
					));

					$request = $client->request('POST');
					$dom = new Zend_Dom_Query($request->getBody());

					$downloadPage = $dom->query('.customer_download');
					if ($downloadPage->count())
					{
						$loggedIn = true;
					}
				}

				if ($loggedIn)
				{
					$unsupportedDownloads = array();
					$downloads = array();

					$downloadList = $dom->query('select.textCtrl');
					$options = $downloadList->current()->getElementsByTagName('option');

					foreach ($options AS $option)
					{
						if (strpos($option->nodeValue, $unsupportedString))
						{
							$unsupportedDownloads[$option->getAttribute('value')] = str_replace($unsupportedString, '', $option->nodeValue);
						}
						else
						{
							$downloads[$option->getAttribute('value')] = $option->nodeValue;
						}
					}

					$unsupported = $this->_input->filterSingle('unsupported', XenForo_Input::BOOLEAN);
					if ($unsupported && $unsupportedDownloads)
					{
						$downloads = array_slice($unsupportedDownloads, 0, 1, true);
					}
					else
					{
						$downloads = array_slice($downloads, 0, 1, true);
					}

					$preparedDownloads = array();
					foreach ($downloads AS $downloadId => $versionString)
					{
						$preparedDownloads[$downloadId] = array(
							'download_id' => $downloadId,
							'version_id' => $addOnModel->getVersionIdFromVersionString($versionString),
							'version_string' => $versionString,
							'license_key' => $licenseKey,
							'cookie_jar' => $client->getCookieJar()
						);

					}

					$preparedDownload = array_shift($preparedDownloads);

					if (XenForo_Application::$versionId >= $preparedDownload['version_id'] || !$preparedDownload)
					{
						throw $this->getErrorOrNoPermissionResponseException(new XenForo_Phrase('xendevtools_latest_version_already_installed'));
					}

					XenForo_Application::getSession()->set('xenforoDownloadPending', $preparedDownload);
					//$dynamicRedirect = true;
				}
				else
				{
					throw $this->getErrorOrNoPermissionResponseException(new XenForo_Phrase('xendevtools_check_username_password_and_license_key'));
				}

				break;
		}

		if (isset($filesToProcess['zip']))
		{
			$processedZipFiles = $addOnModel->processZipFiles($filesToProcess['zip']);
			$addOnModel->installAddOn($processedZipFiles);
		}

		if (isset($filesToProcess['xml']))
		{
			$processedXmlFiles = $addOnModel->processXmlFiles($filesToProcess['xml']);
			foreach ($processedXmlFiles AS $xmlFile)
			{
				$addOnExists = $addOnModel->getAddOnById($xmlFile['addon_id']);
				if ($addOnExists)
				{
					$addOnModel->installAddOnXmlFromFile($xmlFile['path'], $xmlFile['addon_id']);
				}
				else
				{
					$addOnModel->installAddOnXmlFromFile($xmlFile['path']);
				}
			}
		}

		// ugly hack...
		$redirect = XenForo_Link::buildAdminLink('add-ons');
		if (XenForo_Application::isRegistered('addOnRedirect'))
		{
			$redirect = XenForo_Application::get('addOnRedirect');
		}

		if ($redirect instanceof XenForo_ControllerResponse_Abstract)
		{
			return $redirect;
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS, $redirect
		);
	}

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

			$xmlObject = $this->_getAddOnModel()->getAddOnXml($addOn);
			$xmlFileName = 'addon-' . $addOnId . '.xml';

			if ($directories['library_check'])
			{
				$source = 'library/' . $directories['library_dir'];
				$xmlObject->save($source . '/' . $xmlFileName);
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

					XenForo_Helper_File::createDirectory($jsDestination . '/' . 'min');

					$minify = new XenDevTools_Helper_Minify();
					foreach (array_keys($jsFiles) AS $jsFile)
					{
						$minify->addSource($jsDestination . '/' . $jsFile);
						$minify->setTarget($jsDestination . '/' . 'min' . '/' . $jsFile);
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

			$xmlPrefix = $directories['build_location'] . $addOnId . '/' . $addOnId . '/';

			$destXml = array(
				'root' => $xmlPrefix . $xmlFileName,
				'library' => $xmlPrefix . 'upload/library/' . $directories['library_dir'] . '/' . $xmlFileName
			);

			foreach ($destXml AS $dest)
			{
				$xmlObject->save($dest);
			}

			$source = $directories['build_location'] . $addOnId . '/' . $addOnId;
			$destination = $directories['build_location'] . $addOnId;

			$zip = new XenDevTools_Helper_Zip();
			$zip->compress($source, $destination, $directories['build_location'], $addOnId . '-' . $addOn['version_string']);

			$download = $this->_input->filterSingle('download', XenForo_Input::STRING);
			if ($download)
			{
				$fileName = $addOnId . '-' . $addOn['version_string'] . '.zip';
				$viewParams = array(
					'filePath' => $destination . '/' . $fileName,
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