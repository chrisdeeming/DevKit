/**
 * @todo The order of methods in this class is somewhat random.
 */

//TODO: Capitalise all lowercase IDs required by Javascript (#templateEditor etc.)

/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.AdvancedTextArea = function($textarea) { this.__construct($textarea); };
	XenForo.AdvancedTextArea.prototype =
	{
		__construct: function ($textarea)
		{
			this.textarea = $textarea;

			this.initialize();
		},

		initialize: function()
		{
			var initUndo = function($textarea)
				{
					if (!$textarea.data('undoStack'))
					{
						$textarea.data('undoStack', [[$textarea.val(), 0, 0]]);
					}
				},
				pushUndo = function($textarea, resetRedo)
				{
					var textarea = $textarea[0],
						stack = $textarea.data('undoStack');

					if (!stack)
					{
						stack = [];
					}

					stack.push(
						[$textarea.val(), textarea.selectionStart, textarea.selectionEnd]
					);

					$textarea.data('undoStack', stack);
					if (resetRedo)
					{
						$textarea.data('redoStack', []);
					}
				},
				popUndo = function($textarea)
				{
					var textarea = $textarea[0],
						undoStack = $textarea.data('undoStack'),
						undo,
						redoStack = $textarea.data('redoStack');

					if (!redoStack)
					{
						redoStack = [];
					}
					redoStack.push(
						[$textarea.val(), textarea.selectionStart, textarea.selectionEnd]
					);
					$textarea.data('redoStack', redoStack);

					if (undoStack && undoStack.length)
					{
						undo = undoStack.pop();
						$textarea.val(undo[0]);
						textarea.selectionStart = undo[1];
						textarea.selectionEnd = undo[2];
						$textarea.data('lastSelPosition', undo[2]);
					}

				},
				popRedo = function($textarea)
				{
					var textarea = $textarea[0],
						undoStack = $textarea.data('undoStack'),
						redoStack = $textarea.data('redoStack'),
						redo;

					if (redoStack && redoStack.length)
					{
						pushUndo($textarea, false);

						redo = redoStack.pop();
						$textarea.val(redo[0]);
						textarea.selectionStart = redo[1];
						textarea.selectionEnd = redo[2];
						$textarea.data('lastSelPosition', redo[2]);
					}
				},
				updateLastSelPosition = function($textarea)
				{
					var lastSelPosition = $textarea.data('lastSelPosition'),
						pushed = false;

					if (typeof lastSelPosition != 'undefined' && !isNaN(lastSelPosition))
					{
						if ($textarea[0].selectionEnd != lastSelPosition)
						{
							pushUndo($textarea, true);
							pushed = true;
						}
					}

					setTimeout(function() {
						$textarea.data('lastSelPosition', $textarea[0].selectionEnd);
					}, 0);

					return pushed;
				};

			var self = this;
			this.textarea.on('keypress', function(e)
			{
				updateLastSelPosition($(this));
			});
			this.textarea.on('keydown', function(e)
			{
				var $this = $(this);
				initUndo($this);

				if (e.keyCode == 13)
				{
					pushUndo($this, true);
				}

				if (e.keyCode == 90 && (e.metaKey || e.ctrlKey)) // ctrl+z
				{
					e.preventDefault();

					if (e.shiftKey)
					{
						// redo
						popRedo($this);
					}
					else
					{
						// undo
						popUndo($this);
					}
				}
				else if (e.keyCode == 89 && (e.metaKey || e.ctrlKey)) // ctrl+y
				{
					// redo
					e.preventDefault();
					popRedo($this);
				}
				else if (e.keyCode == 9 && !e.metaKey && !e.ctrlKey && !e.altKey) // tab, no modifiers
				{
					e.preventDefault();

					var start = this.selectionStart,
						end = this.selectionEnd,
						val = $this.val(),
						before = val.substring(0, start),
						after = val.substring(end);

					var replace = true;
					if (start != end)
					{
						var sel = val.substring(start, end);
						if (sel.indexOf("\n") != -1)
						{
							replace = false;
							var lenAdjust = 0;

							var lastLineBreak = before.lastIndexOf("\n");
							if (lastLineBreak == -1)
							{
								// first line
								sel = before + sel;
								lenAdjust = before.length;
								before = '';
							}
							else
							{
								sel = before.substring(lastLineBreak) + sel;
								lenAdjust = before.length - lastLineBreak;
								before = before.substring(0, lastLineBreak);
							}

							if (e.shiftKey)
							{
								var regex = /(\n|^)(\t|[ ]{1,8})/g;

								if (sel.match(regex))
								{
									start -= 1;
									lenAdjust--;
								}
								sel = sel.replace(regex, "$1");
							}
							else
							{
								sel = sel.replace(/(\n|^)/g, "$1\t");
								start += 1;
								lenAdjust++;
							}

							pushUndo($this, true);
							$this.val(before + sel + after);
							this.selectionStart = start;
							this.selectionEnd = start + sel.length - lenAdjust;
						}
					}

					if (replace && !e.shiftKey)
					{
						$this.val(before + "\t" + after);
						this.selectionStart = this.selectionEnd = start + 1;
					}
				}
				else if (e.keyCode == 13 && !e.metaKey && !e.ctrlKey && !e.altKey && !e.shiftKey)
				{
					var start = this.selectionStart,
						end = this.selectionEnd,
						val = $this.val(),
						before = val.substring(0, start),
						after = val.substring(end),
						lastLineBreak = before.lastIndexOf("\n");
					var searchString = (lastLineBreak == -1 ? before : before.substring(lastLineBreak + 1)),
						match = searchString.match(/^(\s+)/);

					if (match)
					{
						e.preventDefault();
						$this.val(before + "\n" + match[1] + after);
						this.selectionStart = this.selectionEnd = start + match[1].length + 1;
					}
				}
				else if (e.keyCode == 83 && (e.ctrlKey || e.metaKey)) // ctrl/cmd+s
				{
					e.preventDefault();
					self.$saveReloadButton.click();
				}
			});
			this.textarea.on('cut paste', '.textCtrl.code', function() {
				if (!updateLastSelPosition($(this)))
				{
					pushUndo($(this), true);
				}
			});
		}
	};

// *********************************************************************

	XenForo.register('.AdvancedTextArea', 'XenForo.AdvancedTextArea');
}
(jQuery, this, document);