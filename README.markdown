# Asset Compiler

Symphony CMS extension to concatenate & minify Javascript & CSS assets and update Symphony's output with the newly compiled, SHA1-tagged, filename.

- Version: 1.6
- Date: 16th August 2013
- Requirements: Symphony 2.3
- Author: Chris Batchelor

## Synopsis

**Asset Compiler** scans an XSLT Template (`master.xsl` by default) for **CSS** and **Javascript** assets with an attribute of `data-compile='true'`. These assets are concatenated (in-order) into a single, minified file and tagged with a unique hash (to ensure fresh caching). Symphony's HTML output is updated, removing any `data-compile='true'` assets and replacing them with the single, minified file (*just before closing `</head>` for CSS and just before closing `</body>` for Javascripts*).

There are also a checkbox On/Off switches to control whether to serve the compiled assets. Javascript compilation is via an CURL API call to **Google's Closure Compiler**.

As of Version 1.6 **Asset Compiler** has support for automatically updating multiple manifest setup `config.php` files with the updated production filenames to save you having to do it by hand. See FAQs for more details.

## Installation

1. Extract files into `extension/asset-compiler` directory

2. Enable extension in **System** > **Extensions**

3. Add a `data-compile='true'` attribute to any `<link>` or `<script>` element in `master.xsl` you wish to be compiled. All other stylesheets and javascripts in the template are ignored.

4. In **System** > **Preferences** click ***Compile All Assets***

5. Tick one or both of ***Serve Compiled Javascript/Stylesheet Assets*** and click ***Save Changes***

**Note:** the assets can be in any folder under the Symphony root, absolute paths for the domain (e.g. `/workspace/styles`) are trimmed of their leading `/` and appended to Symphony root prior to concatenation.

## Frequently Asked Questions

**How do I alter the compression/minification level for Google Closure Compiler (Javascript)?**

Alter the `closure_compression` config entry in `manifest/config.php`. It must be one of the following 3 options: `WHITESPACE_ONLY`, `SIMPLE_OPTIMIZATIONS` or `ADVANCED_OPTIMIZATIONS`. By default it is set to `WHITESPACE_ONLY` to ensure maximum compatibility.

**How do I change which XSLT Template is read?** Default: `master.xsl`

Alter the `xslt_template` config entry in `manifest/config.php`, it should be relative to Symphony's root directory

**How do I change the stylesheet output directory?** Default: `styles`

Alter the `styles_path` config entry in `manifest/config.php`, it should be relative to Symphony's root directory

**How do I change the javascripts output directory?** Default: `scripts`

Alter the `scripts_path` config entry in `manifest/config.php`, it should be relative to Symphony's root directory

**How do I enable support for multiple (symlinked) manifest folders?**

(As of Version 1.6): Set `multi_config` to `yes` in all your `config.php` files and then all `config.php` files will be updated with the new production filename on compilation. Previously this had to be done by hand for multiple manifest setups as only `manifest/config.php` was updated; now *all* matchin `manifest.*/config.php` are updated. **NOTE**: If you have a multiple manifest setup that uses a different naming convention than the standard `manifest.dev`, `manifest.prod` etc (i.e. `manifest.*`) then you'll need to edit the glob on Line #228 of `extension.driver.php` for your specific setup.

