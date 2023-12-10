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
		$config = $this->wire()->config;
		$dir = $this->getFilesDir();
		$path = $this->getFilesDir(true);
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
			$render_file_base = substr($render_file, 0, -4); // Remove ".php"

			// Default headline: can be overridden in render file by $this->wire('processHeadline');
			$this->headline($this->fileToLabel($render_file_base));

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
			return $this->wire()->files->render($path . $render_file);
		}

		// Render file not found
		else {
			$this->headline($this->_('Error'));
			return $this->_('Render file not found.');
		}
	}

	/**
	 * Get an array of allowed menu data for render files
	 *
	 * @return array
	 */
	public function ___getMenuData() {
		$data = [];
		if($this->menuItems) {
			foreach($this->menuItems as $render_file) {
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
	public function fileToLabel($basename) {
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
	 * @return array
	 */
	public function getRenderFiles() {
		$path = $this->getFilesDir(true);
		return $this->wire()->files->find($path, ['extensions' => 'php', 'returnRelative' => true]);
	}

	/**
	 * Config inputfields
	 *
	 * @param InputfieldWrapper $inputfields
	 */
	public function getModuleConfigInputfields($inputfields) {
		$modules = $this->wire()->modules;
		$render_files = $this->getRenderFiles();

		if($render_files) {
			/** @var InputfieldCheckboxes $f */
			$f = $modules->get('InputfieldCheckboxes');
			$f_name = 'menuItems';
			$f->name = $f_name;
			$f->label = $this->_('Menu items');
			$f->description = $this->_('Items selected here will appear in the admin flyout menu and in the list shown when the Process page is viewed without a URL segment.');
			foreach($render_files as $render_file) {
				$render_file_base = substr($render_file, 0, -4); // Remove ".php"
				$label = $this->fileToLabel($render_file_base);
				$f->addOption($render_file, $label);
			}
			$f->value = $this->$f_name;
			$inputfields->add($f);
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
