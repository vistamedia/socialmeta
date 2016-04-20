<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.Socialmeta
 *
 * @copyright   Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @copyright   Copyright (C) 2016 Emmanuel Danan. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @TODO		Rewrite most of this creating an overridable meta object containing all the names/properties
 */

defined('_JEXEC') or die;

/**
 * Joomla! Socialmeta plugin.
 *
 * @since  1.0
 */
class PlgSystemSocialmeta extends JPlugin
{
	protected $defaultimage = '';
	protected $fbappid = '';
	protected $facebookmeta_auth = '';
	protected $facebookmeta_pub = '';
	protected $facebookmeta_twittersite = '';
	protected $facebookmeta_admin = '';
	protected $facebookmeta_titlelimit = '';
	protected $facebookmeta_desclimit = '';
	protected $db;
	protected $autoloadLanguage = true;

	/**
	 * Constructor.
	 *
	 * @param   object  &$subject  The object to observe.
	 * @param   array   $config    An optional associative array of configuration settings.
	 *
	 * @since   1.0
	 */
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);

		// Set some vars...
		$this->defaultimage 						= $this->params->get('facebookmeta_defaultimage','');
		$this->fbappid 									= $this->params->get('facebookmeta_appid','');
		$this->facebookmeta_auth				= $this->params->get('facebookmeta_default_userid','');
		$this->facebookmeta_pub					= $this->params->get('facebookmeta_pageid','');
		$this->facebookmeta_twittersite	= $this->params->get('facebookmeta_twittersite','');
		$this->facebookmeta_admin				= $this->params->get('facebookmeta_appadmin','');
		$this->facebookmeta_titlelimit	= $this->params->get('facebookmeta_titlelimit', 68);
		$this->facebookmeta_desclimit		= $this->params->get('facebookmeta_desclimit', 200);

		// Get the application if not done by JPlugin. This may happen during upgrades from Joomla 2.5.
		if (empty($this->app))
		{
			$this->app = JFactory::getApplication();
		}

	}

	/**
	 * Add the metatags.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	public function onBeforeCompileHead()
	{
		$document 	= JFactory::getDocument();
		$config 	= JFactory::getConfig();
		$jinput 	= JFactory::getApplication()->input;
		$option		= $jinput->get('option', '', 'CMD');
		$view 		= $jinput->get('view', '', 'CMD');
		$context	= $option . '.' . $view;
		$id 			= (int)$jinput->get('id', '', 'CMD');

		$objectype	= "article"; // set a default object type

 		// Add some JS to the forms and Exclude meta creation in the Admin
		if ($this->app->isAdmin())
		{
			// A small test to integrate a character counter for the title
			$script = "jQuery(document).ready(function($){
						$('#jform_attribs_facebookmeta_title').characterCounter({
							limit: ".$this->facebookmeta_titlelimit.",
							counterFormat: '%1 ".JText::_('PLG_SYSTEM_SOCIALMETA_CHARSLEFT')."'
						});
						$('#jform_attribs_facebookmeta_desc').characterCounter({
							limit: ".$this->facebookmeta_desclimit.",
							counterFormat: '%1 ".JText::_('PLG_SYSTEM_SOCIALMETA_CHARSLEFT')."'
						});
						$('#jform_params_facebookmeta_title').characterCounter({
							limit: ".$this->facebookmeta_titlelimit.",
							counterFormat: '%1 ".JText::_('PLG_SYSTEM_SOCIALMETA_CHARSLEFT')."'
						});
						$('#jform_params_facebookmeta_desc').characterCounter({
							limit: ".$this->facebookmeta_desclimit.",
							counterFormat: '%1 ".JText::_('PLG_SYSTEM_SOCIALMETA_CHARSLEFT')."'
						});
					  });
					  ";
			$document->addScript(JURI::root( true ) . '/plugins/system/socialmeta/js/jquery.charactercounter.js');
			$document->addScriptDeclaration($script);

			// css to style the counter
			$css = "span.exceeded { color: #E00B0B; } .help-block { font-size: 11px; }";
			$document->addStyleDeclaration($css);

			return true;
		}

		// Don't process meta on RSS feeds to avoid crashes
        if ($jinput->get('format', '', 'CMD') == 'feed')
        {
            return true;
        }


		// Find the language code of your page
		$lang 	= JFactory::getLanguage();
		$locale = $lang->getTag();
		$locale = str_replace('-', '_', $locale);

		// We intialize the meta image property with the default image if set
		if ($this->defaultimage)
		{
			$size 				= getimagesize(JURI::base() . $this->defaultimage);
			$metaimage 			= '<meta property="og:image" content="' . JURI::base() . $this->defaultimage .'" />';
			$metaimagewidth 	= '<meta property="og:image:width" content="' . $size[0] .'" />';
			$metaimageheight 	= '<meta property="og:image:height" content="' . $size[1] .'" />';
			$metaimagemime	 	= '<meta property="og:image:type" content="' . $size['mime'] .'" />';
		}

		$metaurl 		= '<meta property="og:url" content="' . JURI::current() .'" />';
		$metatype 		= '<meta property="og:type" content="article" />';
		$metatypetw 	= '<meta name="twitter:card" content="summary_large_image" />';
		$metasitename	= '<meta property="og:site_name" content="' . $config->get( 'sitename' ) .'" />';
		$metalocale		= '<meta property="og:locale" content="' . $locale .'" />';
		if ($this->fbappid) {
			$metafbappid 	= '<meta property="fb:app_id" content="'.$this->fbappid.'" />';
		} else {
			$metafbappid = '';
		}
		if ($this->facebookmeta_admin) 	{
			$metafbadmins 	= '<meta property="fb:admins" content="'.$this->facebookmeta_admin.'" />';
		} else {
			$metafbadmins		= '';
		}

/*
echo '<pre>';
print_r($context);
echo '</pre>';
*/

		// Restrict the context
		if ( ( $option == 'com_content' && $view == 'article') || ( $option == 'com_flexicontent' && $view == 'item') ) {
			$article 		= $this->getObjectContent($id);
			$article->tags 	= new JHelperTags;
			$category		= $this->getObjectContent($article->catid, 'category');

			// Add the tags to the article object
			if (!empty($article->id))
			{
				$article->tags->getItemTags('com_content.article', $article->id);
			}
			$attribs = json_decode($article->attribs);

			// we set the article type as default type if no data is provided
			$facebookmeta_ogtype				= @$attribs->facebookmeta_og_type ? $attribs->facebookmeta_og_type : "article";
			$facebookmeta_image					= @$attribs->facebookmeta_image;
			$facebookmeta_title					= @$attribs->facebookmeta_title;
			$facebookmeta_desc					= @$attribs->facebookmeta_desc;
			$facebookmeta_author				= $this->getUserFacebookProfile ( $article->created_by );
			$facebookmeta_authortw			= $this->getUserTwitterProfile ( $article->created_by );
			$facebookmeta_seealso1			= @$attribs->facebookmeta_seealso1;
			$facebookmeta_seealso2			= @$attribs->facebookmeta_seealso2;
			$facebookmeta_seealso3			= @$attribs->facebookmeta_seealso3;
			$facebookmeta_video					= @$attribs->facebookmeta_video;
			$facebookmeta_video_type		= @$attribs->facebookmeta_video_type;
			$facebookmeta_video_width		= @$attribs->facebookmeta_video_width;
			$facebookmeta_video_height	= @$attribs->facebookmeta_video_height;


			// We have to set the article sharing image https://developers.facebook.com/docs/sharing/best-practices#images
			if ($facebookmeta_image) {
				$size 						= getimagesize(JURI::base() . $facebookmeta_image);
				$metaimage 				= '<meta property="og:image" content="' . JURI::base() . $facebookmeta_image .'" />';
				$metaimagewidth 	= '<meta property="og:image:width" content="' . $size[0] .'" />';
				$metaimageheight 	= '<meta property="og:image:height" content="' . $size[1] .'" />';
				$metaimagemime	 	= '<meta property="og:image:type" content="' . $size['mime'] .'" />';
			}
			if ($article->modified) {
				$metaupdated  = '<meta property="og:updated_time" content="'. $this->to8601($article->modified) . '" />';
			} else {
				$metaupdated	= '';
			}
			if ($this->facebookmeta_auth) {
				$metaauth  	= '<meta property="article:author" content="'. ( $facebookmeta_author ? $facebookmeta_author : $this->facebookmeta_auth ) . '" />';
			} else {
				$metaauth		= '';
			}
			if ($this->facebookmeta_twittersite) {
				$metaauthtw 	= '<meta name="twitter:site" content="'. ( $facebookmeta_authortw ? $facebookmeta_authortw : $this->facebookmeta_twittersite ) . '" />';
			} else {
				$metaauthtw		= '';
			}
			if ($this->facebookmeta_pub) {
				$metapublisher  	= '<meta property="article:publisher" content="'. $this->facebookmeta_pub . '" />';
			}
			$metasection  			= '<meta property="article:section" content="'. $category->title . '" />';
			$metapub					= array();
			$metapub['modified']  		= '<meta property="article:modified_time" content="'. $this->to8601($article->modified) . '" />';
			$metapub['publish_ub']		= '<meta property="article:published_time" content="'. $this->to8601($article->publish_up) . '" />';
			if ($article->publish_down != '0000-00-00 00:00:00') {
				$metapub['publish_down']	= '<meta property="article:expiration_time" content="'. $this->to8601($article->publish_down) . '" />';
			}
			if (count($article->tags->itemTags)) {
				$metatags = array();
				foreach ($article->tags->itemTags as $tag) {
					$metatags[] = '<meta property="article:tag" content="' . $tag->title .'" />';
				}
			}
			if ($facebookmeta_seealso1) {
				$metaseealso1  	= '<meta property="og:see_also" content="'. JURI::base().substr(JRoute::_(ContentHelperRoute::getArticleRoute($facebookmeta_seealso1,$article->catid)), strlen(JURI::base(true)) + 1) . '" />';
			}
			if ($facebookmeta_seealso2) {
				$metaseealso2  	= '<meta property="og:see_also" content="'. JURI::base().substr(JRoute::_(ContentHelperRoute::getArticleRoute($facebookmeta_seealso2,$article->catid)), strlen(JURI::base(true)) + 1) . '" />';
			}
			if ($facebookmeta_seealso3) {
				$metaseealso3  	= '<meta property="og:see_also" content="'. JURI::base().substr(JRoute::_(ContentHelperRoute::getArticleRoute($facebookmeta_seealso3,$article->catid)), strlen(JURI::base(true)) + 1) . '" />';
			}

			// We create the video object if video link has been provided
			if ($facebookmeta_video) {
				$url_scheme 		= parse_url($facebookmeta_video, PHP_URL_SCHEME); // hhtp || https
				$metavideo 			= $facebookmeta_video ? '<meta property="og:video" content="'.$facebookmeta_video.'" />' : "";
				$metavideotw 		= $facebookmeta_video ? '<meta name="twitter:video" content="'.$facebookmeta_video.'" />' : "";
				$metavideotype 		= ($facebookmeta_video_type == ("application/x-shockwave-flash" || "video/mp4")) ? '<meta property="og:video:type" content="'.$facebookmeta_video_type.'" />' : "";
				$metavideoheight 	= ((int)$facebookmeta_video_height != 0) ? '<meta property="og:video:height" content="'.$facebookmeta_video_height.'" />' : "";
				$metavideoheighttw 	= ((int)$facebookmeta_video_height != 0) ? '<meta name="twitter:player:height" content="'.$facebookmeta_video_height.'" />' : "";
				$metavideowidth 	= ((int)$facebookmeta_video_width != 0) ? '<meta property="og:video:width" content="'.$facebookmeta_video_width.'" />' : "";
				$metavideowidthtw 	= ((int)$facebookmeta_video_width != 0) ? '<meta name="twitter:player:width" content="'.$facebookmeta_video_width.'" />' : "";
				if ($metavideotype && $metavideoheight && $metavideowidth) {

					if ( $url_scheme == "https" ) { // && type == should be treated as a video object
						$metavideosecureourl	= '<meta property="og:video:secure_url" content="'.$facebookmeta_video.'" />';
						if ($facebookmeta_ogtype == "video") {
							// && type == should be treated as a video object
							$metatype 				= '<meta property="og:type" content="video" />';
							$metatypetw				= '<meta name="twitter:card" content="player" />';
						}

					} else {
						$metavideosecureourl = "";
					}

				}
			}

			// We use the title of the article if none is provided
			if ($facebookmeta_title) {
				$metatitle = '<meta property="og:title" content="' . $this->striptagsandcut ( $facebookmeta_title ) .'" />';
			} else {
				$metatitle = '<meta property="og:title" content="' . $this->striptagsandcut ( $article->title, $this->facebookmeta_titlelimit ) .'" />';
			}
			// We use the introtext field if none is provided
			if ($facebookmeta_desc) {
				$metadesc = '<meta property="og:description" content="' . $this->striptagsandcut ( $facebookmeta_desc ) .'" />';
			} else {
				$metadesc = '<meta property="og:description" content="' . $this->striptagsandcut ( $article->introtext, $this->facebookmeta_desclimit ) .'" />';
			}
		}

		$document->addCustomTag('<!-- BOF Facebookmeta plugin for Joomla! https://github.com/vistamedia/socialmeta -->');
		// og:site_name
		if ($this->params->get('og_site_name',1)) {
			$document->addCustomTag('<!-- og common meta -->');
			$document->addCustomTag($metasitename);
		}
		// og:type
		if ($this->params->get('og_type',1)) {
			$document->addCustomTag($metatype);
		}
		// og:url
		if ($this->params->get('og_url',1)) {
			$document->addCustomTag($metaurl);
		}
		// og:locale
		if ($this->params->get('og_locale',1)) {
			$document->addCustomTag($metalocale);
		}
		// og:title
		if ($this->params->get('og_title',1)) {
			$document->addCustomTag($metatitle);
		}
		// og:description
		if ($this->params->get('og_description',1)) {
			$document->addCustomTag($metadesc);
		}
		// og:updated_time
		if ($this->params->get('og_updated_time',1)) {
			$document->addCustomTag($metaupdated);
		}
		// og:image
		if ($this->params->get('og_image',1) && @$metaimage) {
			$document->addCustomTag($metaimage);
			// og:image:width
			$document->addCustomTag($metaimagewidth);
			// og:image:height
			$document->addCustomTag($metaimageheight);
			// og:image:type
			$document->addCustomTag($metaimagemime);
		}
		// og:video (has sub-properties)
		if ($this->params->get('og_video',1)) {
			if (@$metavideo) {
				$document->addCustomTag($metavideo);
			}
			if (@$metavideosecureourl) {
				$document->addCustomTag($metavideosecureourl);
			}
			if (@$metavideotype) {
				$document->addCustomTag($metavideotype);
			}
			if (@$metavideowidth) {
				$document->addCustomTag($metavideowidth);
			}
			if (@$metavideoheight) {
				$document->addCustomTag($metavideoheight);
			}
		}
		// og:see_also (array)
		if ($this->params->get('og_see_also',1)) {
			if (@$metaseealso1) {
				$document->addCustomTag($metaseealso1);
			}
			if (@$metaseealso2) {
				$document->addCustomTag($metaseealso2);
			}
			if (@$metaseealso3) {
				$document->addCustomTag($metaseealso3);
			}
		}

		if ($facebookmeta_ogtype == "article") {
			$document->addCustomTag('<!-- og:article specific meta -->');
			// article:author
			if ($this->params->get('article_author',1)) {
				$document->addCustomTag(@$metaauth);
			}
			// article:publisher
			if ($this->params->get('article_publisher',1)) {
				$document->addCustomTag(@$metapublisher);
			}

			// article:modified_time || article:published_time || article:expiration_time
			if ($this->params->get('article_published_time',1)) {
				foreach ($metapub as $m) {
					$document->addCustomTag($m);
				}
			}
			// article:section
			if ($this->params->get('article_section',1)) {
				$document->addCustomTag($metasection);
			}
			// article:tag (array)
			if ($this->params->get('article_tag',1)) {
				if (@$metatags) {
					foreach ($metatags as $metatag) {
						$document->addCustomTag($metatag);
					}
				}
			}
		}

		// fb:app_id
		if ($this->params->get('fb_app_id',1)) {
			$document->addCustomTag('<!-- Facebook specific -->');
			$document->addCustomTag($metafbappid);
		}
		// fb:admins
		if ($this->params->get('fb_admins',1)) {
			$document->addCustomTag($metafbadmins);
		}

		// twitter:card
		if ($this->params->get('twitter_card',1)) {
			$document->addCustomTag('<!-- Twitter Specific -->');
			$document->addCustomTag($metatypetw);
		}
		// twitter:site
		if ($this->params->get('twitter_site',1)) {
			$document->addCustomTag($metaauthtw);
		}
		// twitter:video
		if ($facebookmeta_ogtype == "video") {
			if ($this->params->get('twitter_video',1)) {
				if ($metavideo) {
					$document->addCustomTag($metavideotw);
				}
				if ($metavideowidth) {
					$document->addCustomTag($metavideowidthtw);
				}
				if ($metavideoheight) {
					$document->addCustomTag($metavideoheighttw);
				}
			}
		}

		$document->addCustomTag('<!-- EOF Socialmeta plugin for Joomla! https://github.com/vistamedia/socialmeta -->');
	}

	/**
	 * Add the forms.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	function onContentPrepareForm($form, $data) {
		$app = JFactory::getApplication();
		$option = $app->input->get('option');

		switch($option) {
			case 'com_content':
				if ($app->isAdmin()) {
					JForm::addFormPath(__DIR__ . '/forms');
					$form->loadFile('com_content', false);
				}
				return true;
			case 'com_contact':
				if ($app->isAdmin()) {
					JForm::addFormPath(__DIR__ . '/forms');
					$form->loadFile('com_contact', false);
				}
				return true;
			case 'com_flexicontent':
				if ($app->isAdmin()) {
					JForm::addFormPath(__DIR__ . '/forms');
					$form->loadFile('com_flexicontent', false);
				}
				return true;
		}
		return true;
	}

	/**
	 * Method to retrieve the main data object of a component.
	 *
	 * @param 	int 		$id
	 * @param 	string 		$table
	 * @return  object.
	 *
	 * @since   1.0
	 */
	private function getObjectContent($id, $table = 'content')
	{
		$db = JFactory::getDbo ();

		$dataobject	= JTable::getInstance($table);
		$dataobject->load($id);

		return $dataobject;
	}

	/**
	 * Strip html tags and cut after x characters
	 * Borrowed from FLEXIcontent www.flexicontent.org
	 *
	 * @param 	string 		$text
	 * @param 	int 		$nb
	 * @return 	string
	 * @since 1.0
	 */
	private function striptagsandcut( $text, $chars=null, &$uncut_length=0 )
	{
		// Convert html entities to characters so that they will not be removed ... by strip_tags
		$text = html_entity_decode ($text, ENT_NOQUOTES, 'UTF-8');

		// Strip SCRIPT tags AND their containing code
		$text = preg_replace( '#<script\b[^>]*>(.*?)<\/script>#is', '', $text );

		// Add whitespaces at start/end of tags so that words will not be joined,
		//$text = preg_replace('/(<\/[^>]+>((?!\P{L})|(?=[0-9])))|(<[^>\/][^>]*>)/u', ' $1', $text);
		$text = preg_replace('/(<\/[^>]+>(?![\:|\.|,|:|"|\']))|(<[^>\/][^>]*>)/u', ' $1', $text);

		// Strip html tags
		$cleantext = strip_tags($text);

		// clean additionnal plugin tags
		$patterns = array();
		$patterns[] = '#\[(.*?)\]#';
		$patterns[] = '#{(.*?)}#';
		$patterns[] = '#&(.*?);#';

		foreach ($patterns as $pattern) {
			$cleantext = preg_replace( $pattern, '', $cleantext );
		}

		// Replace multiple spaces, tabs, newlines, etc with a SINGLE whitespace so that text length will be calculated correctly
		$cleantext = preg_replace('/[\p{Z}\s]{2,}/u', ' ', $cleantext);  // Unicode safe whitespace replacing

		// Calculate length according to UTF-8 encoding
		$uncut_length = JString::strlen($cleantext);

		// Cut off the text if required but reencode html entities before doing so
		if ($chars) {
			if ($uncut_length > $chars) {
				$cleantext = JString::substr( $cleantext, 0, $chars ).'...';
			}
		}

		// Reencode HTML special characters, (but do not encode UTF8 characters)
		$cleantext = htmlspecialchars($cleantext, ENT_QUOTES, 'UTF-8');

		return $cleantext;
	}

	/**
	 * Returns a formated ISO8601 date
	 *
	 * @param 	string 		$datetime
	 * @return 	string
	 * @since 1.0
	 */
	private function to8601 ( $datetime )
	{
		$date = new DateTime( $datetime );
		return $date->format(DateTime::ISO8601);
	}

	/**
	 * Returns a the facebookprofile store in the contact
	 *
	 * @TODO: getExternalProperties(userid,property)
	 * @param 	int 		$userid
	 * @return 	string		Facebook profile URL of the user
	 * @since 1.0
	 */
	private function getUserFacebookProfile ( $userid )
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('params');
		$query->from($db->quoteName('#__contact_details'));
		$query->where($db->quoteName('user_id')." = ".$db->quote($userid));

		// Reset the query using our newly populated query object.
		$db->setQuery($query);
		$userparams = $db->loadResult();

		if ($userparams) {
			$userparams = json_decode($userparams);
			$fbprofile = $userparams->facebookmeta_fbuserprofile ? $userparams->facebookmeta_fbuserprofile : '';
		} else {
			$fbprofile = '';
		}

		return $fbprofile;
	}

	/**
	 * Returns a the facebookprofile store in the contact
	 *
	 * @TODO: getExternalProperties(userid,property)
	 * @param 	int 		$userid
	 * @return 	string		Facebook profile URL of the user
	 * @since 1.0
	 */
	private function getUserTwitterProfile ( $userid )
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('params');
		$query->from($db->quoteName('#__contact_details'));
		$query->where($db->quoteName('user_id')." = ".$db->quote($userid));

		// Reset the query using our newly populated query object.
		$db->setQuery($query);
		$userparams = $db->loadResult();

		if ($userparams) {
			$userparams = json_decode($userparams);
			$twprofile = $userparams->facebookmeta_twitteruser ? $userparams->facebookmeta_twitteruser : '';
		} else {
			$twprofile = '';
		}

		return $twprofile ? $twprofile : '';
	}
}
