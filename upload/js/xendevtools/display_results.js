/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.ToolSubmit = function($element) { this.__construct($element); };
	XenForo.ToolSubmit.prototype =
	{
		__construct: function($form)
		{
			this.form = $form;

			$form.bind(
			{
				AutoValidationComplete: $.context(this, 'ajaxSuccess')
			});
		},

		submitData: function(e)
		{

			/*url = this.form.attr('action');

			postData = {
				data: this.form.find('textarea').val(),
				tool: $('.Tool').val()
			};

			this.xhr = XenForo.ajax(
				url, postData,
				$.context(this, 'ajaxSuccess'),
				{ error: true }
			);*/
		},

		ajaxSuccess: function(e)
		{
			e.preventDefault();

			if (XenForo.hasResponseError(e.ajaxData))
			{
				return false;
			};

			$(e.ajaxData.templateHtml).xfInsert('replaceAll', this.form.find('.CodeContainer'));
		}
	};

	// Register form controls
	XenForo.register('.ToolSubmit', 'XenForo.ToolSubmit');
}
(jQuery, this, document);