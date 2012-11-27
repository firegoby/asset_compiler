<?php

  Class extension_asset_compiler extends Extension {

    public function install() {
      Symphony::Configuration()->set('enabled', 'no', 'asset_compiler');
      Symphony::Configuration()->set('xslt_template', 'workspace/utilities/master.xsl', 'asset_compiler');
      Symphony::Configuration()->set('scripts_path', 'workspace/scripts', 'asset_compiler');
      Symphony::Configuration()->set('styles_path', 'workspace/styles', 'asset_compiler');
      Symphony::Configuration()->set('closure_compression', 'WHITESPACE_ONLY', 'asset_compiler');
      return Symphony::Configuration()->write();
    }

    public function uninstall() {
      Symphony::Configuration()->remove('asset_compiler');
      return Symphony::Configuration()->write();
    }

    public function getSubscribedDelegates(){
      return array(
        array(
          'page' => '/system/preferences/',
          'delegate' => 'AddCustomPreferenceFieldsets',
          'callback' => 'appendPreferences'
        ),
        array(
          'page' => '/system/preferences/',
          'delegate' => 'Save',
          'callback' => '__SavePreferences'
        ),
        array(
          'page' => '/frontend/',
          'delegate' => 'FrontendParamsResolve',
          'callback' => '__addParam'
        )
      );
    }

    /**
     * Append Asset Compiler preferences
     *
     * @param array $context
     *  delegate context
     */
    public function appendPreferences($context) {
      // Create preference group
      $group = new XMLElement('fieldset');
      $group->setAttribute('class', 'settings');
      $group->appendChild(new XMLElement('legend', __('Asset Compiler')));

      // Append on/off settings
      $label = Widget::Label();
      $input = Widget::Input('settings[asset_compiler][enabled]', 'yes', 'checkbox');
      if(Symphony::Configuration()->get('enabled', 'asset_compiler') == 'yes') $input->setAttribute('checked', 'checked');
      $label->setValue($input->generate() . ' ' . __('Serve Compiled Production Assets'));
      $group->appendChild($label);
      // Append help
      $xslt_template_filename = Symphony::Configuration()->get('xslt_template', 'asset_compiler');
      $group->appendChild(new XMLElement('p', __("Enable this to serve compiled assets (concatenated, minified & SHA1 tagged) from <code>" . $xslt_template_filename . "</code>"), array('class' => 'help')));
      $group->appendChild(new XMLElement('p', __("Recommend disabling before compiling, then re-enabling once compilation successful completed."), array('class' => 'help')));

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


    private function compileStylesheets() {
      $css_files = array();
      $results = "";
      $success = FALSE;
      $xslt_template_filename = Symphony::Configuration()->get('xslt_template', 'asset_compiler');
      $xslt_template = file_get_contents(DOCROOT . '/' . $xslt_template_filename);
      $styles_path = Symphony::Configuration()->get('styles_path', 'asset_compiler');

      if (preg_match('/<!-- STYLESHEETS_LIST_START -->(.*?)<!-- STYLESHEETS_LIST_END -->/si', $xslt_template, $matches)) {
        if (preg_match_all('/href="(.*?)"/si', $matches[1], $files)) {
          foreach ($files[1] as $css_file) {
            array_push($css_files, ltrim($css_file, '/'));
          }
          // concatenate css files
          $production_css = "";
          foreach ($css_files as $css_file) {
            $production_css .= file_get_contents(DOCROOT . '/' . $css_file);
            $results .= "Concatenated <strong>" . $css_file . "</strong><br />";
          }
          $compiled = $this->stripCSSWhitespace($production_css);
          // old file
          preg_match('/production-[a-f0-9]*\.min\.css/', $xslt_template, $pm_matches);
          $old_filename = $pm_matches[0];
          $old_file = DOCROOT . '/' . $styles_path . '/' . $old_filename;
          // new file
          $new_sha = substr(sha1($compiled), 0, 10);
          $new_filename = "production-" . $new_sha . ".min.css";
          $new_file = DOCROOT . '/' . $styles_path . '/' . $new_filename;
            // create new file and delete old (if different)
          if ($old_filename == $new_filename && file_exists($old_file)) {
            $results .= "SHA1 hashes of old and new compiled stylesheets match, production stylesheet up-to-date<br />";
          } else if (file_put_contents($new_file, $compiled)) {
            $results .= "Saved compiled stylesheets to <strong>" . $new_filename . "</strong><br />";
            if ($old_filename != $new_filename && file_exists($old_file)) {
              if (unlink($old_file)) {
                $results .= "Deleted previous production stylesheet file <strong>" . $styles_path . '/' . $old_filename . '</strong><br />';
              } else {
                $results .= "<strong>Warning: </strong> Could not delete old compiled stylesheet file <strong>" . $old_filename . '</strong><br />';
              }
            }
          } else {
            $results .= "<strong>Error: </strong> Could not save compiled stylesheet to <strong>" . $new_filename . "</strong><br />";
          }
          // update master.xsl with $new_file
          $new_master_xsl = preg_replace('/production-[a-f0-9]*\.min\.css/', $new_filename, $xslt_template, 1, $count);
          if ($count == 1 && file_put_contents(DOCROOT . '/' . $xslt_template_filename, $new_master_xsl)) {
          $results .= "Updated <strong>" . $xslt_template_filename . "</strong> with <strong>" . $new_filename . "</strong>";
            $success = TRUE;
          } else {
            $results .= "<strong>Error: </strong> Could not update <strong>" . $xslt_template_filename . "</strong> with <strong>" . $new_filename . "</strong><br />";
          }
        }
      }
      if ($success) {
        Administration::instance()->Page->pageAlert(__('Stylesheet compilation successful. Updated ' . $xslt_template_filename), Alert::SUCCESS);
      } else {
        Administration::instance()->Page->pageAlert(__('Stylesheet compilation failed. Did NOT update ' . $xslt_template_filename), Alert::ERROR);
      }
      return $results;
    }

    private function compileJavascripts() {
      $js_files = array();
      $results = "";
      $success = FALSE;
      $xslt_template_filename = Symphony::Configuration()->get('xslt_template', 'asset_compiler');
      $xslt_template = file_get_contents(DOCROOT . '/' . $xslt_template_filename);
      $scripts_path = Symphony::Configuration()->get('scripts_path', 'asset_compiler');

      if (preg_match('/<!-- JAVASCRIPTS_LIST_START -->(.*?)<!-- JAVASCRIPTS_LIST_END -->/si', $xslt_template, $matches)) {
        if (preg_match_all('/src="(.*?)"/si', $matches[1], $files)) {
          foreach ($files[1] as $js_file) {
            array_push($js_files, ltrim($js_file, '/'));
          }
          // concatenate js files
          $production_js = "";
          foreach ($js_files as $js_file) {
            $production_js .= file_get_contents(DOCROOT . '/' . $js_file);
            $results .= "Concatenated <strong>" . $js_file . "</strong><br />";
          }
          // CURL call to Google Closure with $production_js
          $closure_compression = Symphony::Configuration()->get('closure_compression', 'asset_compiler');
          $ch = curl_init('http://closure-compiler.appspot.com/compile');
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($ch, CURLOPT_POST, 1);
          curl_setopt($ch, CURLOPT_POSTFIELDS, 'output_info=compiled_code&output_format=text&compilation_level=' . $closure_compression . '&js_code=' . urlencode($production_js));
          $compiled = curl_exec($ch);
          curl_close($ch);
          // process compiled js
          if (strlen($compiled) == 0) {
            $results .= "<strong>Error: </strong> Empty response from Google Closure Compiler<br />";
          } else {
            // old file
            preg_match('/production-[a-f0-9]*\.min\.js/', $xslt_template, $pm_matches);
            $old_filename = $pm_matches[0];
            $old_file = DOCROOT . '/' . $scripts_path . '/' . $old_filename;
            // new file
            $new_sha = substr(sha1($compiled), 0, 10);
            $new_filename = "production-" . $new_sha . ".min.js";
            $new_file = DOCROOT . '/' . $scripts_path . '/' . $new_filename;
            // create new file and delete old (if different)
            if ($old_filename == $new_filename & file_exists($old_file)) {
              $results .= "SHA1 hashes of old and new compiled javascripts match, production javascript up-to-date<br />";
            } else if (file_put_contents($new_file, $compiled)) {
              $results .= "Saved compiled javascripts to <strong>" . $new_filename . "</strong><br />";
              if ($old_filename != $new_filename && file_exists($old_file)) {
                if (unlink($old_file)) {
                  $results .= "Deleted previous production javascript file <strong>" . $scripts_path . '/' . $old_filename . '</strong><br />';
                } else {
                  $results .= "<strong>Warning: </strong> Could not delete old compiled javascript file <strong>" . $old_filename . '</strong><br />';
                }
              }
            } else {
              $results .= "<strong>Error: </strong> Could not save compiled javascripts to <strong>" . $new_filename . "</strong><br />";
            }
            // update master.xsl with $new_file
            $new_master_xsl = preg_replace('/production-[a-f0-9]*\.min\.js/', $new_filename, $xslt_template, 1, $count);
            if ($count == 1 && file_put_contents(DOCROOT . '/' . $xslt_template_filename, $new_master_xsl)) {
              $results .= "Updated <strong>" . $xslt_template_filename . "</strong> with <strong>" . $new_filename . "</strong>";
              $success = TRUE;
            } else {
              $results .= "<strong>Error: </strong> Could not update <strong>" . $xslt_template_filename . " with " . $new_filename . "</strong><br />";
            }
          }
        }
      }
      if ($success) {
        Administration::instance()->Page->pageAlert(__('Javascript compilation successful. Updated ' . $xslt_template_filename), Alert::SUCCESS);
      } else {
        Administration::instance()->Page->pageAlert(__('Javascript compilation failed. Did NOT update ' . $xslt_template_filename), Alert::ERROR);
      }
      return $results;
    }

    private function stripCSSWhitespace($css)
    {
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

      return trim($css);
    }


    /**
     * Save preferences
     *
     * @param array $context
     *  delegate context
     */
    public function __SavePreferences($context) {

      // Disable production mode by default
      if(!is_array($context['settings'])) {
        $context['settings'] = array('asset_compiler' => array('enabled' => 'no'));
      }

      // Disable production mode if it has not been set to 'yes'
      elseif(!isset($context['settings']['asset_compiler'])) {
        $context['settings']['asset_compiler']['enabled'] = 'no';
      }
    }

    /**
     * Add production mode to parameter pool
     *
     * @param array $context
     *  delegate context
     */
    public function __addParam($context) {
      $context['params']['serve-production-assets'] = (Symphony::Configuration()->get('enabled', 'asset_compiler') == 'yes' ? 'true' : 'false');
    }

  }
