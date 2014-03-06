<?php

class XenDevTools_Model_AddOn extends XFCP_XenDevTools_Model_AddOn
{
	protected $_annoyingFilenames = array(
		'.DS_Store', // mac specific
		'.localized', // mac specific
		'Thumbs.db' // windows specific
	);

	/**
	 * Recursively copies files from a source to a destination
	 *
	 */
	public function addOnBuilderCopyFiles($source, $destination)
	{
		try
		{
			@mkdir($destination, $mode = 0777, $recursive = true);
			$directory = dir($source);
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
}