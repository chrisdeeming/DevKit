<?php

class XenDevTools_Model_ContentType extends XFCP_XenDevTools_Model_ContentType
{
	public function getContentType($contentType)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_content_type
			WHERE content_type = ?
		', $contentType);
	}

	public function getContentTypeFieldFromQuery($contentType, $contentTypeFieldName)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_content_type_field
			WHERE content_type = ?
				AND field_name = ?
		', array($contentType, $contentTypeFieldName));
	}

	public function prepareContentType(array $contentType)
	{
		$contentType['fields'] = @unserialize($contentType['fields']);

		return $contentType;
	}

	public function prepareContentTypes(array $contentTypes)
	{
		foreach ($contentTypes AS &$contentType)
		{
			$contentType = $this->prepareContentType($contentType);
		}

		return $contentTypes;
	}

	public function getContentTypeFieldNames()
	{
		return array(
			'0' => '',
			'alert_handler_class' => 'alert_handler_class',
			'attachment_handler_class' => 'attachment_handler_class',
			'edit_history_handler_class' => 'edit_history_handler_class',
			'like_handler_class' => 'like_handler_class',
			'moderation_queue_handler_class' => 'moderation_queue_handler_class',
			'moderator_handler_class' => 'moderator_handler_class',
			'moderator_log_handler_class' => 'moderator_log_handler_class',
			'news_feed_handler_class' => 'news_feed_handler_class',
			'permission_handler_class' => 'permission_handler_class',
			'report_handler_class' => 'report_handler_class',
			'search_handler_class' => 'search_handler_class',
			'sitemap_handler_class' => 'sitemap_handler_class',
			'spam_handler_class' => 'spam_handler_class',
			'stats_handler_class' => 'stats_handler_class',
			'warning_handler_class' => 'warning_handler_class'
		);
	}
}