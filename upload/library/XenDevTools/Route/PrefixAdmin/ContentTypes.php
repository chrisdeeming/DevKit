<?php

/**
 * Route prefix handler for editing and displaying content types in the admin control panel.
 *
 * @package ContentTypeManagement
 */
class XenDevTools_Route_PrefixAdmin_ContentTypes implements XenForo_Route_Interface
{
	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithStringParam($routePath, $request, 'content_type');
		return $router->getRouteMatch('XenDevTools_ControllerAdmin_ContentType', $action, 'development', 'contentTypeManagement');
	}

	/**
	 * Method to build a link to the specified page/action with the provided
	 * data and params.
	 *
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		return XenForo_Link::buildBasicLinkWithStringParam($outputPrefix, $action, $extension, $data, 'content_type');
	}
}