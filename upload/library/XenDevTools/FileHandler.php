<?php

/**
 * Helper to manage templates as files.
 * Portions taken from XenForo_Template_FileHandler.
 * Modified for use with uncompiled templates.
 */
class XenDevTools_FileHandler
{
	protected static $_instance;

	protected $_path = null;
	protected $_basePath = '';

	protected $_styleId = 0;

	protected function __construct($type = 'public')
	{
		$this->_basePath = './templates/';

		switch ($type)
		{
			case 'public':

				$this->_path = $this->_basePath . 'public/' . $this->_styleId;
				break;

			case 'admin':

				$this->_path = $this->_basePath . 'admin';
		}
	}

	public static function getInstance($type = 'public')
	{
		if (empty(self::$_instance[$type]))
		{
			self::$_instance[$type] = new self($type);
		}

		return self::$_instance[$type];
	}

	/**
	 * Get the file name of the specified template
	 *
	 * @param string $title
	 * @param integer $templateId
	 *
	 * @return string
	 */
	public static function get($title, $templateId)
	{
		return self::getInstance()->_getFileName($title);
	}

	/**
	 * Gets the file the specified template(s)
	 *
	 * Each parameter can be passed as
	 * -	a scalar (to match that parameter)
	 * -	null (to use a wildcard for that parameter)
	 * -	an array of scalars (to match multiple specific items)
	 *
	 * @param string|array|null $title
	 * @param string|array|null $templateId
	 */
	public static function getFileNamesFromTemplateNames($title, $templateId)
	{
		return self::getInstance()->_getFileNames($title);
	}

	protected function _getFileNames($title)
	{
		$title = $this->_prepareWildcard($title);

		$files = array();
		foreach ($title AS $_title)
		{
			$files[] = glob($this->_getFileName($_title));
		}

		return $files;
	}

	/**
	 * Save the specified template
	 *
	 * @param string $title
	 * @param integer $templateId
	 * @param string $template
	 *
	 * @return string $filename
	 */
	public static function save($title, $template, $type = 'public')
	{
		return self::getInstance($type)->_saveTemplate($title, $template);
	}

	/**
	 * Delete the specified template(s)
	 *
	 * Each parameter can be passed as
	 * -	a scalar (to match that parameter)
	 * -	null (to use a wildcard for that parameter)
	 * -	an array of scalars (to match multiple specific items)
	 *
	 * @param string|array|null $title
	 * @param string|array|null $templateId
	 */
	public static function delete($title, $type = 'public')
	{
		self::getInstance($type)->_deleteTemplate($title);
	}

	protected function _createTemplateDirectory()
	{
		if (!is_dir($this->_path))
		{
			if (XenForo_Helper_File::createDirectory($this->_path))
			{
				return XenForo_Helper_File::makeWritableByFtpUser($this->_path);
			}
			else
			{
				return false;
			}
		}

		$this->_writeHtaccess();
		return true;
	}

	protected function _writeHtaccess()
	{
		$fp = @fopen($this->_basePath . '.htaccess', 'w');
		if ($fp)
		{
			fwrite($fp, "Order deny,allow\nDeny from all");
			fclose($fp);

			XenForo_Helper_File::makeWritableByFtpUser($this->_basePath . '.htaccess');
		}

		return true;
	}

	/**
	 * @see XenDevTools_FileHandler::save
	 */
	protected function _saveTemplate($title, array $template)
	{
		$this->_createTemplateDirectory();
		$fileName = $this->_getFileName($title);
		if (!strpos($fileName, '.css'))
		{
			$fileName .= '.html';
		}

		file_put_contents($fileName, $template['template']);
		XenForo_Helper_File::makeWritableByFtpUser($fileName);

		if (!$template['last_edit_date'])
		{
			$template['last_edit_date'] = XenForo_Application::$time;
		}
		touch($fileName, $template['last_edit_date']);

		return $fileName;
	}

	/**
	 * @see XenDevTools_FileHandler::delete
	 */
	protected function _deleteTemplate($title)
	{
		$this->_createTemplateDirectory();

		$title = $this->_prepareWildcard($title);

		foreach ($title AS $_title)
		{
			if (!strpos($_title, '.css') && $_title != '*')
			{
				$_title .= '.html';
			}

			$files = glob($this->_getFileName($_title));

			if (is_array($files))
			{
				foreach ($files AS $file)
				{
					@unlink($file);
				}
			}
		}
	}

	/**
	 * Takes a parameter for the filename and turns it into an array of parameters
	 *
	 * @param mixed $item
	 *
	 * @return array
	 */
	protected function _prepareWildcard($item)
	{
		if (is_null($item))
		{
			return array('*');
		}
		else if (!is_array($item))
		{
			return array($item);
		}
		else
		{
			return $item;
		}
	}

	/**
	 * Prepares a glob-friendly filename or wildcard for the specified template(s)
	 *
	 * @param string $title
	 * @param integer $templateId
	 *
	 * @return string
	 */
	protected function _getFileName($title)
	{
		if ($title !== '*')
		{
			$title = preg_replace('/[^a-z0-9_\.-]/i', '', $title);
		}

		return sprintf('%s/%s', $this->_path, $title);
	}
}