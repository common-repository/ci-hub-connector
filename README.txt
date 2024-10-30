=== CI HUB Connector ===
Contributors: cihubconnector
Donate link: https://ci-hub.com
Tags: DAM MAM PIM, Connector, Drag-and-drop, metadata, synchronization, dropbox box.com, wordpress VIP, stock media, images library, content, assets, cloud storage, low res
Requires at least: 4.1
Tested up to: 6.3
Stable tag: 1.2.104
Requires PHP: 5.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Work better with images, text and video by connecting your WordPress Site to your cloud storage or the stock media platform of your choice. Easy, fast, and simple!

== Description ==

## CI HUB is the ultimate digital supply chain connector handling images, Graphics Stock Media, and more.

The CI HUB Connector is an enterprise level productivity tool that allows you to connect to Images, Templates, Graphics, etc. Access your Assets and drag assets into the website you work in.
CI HUB Connector synchronizes your WordPress Media Library to the connected services. If there is a new version of an image, or if there are changes to the copyright of an image you are using, CI HUB will tell you.
The plugin also keeps your website up to date. CI HUB Connector synchronizes your WordPress Media library in Sync with your DAM/MAM/PIM or Cloud Storage. 
Check out the CI HUB Connector here [here](https://ci-hub.com).

## Key Features

Access assets directly. Connect to Images, Templates, Graphics, and Text from your DAM/MAM/PIM or Cloud Storage, directly in WordPress Bi-directional.
Download, make changes, and upload back to your repository Metadata. Linked metadata can be transformed into styled content.

## Main Benefits

* Simplifies daily work.
* Streamlines workflows.
* Connects internal and external units.
* Consistent and compliant asset handling.
* Track Copyright changes to know you are using licensed imaging
* Ensures production on demand. 

### We are already connected to:

Dropbox, Google Drive, Google Photos, Pixelboxx, Picturepark, Bynder, Asana, Workfront, eye base, MyView, SiteFusion, Adobe Stock, Getty-Images, iStock, Bandmaster, Brandfolder, CELUM ContentHub, Sharedien, Aprimo, SiteCore, Veeva, WebDAM, Adobe Creative Cloud Library, Adobe Lightroom see more [here](https://ci-hub.com/ci-hub-integrations-to-the-leading-data-source-systems/).

### We are integrated in:

Adobe CC, Microsoft365, Google Workspace, Figma, Sketch, with many more coming soon

### Free Trial and Subscription Payment:

All first-time users receive a 30-day free trial upon registration/creation of a CI HUB ID.
Payment via credit card is required upon the conclusion of the trial period to continue use. The amount is €10,50/month/Yearly invoiced, plus applicable taxes. 
The costs are not for downloading the plugin, as it is only the subscription to CI HUB that incurs the cost stated above.
Corporate plans are available on request at sales@ci-hub.com.

### Important notes:

To connect the CI HUB Connector to one of the selected third-party system, you may need a login/user account with that system. The availability and/or the right to connect to the third-party system is not part of the CI HUB Connector or the CI HUB Services. There may be an additional cost and/or agreements with the provider of the third-party system to use the third-party system. CI HUB reserves the right to remove the third-party system from the list of available systems or add new third-party systems at any time and without prior notice. To use CI HUB Connector, a connection to the Internet is required at all times. (Corporate setups with Proxy or Firewalls are available on request)
To see Demo-Video, Tutorials, and more, go to [https://ci-hub.com/ci-hub-how-to-tutorial/](https://ci-hub.com/ci-hub-how-to-tutorial/) or visit our Youtube Channel.

#### Try it for FREE for 30 Days.

### Shortcodes:

#### Metadata shortcode "cihub_metadata":

#### Attribute: id
Required: false
Default: Current Post ID
Description: The ID of the attachment. If empty, the ID of the current post will be used.

#### Attribute: key
Required: true
Default: -
Description: The key of the metadata field.

#### Attribute: wrapper
Required: false
Default: div
Description: Use "" or "false" to remove the wrapper element.

#### Attribute: mode
Required: false
Default: value
Description: "value" => metadata value / name => localized name for key / entry => full metadata entry

#### Attribute: htmlattributes
Required: false
Default: -
Description: Comma separated list of HTML attributes.

#### Attribute: content
Required: false
Default: true
Description: Use "" or "false" to disable insertion of content inside the wrapper element.

#### Attribute: throwerror
Required: false
Default: true
Description: Use "" or "false" to disable errors and use fallback instead.

#### Attribute: fallback
Required: false
Default: -
Description: Used in combination with throwerror = "false" as a fallback for a not existing value.
<br />
All occurrences of "{metadata}" will be replaced with the result of the shortcode for the corresponding key specified with the attribute "key".

All occurrences of "{metadata.key}" will be replaced with the result of the shortcode for the key specified after the dot.

### Actions:

#### Action: com_ci_hub_upload_asset
Params: post_id (Integer)
Description: Triggered after an asset was uploaded to the media library.

#### Action: com_ci_hub_relink_item
Params: post_id (Integer)
Description: Triggered after an asset in the media library was relinked.

== Screenshots ==

1. Easily connect 60+ of the leading systems to WordPress. E.g. DAM, MAM, PIM, Work Management, Cloud Storage or Stock Media. Box, Aprimo, Bynder, Celum, Widen, and many more. www.ci-hub.com
2. Transfer files easily from your storage system and easily organize your media library
3. Connect multiple asset storage solutions at once
4. Convert the files quickly and easily to make sure your images are optimized for site speed optimization
5. Seamlessly connect your stock image providers for hassle free access and be warned when the licenses change
6. Test it out for 30 days or reach out to us directly for more information

== Frequently Asked Questions ==

= How do I install CI HUB Connector? =

To install the CI HUB Connector, follow the steps below:

* From your WordPress dashboard -> Go to Plugins -> Click on ‘Add new’-> In the Search field, enter CI HUB and choose CI HUB Connector.
* Press install -> After installation, click Activate.

= Does it work with other WordPress plugins? =

We have tested the plugin with several other plugins, including Page Builders like Elementor. Since CI HUB hooks directly into the WordPress Core media library, it works in combination with almost any plugin. If you experience an incompatibility issue, please report it to us (sales@ci-hub.com) and the plugin that conflicts with CI HUB Connector.

= Do I need to know how to code? =

No! CI HUB Connector provides you with everything.

= Is there a shortcode? =

Yes, "CIHUB_METADATA".

= What do I need to start using this plugin? =

You need a CI HUB Account, just register [here](https://ci-hub.com/user-registration). There you can activate your 30 day free trial.

== Changelog ==

= 1.2.104 =
* Alt text mapping feature

= 1.2.103 =
* WordPress 6.3 compatibility

= 1.2.102 =
* Edit readme

= 1.2.101 =
* Edit readme

= 1.2.100 =
* WordPress 6.1 compatibility

= 1.2.99 =
* Add feature

= 1.2.98 =
* Minor bug fixes

= 1.2.90 =
* Minor bug fixes

= 1.2.71 =
* Media library reload items after upload

= 1.2.70 =
* Edit readme

= 1.2.69 =
* Readme: add wordpress.com notice and edit "tested up to" field

= 1.2.68 =
* Edit readme

= 1.2.67 =
* Edit readme

= 1.2.66 =
* Minor bug fixes

= 1.2.65 =
* Initial version

== Upgrade Notice ==

= 1.2.104 =
* Add feature

= 1.2.103 =
* Test compatibility

= 1.2.102 =
* Edit readme

= 1.2.101 =
* Edit readme

= 1.2.100 =
* Increase compatibility

= 1.2.99 =
* Add feature

= 1.2.98 =
* Increased stability

= 1.2.90 =
* Increased user experience and compatibility

= 1.2.71 =
* Increased user experience

= 1.2.70 =
* Edit readme

= 1.2.69 =
* Edit readme

= 1.2.68 =
* Edit readme

= 1.2.67 =
* Edit readme

= 1.2.66 =
* Increased stability

= 1.2.65 =
* Initial version