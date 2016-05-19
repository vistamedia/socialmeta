<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.Socialmeta
 *
 * @copyright   Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @copyright   Copyright (C) 2016 Emmanuel Danan. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @TODO		Rewrite most of this creating an overridable meta object containing all the names/properties
 * @TODO		Allow the form to be triggered from com_categories & com_menus
 */

defined('_JEXEC') or die;

require_once JPATH_SITE . '/components/com_content/helpers/route.php';

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
		$this->defaultimage 				= $this->params->get('facebookmeta_defaultimage','');
		$this->fbappid 						= $this->params->get('facebookmeta_appid','');
		$this->facebookmeta_auth			= $this->params->get('facebookmeta_default_userid','');
		$this->facebookmeta_pub				= $this->params->get('facebookmeta_pageid','');
		$this->facebookmeta_twittersite		= $this->params->get('facebookmeta_twittersite','');
		$this->facebookmeta_googleplus		= $this->params->get('facebookmeta_googleplus','');
		$this->facebookmeta_googlepluslogo	= $this->params->get('facebookmeta_googlepluslogo','');
		$this->facebookmeta_admin			= $this->params->get('facebookmeta_appadmin','');
		$this->facebookmeta_titlelimit		= $this->params->get('facebookmeta_titlelimit', 68);
		$this->facebookmeta_desclimit		= $this->params->get('facebookmeta_desclimit', 200);
		$this->facebookmeta_article_image	= $this->params->get('facebookmeta_article_image', 2);
		$this->flexicontent_image_field		= $this->params->get('facebookmeta_flexicontent_image_field', '');
		$this->flexicontent_imagesize_field	= $this->params->get('facebookmeta_flexicontent_imagesize_field', 'medium');

		// Get the application if not done by JPlugin. This may happen during upgrades from Joomla 2.5.
		if (empty($this->app))
		{
			$this->app = JFactory::getApplication();
		}

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
		if ($this->app->isAdmin())
		{
			return true;
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
		$id 		= (int)$jinput->get('id', '', 'CMD');
		$allowed	= array( 'com_content.article','com_flexicontent.item' );
		$googledata = new StdClass();
		$googledata->{'@context'} = 'http://schema.org/';
		$googledata->{'@type'} = 'Article';
		if (!empty($this->facebookmeta_googleplus)) {
			$googledata->publisher = new StdClass();
			$googledata->publisher->{'@type'} = 'Organization';
			$googledata->publisher->name = $this->facebookmeta_googleplus;
		}
		if (!empty($this->facebookmeta_googlepluslogo)) {
			$size 	= getimagesize(JURI::base() . $this->facebookmeta_googlepluslogo);
			$googledata->publisher->logo 				= new StdClass();
			$googledata->publisher->logo->{'@type'} 	= 'ImageObject';
			$googledata->publisher->logo->url 			= JURI::base() . $this->facebookmeta_googlepluslogo;
			$googledata->publisher->logo->width 		= $size[0];
			$googledata->publisher->logo->height 		= $size[1];
			$googledata->publisher->logo->fileFormat	= $size['mime'];
		}

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
			$css = 
				"
				span.exceeded { color: #E00B0B; } 
				.counter { padding-left: 15px; font-size: 11px; }
				.media-body { padding-left: 15px; }
				#videoscreen { background-image: url(../plugins/system/socialmeta/img/screen-mini.png); width:300px; height:246px; float: left; }
				#videoscreen img { padding: 11px 0 0 11px !important; width: 278px !important; height: 157px !important; }
				";
			$document->addStyleDeclaration($css);

			return true;
		}

		// Don't process meta on RSS feeds to avoid crashes
    if ($jinput->get('format', '', 'CMD') == 'feed')
    {
        return true;
    }

		// We check if the view is allowed
		if ( !in_array($context, $allowed) )
		{
				return true;
		}

		// Find the language code of your page
		$lang 	= JFactory::getLanguage();
		$locale = $lang->getTag();
		$languagecode_installed = JPluginHelper::isEnabled('system', 'languagecode');
		if ($languagecode_installed) {
			$locale = $this->getNewLanguageCode($locale);
		}
		$locale = str_replace('-', '_', $locale);

		// We intialize the meta image property with the default image if set
		if ($this->defaultimage)
		{
			$size 				= getimagesize(JURI::base() . $this->defaultimage);
			$metaimage 			= '<meta property="og:image" content="' . JURI::base() . $this->defaultimage .'" />';
			$metaimagewidth 	= '<meta property="og:image:width" content="' . $size[0] .'" />';
			$metaimageheight 	= '<meta property="og:image:height" content="' . $size[1] .'" />';
			$metaimagemime	 	= '<meta property="og:image:type" content="' . $size['mime'] .'" />';
			$googledata->image 				= new StdClass();
			$googledata->image->{'@type'} 	= 'ImageObject';
			$googledata->image->url 		= JURI::base() . $this->defaultimage;
			$googledata->image->width 		= $size[0];
			$googledata->image->height 		= $size[1];
			$googledata->image->fileFormat	= $size['mime'];
		}

		$metaurl 		= '<meta property="og:url" content="' . JURI::current() .'" />';
		$googledata->mainEntityOfPage = JURI::current();
		$metatype 		= '<meta property="og:type" content="article" />';
		$metatypetw 	= '<meta name="twitter:card" content="summary_large_image" />';
		$metasitename	= '<meta property="og:site_name" content="' . $config->get( 'sitename' ) .'" />';
		$metalocale		= '<meta property="og:locale" content="' . $locale .'" />';
		$googledata->inLanguage = $lang->getTag();
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

		// Handle values of the content table
		if ( ( $option == 'com_content' && $view == 'article') || ( $option == 'com_flexicontent' && $view == 'item') ) {
			$article 		= $this->getObjectContent($id);
			$article->tags 	= new JHelperTags;
			$category		= $this->getObjectContent($article->catid, 'category');
			$images			= json_decode($article->images);
			$facebookmeta_image = '';

			// Logic and pattern partially borrowed from SocialMetaTags plugin https://github.com/hans2103/pkg_SocialMetaTags
			if ($this->facebookmeta_article_image != 0) {
				// Sets default images
	            if (strpos($article->fulltext, '<img') !== false)
	            {
					// Get img tag from article
					preg_match('/(?<!_)src=([\'"])?(.*?)\\1/', $article->fulltext, $articleimages);
					$facebookmeta_image = $articleimages[2];
	            }
				if (strpos($article->introtext, '<img') !== false)
	            {
					// Get img tag from article
					preg_match('/(?<!_)src=([\'"])?(.*?)\\1/', $article->introtext, $articleimages);
					$facebookmeta_image = $articleimages[2];
	            }
				if ($this->facebookmeta_article_image == 2) {
					if (!empty($images->image_fulltext))
		            {
						$facebookmeta_image = $images->image_fulltext;
		            }
		        }
				if ($this->facebookmeta_article_image == 1) {
		            if (!empty($images->image_intro))
		            {
						$facebookmeta_image = $images->image_intro;
		            }
	            }
			}

			// Add the tags to the article object
			if (!empty($article->id))
			{
				$article->tags->getItemTags('com_content.article', $article->id);
			}
			$attribs = json_decode($article->attribs);

			// we set the article type as default type if no data is provided
			$facebookmeta_ogtype			= @$attribs->facebookmeta_og_type ? $attribs->facebookmeta_og_type : "article";
			$facebookmeta_image				= !empty($attribs->facebookmeta_image) ? $attribs->facebookmeta_image : $facebookmeta_image;
			$facebookmeta_title				= @$attribs->facebookmeta_title;
			$facebookmeta_desc				= @$attribs->facebookmeta_desc;
			$facebookmeta_author			= $this->getUserFacebookProfile ( $article->created_by );
			$facebookmeta_authortw			= $this->getUserTwitterProfile ( $article->created_by );
			$facebookmeta_seealso1			= @$attribs->facebookmeta_seealso1;
			$facebookmeta_seealso2			= @$attribs->facebookmeta_seealso2;
			$facebookmeta_seealso3			= @$attribs->facebookmeta_seealso3;
			$facebookmeta_video				= @$attribs->facebookmeta_video;
			$facebookmeta_video_secure_url	= @$attribs->facebookmeta_video_secure_url;
			$facebookmeta_video_type		= @$attribs->facebookmeta_video_type;
			$facebookmeta_video_width		= @$attribs->facebookmeta_video_width;
			$facebookmeta_video_height		= @$attribs->facebookmeta_video_height;


			// We have to set the article sharing image https://developers.facebook.com/docs/sharing/best-practices#images
			if ($facebookmeta_image) {
				$size 				= getimagesize(JURI::base() . $facebookmeta_image);
				$metaimage 			= '<meta property="og:image" content="' . JURI::base() . $facebookmeta_image .'" />';
				$metaimagewidth 	= '<meta property="og:image:width" content="' . $size[0] .'" />';
				$metaimageheight 	= '<meta property="og:image:height" content="' . $size[1] .'" />';
				$metaimagemime	 	= '<meta property="og:image:type" content="' . $size['mime'] .'" />';
				$googledata->image->url 		= JURI::base() . $facebookmeta_image;
				$googledata->image->width 		= $size[0];
				$googledata->image->height 		= $size[1];
				$googledata->image->fileFormat	= $size['mime'];
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
			$googledata->author = new StdClass();
			$googledata->author->{'@type'} = 'Person';
			$googledata->author->name = $article->created_by_alias ? $article->created_by_alias : $this->getUserName($article->created_by);
			if ($this->facebookmeta_twittersite) {
				$metaauthtw 	= '<meta name="twitter:site" content="'. ( $facebookmeta_authortw ? $facebookmeta_authortw : $this->facebookmeta_twittersite ) . '" />';
			} else {
				$metaauthtw		= '';
			}
			if ($this->facebookmeta_pub) {
				$metapublisher  	= '<meta property="article:publisher" content="'. $this->facebookmeta_pub . '" />';
			}
			$metasection  			= '<meta property="article:section" content="'. $category->title . '" />';
			$googledata->articleSection = $category->title;
			$metapub					= array();
			$metapub['modified']  		= '<meta property="article:modified_time" content="'. $this->to8601($article->modified) . '" />';
			$googledata->dateModified 	= $this->to8601($article->modified);
			$metapub['publish_ub']		= '<meta property="article:published_time" content="'. $this->to8601($article->publish_up) . '" />';
			$googledata->datePublished 	= $this->to8601($article->publish_up);
			if ($article->publish_down != '0000-00-00 00:00:00') {
				$metapub['publish_down']	= '<meta property="article:expiration_time" content="'. $this->to8601($article->publish_down) . '" />';
			}
			if (count($article->tags->itemTags)) {
				$metatags 		= array();
				$articletags 	= array();
				foreach ($article->tags->itemTags as $tag) {
					$metatags[] = '<meta property="article:tag" content="' . $tag->title .'" />';
					$articletags[] = $tag->title;
				}
				$googledata->keywords = implode(',', $articletags);
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
				$metavideo 			= $facebookmeta_video ? '<meta property="og:video" content="'.htmlentities($facebookmeta_video).'" />' : "";
				$metavideotw 		= $facebookmeta_video ? '<meta name="twitter:player" content="'.htmlentities($facebookmeta_video).'" />' : "";
				$metavideotype 		= ($facebookmeta_video_type == ("application/x-shockwave-flash" || "video/mp4")) ? '<meta property="og:video:type" content="'.$facebookmeta_video_type.'" />' : "";
				$metavideoheight 	= ((int)$facebookmeta_video_height != 0) ? '<meta property="og:video:height" content="'.$facebookmeta_video_height.'" />' : "";
				$metavideoheighttw 	= ((int)$facebookmeta_video_height != 0) ? '<meta name="twitter:player:height" content="'.$facebookmeta_video_height.'" />' : "";
				$metavideowidth 	= ((int)$facebookmeta_video_width != 0) ? '<meta property="og:video:width" content="'.$facebookmeta_video_width.'" />' : "";
				$metavideowidthtw 	= ((int)$facebookmeta_video_width != 0) ? '<meta name="twitter:player:width" content="'.$facebookmeta_video_width.'" />' : "";
				if ($metavideotype && $metavideoheight && $metavideowidth) {

					if ( $url_scheme == "https" ) { // && type == should be treated as a video object
						$metavideosecureourl	= '<meta property="og:video:secure_url" content="'.htmlentities($facebookmeta_video_secure_url ? $facebookmeta_video_secure_url : $facebookmeta_video).'" />';
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
				$googledata->headline = $this->striptagsandcut ( $facebookmeta_title );
				$googledata->name = $this->striptagsandcut ( $facebookmeta_title );
			} else {
				$metatitle = '<meta property="og:title" content="' . $this->striptagsandcut ( $article->title, $this->facebookmeta_titlelimit ) .'" />';
				$googledata->headline = $this->striptagsandcut ( $this->striptagsandcut ( $article->title, $this->facebookmeta_titlelimit ) );
				$googledata->name = $this->striptagsandcut ( $this->striptagsandcut ( $article->title, $this->facebookmeta_titlelimit ) );
			}
			// We use the introtext field if none is provided
			if ($facebookmeta_desc) {
				$metadesc = '<meta property="og:description" content="' . $this->striptagsandcut ( $facebookmeta_desc ) .'" />';
				$googledata->description = $this->striptagsandcut ( $facebookmeta_desc );
			}
			elseif (!empty($article->metadesc)) {
				$metadesc = '<meta property="og:description" content="' . $this->striptagsandcut ( $article->metadesc ) .'" />';
				$googledata->description = $this->striptagsandcut ( $article->metadesc );
			} else {
				$metadesc = '<meta property="og:description" content="' . $this->striptagsandcut ( $article->introtext, $this->facebookmeta_desclimit ) .'" />';
				$googledata->description = $this->striptagsandcut ( $article->introtext, $this->facebookmeta_desclimit );
			}
			
			// com_flexicontent specific routine to overrride image
			if ( ( $option == 'com_flexicontent' && $view == 'item') ) {
				
				if ($this->flexicontent_image_field) {
				
					$image_field = $this->getFCfieldname($this->flexicontent_image_field);
					
					require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.fields.php');
					    
					$thumb_size = $this->flexicontent_imagesize_field;
					
					$thumbs_arr =
					  FlexicontentFields::renderFields(
					    $item_per_field=true,
					    $itemids = array($article->id),
					    $field_names = array($image_field),
					    $view ='item',
					    $field_methods = array('display_'.$thumb_size.'_src'),
					    $cfparams = array()
					  );
					
					$thumb = $thumbs_arr[$article->id][$image_field]['display_'.$thumb_size.'_src'];
					
					if (!empty($thumb)) {
						// Override the og:image properties
						$size 				= getimagesize(JURI::base().substr($thumb, strlen(JURI::base(true)) + 1));
						$metaimage 			= '<meta property="og:image" content="' . JURI::base().substr($thumb, strlen(JURI::base(true)) + 1) .'" />';
						$metaimagewidth 	= '<meta property="og:image:width" content="' . $size[0] .'" />';
						$metaimageheight 	= '<meta property="og:image:height" content="' . $size[1] .'" />';
						$metaimagemime	 	= '<meta property="og:image:type" content="' . $size['mime'] .'" />';
						$googledata->image->url 		= JURI::base().substr($thumb, strlen(JURI::base(true)) + 1);
						$googledata->image->width 		= $size[0];
						$googledata->image->height 		= $size[1];
						$googledata->image->fileFormat	= $size['mime'];
					}
				}
			}
		}

/*
echo '<pre>';
print_r( $googledata );
//print_r( json_encode( $googledata ) );
echo '</pre>';
*/
		
		$document->addCustomTag('<!-- BOF Socialmeta plugin for Joomla! https://github.com/vistamedia/socialmeta -->');

		$document->addCustomTag('<!-- Google structured data -->');
		$document->addCustomTag( '<script type="application/ld+json">'.json_encode( $googledata ).'</script>' );

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
	function onContentPrepareForm($form, $data)
	{
		if (!($form instanceof JForm))
		{
			$this->_subject->setError('JERROR_NOT_A_FORM');
			return false;
		}

		$app  = JFactory::getApplication();
		$name = $form->getName();

		// Check we are manipulating a -supported- form.
		switch($name)
		{
			case 'com_content.article':
				if ($app->isAdmin()) {
					JForm::addFormPath(__DIR__ . '/forms');
					$form->loadFile('com_content', false);
				}
				return true;
			case 'com_contact.contact':
				if ($app->isAdmin()) {
					JForm::addFormPath(__DIR__ . '/forms');
					$form->loadFile('com_contact', false);
				}
				return true;
			case 'com_flexicontent.item':
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
	 * Returns a the twitterprofile store in the contact
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

	/**
	 * Returns a the the name of a user
	 *
	 * @TODO: getExternalProperties(userid,property)
	 * @param 	int 		$userid
	 * @return 	string		Facebook profile URL of the user
	 * @since 1.0
	 */
	private function getUserName ( $userid )
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('name');
		$query->from($db->quoteName('#__users'));
		$query->where($db->quoteName('id')." = ".$db->quote($userid));
		$db->setQuery($query);
		$username = $db->loadResult();

		return $username;
	}

	/**
	 * Returns the optimal size thumbnail or raise an error if the field is not an image
	 * NOTE: experimental, not used yet
	 *
	 * @param 	string 		$fieldname
	 * @return 	void		true on success
	 *
	 * @since 1.0
	 */
	private function decideFCimageFieldThumb( $fieldname )
	{
		$img_fieldname = '';
		
		$user 	= JFactory::getUser();
		$db 	= JFactory::getDBO();
		$query 	= 'SELECT attribs #__flexicontent_fields WHERE `name`='. $db->Quote($img_fieldname);
		$db->setQuery($query);
		$data = $db->loadAssocList();
		if ( !$data )
		{
			$isSuperAdmin = $user->authorise('core.admin', 'root.1');
			if (JDEBUG || $isSuperAdmin) {
				JFactory::getApplication()->enqueueMessage('FLEXIcontent image field name : '.$img_fieldname.' was not found', 'notice');
			}
			return false;
		}
		$fparams = new JRegistry($data->attribs);
		
		$w_l = $fparams->get('w_l');
		$h_l = $fparams->get('h_l');
		
		$sizes = array('s', 'm', 'l');
		$size_found = false;
		foreach($sizes as $size)
		{
			$width  = $fparams->get('w_'.$size);
			$height = $fparams->get('h_'.$size);
			if ($width  < 400 || $width  > 1000) continue;
			if ($height < 300 || $height > 800) continue;
			$size_found = $size;
			break;
		}
		return $size_found;
	}

	/**
	 * Check if the image field is an image and is published
	 *
	 * @param 	string 		$fieldname
	 * @return 	void		true on success
	 *
	 * @since 1.0
	 */
	private function isValidImageField ( $fieldname )
	{
		$user 	= JFactory::getUser();
		$db 	= JFactory::getDBO();
		$query 	= $db->getQuery(true);
		$query->select('*');
		$query->from($db->quoteName('#__flexicontent_fields'));
		$query->where($db->quoteName('name')." = ".$db->quote($fieldname));
		$db->setQuery($query);
		$data = $db->loadObject();

		if ( !$data ) // does the field exist
		{
			$isSuperAdmin = $user->authorise('core.admin', 'root.1');
			if (JDEBUG || $isSuperAdmin) {
				JFactory::getApplication()->enqueueMessage('FLEXIcontent field : '.$fieldname.' was not found', 'notice');
			}
			return false;
		}
		elseif ( $data->field_type !== 'image' ) // is the field an image field
		{
			$isSuperAdmin = $user->authorise('core.admin', 'root.1');
			if (JDEBUG || $isSuperAdmin) {
				JFactory::getApplication()->enqueueMessage('FLEXIcontent field : '.$fieldname.' is not an image', 'notice');
			}
			return false;

		} 
		elseif ( $data->published != 1 ) // is the field published
		{
			$isSuperAdmin = $user->authorise('core.admin', 'root.1');
			if (JDEBUG || $isSuperAdmin) {
				JFactory::getApplication()->enqueueMessage('FLEXIcontent image field name : '.$fieldname.' is unpublised', 'notice');
			}
			return false;

		}
		else
		{
			return true;
		}
	}
	
	/**
	 * Check if the image field is an image and is published
	 *
	 * @param 	string 		$fieldname
	 * @return 	void		true on success
	 *
	 * @since 1.0
	 */
	private function getFCfieldname ( $id )
	{
		$db 	= JFactory::getDBO();
		$query 	= $db->getQuery(true);
		$query->select('name');
		$query->from($db->quoteName('#__flexicontent_fields'));
		$query->where($db->quoteName('id')." = ".$db->quote($id));
		$db->setQuery($query);
		$fieldname = $db->loadResult();
	
		return $fieldname;
	}

	/**
	 * Get the modified language code if the plugin is enabled
	 *
	 * @param 	string 		$tag		the language tag
	 * @return 	string		$newtag		the modified language tag
	 *
	 * @since 1.1
	 */
	private function getNewLanguageCode ( $tag = 'en-GB' )
	{
		$db 	= JFactory::getDBO();
		$query 	= $db->getQuery(true);
		$query->select('params');
		$query->from($db->quoteName('#__extensions'));
		$query->where($db->quoteName('name')." = ".$db->quote('plg_system_languagecode'));
		$db->setQuery($query);
		$params = $db->loadResult();
		
		$lcparams = new JRegistry($params);
		
		$newtag = $lcparams->get(strtolower($tag));
		
/*
echo '<pre>';
print_r( $lcparams->get(strtolower($tag)) );
echo '</pre>';
*/

		return (!empty($newtag)) ? $newtag : $tag;
	}	

}
