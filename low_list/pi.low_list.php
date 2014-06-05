<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array(
	'pi_name'        => 'Low List',
	'pi_version'     => '0.0.1',
	'pi_author'      => 'Lodewijk Schutte ~ Low',
	'pi_author_url'  => 'http://gotolow.com/',
	'pi_description' => 'Work with custom character-separated lists',
	'pi_usage'       => ''
);

/**
 * Low List Plugin class
 *
 * @package        low_list
 * @author         Lodewijk Schutte <hi@gotolow.com>
 * @link           http://gotolow.com/
 */
class Low_list {

	// --------------------------------------------------------------------
	// PROPERTIES
	// --------------------------------------------------------------------

	/**
	 * Params
	 */
	private $_params = array(
		'items' => '',
		'as'    => 'key:val',
		'sep'   => '|',
		'range' => 'no'
	);

	/**
	 * Items to work with
	 */
	private $_items = array();

	// --------------------------------------------------------------------
	// METHODS
	// --------------------------------------------------------------------

	/**
	 * Constructor: set default parameters and items
	 *
	 * @access      public
	 * @return      void
	 */
	public function __construct()
	{
		// Fetch the params, fallback to default
		foreach ($this->_params AS $key => $val)
		{
			$this->_params[$key] = ee()->TMPL->fetch_param($key, $val);
		}

		// Set internal items
		$this->_items = explode($this->_params['sep'], $this->_params['items']);
		$this->_items = array_map('trim', $this->_items);
	}


	/**
	 * Each: loop through items and create the list for each item
	 *
	 * @access      public
	 * @return      void
	 */
	public function each()
	{
		// Shortcut to the items
		$items = $this->_items;

		// Create range if necessary
		if ($this->_params['range'] == 'yes' && count($items) == 2)
		{
			$items = range($items[0], $items[1]);
		}

		// Shortcut to the names of the vars we're using
		$as = $this->_params['as'];

		// Transform to the var key and the var value
		list($var_key, $var_val) = (strpos($as, ':') === FALSE)
			? array($as, $as)
			: explode(':', $as, 2);

		// Initiate vars for template use
		$vars = array();

		// Loop through items, add vars
		foreach ($items AS $row)
		{
			// Default key and val with the whole string
			$key = $val = $row;

			// Override with key/val if colon is found
			if (strpos($row, ':') !== FALSE)
			{
				list($key, $val) = explode(':', $row, 2);
			}

			$vars[] = array(
				$var_key => $key,
				$var_val => $val
			);
		}

		return ee()->TMPL->parse_variables(ee()->TMPL->tagdata, $vars);
	}

	/**
	 * {exp:low_list:has value="foo" items="foo|bar|baz"}
	 */
	public function has()
	{
		return $this->_equate();
	}

	/**
	 * {exp:low_list:has_not value="foo" items="foo|bar|baz"}
	 */
	public function has_not()
	{
		return $this->_equate(FALSE);
	}

	/**
	 * Do the equation
	 */
	private function _equate($in = TRUE)
	{
		// Get params
		$needle = ee()->TMPL->fetch_param('value');

		// Check requirements
		if ( ! strlen($needle)) return;

		// All the magic
		$result = in_array($needle, $this->_items);

		// Reverse it if necessary
		if ($in === FALSE) $result = ! $result;

		// What do we return?
		if ($tagdata = ee()->TMPL->tagdata)
		{
			$it = $result ? $tagdata : '';
		}
		else
		{
			$it = $result ? 'TRUE' : 'FALSE';
		}

		// Please
		return $it;
	}
} // END CLASS

/* End of file pi.low_list.php */