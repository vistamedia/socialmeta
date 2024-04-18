<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.Socialmeta
 *
 * @copyright   Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @copyright   Copyright (C) 2016 Emmanuel Danan. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @TODO        Rewrite most of this creating an overridable meta object containing all the names/properties
 * @TODO        Allow the form to be triggered from com_categories & com_menus
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;
version_compare(JVERSION, '4.0', 'lt')
    ? require_once JPATH_SITE . '/components/com_content/helpers/route.php'
    : require_once JPATH_SITE . '/components/com_content/src/Helper/RouteHelper.php';

/**
 * Joomla! Socialmeta plugin.
 *
 * @since  1.0
 */
#[AllowDynamicProperties] //php8.2 compatibility
class PlgSystemSocialmeta extends JPlugin
{
	protected $defaultimage = '';
	protected $fbappid = '';
	protected $facebookmeta_auth = '';
	protected $facebookmeta_pub = '';
	protected $facebookmeta_twittersite = '';
	protected $facebookmeta_googleplus = '';
	protected $facebookmeta_googlepluslogo = '';
	protected $facebookmeta_admin = '';
	protected $facebookmeta_titlelimit = '';
	protected $facebookmeta_desclimit = '';
	protected $facebookmeta_article_image = '';
	protected $flexicontent_image_field = '';
	protected $flexicontent_imagesize_field = '';
	protected $db;
	protected $autoloadLanguage = true;
	private $app;

