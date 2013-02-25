<?php
App::uses('HtmlHelper', 'View/Helper');

/**
 * DataTable Helper
 *
 * @package Plugin.DataTable
 * @subpackage Plugin.DataTable.View.Helper
 * @author Tigran Gabrielyan
 *
 * @property HtmlHelper $Html
 */
class DataTableHelper extends HtmlHelper {

/**
 * Settings
 *
 * - `table` See `render()` method for setting info
 * - `scriptBlock` String for block name or false to disable output of default init script
 * - `js` See `script()` method for setting info
 *
 * @var array
 */
	public $settings = array(
		'table' => array(
			'class' => 'dataTable',
			'trOptions' => array(),
			'thOptions' => array(),
			'theadOptions' => array(),
			'tbody' => '',
			'tbodyOptions' => array(),
			'tfoot' => '',
			'tfootOptions' => array(),
		),
		'scriptBlock' => 'script',
		'js' => array(
			'bServerSide' => true,
			'oLanguage' => array()
		),
		'i18nDomain' => 'dataTable'
	);

/**
 * Table header labels
 *
 * @var array
 */
	protected $_labels = array();

/**
 * Column data passed from controller
 *
 * @var array
 */
	protected $_dtColumns;

/**
 * Javascript settings for all paginated models
 *
 * @var
 */
	protected $_dtSettings = array();

/**
 * All models setup for pagination
 *
 * @var array
 */
	protected $_paginatedModels = array();

/**
 * Constructor
 *
 * @param View $View The View this helper is being attached to.
 * @param array $settings Configuration settings for the helper.
 */
	public function __construct(View $View, $settings = array()) {
		parent::__construct($View, $settings);

		$this->settings['js']['oLanguage'] = array(
			'sEmptyTable' => __d($this->settings['i18nDomain'], "No data available in table"),
			'sInfo' => __d($this->settings['i18nDomain'], "Showing _START_ to _END_ of _TOTAL_ entries"),
			'sInfoEmpty' => __d($this->settings['i18nDomain'], "Showing 0 to 0 of 0 entries"),
			'sInfoFiltered' => __d($this->settings['i18nDomain'], "(filtered from _MAX_ total entries)"),
			'sInfoPostFix' => __d($this->settings['i18nDomain'], " "),
			'sInfoThousands' => __d($this->settings['i18nDomain'], ","),
			'sLengthMenu' => __d($this->settings['i18nDomain'], "Show _MENU_ entries"),
			'sLoadingRecords' => __d($this->settings['i18nDomain'], "Loading..."),
			'sProcessing' => __d($this->settings['i18nDomain'], "Processing..."),
			'sSearch' => __d($this->settings['i18nDomain'], "Search:"),
			'sZeroRecords' => __d($this->settings['i18nDomain'], "No matching records found"),
			'oPaginate' => array(
				'sFirst' => __d($this->settings['i18nDomain'], "First"),
				'sLast' => __d($this->settings['i18nDomain'], "Last"),
				'sNext' => __d($this->settings['i18nDomain'], "Next"),
				'sPrevious' => __d($this->settings['i18nDomain'], "Previous")
			),
			'oAria' => array(
				'sSortAscending' => __d($this->settings['i18nDomain'], ": activate to sort column ascending"),
				'sSortDescending' => __d($this->settings['i18nDomain'], ": activate to sort column descending")
			)
		);

		$this->settings = Hash::merge($this->settings, $settings);
		if (isset($this->_View->viewVars['dtColumns'])) {
			$dtColumns = $this->_View->viewVars['dtColumns'];
			foreach($dtColumns as $model => $columns) {
				$this->_paginatedModels[] = $model;
				$this->_parseSettings($model, $columns);
			}
		}
	}

/**
 * Output dataTable settings to script block
 *
 * @param string $viewFile
 */
	public function afterRender($viewFile) {
		foreach($this->_paginatedModels as $model) {
			if (!array_key_exists($model, $this->_dtSettings)) {
				$this->jsSettings($model);
			}
		}
		$jsVar = sprintf('var dataTableSettings = %s;', json_encode($this->_dtSettings));
		$this->scriptBlock($jsVar, array('block' => 'dataTableSettings'));
		if ($this->settings['scriptBlock'] !== false) {
			$initScript = <<< INIT_SCRIPT
$(document).ready(function() {
	$('.dataTable').each(function() {
		var table = $(this);
		var model = table.attr('data-model');
		var settings = dataTableSettings[model];
		table.dataTable(settings);
	});
});
INIT_SCRIPT;
			$this->scriptBlock($initScript, array('block' => $this->settings['scriptBlock']));
		}
	}

/**
 * Sets label at the given index.
 *
 * @param int $index of column to change
 * @param string $label new label to be set. `__LABEL__` string will be replaced by the original label
 * @return bool true if set, false otherwise
 */
	public function setLabel($model, $index, $label) {
		$model = $this->_getModel($model);
		$oldLabel = $this->_labels[$model][$index];
		$oldOptions = $options = array();
		if (is_array($oldLabel)) {
			list($oldLabel, $oldOptions) = $oldLabel;
		}
		if (is_array($label)) {
			list($label, $options) = $label;
		}
		$this->_labels[$model][$index] = array(
			$this->_parseLabel($label, $oldLabel),
			array_merge($oldOptions, $options),
		);
		return true;
	}

/**
 * Renders a DataTable
 *
 * Options take on the following values:
 * - `class` For table. Default: `dataTable`
 * - `trOptions` Array of options for tr
 * - `thOptions` Array of options for th
 * - `theadOptions` Array of options for thead
 * - `tbody` Content for tbody
 * - `tbodyOptions` Array of options for tbody
 *
 * The rest of the keys wil be passed as options for the table
 *
 * @param string $model Model to paginate
 * @param array $options Options for table
 * @param array $js Options for js var
 * @return string
 */
	public function render($model = null, $options = array(), $js = array()) {
		$model = $this->_getModel($model);
		$options = array_merge($this->settings['table'], $options);

		$trOptions = $options['trOptions'];
		$thOptions = $options['thOptions'];
		unset($options['trOptions'], $options['thOptions']);

		$theadOptions = $options['theadOptions'];
		$tbodyOptions = $options['tbodyOptions'];
		$tfootOptions = $options['tfootOptions'];
		unset($options['theadOptions'], $options['tbodyOptions'], $options['tfootOptions']);

		$tbody = $options['tbody'];
		$tfoot = $options['tfoot'];
		unset($options['tbody'], $options['tfoot']);

		$tableHeaders = $this->tableHeaders($this->_labels[$model], $trOptions, $thOptions);
		$tableHead = $this->tag('thead', $tableHeaders, $theadOptions);
		$tableBody = $this->tag('tbody', $tbody, $tbodyOptions);
		$tableFooter = $this->tag('tfoot', $tfoot, $tfootOptions);
		$options['data-model'] = $model;
		$table = $this->tag('table', $tableHead . $tableBody . $tableFooter, $options);
		$this->jsSettings($model, $js);

		return $table;
	}

/**
 * Renders table headers with column-specific attribute support
 *
 * @param $names
 * @param null $trOptions
 * @param null $thOptions
 * @return string
 */
	public function tableHeaders($names, $trOptions = null, $thOptions = null) {
		$out = array();
		foreach ($names as $name) {
			$arg = $name;
			$options = array();
			if (is_array($name)) {
				list($arg, $options) = $name;
			}
			$options = array_merge((array)$thOptions, $options);
			$out[] = sprintf($this->_tags['tableheader'], $this->_parseAttributes($options), $arg);
		}
		return sprintf($this->_tags['tablerow'], $this->_parseAttributes($trOptions), join(' ', $out));
	}

/**
 * Returns js settings either as an array or json-encoded string
 *
 * @param array $settings
 * @param bool $encode
 * @return array|string
 */
	public function jsSettings($model, $settings = array(), $encode = false) {
		$model = $this->_getModel($model);
		$settings = Hash::merge($this->settings['js'], (array)$settings);
		if (!empty($settings['bServerSide'])) {
			if (!isset($settings['sAjaxSource']) || $settings['sAjaxSource'] === true) {
				$settings['sAjaxSource'] = $this->request->here();
			}
			if (!is_string($settings['sAjaxSource'])) {
				if (!isset($settings['sAjaxSource']['model'])) {
					if (isset($settings['model'])) {
						$model = $this->_getModel($settings['model']);
						unset($settings['model']);
					}
					$settings['sAjaxSource']['?'] = array(
						'model' => $model
					);
				}
				$settings['sAjaxSource'] = Router::url($settings['sAjaxSource']);
			}
		}
		if (isset($settings['aoColumns']) && $settings['aoColumns'] === true) {
			$settings['aoColumns'] = $this->_dtColumns[$model];
		}
		$this->_dtSettings[$model] = $settings;
		return ($encode) ? json_encode($settings) : $settings;
	}

/**
 * Parse settings
 *
 * @param string $model
 * @param array $columns
 * @return array
 */
	protected function _parseSettings($model, $columns) {
		foreach($columns as $field => $options) {
			if (isset($options['useColumn']) && !$options['useColumn']) {
				continue;
			}
			if ($options === null) {
				$label = $field;
				$options = array(
					'bSearchable' => false,
					'bSortable' => false,
				);
			} else {
				$label = $options['label'];
				unset($options['label']);
				if (isset($options['bSearchable'])) {
					$options['bSearchable'] = (boolean)$options['bSearchable'];
				}
			}
			$this->_labels[$model][] = $this->_parseLabel($label);
			$this->_dtColumns[$model][] = $options;
		}
		return $this->_dtColumns[$model];
	}

/**
 * Parse a label with its options
 *
 * @param $label
 * @param string $oldLabel
 * @return string
 */
	protected function _parseLabel($label, $oldLabel = '') {
		$replacements = array(
			'__CHECKBOX__' => '<input type="checkbox" class="check-all">',
			'__LABEL__' => $oldLabel,
		);
		foreach($replacements as $search => $replace) {
			$label = str_replace($search, $replace, $label);
		}
		return $label;
	}

/**
 * Validates model is a paginated model
 * 
 * @param $model
 * @return string
 */
	protected function _getModel($model) {
		if ($model === null && !empty($this->request->params['models'])) {
			$model = current(array_keys($this->request->params['models']));
		}
		if (!in_array($model, $this->_paginatedModels)) {
			trigger_error("DataTableHelper: Model {$model} is not setup for pagination", E_USER_ERROR);
			return null;
		}
		return $model;
	}

}