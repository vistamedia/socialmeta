<?php
/**
 * @package     Joomla.Platform
 * @subpackage  Form
 *
 * @copyright   Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;

JFormHelper::loadFieldClass('text');

/**
 * Form Field class for the Joomla Platform.
 * Supports a URL text field
 *
 * @link   http://www.w3.org/TR/html-markup/input.url.html#input.url
 * @see    JFormRuleUrl for validation of full urls
 * @since  11.1
 */
class JFormFieldVideo extends JFormFieldText
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  11.1
	 */
	protected $type = 'Video';

	/**
	 * Method to get the field input markup.
	 *
	 * @return  string  The field input markup.
	 *
	 * @since   3.1.2 (CMS)
	 */
	protected function getInput()
	{
		// Translate placeholder text
		$hint = $this->translateHint ? JText::_($this->hint) : $this->hint;

		// Initialize some field attributes.
		$size         = !empty($this->size) ? ' size="' . $this->size . '"' : '';
		$maxLength    = !empty($this->maxLength) ? ' maxlength="' . $this->maxLength . '"' : '';
		$class        = !empty($this->class) ? ' class="' . $this->class . '"' : '';
		$readonly     = $this->readonly ? ' readonly' : '';
		$disabled     = $this->disabled ? ' disabled' : '';
		$required     = $this->required ? ' required aria-required="true"' : '';
		$hint         = $hint ? ' placeholder="' . $hint . '"' : '';
		$autocomplete = !$this->autocomplete ? ' autocomplete="off"' : ' autocomplete="' . $this->autocomplete . '"';
		$autocomplete = $autocomplete == ' autocomplete="on"' ? '' : $autocomplete;
		$autofocus    = $this->autofocus ? ' autofocus' : '';
		$spellcheck   = $this->spellcheck ? '' : ' spellcheck="false"';

		// Note that the input type "url" is suitable only for external URLs, so if internal URLs are allowed
		// we have to use the input type "text" instead.
		$inputType    = $this->element['relative'] ? 'type="text"' : 'type="url"';

		// Initialize JavaScript field attributes.
		$onchange = !empty($this->onchange) ? ' onchange="' . $this->onchange . '"' : '';

		// Including fallback code for HTML5 non supported browsers.
		JHtml::_('jquery.framework');
		JHtml::_('script', 'system/html5fallback.js', false, true);
		
		$script = '
		jQuery(document).ready(function($){
			var url = $("#'.$this->id.'").val();
			if ( url ) {
				getPropertyAndAppend ( url, "og:image", "thumb_image", "img");
				getPropertyAndAppend ( url, "og:title", "thumb_title", "html");
				getPropertyAndAppend ( url, "og:description", "thumb_description", "html");
			} else {
				jQuery("#thumb_image").attr("src", "../plugins/system/socialmeta/img/socialmeta-default-image.png");
			}
			$( "#'.$this->id.'_fetch" ).click(function() {
				var url = $("#'.$this->id.'").val();
				getPropertyAndAppend ( url, "og:video:secure_url", "jform_attribs_facebookmeta_video_secure_url");
				getPropertyAndAppend ( url, "og:video:type", "jform_attribs_facebookmeta_video_type");
				getPropertyAndAppend ( url, "og:video:height", "jform_attribs_facebookmeta_video_height");
				getPropertyAndAppend ( url, "og:video:width", "jform_attribs_facebookmeta_video_width");
				getPropertyAndAppend ( url, "og:image", "thumb_image", "img");
				getPropertyAndAppend ( url, "og:title", "thumb_title", "html");
				getPropertyAndAppend ( url, "og:description", "thumb_description", "html");
//				console.log(url);
		        $("html, body").animate({scrollTop:$(document).height()}, "slow");
		        return false;
			});
			$( "#'.$this->id.'_clear" ).click(function(){
		        $("#jform_attribs_facebookmeta_video_secure_url").val("");
		        $("#jform_attribs_facebookmeta_video_height").val("");
		        $("#jform_attribs_facebookmeta_video_width").val("");
		        $("#jform_attribs_facebookmeta_video_type").val("");
		        $("#'.$this->id.'").val("");
				$("#thumb_image").fadeOut().attr("src", "../plugins/system/socialmeta/img/socialmeta-default-image.png").fadeIn();
		        $("#thumb_title").html("");
		        $("#thumb_description").html("");
		        return false;
			});
		});

		function getPropertyAndAppend ( url, property, destination, mode )
		{
			mode = typeof mode !== "undefined" ? mode : "val";
			jQuery.getJSON("//query.yahooapis.com/v1/public/yql?" +
		    "q=SELECT%20*%20FROM%20html%20WHERE%20url=%27" + 
		    encodeURIComponent( url ) +
		    "%27%20AND%20xpath=%27descendant-or-self::meta%27&format=json&callback=?",
			function (data) {
//				console.log(data);		
			    var res = jQuery.grep(data.query.results.meta, function (video, key) {
			        return video.hasOwnProperty("property") && video.property === property
			    });
				
				if (mode == "img") {
					if (res.length > 0) {
						jQuery("#" + destination).fadeOut().attr("src", res[0].content).fadeIn();
					} else {
				        console.log(property + " not found");
					}
				} else if (mode == "html") {
					if (res.length > 0) {
						jQuery("#" + destination).html( res[0].content );
					} else {
				        console.log(property + " not found");
					}
				} else {
				    if (res.length > 1) {
//					    console.log(res[1].content);
				        jQuery("#" + destination).val( res[1].content );
				    } else if (res.length > 0) {
//					    console.log(res[0].content);
				        jQuery("#" + destination).val( res[0].content );
				    } else {
				        console.log(property + " not found");
				        jQuery("#" + destination).val( property + " not found" ).addClass("invalid");
				        jQuery("#" + destination + "-lbl").addClass("invalid");
				    }
			    }
			});
		}
		';

		$document 	= JFactory::getDocument();
		$document->addScriptDeclaration($script);

		return '<div class="input-append"><input ' . $inputType . ' name="' . $this->name . '"' . $class . ' id="' . $this->id . '" value="'
			. htmlspecialchars(JStringPunycode::urlToUTF8($this->value), ENT_COMPAT, 'UTF-8') . '"' 
			. $size . $disabled . $readonly
			. $hint . $autocomplete . $autofocus 
			. $spellcheck . $onchange . $maxLength 
			. $required . ' /><a href="#" class="btn" id="'.$this->id.'_fetch">'.JText::_('PLG_SYSTEM_SOCIALMETA_FETCH_PROPERTIES').'</a><a href="#" class="btn" id="'.$this->id.'_clear">'.JText::_('PLG_SYSTEM_SOCIALMETA_CLEAR').'</a></div><span class="clearfix"></span>'
			. '<div class="media"><div id="videoscreen"><a class="pull-left" href="#"><img class="media-object" src="" id="thumb_image" width="200" height="100"></a></div>'
			. '<div class="media-body hidden-phone"><h4 class="media-heading" id="thumb_title"></h4><span id="thumb_description"></span></div></div>';
	}
}
