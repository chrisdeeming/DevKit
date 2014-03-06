<?php

class XenDevTools_Listener
{
	public static function extendAddOnController($class, array &$extend)
	{
		$extend[] = 'XenDevTools_ControllerAdmin_AddOn';
	}

	public static function extendPhraseController($class, array &$extend)
	{
		$extend[] = 'XenDevTools_ControllerAdmin_Phrase';
	}

	public static function extendLanguageController($class, array &$extend)
	{
		$extend[] = 'XenDevTools_ControllerAdmin_Language';
	}

	public static function extendAddOnModel($class, array &$extend)
	{
		$extend[] = 'XenDevTools_Model_AddOn';
	}

	public static function extendPhraseModel($class, array &$extend)
	{
		$extend[] = 'XenDevTools_Model_Phrase';
	}
}

