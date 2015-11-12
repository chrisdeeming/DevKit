<?php

class XenDevTools_Model_AddOn extends XFCP_XenDevTools_Model_AddOn
{
	protected $_zipMimeTypes = array(
		'application/zip',
		'application/octet-stream'
	);

	protected $_annoyingFilenames = array(
		'.DS_Store', // mac specific
		'.localized', // mac specific
		'Thumbs.db' // windows specific
	);

	public function getFileTypes()
	{
		$fileTypes = array('zip' => 'ZIP', 'rar' => 'RAR', 'xml' => 'XML');
		if (!extension_loaded('zip'))
		{
			unset ($fileTypes['zip']);
		}

		if (!extension_loaded('rar'))
		{
			unset ($fileTypes['rar']);
		}

		return $fileTypes;
	}

	public function extractArchive($filePath, $fileName, $filter = 'Zip')
	{
		$_fileName = pathinfo($fileName, PATHINFO_FILENAME);

		$baseDir = XenForo_Helper_File::getInternalDataPath();
		$extractTo = $baseDir . '/addons/_install/' . $_fileName;

		if (XenForo_Helper_File::createDirectory($extractTo))
		{
			$decompress = new Zend_Filter_Decompress(array(
				'adapter' => $filter,
				'options' => array(
					'target' => $extractTo
				)
			));

			$decompress->filter($filePath);

			return $extractTo;
		}
	}

	protected $_foundDirs = array();

	/**
	 * Given a directory, this will recursively list all directories within it
	 * If the $allowedDirs array is defined, only the directories specified will be listed.
	 *
	 * @param string $baseDir
	 * @param array $allowedDirs
	 */
	public function getDirectoryListing($baseDir, array $allowedDirs = null)
	{
		$dir = new RecursiveDirectoryIterator($baseDir);
		$iterator = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::SELF_FIRST);

		$dirs = array();
		foreach ($iterator AS $dirName => $dirInfo)
		{
			if (strstr($dirName, '__MACOSX'))
			{
				continue;
			}

			if ($allowedDirs)
			{
				if ($dirInfo->isDir() && in_array($dirInfo->getFileName(), $allowedDirs))
				{
					if (!isset($this->_foundDirs[$dirInfo->getFileName()]))
					{
						$dirs[] = array(
							'file' => $dirInfo->getFileName(),
							'path' => $dirName
						);

						$this->_foundDirs[$dirInfo->getFileName()] = $dirInfo->getFileName();
					}
				}
			}
			else
			{
				if ($dirInfo->isDir())
				{
					if (!isset($this->_foundDirs[$dirInfo->getFileName()]))
					{
						$dirs[] = array(
							'file' => $dirInfo->getFileName(),
							'path' => $dirName
						);

						$this->_foundDirs[$dirInfo->getFileName()] = $dirInfo->getFileName();
					}
				}
			}
		}

