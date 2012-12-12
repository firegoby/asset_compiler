<?php

  Class extension_asset_compiler extends Extension {


    public function install() {
      Symphony::Configuration()->set('enabled_scripts', 'no', 'asset_compiler');
      Symphony::Configuration()->set('enabled_styles', 'no', 'asset_compiler');
      Symphony::Configuration()->set('xslt_template', 'workspace/utilities/master.xsl', 'asset_compiler');
      Symphony::Configuration()->set('scripts_path', 'workspace/scripts', 'asset_compiler');
      Symphony::Configuration()->set('styles_path', 'workspace/styles', 'asset_compiler');
      Symphony::Configuration()->set('latest_script', 'production-a1b2c3d4e5.min.js', 'asset_compiler');
      Symphony::Configuration()->set('latest_style', 'production-a1b2c3d4e5.min.css', 'asset_compiler');
      Symphony::Configuration()->set('closure_compression', 'WHITESPACE_ONLY', 'asset_compiler');
      return Symphony::Configuration()->write();
    }


    public function uninstall() {
      Symphony::Configuration()->remove('asset_compiler');
      return Symphony::Configuration()->write();
    }


    public function getSubscribedDelegates() {
      return array(
        array(
          'page' => '/system/preferences/',
          'delegate' => 'AddCustomPreferenceFieldsets',
          'callback' => 'appendPreferences'
        ),
        array(
          'page' => '/system/preferences/',
          'delegate' => 'Save',
          'callback' => 'savePreferences'
        ),
        array(
          'page' => '/frontend/',
          'delegate' => 'FrontendOutputPostGenerate',
          'callback' => 'parse_html'
        ),
      );
    }


    // Preferences page setup and actions
    public function appendPreferences($context) {
      // Create preference group
      $group = new XMLElement('fieldset');
      $group->setAttribute('class', 'settings');
      $group->appendChild(new XMLElement('legend', __('Asset Compiler')));

      // Append scripts on/off
      $label = Widget::Label();
      $input = Widget::Input('settings[asset_compiler][enabled_scripts]', 'yes', 'checkbox');
      if(Symphony::Configuration()->get('enabled_scripts', 'asset_compiler') == 'yes') $input->setAttribute('checked', 'checked');
      $label->setValue($input->generate() . ' ' . __('Serve Compiled Javascript Assets'));
      $group->appendChild($label);

      // Append styles on/off
      $label = Widget::Label();
      $input = Widget::Input('settings[asset_compiler][enabled_styles]', 'yes', 'checkbox');
      if(Symphony::Configuration()->get('enabled_styles', 'asset_compiler') == 'yes') $input->setAttribute('checked', 'checked');
      $label->setValue($input->generate() . ' ' . __('Serve Compiled Stylesheet Assets'));
      $group->appendChild($label);

      // Append help
      $xslt_template_filename = Symphony::Configuration()->get('xslt_template', 'asset_compiler');
      $group->appendChild(new XMLElement('p', __("Enable to serve compiled assets (<em>concatenated, minified & SHA1 tagged</em>) from <code>" . $xslt_template_filename . "</code>"), array('class' => 'help')));
      $group->appendChild(new XMLElement('p', __("Simply add a <code>data-compile='true'</code> attribute to any <code>script</code> or <code>link</code> element in <code>" . $xslt_template_filename . "</code> to be compiled."), array('class' => 'help')));
      $group->appendChild(new XMLElement('p', __("<strong>Recommended Usage: </strong>Disable before compiling, then re-enable once assets successfully compiled."), array('class' => 'help')));

      // create control frame
      $div = new XMLElement('div', NULL, array('id' => 'compile-actions', 'class' => 'label'));
      $span = new XMLElement('span', NULL, array('class' => 'frame'));

      //append action buttons
      $span->appendChild(new XMLElement('button', __('Compile All Assets'), array('name' => 'action[compile-all]', 'type' => 'submit')));
      $span->appendChild(new XMLElement('button', __('Compile Javascripts Only'), array('name' => 'action[compile-scripts]', 'type' => 'submit')));
      $span->appendChild(new XMLElement('button', __('Compile Stylesheets Only'), array('name' => 'action[compile-styles]', 'type' => 'submit')));
      $div->appendChild($span);
      $group->appendChild($div);

      $compiler_action = FALSE;

      if(isset($_POST['action']['compile-all'])){
        $compiler_action = TRUE;
        $js_results = $this->compileJavascripts();
        $css_results = $this->compileStylesheets();
        $results = $js_results . '<hr />' . $css_results;
      }
      if(isset($_POST['action']['compile-scripts'])){
        $compiler_action = TRUE;
        $results = $this->compileJavascripts();
      }
      if(isset($_POST['action']['compile-styles'])){
        $compiler_action = TRUE;
        $results = $this->compileStylesheets();
      }

      if ($compiler_action) {
        // Append output results
        $group->appendChild(new XMLElement('p', __($results)), array());
      }

      // Append new preference group
      $context['wrapper']->appendChild($group);
    }


    // replace asset links in Symphony's output HTML
    public function parse_html($context) {
      $html = $context['output'];
      // replace styles
      if (Symphony::Configuration()->get('enabled_styles', 'asset_compiler') == 'yes') {
        $style = Symphony::Configuration()->get('latest_style', 'asset_compiler');
        $styles_path = Symphony::Configuration()->get('styles_path', 'asset_compiler');
        $link_tag = '<link rel="stylsheet" href="' . $styles_path . '/' . $style . '" />';
        $html = preg_replace('/<link[^>]* data-compile=["\']true["\'][^>]*\/?>/i', '', $html);
        $html = preg_replace('/<\/head>/', $link_tag . '</head>', $html);
      }
      // replace scripts
      if (Symphony::Configuration()->get('enabled_scripts', 'asset_compiler') == 'yes') {
        $script = Symphony::Configuration()->get('latest_script', 'asset_compiler');
        $scripts_path = Symphony::Configuration()->get('scripts_path', 'asset_compiler');
        $script_tag = '<script src="' . $scripts_path . '/' . $script . '"></script>';
        $html = preg_replace('/<script[^>]* data-compile=["\']true["\'][^>]*\/?>(<\/script>)?/i', '', $html);
        $html = preg_replace('/<\/body>/', $script_tag . '</body>', $html);
      }
      $context['output'] = $html;
    }


    public function savePreferences($context) {
      // Disable by default
      if(!is_array($context['settings'])) {
        $context['settings'] = array('asset_compiler' => array('enabled_styles' => 'no', 'enabled_scripts' => 'no'));
      }
      // Disable enabled styles status if it has not been set to 'yes'
      if(!isset($context['settings']['asset_compiler']['enabled_styles'])) {
        $context['settings']['asset_compiler']['enabled_styles'] = 'no';
      }
      // Disable enabled scripts status if it has not been set to 'yes'
      if(!isset($context['settings']['asset_compiler']['enabled_scripts'])) {
        $context['settings']['asset_compiler']['enabled_scripts'] = 'no';
      }
    }


    // wrapper of compileAsset() for CSS stylesheets
    private function compileStylesheets() {
      return $this->compileAsset(
        "stylesheet",
        "css",
        "//link[@rel='stylesheet'][@data-compile='true']",
        "href",
        Symphony::Configuration()->get('styles_path', 'asset_compiler'),
        "latest_style",
        array($this, 'stripCSSWhitespace')
      );
    }


    // wrapper of compileAsset() for Javascript files
    private function compileJavascripts() {
      return $this->compileAsset(
        "javascript",
        "js",
        "//script[@data-compile='true']",
        "src",
        Symphony::Configuration()->get('scripts_path', 'asset_compiler'),
        "latest_script",
        array($this, 'googleClosureCompile')
      );
    }


    /*
     * Generalised Asset Compiler
     *
     * Params
     * $type          String: Human readable name of the asset type
     * $extension     String: filetype extension
     * $xpath         String: XPath selector for the HTML elements to compile
     * $attr          String: Element attribute containing filename
     * $output_path   String: Directory to write compiled asset
     * $config_entry  String: Config entry to hold latest successfully compiled filename
     * $compiler      Function: Compiler callback function :: input String -> Array(success Bool, result String)
     * Return
     * $results       HTML string detailing compilation outcome
     */
    private function compileAsset($type, $extension, $xpath, $attr, $output_path, $config_entry, $compiler) {
      $files = array();
      $results = "";
      $success = FALSE;
      $xslt_template_filename = Symphony::Configuration()->get('xslt_template', 'asset_compiler');
      $xslt_template = file_get_contents(DOCROOT . '/' . $xslt_template_filename);
      // extract filenames
      $doc = new SimpleXMLElement($xslt_template_filename, 0, true, 'http://www.w3.org/1999/XSL/Transform');
      foreach ($doc->xpath($xpath) as $elem) {
        array_push($files, ltrim($elem[$attr], '/'));
      }
      // concatenate files
      $concatenated = "";
      foreach ($files as $file) {
        $concatenated .= file_get_contents(DOCROOT . '/' . $file);
        $results .= "Concatenated <strong>" . $file . "</strong><br />";
      }
      $compilation_results = call_user_func($compiler, $concatenated);
      if ($compilation_results[0] == FALSE) {
        $results .= '<strong>Error: </strong>' . $compilation_results[1] . '<br />';
      } else {
        $compiled = $compilation_results[1];
        // old file
        $old_filename = Symphony::Configuration()->get($config_entry, 'asset_compiler');
        $old_file = DOCROOT . '/' . $output_path . '/' . $old_filename;
        // new file
        $new_sha = substr(sha1($compiled), 0, 10);
        $new_filename = "production-" . $new_sha . ".min." . $extension;
        $new_file = DOCROOT . '/' . $output_path . '/' . $new_filename;
        // check if anything needs updating
        if ($old_filename == $new_filename && file_exists($old_file)) {
          $results .= "SHA1 hashes of old and new compiled " . $type . " match, production " . $type . " up-to-date<br />";
          $success = TRUE;
        // create new asset file
        } else if (file_put_contents($new_file, $compiled)) {
          $results .= 'Saved compiled ' . $type . ' to <strong>' . $new_filename . '</strong><br />';
          Symphony::Configuration()->set($config_entry, $new_filename, 'asset_compiler');
          Symphony::Configuration()->write();
          $success = TRUE;
          // delete old asset if different
          if ($old_filename != $new_filename && file_exists($old_file)) {
            if (unlink($old_file)) {
              $results .= "Deleted previous production " . $type . " file <strong>" . $output_path . '/' . $old_filename . '</strong><br />';
            } else {
              $results .= "<strong>Warning: </strong> Could not delete old production " . $type . " file <strong>" . $old_filename . '</strong><br />';
            }
          }
        } else {
          $results .= "<strong>Error: </strong> Could not save compiled " . $type . " to <strong>" . $new_filename . "</strong><br />";
        }
      }
      // admin page alerts
      if ($success) {
        Administration::instance()->Page->pageAlert(__(ucfirst($type) . ' compilation successful.'), Alert::SUCCESS);
      } else {
        Administration::instance()->Page->pageAlert(__(ucfirst($type) . ' compilation failed.'), Alert::ERROR);
      }
      return $results;
    }


    // call Google's Closure Compiler for javascript minification
    private function googleClosureCompile($js) {
      $closure_compression = Symphony::Configuration()->get('closure_compression', 'asset_compiler');
      $ch = curl_init('http://closure-compiler.appspot.com/compile');
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, 'output_info=compiled_code&output_format=text&compilation_level=' . $closure_compression . '&js_code=' . urlencode($js));
      $compiled = curl_exec($ch);
      curl_close($ch);
      return (strlen($compiled) != 0 ? array(TRUE, $compiled) : array(FALSE, "Google Closure Compiler returned empty"));
    }


    // simple CSS whitespace stripper
    private function stripCSSWhitespace($css) {
      $replace = array(
        "#/\*.*?\*/#s" => "",  // Strip C style comments.
        "#\s\s+#"      => " ", // Strip excess whitespace.
      );
      $search = array_keys($replace);
      $css = preg_replace($search, $replace, $css);
      $replace = array(
        ": "  => ":",
        "; "  => ";",
        " {"  => "{",
        " }"  => "}",
        ", "  => ",",
        "{ "  => "{",
        ";}"  => "}", // Strip optional semicolons.
        ",\n" => ",", // Don't wrap multiple selectors.
        "\n}" => "}", // Don't wrap closing braces.
        "} "  => "}\n", // Put each rule on it's own line.
      );
      $search = array_keys($replace);
      $css = str_replace($search, $replace, $css);
      return array(TRUE, trim($css));
    }
  }
