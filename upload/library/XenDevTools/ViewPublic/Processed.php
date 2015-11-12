<?php

class XenDevTools_ViewPublic_Processed extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$this->_params['bbCodeParser'] = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
		$output = $this->_renderer->getDefaultOutputArray(get_class($this), $this->_params, $this->_templateName);

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}