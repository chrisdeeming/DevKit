<?php

class XenDevTools_ViewAdmin_Download extends XenForo_ViewAdmin_Base
{
	public function renderRaw()
	{
		$this->setDownloadFileName($this->_params['fileName']);

		$this->_response->setHeader('Content-type', 'application/x-zip', true);
		$this->_response->setHeader('Content-Length', filesize($this->_params['filePath']), true);
		$this->_response->setHeader('ETag', XenForo_Application::$time, true);
		$this->_response->setHeader('X-Content-Type-Options', 'nosniff');

		return new XenForo_FileOutput($this->_params['filePath']);
	}
}