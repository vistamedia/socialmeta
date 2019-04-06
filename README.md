<img src="http://flexicontent.net/img/socialmeta/socialmeta-logo-bluebg.png" width="300">

# Socialmeta plugin for Joomla!

Social-meta is a system plugin for Joomla! which creates Facebook [open graph](http://og.me) and Twitter [metadata](https://dev.twitter.com/cards/overview) in the head of the document. The idea is to allow the writer to configure properly the way his article will appear on the streams of the social networks.

> PLEASE NOTE...  
... that this document as well as the extension are in progress ;-)

## Do I need Socialmeta?

Hummm... Difficult to say, but just let me ask you a simple question first...

When you share a page of your Joomla! site on Facebook, what do you want to see ?
-----
![screenshot of the basic options](http://flexicontent.net/img/socialmeta/post-compare.jpg)

**If you prefer the second option (right side... just to be sure), you can keep on reading :P**


## Installation

Download the project zip and install it like any other extension:
- Go to extensions > plugins
- Find System - Socialmeta
- Publish it.

> IMPORTANT  
> **Open and save the configuration of the plugin at least once** - even if you didn't provide any data - to set up automatically some default parameters values and be sure to avoid any PHP notices.

## Configuration

- Go to extensions > plugins
- Find System - Socialmeta
- Open it

### Basic
Actually no parameter is really required but if you wish the application to be really efficient, I recommend you to fill up at least the parameters **1, 4 and 6**.

![screenshot of the basic options](http://flexicontent.net/img/socialmeta/socialmeta-plugin-basic-conf.jpg)
Figure 1

1. **Default's site image**  
You can set a default image for any page shared on Facebook except on those set manually with the content plugin. Leave it empty if you don't want to use it.

2. **Facebook Application ID**  
If you have an Application ID for you site you can put it here. The plugin will generate the meta property. Leave it empty if you don't want to use it.  
https://developers.facebook.com/docs/apps/register  
Only useful for [Facebook insights](https://www.facebook.com/help/336893449723054/)

3. **Admin ID**  
Enter the Facebook ID of the user who will be able to access to the sharing stats of the pages. See Facebook insights for more information.  
http://findmyfbid.com/  
Only useful for [Facebook insights](https://www.facebook.com/help/336893449723054/)

4. **Facebook profile URL**  
Enter the user profile URL with the https:// prefix. It will define the default author of your website's. This setting can be overridden for each author in the contact component under the Facebook tab.

5. **Facebook page URL**  
Enter the user profile URL with the https:// prefix. It will define the publisher of the articles.  

6. **Twitter @username**  
Enter here the twitter @username of your whole site. Enter the Twitter account of a default author/entity to follow. This setting can be overridden for each author in the contact component under the Facebook tab

7. **Title limit**  
You can modify here the size of the title in the character counter. The accurate setting is 68 today, but this value may change in the future depending on Facebook recommendations.

8. **Description limit**  
You can modify here the size of the description in the character counter. The accurate setting is 200 today, but this value may change in the future depending on Facebook recommendations.

### Components
Here come the explanations about the components tab

![screenshot of the advanced options](http://flexicontent.net/img/socialmeta/socialmeta-plugin-components-conf.jpg)
Figure 1bis

### Advanced
Don't be scared, there's nothing to do here except if you get some specific compatibility issues. In this particular case this settings will allow you to **disable globally** the creation of **some specific meta**. It may be the case for example if you have some kind of sharing button extension which already creates the fb:app_id meta.  

![screenshot of the advanced options](http://flexicontent.net/img/socialmeta/socialmeta-plugin-advanced-conf.jpg)
Figure 2 - This screen needs an update

## Usage

### Automatic mode
The plugin functions automatically pretty good but if you come to this extension, it's probably because you wish to make your post more beautiful and get more impact, right?  
That was my purpose too. Thus the automatic mode only serves one purpose: to work the best at it can with the existing articles.  
If, for whatever reason, the automatic mode does not produce the result you expect, you can just edit the article and use the manual override.

#### Meta tags creation: rules and default values

| Property                | Default value                                                                      | Overridable |
|-------------------------|------------------------------------------------------------------------------------|:-----------:|
| og:site_name            | site name from Joomla! global configuration                                        |             |
| og:url                  | the url is generated by the component router                                       |             |
| og:locale               | the page language                                                                  |             |
| og:type                 | article overridable by video (website wil be soon available for multi-items views) |     yes     |
| og:title                | will use the 68 first characters of the article title                              |     yes     |
| og:description          | will use the 200 first characters of the article text                              |     yes     |
| og:image                | will use the article images. full + html, intro + html, or html (plugin conf.)     |     yes     |
| og:image:width          | automatic from og:image                                                            |      -      |
| og:image:height         | automatic from og:image                                                            |      -      |
| og:image:type           | automatic from og:image                                                            |      -      |
| og:see_also             | only available manually (article selector to link related items)                   |     yes     |
| og:updated_time         | automatic from object table **modified** in ISO8601                                |             |
| article:author          | socialmeta configuration (fig.2-4) overridable in contact view                     |             |
| article:expiration_time | automatic from object table **publish_down** in ISO8601                            |             |
| article:modified_time   | automatic from object table **modified** in ISO8601                                |             |
| article:published_time  | automatic from object table **publish_up** in ISO8601                              |             |
| article:publisher       | socialmeta configuration (fig.2-5)                                                 |             |
| article:section         | automatic from object table **catid**                                              |             |
| article:tag             | automatic from object table `getItemTags(**id**)`                                  |             |
| fb:app_id               | socialmeta configuration (fig.2-2)                                                 |             |
| fb:admins               | socialmeta configuration (fig.2-3)                                                 |             |
| twitter:card            | idem og:type (article == summary_large_image) (video == player)                    |             |
| twitter:site            | socialmeta configuration (fig.2-6) overridable in contact view                     |             |


### Manual article override
What makes this extension different from other implementations is precisely THIS feature. Socialmeta gives you the ability to override the automatic meta creation by your own input for each article. It gives the author/publisher a total flexibility on the way his content will be rendered on the social networks.

This is how it looks in the Joomla! article view.  
![screenshot of the article view](http://flexicontent.net/img/socialmeta/socialmeta-article-form.jpg)
Figure 3

1. **Image**  
The image used when sharing the article on Facebook, Google+ and Twitter. If none is provided the automatic override order is Default image -> HTML of the article (first one) -> intro or full (depending on your configuration)
1. **Title**  
If empty the article title will be used instead
1. **Description**  
If empty the beginning of the article will be used instead (all html tags will be automatically striped)
1. **Related article**  
Add some related resources to try to gain related items from your site under your post in the stream.

### Sites with multiple authors
> NOTE:  
Before taking any decision on the values you will provide for fig.1-4, fig.1-5, fig.5-1 and fig.5-2, I strongly recommend you the reading of the following articles: [New Open Graph tags for media publishers](https://developers.facebook.com/blog/post/2013/06/19/platform-updates--new-open-graph-tags-for-media-publishers-and-more/) & [Using Author Tags with Facebook Story Previews](http://www.trueanthem.com/blog/using-author-tags-with-facebook-story-previews/)    
These articles will help you do decide about your author/publisher strategy.

If you wish each author to be linked with his publications on the facebook stream you can add his own Facebook profile URL and Twitter @username in the Contact component.
To perform this just create a contact for each user for which you would like to override the general settings.  
![screenshot of the contact creation](http://flexicontent.net/img/socialmeta/socialmeta-contact-form-link.jpg)  
Figure 4

1. Create a new contact  
1. Give him a name and link it with a existing user (fig.4-1)  
1. Go the social tab (fig.4-2)

![screenshot of the contact creation social tab](http://flexicontent.net/img/socialmeta/socialmeta-contact-form-social.jpg)
Figure 5

1. Provide Facebook (fig.5-1) and Twitter (fig.5-2) credentials.  
_Note that Facebook must be an Url and Twitter a @username_
1. Save the new contact.

> NOTE  
> You absolutely don't need to use the contact component except once to do what I said previously. I just use its database table to store the user social profiles. The contact doesn't need to be published. You can even disable the component if you wish ;)  
I made this choice to avoid having to create a user plugin which may conflict with other extensions which will also override the user management.  
It may change in the future but that's the most reliable solution I found up to now.

## Disclaimer
This plugin was created for a friend of mine running a Joomla! website. It was supposed to serve a particular purpose, because IMHO there was a lack on that particular matter. As it could be useful for anyone using Joomla!, we now **try to improve** it and to make it really **generic**. So, be confident, send me suggestions, and we will build together **THE** solution to fix that **once for all** ;)  

## Known issues and ... todo ;)
- ~~Create parameters to choose the automatic **image creation mode**~~
- ~~Use **meta description** from the object before using the text field (up to now it is how the description is fetched from the facebook url scrapper)~~
- Manage **JS errors** of the video properties fetching engine (see fig.3-5)  
  - no image...   
  - no description...  
  - no video data...
- **Creation settings** for visible elements
  - ~~Images: use image field or parse html~~
  - og:author (contact view) add or override
  - Add twitter:creator or override twitter:site
- Implement **category view** (soon - everything is ready, I just want to cleanup the `onBeforeCompileHead{...}` method)
- Implement **menu component** (soon - everything is ready, I just want to cleanup the `onBeforeCompileHead{...}` method)
- Implement **Google+** as well (soon - I didn't have to study the effects of the markup up to now as I don't often use Google+ myself.)

## Limitation
Up to now Socialmeta is only compatible with Joomla articles (com_content) and FLEXIcontent items (com_flexicontent). It only implements 2 objects from the open graph protocol: article and video.  
If you are looking for an open graph solution for implementing the product object type to your ecommerce (that would be an extremely wise decision by the way), you should have a further look to the [JED](http://extensions.joomla.org)

## Requirements
Joomla! 3+

## Useful ressources

**Official documents and recommendations**  
[The open graph protocol](http://ogp.me/)  
[Sharing Best Practices for Websites & Mobile Apps](https://developers.facebook.com/docs/sharing/best-practices)  
[The article object](https://developers.facebook.com/docs/reference/opengraph/object-type/article/)  
[Twitter cards official documentation](https://dev.twitter.com/cards/overview)  

**Testing tools**  
[Open Graph Object debugger](https://developers.facebook.com/tools/debug/og/object/)  
[Twitter Card Validator](https://cards-dev.twitter.com/validator)  

**General articles**  
[What You Need to Know About Open Graph Meta Tags for Total Facebook and Twitter Mastery](https://blog.kissmetrics.com/open-graph-meta-tags/)  
[Increase your Social Impact with OpenGraph – Related Articles](http://wersm.com/increase-your-social-impact-with-opengraph-related-articles/)  
[Some meta templates](https://moz.com/blog/meta-data-templates-123)  
[New Open Graph tags for media publishers](https://developers.facebook.com/blog/post/2013/06/19/platform-updates--new-open-graph-tags-for-media-publishers-and-more/)  
[Using Author Tags with Facebook Story Previews](http://www.trueanthem.com/blog/using-author-tags-with-facebook-story-previews/)  
[7 Common Meta Tag Mistakes That Publishers Make](http://www.trueanthem.com/blog/7-common-meta-tag-mistakes-that-publishers-make/)  
[Social Metadata: More Important than You Think](http://www.trueanthem.com/blog/social-metadata-more-important-than-you-think/)  
[Facebook is King, Other Networks Fight for Scraps](http://blog.naytev.com/facebook-is-king/)  


**Articles about video tags and strategy**  
[How to implement opengraph for video](http://www.marketing-mojo.com/blog/how-to-implement-open-graph-tags-for-videos/)  
[Video SEO - basics, essentials & best practises](https://www.speechpad.com/video-seo)  
[How Video Marketing Creates Immediate SEO Results](https://blog.shareaholic.com/video-marketing-seo-results/)  
[Setting Videos not Hosted on Brightcove to Play within Facebook](https://support.brightcove.com/en/video-cloud/docs/setting-videos-not-hosted-brightcove-play-within-facebook)  

**Less interesting but still some information to grab**  
[Social Meta Tags for Google, Twitter and Facebook](http://www.9lessons.info/2014/01/social-meta-tags-for-google-twitter-and.html)  
[Facebook Open Graph Meta Tags Tutorial](http://qnimate.com/open-graph-protocol-in-facebook/)  
[Social Media Tags: 11 Most Important Facebook Meta Tags](http://www.saleoid.com/blog/social-media-tags-11-most-important-facebook-meta-tags/)  
