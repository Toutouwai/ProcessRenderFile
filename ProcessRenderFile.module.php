<?php namespace ProcessWire;

class ProcessRenderFile extends Process implements ConfigurableModule {

	/**
	 * Flyout menu for bookmarks
	 *
	 * @param array $options
	 * @return string
	 */
	public function ___executeNavJSON($options = []) {
		$options['add'] = false;
		$options['edit'] = '{id}/';
		$options['itemLabel'] = 'label';
		$options['items'] = [];
		foreach($this->getMenuData() as $segment => $label) {
			$options['items'][] = [
				'id' => $segment,
				'label' => $label,
			];
		}
		return parent::___executeNavJSON($options);
	}

	/**
	 * Execute
	 *
	 * @return string
	 */
	public function ___execute() {
		$page = $this->wire()->page;
		$render_files = $this->getRenderFiles();
		foreach($render_files as $render_file) {
			// Remove ".php" and convert to lowercase
			$render_file_base = strtolower(substr($render_file, 0, -4));
			if($render_file_base === $page->name) {
				return $this->renderProcessFile($render_file);
			}
		}
		$menu_data = $this->getMenuData();
		if($menu_data) {
			$base_url = $this->wire()->page->url;
			$out = "<ul>";
			foreach($menu_data as $segment => $label) {
				$out .= "<li><a href='{$base_url}{$segment}/'>$label</a></li>";
			}
			$out .= "</ul>";
			return $out;
		} else {
			return $this->_('There are no listable items.');
		}
	}

	/**
	 * Execute unknown URL segment
	 *
	 * @return string
	 */
	public function ___executeUnknown() {
		$segment = $this->wire()->input->urlSegment1;
		$segment_file = $segment . '.php';
		$render_file = null;
		$render_files = $this->getRenderFiles();
		foreach($render_files as $file) {
			if(strtolower($file) === $segment_file) {
				$render_file = $file;
				break;
			}
		}

		// If the render file exists
		if($render_file) {
			return $this->renderProcessFile($render_file);
		}

		// Render file not found
		else {
			$this->headline($this->_('Error'));
			return $this->_('Render file not found.');
		}
	}

	/**
	 * Render a file and load any related assets
	 *
	 * @return string
	 */
	public function renderProcessFile($filename) {
		$config = $this->wire()->config;
		$page = $this->wire()->page;
		$dir = $this->getFilesDir();
		$path = $this->getFilesDir(true);
		$render_file_base = substr($filename, 0, -4); // Remove ".php"
		$label = $this->fileToLabel($render_file_base);

		// Override default final breadcrumb
		$this->breadcrumb($page->url, $page->title);

		// Browser title: can be overridden in render file by $this->wire('processBrowserTitle');
		$this->wire('processBrowserTitle', $label);

		// Headline: can be overridden in render file by $this->wire('processHeadline');
		$this->headline($label);

		// Load any related assets
		$js_path = $path . $render_file_base . '.js';
		if(is_file($js_path)) {
			$js_url = $dir . $render_file_base . '.js';
			$modified = filemtime($js_path);
			$config->scripts->add($js_url . "?v=$modified");
		}
		$procache_installed = $this->wire()->modules->isInstalled('ProCache');
		$scss_path = $path . $render_file_base . '.scss';
		if($procache_installed && is_file($scss_path)) {
			$scss_url = $dir . $render_file_base . '.scss';
			$css_url = $this->wire()->procache->css($scss_url);
			$config->styles->add($css_url);
		} else {
			$css_path = $path . $render_file_base . '.css';
			if(is_file($css_path)) {
				$css_url =  $dir . $render_file_base . '.css';
				$modified = filemtime($css_path);
				$config->styles->add($css_url . "?v=$modified");
			}
		}

		// Render the file
		return $this->wire()->files->render($path . $filename);
	}

	/**
	 * Get an array of allowed menu data for render files
	 *
	 * @return array
	 */
	public function ___getMenuData() {
		$prop = $this->wire()->page->id . '_menuItems';
		$data = [];
		if($this->$prop) {
			foreach($this->$prop as $render_file) {
				$render_file_base = substr($render_file, 0, -4); // Remove ".php"
				$label = $this->fileToLabel($render_file_base);
				$segment = strtolower($render_file_base);
				$data[$segment] = $label;
			}
		}
		return $data;
	}

	/**
	 * Converts a file basename to a space-separated, capitalised label
	 *
	 * @param string $basename
	 * @return string
	 */
	public function ___fileToLabel($basename) {
		$has_caps = $basename !== strtolower($basename);
		$words = explode('-', $basename);
		// If the basename is not already capitalised, capitalise the first letter of each word
		if(!$has_caps) $words = array_map('ucfirst', $words);
		return implode(' ', $words);
	}

	/**
	 * Get the URL/path of the directory that holds the files used by this module
	 *
	 * @param bool $path Return the path to the directory instead of the URL?
	 * @return string
	 */
	public function getFilesDir($path = false) {
		$config = $this->wire()->config;
		$base = $path ? $config->paths->templates : $config->urls->templates;
		return $base . 'ProcessRenderFile/';
	}

	/**
	 * Get the render files in /site/templates/ProcessRenderFile/
	 *
	 * @param array $exclude
	 * @return array
	 */
	public function getRenderFiles($exclude = []) {
		$path = $this->getFilesDir(true);
		$render_files = $this->wire()->files->find($path, ['extensions' => 'php', 'returnRelative' => true]);
		foreach($render_files as $key => $render_file) {
			if(in_array($render_file, $exclude)) unset($render_files[$key]);
		}
		return $render_files;
	}

	/**
	 * Config inputfields
	 *
	 * @param InputfieldWrapper $inputfields
	 */
	public function getModuleConfigInputfields($inputfields) {
		$modules = $this->wire()->modules;
		$exclude = [];
		$process_pages = $this->wire()->pages->find("process=$this, include=hidden");
		foreach($process_pages as $process_page) {
			$exclude[] = $process_page->name . '.php';
		}
		$render_files = $this->getRenderFiles($exclude);

		if($render_files) {
			/** @var InputfieldFieldset $fs */
			$fs = $modules->get('InputfieldFieldset');
			$fs->label = $this->_('Menu items');
			$fs->description = $this->_('Items selected here will appear in the admin flyout menu, and will be listed as the default output when no render file for the Process page exists.');
			$inputfields->add($fs);

			foreach($process_pages as $process_page) {
				/** @var InputfieldCheckboxes $f */
				$f = $modules->get('InputfieldCheckboxes');
				$f_name = $process_page->id . '_menuItems';
				$f->name = $f_name;
				$f->label = $process_page->title;
				foreach($render_files as $render_file) {
					$render_file_base = substr($render_file, 0, -4); // Remove ".php"
					$label = $this->fileToLabel($render_file_base);
					$f->addOption($render_file, $label);
				}
				$f->value = $this->$f_name;
				$fs->add($f);
			}
		}
	}

	/**
	 * Install
	 */
	public function ___install() {
		// Create InputfieldRenderFile directory
		$this->wire()->files->mkdir($this->getFilesDir(true));
		parent::___install();
	}

}