		return $dirs;
	}

	/**
	 * Given a directory, this will recursively list all files within it
	 *
	 * @param string $baseDir
	 */
	public function getFileListing($baseDir)
	{
		$dir = new RecursiveDirectoryIterator($baseDir);
		$iterator = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::SELF_FIRST);

		foreach ($iterator AS $fileName => $fileInfo)
		{
			if (strstr($fileName, '__MACOSX'))
			{
				continue;
			}

			if ($fileInfo->isFile())
			{
				$files[] = array(
					'file' => $fileInfo->getFileName(),
					'path' => $fileName
				);
			}
		}

		return $files;
	}

	/**
	 * Recursively copy files from one directory to another
	 *
	 * @param String $source - Source of files being moved
	 * @param String $destination - Destination of files being moved
	 */
	public function recursiveCopy($source, $destination)
	{
		if (!is_dir($source))
		{
			return false;
		}

		if (!is_dir($destination))
		{
			if (!XenForo_Helper_File::createDirectory($destination))
			{
				return false;
			}
		}

		$dir = new DirectoryIterator($source);
		foreach ($dir AS $dirInfo)
		{
			if ($dirInfo->isFile())
			{
				@copy($dirInfo->getRealPath(), $destination . '/' . $dirInfo->getFilename());
				XenForo_Helper_File::makeWritableByFtpUser($destination . '/' . $dirInfo->getFilename());
			}
			else if (!$dirInfo->isDot() && $dirInfo->isDir())
			{
				$this->recursiveCopy($dirInfo->getRealPath(), $destination . '/' . $dirInfo);
				XenForo_Helper_File::makeWritableByFtpUser($destination . '/' . $dirInfo);
			}
		}

		$this->_directoryCleanUp($destination);

		return true;
	}

	/**
	 * Ascertains the type of XML file. Currently detects a XenForo XML for install.
	 *
	 * @param string $xmlFile
	 */
	public function getXmlType($xmlFile)
	{
		$xml = new SimpleXMLElement($xmlFile, 0, true);

		$xmlDetails = array(
			'type' => (string)$xml->getName(),
			'addon_id' => (string)$xml['addon_id'],
			'version_string' => (string)$xml['version_string']
		);

		return $xmlDetails;
	}

	public function processZipFiles(array &$filesToProcess)
	{
		$processedFiles = array();

		foreach ($filesToProcess AS $key => $fileToProcess)
		{
			try
			{
				if (isset($fileToProcess['url']))
				{
					$processedFiles[$fileToProcess['url']] = $this->extractArchive($fileToProcess['tmp_name'], $fileToProcess['name']);
				}
				else
				{
					$processedFiles[] = $this->extractArchive($fileToProcess['tmp_name'], $fileToProcess['name']);
				}
			}
			catch (Exception $e) { continue; }

			unset ($filesToProcess[$key]);
		}

		return $processedFiles;
	}

	public function processXmlFiles(array &$filesToProcess)
	{
		$processedFiles = array();

		foreach ($filesToProcess AS $key => $fileToProcess)
		{
			$xmlDetails = $this->getXmlType($fileToProcess['tmp_name']);

			if ($xmlDetails['type'] === 'addon')
			{
				$processedFiles[] = array(
					'path' => $fileToProcess['tmp_name'],
					'addon_id' => $xmlDetails['addon_id'],
					'version_string' => $xmlDetails['version_string']
				);
			}

			unset ($filesToProcess[$key]);
		}

		return $processedFiles;
	}

	public function getFileInfoFromFile($fileName)
	{
		$fileInfo = array();

		$realPath = realpath($fileName);
		if ($realPath)
		{
			$fileInfo = array(
				'tmp_name' => $realPath,
				'name' => pathinfo($realPath, PATHINFO_FILENAME)
			);
		}

		return $fileInfo;
	}

	public function getFromDom(Zend_Dom_Query $dom, $selector, $attribute = '')
	{
		$result = $dom->query($selector);

		if (!$result->count())
		{
			return false;
		}

		if ($attribute)
		{
			$result = $result->current()->getAttribute($attribute);
		}

		return $result;
	}

	public function getFileType($fileContents)
	{
		$fileContents = substr($fileContents, 0, 5);

		if (strpos($fileContents, 'Rar') !== false)
		{
			return 'rar';
		}
		else if (strpos($fileContents, 'PK') !== false)
		{
			return 'zip';
		}
		else if (strpos($fileContents, '<?xml') !== false)
		{
			return 'xml';
		}

		return false;
	}
	
	public function installAddOn(array $directories)
	{
		foreach ($directories AS $intOrUrl => $directory)
		{
			$fileList = $this->getFileListing($directory);

			$xmlFile = array();
			foreach ($fileList AS $file)
			{
				if (strstr($file['file'], '.xml'))
				{
					$xmlDetails = $this->getXmlType($file['path']);

					if ($xmlDetails['type'] === 'addon')
					{
						$xmlFile = array(
							'path' => $file['path'],
							'addon_id' => $xmlDetails['addon_id'],
							'version_string' => $xmlDetails['version_string']
						);

						break;
					}
				}
			}

			$allowedDirs = array(
				'js',
				'library',
				'styles',
				'upload'
			);

			$dirList = $this->getDirectoryListing($directory, $allowedDirs);

			$addOnDirs = array();
			foreach ($dirList AS $dir)
			{
				switch ($dir['file'])
				{
					case 'upload':
						$addOnDirs['upload'] = $dir['path'];
						break;

					case 'js':
						$addOnDirs['js'] = $dir['path'];
						break;

					case 'library':
						$addOnDirs['library'] = $dir['path'];
						break;

					case 'styles':
						$addOnDirs['styles'] = $dir['path'];
						break;
				}
			}

			if (!$dirList)
			{
				$dirList = $this->getDirectoryListing($directory);

				$commonLibDirs = array(
					'Authentication' => true, 'BbCode' => true,
					'Captcha' => true, 'ControllerAdmin' => true,
					'ControllerPublic' => true, 'CronEntry' => true,
					'DataWriter' => true, 'Importer' => true,
					'Model' => true, 'Option' => true,
					'Route' => true, 'Template' => true,
					'ViewAdmin' => true, 'ViewPublic' => true,
				);

				foreach ($dirList AS $dir)
				{
					if (isset($commonLibDirs[$dir['file']]))
					{
						$addOnDirs['maybeLibrary'] = $dir['path'] . '/..';
					}
				}
			}

			$copiedFiles = array();
			foreach ($addOnDirs AS $key => $dir)
			{
				if ($key == 'upload')
				{
					$copiedFiles['upload'] = $this->recursiveCopy($dir, '.');

					break;
				}
				elseif ($key == 'maybeLibrary')
				{
					$this->recursiveCopy($dir . '/..', './library');
				}
				elseif ($key == 'js' || $key == 'library' || $key == 'styles')
				{
					$this->recursiveCopy($dir, './' . $key);
				}
			}

			if (!$xmlFile)
			{
				$this->deleteAll($directory);

				throw new XenForo_Exception(
					new XenForo_Phrase('xendevtools_a_valid_installable_xml_not_found'), true
				);
			}

			$addOnExists = $this->getAddOnById($xmlFile['addon_id']);
			if ($addOnExists)
			{
				$this->installAddOnXmlFromFile($xmlFile['path'], $xmlFile['addon_id']);
			}
			else
			{
				$this->installAddOnXmlFromFile($xmlFile['path']);
			}

			if (!is_int($intOrUrl))
			{
				if (Zend_Uri::check($intOrUrl))
				{
					$data = array(
						'addon_id' => $xmlFile['addon_id'],
						'update_url' => $intOrUrl,
						'check_updates' => 1,
						'last_checked' => XenForo_Application::$time,
						'latest_version' => $xmlFile['version_string']
					);

					$writer = XenForo_DataWriter::create('AddOnInstaller_DataWriter_Updater', XenForo_DataWriter::ERROR_SILENT);

					if ($this->isDwUpdate($data['addon_id']))
					{
						$writer->setExistingData($data['addon_id']);
					}

					$writer->bulkSet($data);
					$writer->save();
				}
			}

			$this->deleteAll($directory);
		}

		return true;
	}

	public function isDwUpdate($addOnId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_addon_update_check
			WHERE addon_id = ?
		', $addOnId);
	}

	public function installXenForo(array $directories)
	{
		foreach ($directories AS $directory)
		{
			$allowedDirs = array(
				'upload'
			);

			$dirList = $this->getDirectoryListing($directory, $allowedDirs);

			$addOnDirs = array();
			foreach ($dirList AS $dir)
			{
				switch ($dir['file'])
				{
					case 'upload':
						$addOnDirs['upload'] = $dir['path'];
						break;

				}
			}

			$copiedFiles = array();
			foreach ($addOnDirs AS $key => $dir)
			{
				if ($key == 'upload')
				{
					$copiedFiles['upload'] = $this->recursiveCopy($dir, '.');

					break;
				}
			}
		}

		return true;
	}

	/**
	 * Rebuilds all caches that are touched by add-ons.
	 */
	public function rebuildAddOnCaches()
	{
		$session = false;
		if (XenForo_Application::isRegistered('session'))
		{
			/** @var $session XenForo_Session */
			$session = XenForo_Application::get('session');
		}
		if ($session)
		{
			if ($session->get('xendevSkipCacheRebuild'))
			{
				return true;
			}
		}

		$session->remove('xendevSkipCacheRebuild');

		return parent::rebuildAddOnCaches();
	}

	/**
	 * Recursively copies files from a source to a destination
	 *
	 */
	public function addOnBuilderCopyFiles($source, $destination)
	{
		try
		{
			XenForo_Helper_File::createDirectory($destination);
			$directory = dir($source);

			XenForo_Helper_File::makeWritableByFtpUser($directory);
		}
		catch (Exception $e)
		{
			return false;
		}

		while (FALSE !== ($readdirectory = $directory->read()))
		{
			if ($readdirectory == '.' || $readdirectory == '..')
			{
				continue;
			}
			$PathDir = $source . '/' . $readdirectory;
			if (is_dir($PathDir))
			{
				self::addOnBuilderCopyFiles($PathDir, $destination . '/' . $readdirectory);
				continue;
			}
			copy($PathDir, $destination . '/' . $readdirectory);
		}

		$directory->close();

		$this->_directoryCleanUp($destination);

		return true;
	}

	/**
	 * Accepts the path of a directory then recursively deletes all files in that directory
	 * and then removes the directory.
	 *
	 * If $empty is set to true, then the directory is emptied but not deleted.
	 *
	 * @param string $directory
	 * @param bool $empty
	 *
	 * return bool
	 */
	public function deleteAll($directory, $empty = false)
	{
		if (substr($directory, -1) == '/')
		{
			$directory = substr($directory, 0, -1);
		}

		if (!file_exists($directory) || !is_dir($directory))
		{
			return false;
		}
		elseif(!is_readable($directory))
		{
			return false;
		}
		else
		{
			$directoryHandle = opendir($directory);

			while (($contents = readdir($directoryHandle)) !== false)
			{
				if($contents != '.' && $contents != '..')
				{
					$path = $directory . '/' . $contents;

					if(is_dir($path))
					{
						$this->deleteAll($path);
					}
					else
					{
						unlink($path);
					}
				}
			}

			closedir($directoryHandle);

			if($empty == false)
			{
				if(!rmdir($directory))
				{
					return false;
				}
			}

			return true;
		}
	}

	/**
	 * Recursively scan directories for presence of MacOS hidden and
	 * other annoying files. If specified, the function will delete them.
	 *
	 * @global   array   $annoying_files An array of filenames which are
	 *                                   considered "annoying" (to say the least)
	 * @param    string  $dir            The starting directory
	 * @param    bool    $do_delete      Delete the annoying files or just print
	 *                                   them out (if they're found). Default false.
	 * @param    array   $exclude        An array of files/folders to exclude from
	 *                                   the scan, defaults to '.' and '..'
	 */
	protected function _directoryCleanUp($dir, $doDelete = true, $exclude = array('.', '..'))
	{
		$openHandle = opendir($dir);

		while ($readHandle = readdir($openHandle))
		{
			if (in_array($readHandle, $exclude, true))
			{
				continue;
			}

			$macHidden = strpos($readHandle, '._');
			if ($macHidden !== false && $macHidden === 0)
			{
				if ($doDelete === true)
				{
					@unlink($dir . DIRECTORY_SEPARATOR . $readHandle);
				}
			}

			if (in_array($readHandle, $this->_annoyingFilenames, true))
			{
				if ($doDelete === true)
				{
					@unlink($dir . DIRECTORY_SEPARATOR . $readHandle);
				}
			}

			if (is_dir($dir . DIRECTORY_SEPARATOR . $readHandle))
			{
				self::_directoryCleanUp($dir . DIRECTORY_SEPARATOR . $readHandle, $doDelete, $exclude);
				continue;
			}

		}

		closedir($openHandle);
	}

	public function saveCredentials($username, $password, $license = '')
	{
		if ($license !== false)
		{
			$optionValues = array(
				'xendevCustUsername' => $username,
				'xendevCustPassword' => $password,
				'xendevCustLicense' => $license
			);
		}
		else
		{
			$optionValues = array(
				'xendevForumUsername' => $username,
				'xendevForumPassword' => $password
			);
		}

		return $this->_saveCredentials($optionValues);
	}

	protected function _saveCredentials(array $optionValues)
	{
		foreach ($optionValues AS $optionId => $optionValue)
		{
			$optionWriter = XenForo_DataWriter::create('XenForo_DataWriter_Option');
			$optionWriter->setExistingData($optionId, true);
			$optionWriter->set('option_value', $optionValue);

			$optionWriter->save();
		}

		return $this->getModelFromCache('XenForo_Model_Option')->rebuildOptionCache();
	}

	public function getVersionIdFromVersionString($versionString)
	{
		$stability = 7;
		if (strpos($versionString, 'Release Candidate'))
		{
			$stability = 5;
		}
		elseif (strpos($versionString, 'Beta'))
		{
			$stability = 3;
		}
		elseif (strpos($versionString, 'Alpha'))
		{
			$stability = 1;
		}

		$pl = 0;
		if ($stability < 7)
		{
			$pl = trim(substr($versionString, -2, 2));
		}

		$versionString = trim(substr($versionString, 0, 6));
		$versionArray = explode('.', $versionString, 3);

		$versionId = $versionArray[0];
		if (strlen($versionArray[1]) == 1)
		{
			$versionId .= '0';
		}
		$versionId .= $versionArray[1];

		if (strlen($versionArray[2]) == 1)
		{
			$versionId .= '0';
		}

		if ($pl > 9)
		{
			$stability += 1;
			$pl = substr($pl, 1, 1);
		}

		$versionId .= $versionArray[2];
		$versionId .= $stability;
		$versionId .= $pl;

		return intval($versionId);
	}
}