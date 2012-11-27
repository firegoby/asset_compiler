# Asset Compiler

Symphony CMS extension to concatenate & minify Javascript & CSS assets and update a XSLT Template with the newly compiled SHA1 tagged filename.

- Version: 1.0
- Date: 27th November 2012
- Requirements: Symphony 2.3
- Author: Chris Batchelor

## Synopsis

**Asset Compiler** scans an XSLT Template (`master.xsl` by default) for lists of **CSS** and **Javascript** assets. These assets are then concatenated into a single file, minified and the new file tagged with a unique hash (to bust over-eager caches). The XSLT Template is then automatically updated with these new filenames. There is also a checkbox On/Off switch to control whether to serve these compiled assets. Javascript compilation is via an CURL API call to **Google's Closure Compiler**.

## Installation

1) Extract files into `extension/asset-compiler` directory

2) Select and enable extension in **System** > **Extensions**

3) Update your `master.xsl` template with the following two code blocks, changing the links within the `<!-- xxxx_LIST_START -->` and `<!-- xxxx_LIST_END -->` to point to your chosen assets. All other stylesheets and javascripts in the template not between the `<!-- xxxx_LIST_xxxx -->` markers are ignored.

**Note:** the assets can be in any folder under the Symphony root, absolute paths (e.g. `/workspace/styles`) are trimmed of their leading `/` and appended to Symphony root prior to concatenation.

**For your CSS stylesheets**

    <xsl:choose>
      <xsl:when test="$serve-production-assets='false'">
        <!-- STYLESHEETS_LIST_START -->
        <link rel="stylesheet" href="/workspace/styles/vendor/reset.css"/>
        <link rel="stylesheet" href="/workspace/styles/main.css"/>
        <!-- STYLESHEETS_LIST_END -->
      </xsl:when>
      <xsl:when test="$serve-production-assets='true'">
        <link rel="stylesheet" href="/workspace/styles/production-a1b2c3d4e5.min.css"/>
      </xsl:when>
    </xsl:choose>

**For your Javascripts**

    <xsl:choose>
      <xsl:when test="$serve-production-assets='false'">
        <!-- JAVASCRIPTS_LIST_START -->
        <script src="/workspace/scripts/plugins.js"></script>
        <script src="/workspace/scripts/main.js"></script>
        <!-- JAVASCRIPTS_LIST_END -->
      </xsl:when>
      <xsl:when test="$serve-production-assets='true'">
        <script src="/workspace/scripts/production-a1b2c3d4e5.min.js"></script>
      </xsl:when>
    </xsl:choose>

4) In **System** > **Preferences** click ***Compile All Assets***

5) Tick ***Serve Compiled Production Assets*** and click ***Save Changes***


## Frequently Asked Questions

**How do I alter the compression/minification level for Google Closure Compiler (Javascript)?**

Alter the `closure_compression` config entry in `manifest/config.php`. It must be one of the following 3 options: `WHITESPACE_ONLY`, `SIMPLE_OPTIMIZATIONS` or `ADVANCED_OPTIMIZATIONS`. By default it is set to `WHITESPACE_ONLY` to ensure maximum compatibility.

**How do I change which XSLT Template is read?**

Alter the `xslt_template` config entry in `manifest/config.php`, it should be relative to Symphony's root directory

**How do I change the stylesheet output directory?** Default: `styles`

Alter the `styles_path` config entry in `manifest/config.php`, it should be relative to Symphony's root directory

**How do I change the javascripts output directory?** Default: `scripts`

Alter the `scripts_path` config entry in `manifest/config.php`, it should be relative to Symphony's root directory