	/**
	 * Constructor.
	 *
	 * @param   object  &$subject  The object to observe.
	 * @param   array    $config   An optional associative array of configuration settings.
	 *
	 * @throws Exception
	 * @since   1.0
	 *
	 */
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);

		// Set some vars...
		$this->defaultimage                 = $this->params->get('facebookmeta_defaultimage', '');
		$this->fbappid                      = $this->params->get('facebookmeta_appid', '');
		$this->facebookmeta_auth            = $this->params->get('facebookmeta_default_userid', '');
		$this->facebookmeta_pub             = $this->params->get('facebookmeta_pageid', '');
		$this->facebookmeta_twittersite     = $this->params->get('facebookmeta_twittersite', '');
		$this->facebookmeta_googleplus      = $this->params->get('facebookmeta_googleplus', '');
		$this->facebookmeta_googlepluslogo  = $this->params->get('facebookmeta_googlepluslogo', '');
		$this->facebookmeta_admin           = $this->params->get('facebookmeta_appadmin', '');
		$this->facebookmeta_titlelimit      = $this->params->get('facebookmeta_titlelimit', 68);
		$this->facebookmeta_desclimit       = $this->params->get('facebookmeta_desclimit', 200);
		$this->facebookmeta_article_image   = $this->params->get('facebookmeta_article_image', 2);
		$this->flexicontent_image_field     = $this->params->get('facebookmeta_flexicontent_image_field', '');
		$this->flexicontent_imagesize_field = $this->params->get('facebookmeta_flexicontent_imagesize_field', 'medium');

		// Get the application if not done by JPlugin. This may happen during upgrades from Joomla 2.5.
		$this->app = $this->app ?? JFactory::getApplication();

		// Is superadmin Flag
		$this->isSuperAdmin = JFactory::getUser()->authorise('core.admin', 'root.1');
		// Is debug Flag
		$this->debug = JDEBUG || $this->isSuperAdmin;
	}

	/**
	 * Add the facebook/linkedin fix.
	 * It disable the gzip compression for both user agents
	 *
	 * Found in SocialMetaTags plugin https://github.com/hans2103/pkg_SocialMetaTags
	 * Borrowed from https://github.com/dgt41/facebookfix
	 *
	 * @return  void
	 *
	 * @since   1.1
	 */
	function onAfterRoute()
	{
		if ($this->app->isClient('administrator'))
		{
			return;
		}

		$unsupported = false;

		if (isset($_SERVER['HTTP_USER_AGENT']))
		{
			/* Facebook User Agent
			* facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)
			* LinkedIn User Agent
			* LinkedInBot/1.0 (compatible; Mozilla/5.0; Jakarta Commons-HttpClient/3.1 +http://www.linkedin.com)
			*/
			$pattern = strtolower('/facebookexternalhit|LinkedInBot/x');

			if (preg_match($pattern, strtolower($_SERVER['HTTP_USER_AGENT'])))
			{
				$unsupported = true;
			}
		}

		if (($this->app->get('gzip') == 1) && $unsupported)
		{
			$this->app->set('gzip', 0);
		}
	}

	/**
	 * Add the metatags.
	 *
	 * @return  void|bool
	 *
	 * @throws Exception
	 * @since   1.0
	 */
	public function onBeforeCompileHead()
	{
		// Common API instances
		$document = JFactory::getDocument();
		$config   = JFactory::getConfig();
		$jinput   = JFactory::getApplication()->input;
		$lang     = JFactory::getLanguage();

		// Common view DATA (component and view names)
		$option   = $jinput->get('option', '', 'CMD');
		$view     = $jinput->get('view', '', 'CMD');

		// For views that have custom VIEW and custom ID ...
		$view_actual = $jinput->get('view_actual', '', 'CMD');
		$id_actual   = (int) $jinput->get('id_actual', '', 'CMD');
		$view = $view_actual ?: $view;

		/**
		 * Add some JS to the forms and Exclude meta creation in the Admin
		 */
		if ($this->app->isClient('administrator'))
		{
			// A small test to integrate a character counter for the title
			$script = "jQuery(document).ready(function($){
				$('#jform_attribs_facebookmeta_title, #jform_params_facebookmeta_title').characterCounter({
					limit: " . $this->facebookmeta_titlelimit . ",
					counterFormat: '%1 " . Text::_('PLG_SYSTEM_SOCIALMETA_CHARSLEFT') . "'
				});
				$('#jform_attribs_facebookmeta_desc, #jform_params_facebookmeta_desc').characterCounter({
					limit: " . $this->facebookmeta_desclimit . ",
					counterFormat: '%1 " . Text::_('PLG_SYSTEM_SOCIALMETA_CHARSLEFT') . "'
				});
			});
			";
			$document->addScript(JURI::root(true) . '/plugins/system/socialmeta/js/jquery.charactercounter.js');
			$document->addScriptDeclaration($script);

			// css to style the counter
			$css = "
				span.exceeded { color: #E00B0B; }
				.counter { padding-left: 15px; font-size: 11px; }
				.media-body { padding-left: 15px; }
				#videoscreen { background-image: url(../plugins/system/socialmeta/img/screen-mini.png); width:300px; height:246px; float: left; }
				#videoscreen img { padding: 11px 0 0 11px !important; width: 278px !important; height: 157px !important; }
			";
			$document->addStyleDeclaration($css);

			return true;
		}


		/**
		 * Don't process meta on non-HTML formats, e.g. RSS feeds
		 */
		if ($jinput->get('format', 'html', 'CMD') !== 'html')
		{
			return true;
		}


		/**
		 * Check current component - view pair is supported
		 */
		$viewConfig = $this->_loadXmlConfig($option, $view);
		if (!$viewConfig)
		{
			return true;
		}


		/**
		 * Create google microdata
		 */
		$googledata               = new StdClass();
		$googledata->{'@context'} = 'http://schema.org/';
		$googledata->{'@type'}    = (string) $viewConfig->microdata->attributes()['type']; // TODO modify this to use microdata per flexicontent type

		// Add Google structured data for publishers
		if (!empty($this->facebookmeta_googleplus))
		{
			$googledata->publisher            = new StdClass();
			$googledata->publisher->{'@type'} = 'Organization';
			$googledata->publisher->name      = $this->facebookmeta_googleplus;
		}
		if (!empty($this->facebookmeta_googlepluslogo) && $size = @ getimagesize(JPath::clean(JPATH_SITE . '/' . $this->facebookmeta_googlepluslogo)))
		{
			$googledata->publisher->logo             = new StdClass();
			$googledata->publisher->logo->{'@type'}  = 'ImageObject';
			$googledata->publisher->logo->url        = JURI::base() . $this->facebookmeta_googlepluslogo;
			$googledata->publisher->logo->width      = $size[0];
			$googledata->publisher->logo->height     = $size[1];
			$googledata->publisher->logo->fileFormat = $size['mime'];
		}


		/**
		 * Find the language code of your page
		 */
		$locale = JPluginHelper::isEnabled('system', 'languagecode')
			? $this->getNewLanguageCode($lang->getTag())
			: $lang->getTag();
		$locale = str_replace('-', '_', $locale);


		/**
		 * We initialize the meta image property with the default image if set - (plugin configuration)
		 */
		if ($this->defaultimage && $size = @ getimagesize(JPath::clean(JPATH_SITE . '/' . $this->defaultimage)))
		{
			$metaimage                     = '<meta property="og:image" content="' . JURI::base() . $this->defaultimage . '" />';
			$metaimagetw                   = '<meta name="twitter:image" content="' . JURI::base() . $this->defaultimage . '" />';
			$metaimagewidth                = '<meta property="og:image:width" content="' . $size[0] . '" />';
			$metaimageheight               = '<meta property="og:image:height" content="' . $size[1] . '" />';
			$metaimagemime                 = '<meta property="og:image:type" content="' . $size['mime'] . '" />';
			$googledata->image             = new StdClass();
			$googledata->image->{'@type'}  = 'ImageObject';
			$googledata->image->url        = JURI::base() . $this->defaultimage;
			$googledata->image->width      = $size[0];
			$googledata->image->height     = $size[1];
			$googledata->image->fileFormat = $size['mime'];
		}

		/**
		 * We initialize other meta data if set - (plugin configuration)
		 */
		$metaurl                      = '<meta property="og:url" content="' . JURI::getInstance()->toString() . '" />';
		$googledata->mainEntityOfPage = JURI::current();
		$metatype                     = '<meta property="og:type" content="article" />';
		//$metatypetw                   = '<meta name="twitter:card" content="summary_large_image" />';  // THIS IS NOT WORKING, use line below
		$metatypetw                   = '<meta name="twitter:card" content="summary" />';
		$metasitename                 = '<meta property="og:site_name" content="' . $config->get('sitename') . '" />';
		$metalocale                   = '<meta property="og:locale" content="' . $locale . '" />';
		$googledata->inLanguage       = $lang->getTag();
		$metafbappid                  = $this->fbappid ? '<meta property="fb:app_id" content="' . $this->fbappid . '" />' : '';
		$metafbadmins                 = $this->facebookmeta_admin ? '<meta property="fb:admins" content="' . $this->facebookmeta_admin . '" />' : '';


		/**
		 * Default handler, handles com_content.article, com_flexicontent.item and standard pattern views ...
		 */
		$is_com_content      = ($option == 'com_content' && $view == 'article') || ($option == 'com_flexicontent' && $view == 'item');
		$use_default_handler = $is_com_content || true;  // ... TODO examine more

		if ($use_default_handler)
		{
			// Component - View context and ID of record
			$context      = $option . '.' . $view;
			$id           = (int) $jinput->get('id', '', 'CMD');
			// For views that have custom ID ...
			$id = $id_actual ?: $id;

			// Tags context, J-Table name, J-Table prefix
			$tags_context = $is_com_content ? 'com_content.content' : $context;
			$jtable       = (string) $viewConfig->dbdata->attributes()['jtable'];
			$prefix       = (string) $viewConfig->dbdata->attributes()['prefix'];

			// Load item and category data from correct j-table
			Table::addIncludePath(JPATH_ADMINISTRATOR . '/component/' . $option . '/tables');

			$article    = $this->getObjectContent($id, $jtable, $prefix);
			if (!$article) return;
			//echo '<pre>'; print_r($article); exit;

			// Get category but do not abort if none
			$category   = !empty($article->catid) ? $this->getObjectContent($article->catid, 'category') : false;
			//if (!$category) return;

			// Non com-content components use description field
			$title_dbcol       = (string) $viewConfig->title->attributes()['dbcolumn'];
			$introtext_dbcol   = (string) $viewConfig->introtext->attributes()['dbcolumn'];
			$fulltext_dbcol    = (string) $viewConfig->fulltext->attributes()['dbcolumn'];

			$image_conf        = $viewConfig->image;
			$image_dbcol       = !empty($image_conf) ? (string) $image_conf->attributes()['dbcolumn'] : false;
			$image_srcprop     = !empty($image_conf) ? (string) $image_conf->attributes()['sourceproperty'] : false;
			$image_isjson      = !empty($image_conf) ? (int) $image_conf->attributes()['isjson'] : 0;
			$image_ismultiple  = !empty($image_conf) ? (int) $image_conf->attributes()['ismultiple'] : 0;

			$article_title     = $article->{$title_dbcol};
			$article_introtext = $article->{$introtext_dbcol};
			$article_fulltext  = $article->{$fulltext_dbcol};
			$article_image     = $image_dbcol ? $article->{$image_dbcol} : false;

			// Get item's category and (for com-content only) decode json of (intro/full)-text images
			$images   = $is_com_content ? json_decode($article->images) : (object)['image_fulltext'=>'', 'image_intro'=>''];

			// Logic and pattern partially borrowed from SocialMetaTags plugin https://github.com/hans2103/pkg_SocialMetaTags
			$facebookmeta_image = '';
			if ($this->facebookmeta_article_image != 0)
			{
				/**
				 * Get img tag from article description text(s)
				 * Priority (high to low): [0] via configuration, [1] (intro/full)-text images (com-content only), [2] introtext, [3] fulltext
				 */
				// [0]
				if ($article_image)
				{
					$facebookmeta_image = $image_isjson ? json_decode($article_image, true) : $article_image;
					$facebookmeta_image = $facebookmeta_image && $image_ismultiple ? reset($facebookmeta_image) : $facebookmeta_image;
					$facebookmeta_image = $facebookmeta_image && $image_srcprop ? $facebookmeta_image[$image_srcprop] : $facebookmeta_image;
				}

				// [1]
				if (!$facebookmeta_image)
				{
					$facebookmeta_image = $this->facebookmeta_article_image == 2 && !empty($images->image_fulltext) ? $images->image_fulltext : $facebookmeta_image;
					$facebookmeta_image = $this->facebookmeta_article_image == 1 && !empty($images->image_intro) ? $images->image_intro : $facebookmeta_image;
				}

				// [2]
				if (!$facebookmeta_image && strpos($article_introtext, '<img') !== false)
				{
					preg_match('/(?<!_)src=([\'"])?(.*?)\\1/', $article_introtext, $articleimages);
					$facebookmeta_image = $articleimages[2];
				}

				// [3]
				if (!$facebookmeta_image && strpos($article_fulltext, '<img') !== false)
				{
					// Get img tag from article
					preg_match('/(?<!_)src=([\'"])?(.*?)\\1/', $article_fulltext, $articleimages);
					$facebookmeta_image = $articleimages[2];
				}
			}

			// Add the tags to the article object
			if (!empty($article->id))
			{
				$article->tags = new JHelperTags;
				$article->tags->getItemTags($tags_context, $article->id);
			}

			// Get current record data
			// NOTE: Current item attributes will be empty if the edit form has not been saved after the plugin was installed and enabled
			$form_fields_group = $viewConfig->form->attributes()['fields_group'] ?? false;
			try {
				$attribs = $form_fields_group ? json_decode($article->{$form_fields_group}) : false;
			} catch (\Throwable $e){
				$attribs = new stdClass();
			}

			// we set the article type as default type if no data is provided
			$facebookmeta_ogtype           = @$attribs->facebookmeta_og_type ? $attribs->facebookmeta_og_type : "article";
			$facebookmeta_image            = !empty($attribs->facebookmeta_image) ? $attribs->facebookmeta_image : $facebookmeta_image;
			$facebookmeta_title            = @$attribs->facebookmeta_title;
			$facebookmeta_desc             = @$attribs->facebookmeta_desc;
			$facebookmeta_author           = $this->getUserFacebookProfile($article->created_by);
			$facebookmeta_authortw         = $this->getUserTwitterProfile($article->created_by);
			$facebookmeta_seealso1         = @$attribs->facebookmeta_seealso1;
			$facebookmeta_seealso2         = @$attribs->facebookmeta_seealso2;
			$facebookmeta_seealso3         = @$attribs->facebookmeta_seealso3;
			$facebookmeta_video            = @$attribs->facebookmeta_video;
			$facebookmeta_video_secure_url = @$attribs->facebookmeta_video_secure_url;
			$facebookmeta_video_type       = @$attribs->facebookmeta_video_type;
			$facebookmeta_video_width      = @$attribs->facebookmeta_video_width;
			$facebookmeta_video_height     = @$attribs->facebookmeta_video_height;


			// Check image is absolute url
			$facebookmeta_image_is_abs   = (boolean) parse_url($facebookmeta_image, PHP_URL_SCHEME); // preg_match("#^http|^https#i", $facebookmeta_image);
			// Check image is local site
			$facebookmeta_image_is_local = !$facebookmeta_image_is_abs || strpos($facebookmeta_image, JURI::base()) === 0;

			// Get full image url and full image file path, if external URL then we use the URL itself as file path getimagesize() supports this
			if ($facebookmeta_image && $facebookmeta_image_is_local)
			{
				$facebookmeta_image_url  = $facebookmeta_image_is_abs ? $facebookmeta_image : JURI::base() . $facebookmeta_image;
				$facebookmeta_image_file = $facebookmeta_image_is_abs
					? str_replace(JURI::base(), JPATH_SITE . '/', $facebookmeta_image)
					: JPATH_SITE . '/' . $facebookmeta_image;
			}
			else
			{
				$facebookmeta_image_file = $facebookmeta_image;
				$facebookmeta_image_url  = $facebookmeta_image;
			}

			// !! Do not try to get size of external image URLs to avoid random long delays
			$size = $facebookmeta_image_file && $facebookmeta_image_is_local ? @ getimagesize(JPath::clean($facebookmeta_image_file)) :false;

			// We have to set the article sharing image https://developers.facebook.com/docs/sharing/best-practices#images
			if ($facebookmeta_image_url)
			{
				$metaimage       = '<meta property="og:image" content="' . $facebookmeta_image_url . '" />';
				$metaimagetw     = '<meta name="twitter:image" content="' . $facebookmeta_image_url . '" />';
				$metaimagewidth  = $size ? '<meta property="og:image:width" content="' . $size[0] . '" />' : '';
				$metaimageheight = $size ? '<meta property="og:image:height" content="' . $size[1] . '" />' : '';
				$metaimagemime   = $size ? '<meta property="og:image:type" content="' . $size['mime'] . '" />' : '';

				$googledata->image             = new StdClass();
				$googledata->image->{'@type'}  = 'ImageObject';
				$googledata->image->url        = $facebookmeta_image_url;
				if ($size)
				{
					$googledata->image->width = $size[0];
					$googledata->image->height = $size[1];
					$googledata->image->fileFormat = $size['mime'];
				}
			}

			$metaupdated  = $article->modified
				? '<meta property="og:updated_time" content="' . $this->to8601($article->modified) . '" />' : '';
			$metaauth     = $this->facebookmeta_auth
				? '<meta property="article:author" content="' . ($facebookmeta_author ? $facebookmeta_author : $this->facebookmeta_auth) . '" />'
					//. "\n" . '	<meta content="' . ($facebookmeta_author ? $facebookmeta_author : $this->facebookmeta_auth) . '" property="author" >'
				: '';

			$googledata->author            = new StdClass();
			$googledata->author->{'@type'} = 'Person';
			$googledata->author->name      = !empty($article->created_by_alias) ? $article->created_by_alias : $this->getUserName($article->created_by);

			$metaauthtw    = $this->facebookmeta_twittersite
				? '<meta name="twitter:site" content="' . ($facebookmeta_authortw ? $facebookmeta_authortw : $this->facebookmeta_twittersite) . '" />' : '';
			$metapublisher = $this->facebookmeta_pub
				? '<meta property="article:publisher" content="' . $this->facebookmeta_pub . '" />' : '';

			$metasection                = $category ? '<meta property="article:section" content="' . $category->title . '" />' : '';
			$googledata->articleSection = $category ? $category->title : '';
			$metapub                    = array();
			$metapub['modified']        = '<meta property="article:modified_time" content="' . $this->to8601($article->modified) . '" />';
			$googledata->dateModified   = $this->to8601($article->modified);
			$article->publish_up        = !empty($article->publish_up) && $article->publish_up != '0000-00-00 00:00:00'
				? $article->publish_up
				: (!empty($article->created) ? $article->created : '');

			$metapub['publish_up']      = '<meta property="article:published_time" content="' . $this->to8601($article->publish_up) . '" />'
				//. "\n" . '	<meta content="' . $this->to8601($article->publish_up) . '" property="pubdate">'
				;
			$googledata->datePublished  = $this->to8601($article->publish_up);
			if (!empty($article->publish_down) && $article->publish_down != '0000-00-00 00:00:00')
			{
				$metapub['publish_down'] = '<meta property="article:expiration_time" content="' . $this->to8601($article->publish_down) . '" />';
			}
			if (!empty($article->tags->itemTags))
			{
				$metatags    = array();
				$articletags = array();
				foreach ($article->tags->itemTags as $tag)
				{
					$metatags[]    = '<meta property="article:tag" content="' . $tag->title . '" />';
					$articletags[] = $tag->title;
				}
				$googledata->keywords = implode(',', $articletags);
			}
			if ($facebookmeta_seealso1)
			{
				$metaseealso1 = '<meta property="og:see_also" content="' . JURI::base() . substr(JRoute::_(ContentHelperRoute::getArticleRoute($facebookmeta_seealso1, $article->catid)), strlen(JURI::base(true)) + 1) . '" />';
			}
			if ($facebookmeta_seealso2)
			{
				$metaseealso2 = '<meta property="og:see_also" content="' . JURI::base() . substr(JRoute::_(ContentHelperRoute::getArticleRoute($facebookmeta_seealso2, $article->catid)), strlen(JURI::base(true)) + 1) . '" />';
			}
			if ($facebookmeta_seealso3)
			{
				$metaseealso3 = '<meta property="og:see_also" content="' . JURI::base() . substr(JRoute::_(ContentHelperRoute::getArticleRoute($facebookmeta_seealso3, $article->catid)), strlen(JURI::base(true)) + 1) . '" />';
			}

			// We create the video object if video link has been provided
			if ($facebookmeta_video)
			{
				$url_scheme        = parse_url($facebookmeta_video, PHP_URL_SCHEME); // hhtp || https
				$metavideo         = $facebookmeta_video ? '<meta property="og:video" content="' . htmlentities($facebookmeta_video) . '" />' : "";
				$metavideotw       = $facebookmeta_video ? '<meta name="twitter:player" content="' . htmlentities($facebookmeta_video) . '" />' : "";
				$metavideotype     = ($facebookmeta_video_type == ("application/x-shockwave-flash" || "video/mp4")) ? '<meta property="og:video:type" content="' . $facebookmeta_video_type . '" />' : "";
				$metavideoheight   = ((int) $facebookmeta_video_height != 0) ? '<meta property="og:video:height" content="' . $facebookmeta_video_height . '" />' : "";
				$metavideoheighttw = ((int) $facebookmeta_video_height != 0) ? '<meta name="twitter:player:height" content="' . $facebookmeta_video_height . '" />' : "";
				$metavideowidth    = ((int) $facebookmeta_video_width != 0) ? '<meta property="og:video:width" content="' . $facebookmeta_video_width . '" />' : "";
				$metavideowidthtw  = ((int) $facebookmeta_video_width != 0) ? '<meta name="twitter:player:width" content="' . $facebookmeta_video_width . '" />' : "";
				if ($metavideotype && $metavideoheight && $metavideowidth)
				{

					if ($url_scheme == "https")
					{ // && type == should be treated as a video object
						$metavideosecureourl = '<meta property="og:video:secure_url" content="' . htmlentities($facebookmeta_video_secure_url ? $facebookmeta_video_secure_url : $facebookmeta_video) . '" />';
						if ($facebookmeta_ogtype == "video")
						{
							// && type == should be treated as a video object
							$metatype   = '<meta property="og:type" content="video" />';
							$metatypetw = '<meta name="twitter:card" content="player" />';
						}

					}
					else
					{
						$metavideosecureourl = "";
					}
				}
			}

			// We use the title of the article if none is provided
			if ($facebookmeta_title)
			{
				$metatitle            = '<meta property="og:title" content="' . $this->striptagsandcut($facebookmeta_title) . '" />';
				$metattitletw         = '<meta name="twitter:title" content="' . $this->striptagsandcut($facebookmeta_title) . '" />';
				$googledata->headline = $this->striptagsandcut($facebookmeta_title);
				$googledata->name     = $this->striptagsandcut($facebookmeta_title);
			}
			else
			{
				$metatitle            = '<meta property="og:title" content="' . $this->striptagsandcut($article_title, $this->facebookmeta_titlelimit) . '" />';
				$metattitletw         = '<meta name="twitter:title" content="' . $this->striptagsandcut($article_title, $this->facebookmeta_titlelimit) . '" />';
				$googledata->headline = $this->striptagsandcut($this->striptagsandcut($article_title, $this->facebookmeta_titlelimit));
				$googledata->name     = $this->striptagsandcut($this->striptagsandcut($article_title, $this->facebookmeta_titlelimit));
			}
			// We use the introtext or description field if none is provided
			if ($facebookmeta_desc)
			{
				$metadesc                = '<meta property="og:description" content="' . $this->striptagsandcut($facebookmeta_desc) . '" />';
				$metadesctw              = '<meta name="twitter:description" content="' . $this->striptagsandcut($facebookmeta_desc) . '" />';
				$googledata->description = $this->striptagsandcut($facebookmeta_desc);
			}
			elseif (!empty($article->metadesc))
			{
				$metadesc                = '<meta property="og:description" content="' . $this->striptagsandcut($article->metadesc) . '" />';
				$metadesctw              = '<meta name="twitter:description" content="' . $this->striptagsandcut($article->metadesc) . '" />';
				$googledata->description = $this->striptagsandcut($article->metadesc);
			}
			else
			{
				$metadesc                = '<meta property="og:description" content="' . $this->striptagsandcut($article_introtext, $this->facebookmeta_desclimit) . '" />';
				$metadesctw              = '<meta name="twitter:description" content="' . $this->striptagsandcut($article_introtext, $this->facebookmeta_desclimit) . '" />';
				$googledata->description = $this->striptagsandcut($article_introtext, $this->facebookmeta_desclimit);
			}

			// com_flexicontent specific routine to overrride image
			if (($option == 'com_flexicontent' && $view == 'item'))
			{

				if ($this->flexicontent_image_field)
				{

					$image_field = $this->getFCfieldname($this->flexicontent_image_field);

					require_once(JPATH_SITE . DS . 'components' . DS . 'com_flexicontent' . DS . 'classes' . DS . 'flexicontent.fields.php');

					$thumb_size = $this->flexicontent_imagesize_field;

					$thumbs_arr =
						FlexicontentFields::renderFields(
							$item_per_field = true,
							$itemids = array($article->id),
							$field_names = array($image_field),
							$view = 'item',
							$field_methods = array('display_' . $thumb_size . '_src'),
							$cfparams = array()
						);

					$thumb = $thumbs_arr[$article->id][$image_field]['display_' . $thumb_size . '_src'];

					if (!empty($thumb) && $size = @ getimagesize(JPath::clean(JPATH_SITE . '/' . substr($thumb, strlen(JURI::base(true)) + 1))))
					{
						// Override the og:image properties
						$metaimage                     = '<meta property="og:image" content="' . JURI::base() . substr($thumb, strlen(JURI::base(true)) + 1) . '" />';
						$metaimagetw                   = '<meta name="twitter:image" content="' . JURI::base() . substr($thumb, strlen(JURI::base(true)) + 1) . '" />';
						$metaimagewidth                = '<meta property="og:image:width" content="' . $size[0] . '" />';
						$metaimageheight               = '<meta property="og:image:height" content="' . $size[1] . '" />';
						$metaimagemime                 = '<meta property="og:image:type" content="' . $size['mime'] . '" />';
						$googledata->image             = new StdClass();
						$googledata->image->{'@type'}  = 'ImageObject';
						$googledata->image->url        = JURI::base() . substr($thumb, strlen(JURI::base(true)) + 1);
						$googledata->image->width      = $size[0];
						$googledata->image->height     = $size[1];
						$googledata->image->fileFormat = $size['mime'];
					}
				}
			}
		}


		$document->addCustomTag('<!-- BOF Socialmeta plugin for Joomla! https://github.com/vistamedia/socialmeta -->');

		if ($this->params->get('structureddata', 1))
		{
			$document->addCustomTag('<!-- Google structured data -->');
			$document->addCustomTag('<script type="application/ld+json">' . json_encode($googledata) . '</script>');
		}

		// og:site_name
		if ($this->params->get('og_site_name', 1))
		{
			$document->addCustomTag('<!-- og common meta -->');
			$document->addCustomTag($metasitename);
		}
		// og:type
		if ($this->params->get('og_type', 1))
		{
			$document->addCustomTag($metatype);
		}
		// og:url
		if ($this->params->get('og_url', 1))
		{
			$document->addCustomTag($metaurl);
		}
		// og:locale
		if ($this->params->get('og_locale', 1))
		{
			$document->addCustomTag($metalocale);
		}
		// og:title
		if ($this->params->get('og_title', 1))
		{
			$document->addCustomTag($metatitle);
		}
		// og:description
		if ($this->params->get('og_description', 1))
		{
			$document->addCustomTag($metadesc);
		}
		// og:updated_time
		if ($this->params->get('og_updated_time', 1))
		{
			$document->addCustomTag($metaupdated);
		}
		// og:image
		if ($this->params->get('og_image', 1) && @$metaimage)
		{
			$document->addCustomTag($metaimage);
            if (!empty($metaimagetw)) $document->addCustomTag($metaimagetw);
			// og:image:width
			$document->addCustomTag($metaimagewidth);
			// og:image:height
			$document->addCustomTag($metaimageheight);
			// og:image:type
			$document->addCustomTag($metaimagemime);
		}
		// og:video (has sub-properties)
		if ($this->params->get('og_video', 1))
		{
			if (@$metavideo)
			{
				$document->addCustomTag($metavideo);
			}
			if (@$metavideosecureourl)
			{
				$document->addCustomTag($metavideosecureourl);
			}
			if (@$metavideotype)
			{
				$document->addCustomTag($metavideotype);
			}
			if (@$metavideowidth)
			{
				$document->addCustomTag($metavideowidth);
			}
			if (@$metavideoheight)
			{
				$document->addCustomTag($metavideoheight);
			}
		}
		// og:see_also (array)
		if ($this->params->get('og_see_also', 1))
		{
			if (@$metaseealso1)
			{
				$document->addCustomTag($metaseealso1);
			}
			if (@$metaseealso2)
			{
				$document->addCustomTag($metaseealso2);
			}
			if (@$metaseealso3)
			{
				$document->addCustomTag($metaseealso3);
			}
		}

		if ($facebookmeta_ogtype == "article")
		{
			$document->addCustomTag('<!-- og:article specific meta -->');
			// article:author
			if ($this->params->get('article_author', 1))
			{
				$document->addCustomTag(@$metaauth);
			}
			// article:publisher
			if ($this->params->get('article_publisher', 1))
			{
				$document->addCustomTag(@$metapublisher);
			}

			// article:modified_time || article:published_time || article:expiration_time
			if ($this->params->get('article_published_time', 1))
			{
				foreach ($metapub as $m)
				{
					$document->addCustomTag($m);
				}
			}
			// article:section
			if ($this->params->get('article_section', 1))
			{
				$document->addCustomTag($metasection);
			}
			// article:tag (array)
			if ($this->params->get('article_tag', 1))
			{
				if (@$metatags)
				{
					foreach ($metatags as $metatag)
					{
						$document->addCustomTag($metatag);
					}
				}
			}
		}

		// fb:app_id
		if ($this->params->get('fb_app_id', 1))
		{
			$document->addCustomTag('<!-- Facebook specific -->');
			$document->addCustomTag($metafbappid);
		}
		// fb:admins
		if ($this->params->get('fb_admins', 1))
		{
			$document->addCustomTag($metafbadmins);
		}

		// twitter:card
		if ($this->params->get('twitter_card', 1))
		{
			$document->addCustomTag('<!-- Twitter Specific -->');
			$document->addCustomTag($metatypetw);
			$document->addCustomTag($metattitletw);
			$document->addCustomTag($metadesctw);
			if (!empty($metaimagetw)) $document->addCustomTag($metaimagetw);
		}
		// twitter:site
		if ($this->params->get('twitter_site', 1))
		{
			$document->addCustomTag($metaauthtw);
		}
		// twitter:video
		if ($facebookmeta_ogtype == "video")
		{
			if ($this->params->get('twitter_video', 1))
			{
				if ($metavideo)
				{
					$document->addCustomTag($metavideotw);
				}
				if ($metavideowidth)
				{
					$document->addCustomTag($metavideowidthtw);
				}
				if ($metavideoheight)
				{
					$document->addCustomTag($metavideoheighttw);
				}
			}
		}

		$document->addCustomTag('<!-- EOF Socialmeta plugin for Joomla! https://github.com/vistamedia/socialmeta -->');
	}

	/**
	 * Get the modified language code (URL segment) if the languagecode plugin is enabled
	 *
	 * @param    string  $tag  the language tag
	 *
	 * @return   string        the modified language tag (used as URL segment)
	 *
	 * @since 1.1
	 */
	private function getNewLanguageCode($tag = 'en-GB')
	{
		$db = JFactory::getDBO();

		// Load language parameters
		$params = $db->setQuery($db->getQuery(true)
			->select('params')
			->from($db->quoteName('#__extensions'))
			->where($db->quoteName('name') . " = " . $db->quote('plg_system_languagecode'))
		)->loadResult();

		// Parse the parameters
		$lcparams = new JRegistry($params);

		// Return the modified language tag (used as URL segment) (if it exists, otherwise return the given tag)
		return $lcparams->get(strtolower($tag)) ?: $tag;
	}

	/**
	 * Method to retrieve the main data object of a component.
	 *
	 * @param   int     $id
	 * @param   string  $table
	 * @param   string  $prefix
	 *
	 * @return  object.
	 *
	 * @since   1.0
	 */
	private function getObjectContent($id, $table = 'content', $prefix = 'JTable')
	{
		// Load record data
		$dataobject = Table::getInstance($table, $prefix);
		$dataobject ? $dataobject->load($id) : false;

		if ($this->debug && !$dataobject)
		{
			JFactory::getApplication()->enqueueMessage('socialmeta plg: Could not load JTable: ' . $prefix.$table, 'notice');
		}

		return $dataobject;
	}

	/**
	 * Returns the Facebook profile stored in the contact
	 *
	 * @TODO  : getExternalProperties(userid,property)
	 *
	 * @param   int  $userid
	 *
	 * @return    string        Facebook profile URL of the user
	 * @since 1.0
	 */
	private function getUserFacebookProfile($userid)
	{
		$db = JFactory::getDbo();

		// Load user parameters of given user id
		$userparams = $db->setQuery($db->getQuery(true)
			->select('params')
			->from($db->quoteName('#__contact_details'))
			->where($db->quoteName('user_id') . " = " . $db->quote($userid))
		)->loadResult();

		// Decode them
		$userparams = $userparams ? json_decode($userparams) : false;

		// Return the Facebook profile URL (if it exists, otherwise empty)
		return $userparams
			? (!empty($userparams->facebookmeta_fbuserprofile) ? $userparams->facebookmeta_fbuserprofile : '')
			: '';
	}

	/**
	 * Returns the Twitter profile stored in the contact
	 *
	 * @TODO  : getExternalProperties(userid,property)
	 *
	 * @param   int  $userid
	 *
	 * @return    string        Twitter profile URL of the user
	 * @since 1.0
	 */
	private function getUserTwitterProfile($userid)
	{
		$db = JFactory::getDbo();

		// Load user parameters of given user id
		$userparams = $db->setQuery($db->getQuery(true)
			->select('params')
			->from($db->quoteName('#__contact_details'))
			->where($db->quoteName('user_id') . " = " . $db->quote($userid))
		)->loadResult();

		// Decode them
		$userparams = $userparams ? json_decode($userparams) : false;

		// Return the Twitter profile URL (if it exists, otherwise empty)
		return $userparams
			? (!empty($userparams->facebookmeta_twitteruser) ? $userparams->facebookmeta_twitteruser : '')
			: '';
	}

	/**
	 * Returns a formated ISO8601 date
	 *
	 * @param   string  $datetime
	 *
	 * @return    string
	 * @since 1.0
	 */
	private function to8601($datetime)
	{
		$date = new DateTime($datetime);

		return $date->format(DateTime::ISO8601);
	}

	/**
	 * Returns the name of a user
	 *
	 * @TODO  : getExternalProperties(userid,property)
	 *
	 * @param   int  $userid
	 *
	 * @return    string        Facebook profile URL of the user
	 * @since 1.0
	 */
	private function getUserName($userid)
	{
		$db = JFactory::getDbo();
		return $db->setQuery($db->getQuery(true)
			->select('name')
			->from($db->quoteName('#__users'))
			->where($db->quoteName('id') . " = " . $db->quote($userid))
		)->loadResult();
	}

	/**
	 * Strip html tags and cut after x characters
	 * Borrowed from FLEXIcontent www.flexicontent.org
	 *
	 * @param   string  $text
	 * @param   int     $nb
	 *
	 * @return    string
	 * @since 1.0
	 */
	private function striptagsandcut($text, $chars = null, &$uncut_length = 0)
	{
		// Convert html entities to characters so that they will not be removed ... by strip_tags
		$text = html_entity_decode($text, ENT_NOQUOTES, 'UTF-8');

		// Strip SCRIPT tags AND their containing code
		$text = preg_replace('#<script\b[^>]*>(.*?)<\/script>#is', '', $text);

		// Add whitespaces at start/end of tags so that words will not be joined,
		//$text = preg_replace('/(<\/[^>]+>((?!\P{L})|(?=[0-9])))|(<[^>\/][^>]*>)/u', ' $1', $text);
		$text = preg_replace('/(<\/[^>]+>(?![\:|\.|,|:|"|\']))|(<[^>\/][^>]*>)/u', ' $1', $text);

		// Strip html tags
		$cleantext = strip_tags($text);

		// clean additionnal plugin tags
		$patterns   = array();
		$patterns[] = '#\[(.*?)\]#';
		$patterns[] = '#{(.*?)}#';
		$patterns[] = '#&(.*?);#';

		foreach ($patterns as $pattern)
		{
			$cleantext = preg_replace($pattern, '', $cleantext);
		}

		// Replace multiple spaces, tabs, newlines, etc with a SINGLE whitespace so that text length will be calculated correctly
		$cleantext = preg_replace('/[\p{Z}\s]{2,}/u', ' ', $cleantext);  // Unicode safe whitespace replacing

		// Calculate length according to UTF-8 encoding
		$uncut_length = \Joomla\String\StringHelper::strlen($cleantext);

		// Cut off the text if required but reencode html entities before doing so
		if ($chars)
		{
			if ($uncut_length > $chars)
			{
				$cleantext = \Joomla\String\StringHelper::substr($cleantext, 0, $chars) . '...';
			}
		}

		// Reencode HTML special characters, (but do not encode UTF8 characters)
		$cleantext = htmlspecialchars($cleantext, ENT_QUOTES, 'UTF-8');

		return $cleantext;
	}

	/**
	 * Check if the image field is an image and is published
	 *
	 * @param   string  $fieldname
	 *
	 * @return    void        true on success
	 *
	 * @since 1.0
	 */
	private function getFCfieldname($id)
	{
		$db = JFactory::getDBO();

		return $db->setQuery($db->getQuery(true)
			->select('name')
			->from($db->quoteName('#__flexicontent_fields'))
			->where($db->quoteName('id') . " = " . $db->quote($id))
		)->loadResult();
	}

	/**
	 * Add the social-meta form
	 *
	 * @param $form  object|false  The JForm object
	 *
	 * @return bool|void
	 *
	 * @throws Exception
	 * @since version
	 */
	function onContentPrepareForm($form)
	{
		// Common
		$jinput  = JFactory::getApplication()->input;
		$option  = $jinput->get('option', '', 'CMD');
		$view    = $jinput->get('view', '', 'CMD');
		$context = $form->getName();

		// Sanity check form has been loaded
		if (!($form instanceof JForm))
		{
			$this->_subject->setError('JERROR_NOT_A_FORM');
			return false;
		}

		// Allow social meta editing only in backend
		if (!$this->app->isClient('administrator'))
		{
			return;
		}

		// Check current component - view pair is supported
		$viewConfig = $this->_loadXmlConfig($option, $view, $context);
		if (!$viewConfig)
		{
			return true;
		}

		// Load the XML form file of current component - view
		// Default xpath is /form/.../field
		$form_fields_group  = $viewConfig->form->attributes()['fields_group'] ?? false;
		$xml_elements_xpath = '/form/fields' . ($form_fields_group ? "[@name='{$form_fields_group}']" : '');

		// Load the XML form file of current component - view, limit to specific XML elements xpath
		JForm::addFormPath(__DIR__ . '/forms');
		$form->loadFile($option, false, $xml_elements_xpath);

		return true;
	}

	/**
	 * Returns the optimal size thumbnail or raise an error if the field is not an image
	 * NOTE: experimental, not used yet
	 *
	 * @param   string  $fieldname
	 *
	 * @return    void        true on success
	 *
	 * @since 1.0
	 */
	private function decideFCimageFieldThumb($fieldname)
	{
		$db   = JFactory::getDBO();
		$data = $db->setQuery(
			'SELECT attribs #__flexicontent_fields WHERE `name`=' . $db->Quote($fieldname)
		)->loadAssocList();

		if (!$data)
		{
			if ($this->debug)
			{
				JFactory::getApplication()->enqueueMessage('FLEXIcontent image field name : ' . $fieldname . ' was not found', 'notice');
			}

			return false;
		}

		$fparams = new JRegistry($data->attribs);

		$w_l = $fparams->get('w_l');
		$h_l = $fparams->get('h_l');

		$sizes      = array('s', 'm', 'l');
		$size_found = false;

		foreach ($sizes as $size)
		{
			$width  = $fparams->get('w_' . $size);
			$height = $fparams->get('h_' . $size);
			if ($width < 400 || $width > 1000) continue;
			if ($height < 300 || $height > 800) continue;
			$size_found = $size;
			break;
		}

		return $size_found;
	}

	/**
	 * Check if the image field is an image and is published
	 *
	 * @param   string  $fieldname
	 *
	 * @return    void        true on success
	 *
	 * @since 1.0
	 */
	private function isValidImageField($fieldname)
	{
		$db   = JFactory::getDBO();
		$data = $db->setQuery($db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__flexicontent_fields'))
			->where($db->quoteName('name') . " = " . $db->quote($fieldname))
		)->loadObject();

		if (!$data) // does the field exist
		{
			if ($this->debug) JFactory::getApplication()->enqueueMessage('FLEXIcontent field : ' . $fieldname . ' was not found', 'notice');
			return false;
		}
		elseif ($data->field_type !== 'image') // is the field an image field
		{
			if ($this->debug) JFactory::getApplication()->enqueueMessage('FLEXIcontent field : ' . $fieldname . ' is not an image', 'notice');
			return false;
		}
		elseif ($data->published != 1) // is the field published
		{
			if ($this->debug) JFactory::getApplication()->enqueueMessage('FLEXIcontent image field name : ' . $fieldname . ' is unpublised', 'notice');
			return false;
		}

		return true;
	}


	/**
	 * Load and check that the XML form file for the given component-view pair
	 *
	 * @param   $option     string  The component name
	 * @param   $view       string  The view name
	 * @param   $context    string  The form context
	 * @return  false|SimpleXMLElement
	 *
	 * @since version
	 */
	private function _loadXmlConfig($option, $view, $context = null)
	{
		if ($context)
		{
			list($option, $view) = explode('.', $context);
		}

		/**
		 * Check current component is supported
		 */
		$component_form_path = JPath::clean(JPATH_ROOT.'/plugins/system/socialmeta/forms/' . $option .'.xml');
		if (!file_exists($component_form_path))
		{
			return false;
		}


		/**
		 * Attempt to parse the XML file
		 */
		try {
			$xml = simplexml_load_file($component_form_path);
		} catch (\Throwable $e) {
			$xml = false;
		}

		if (!$xml)
		{
			if ($this->debug) $this->app->enqueueMessage('Error parsing component XML file: "' . $component_form_path . '". Social data could not be loaded', 'warning');
			return false;
		}

		// context
		/*if ($context)
		{
			foreach($xml->config->views as $config_view)
			{
				$config_view_form_context = $config_view->form->attributes()['context'] ?? false;
				if ($config_view_form_context && $config_view_form_context === $context) return $config_view;
			}
			return false;
		}*/

		/**
		 * We check if the view is allowed
		 */
		//print_r($option . ' - ' . $view); print_r($context); exit;
		$viewConfig = $xml->config->views->{$view} ?? false;
		return $viewConfig;
	}
}
