<?php

class XenDevTools_Helper_Minify
{
	function __construct($source = '')
	{
		if ($source !== '')
		{
			$this->addSource($source);
		}
	}

	/**
	 * addSource
	 * Adds the filepath to be minified
	 *
	 * @param    string
	 */
	public function addSource($source)
	{
		$this->source = $source;
		if (!$this->can_read_source())
		{
			throw new XenForo_Exception(new XenForo_Phrase('addon_builder_source_file_x_cannot_be_loaded', array('source' => $source)), true);
		}
		if (!$this->is_valid_source())
		{
			throw new XenForo_Exception('Invalid type. This only works with JS files.', true);
		}
		$this->data = $this->get_source_data();
	}

	/**
	 * setTarget
	 * Sets where you want to save the minified file
	 *
	 * @param    string
	 */
	public function setTarget($target = '')
	{
		$this->target = ($target == '' ? $this->get_default_target() : $target);
	}

	/**
	 * exec
	 * Does the minification process
	 */
	public function exec()
	{
		if (!isset($this->source))
		{
			throw new XenForo_Exception('There is no source defined. Use the class constructor or ->addSource(\'/source-dir/source.file\') to add a source to be minified.', true);
		}
		$this->minifyJS();
		$this->save_to_file();
	}

	/**
	 * get_source_data
	 * Grabs the file data using file_get_contents
	 *
	 * @return: string
	 */
	private function get_source_data()
	{
		$data = @file_get_contents($this->source);
		if ($data === false)
		{
			throw new XenForo_Exception('Can\'t read the contents of the source file.', true);
		}
		else
		{
			return $data;
		}
	}

	/**
	 * can_read_source
	 * Tells if the source file can be readed or not
	 *
	 * @return: bool
	 */
	private function can_read_source()
	{
		return (@file_exists($this->source) && is_file($this->source) ? true : false);
	}

	/**
	 * is_valid_source
	 * Tells if the source is valid by its extension
	 *
	 * @return: bool
	 */
	private function is_valid_source()
	{
		return preg_match('/^.*\.(js)$/i', $this->source);
	}

	/**
	 * minifyJS
	 * Sets the minified data for JavaScript
	 */
	private function minifyJS()
	{
		$this->set_minified_data($this->get_minified_data($this->data));
	}

	/**
	 * get_default_target
	 * Set the default file.min.ext from $this->source
	 *
	 * @return: string
	 */
	private function get_default_target()
	{
		throw new XenForo_Exception('There is no target defined.', true);
	}

	/**
	 * get_minified_data
	 * Returns the minified $this->data
	 *
	 * @return: string
	 */
	private function get_minified_data($data)
	{
		$compiler = XenForo_Helper_Http::getClient('http://closure-compiler.appspot.com/compile');
		$compiler->setConfig(array(
			'options' => array(
				'use_ssl' => false
			),
			'timeout' => 600
		));

		$compiler->setMethod(Zend_Http_Client::POST);
		$compiler->setParameterPost(array(
			'js_code' => $data,
			'compilation_level' => 'SIMPLE_OPTIMIZATIONS',
			'output_format' => 'text',
			'output_info' => 'compiled_code',
			'warning_level' => 'QUIET'
		));

		$request = $compiler->request();
		$compiled = $request->getBody();

		return $compiled;
	}

	/**
	 * set_minified_data
	 * Sets $this->minified_data and unset the $this->data
	 */
	private function set_minified_data($string)
	{
		$this->minified_data = $string;
		unset($this->data);
	}

	/**
	 * save_to_file
	 * Saves the minified data to the target file
	 */
	private function save_to_file()
	{
		$path = '';

		$this->target = (!isset($this->target) ? $this->get_default_target() : $this->target);
		if (!isset($this->minified_data))
		{
			throw new XenForo_Exception('There is no data to write to "' . $this->target . '"', true);
		}
		if (($handler = @fopen($this->target, 'w')) === false)
		{
			throw new XenForo_Exception('Can\'t open "' . $this->target . '" for writing.', true);
		}
		if (@fwrite($handler, $this->minified_data) === false)
		{
			throw new XenForo_Exception('The file "' . $path . '" could not be written to. Check if PHP has enough permissions.', true);
		}
		@fclose($handler);
	}
}