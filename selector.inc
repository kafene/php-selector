<?php

// --- Selector.inc - (c) Copyright TJ Holowaychuk <tj@vision-media.ca> MIT Licensed

define('SELECTOR_VERSION', '1.1.3');

/**
 * SelectorDOM.
 *
 * Persitant object for selecting elements.
 *
 *   $dom = new SelectorDOM($html);
 *   $links = $dom->select('a');
 *   $list_links = $dom->select('ul li a');
 *
 */
class SelectorDOM {

    public static $regexMap = [
        '/\s*,\s*/' => '|descendant-or-self::',
        // :button, :submit, etc
        '/:(button|submit|file|checkbox|radio|image|reset|text|password)/' => 'input[@type="\1"]',
        // [id]
        '/\[(\w+)\]/' => '*[@\1]',
        // foo[id=foo]
        '/\[(\w+)=[\'"]?(.*?)[\'"]?\]/' => '[@\1="\2"]',
        // [id=foo]
        '/^\[/' => '*[',
        // div#foo
        '/([\w\-]+)\#([\w\-]+)/' => '\1[@id="\2"]',
        // #foo
        '/\#([\w\-]+)/' => '*[@id="\1"]',
        // div.foo
        '/([\w\-]+)\.([\w\-]+)/' => '\1[contains(concat(" ",@class," ")," \2 ")]',
        // .foo
        '/\.([\w\-]+)/' => '*[contains(concat(" ",@class," ")," \1 ")]',
        // div:first-child
        '/([\w\-]+):first-child/' => '*/\1[position()=1]',
        // div:last-child
        '/([\w\-]+):last-child/' => '*/\1[position()=last()]',
        // :first-child
        '/:first-child/' => '*/*[position()=1]',
        // :last-child
        '/:last-child/' => '*/*[position()=last()]',
        // div:nth-child
        '/([\w\-]+):nth-child\((\d+)\)/' => '*/\1[position()=\2]',
        // :nth-child
        '/:nth-child\((\d+)\)/' => '*/*[position()=\1]',
        // :contains(Foo)
        '/([\w\-]+):contains\((.*?)\)/' => '\1[contains(string(.),"\2")]',
        // >
        '/\s*>\s*/' => '/',
        // ~
        '/\s*~\s*/' => '/following-sibling::',
        // +
        '/\s*\+\s*([\w\-]+)/' => '/following-sibling::\1[position()=1]',
        '/\]\*/' => ']',
        '/\]\/\*/' => ']',
    ];

    /**
     * Load $data into the object
     *
     * @param string|DOMDocument $data
     */
    public function __construct($data) {
        if ($data instanceof DOMDocument) {
            $this->xpath = new DOMXpath($data);
        } else {
            $dom = new DOMDocument();
            @$dom->loadHTML($data);
            $this->xpath = new DOMXpath($dom);
        }
    }

    public function select($selector, $as_array = true) {
        $elements = $this->xpath->evaluate(selector_to_xpath($selector));
        return $as_array ? self::elementsToArray($elements) : $elements;
    }

    /**
     * Convert $elements to an array.
     */
    function elementsToArray($elements) {
      $array = array();
      for ($i = 0, $length = $elements->length; $i < $length; ++$i)
        if ($elements->item($i)->nodeType == XML_ELEMENT_NODE)
          array_push($array, self::elementToArray($elements->item($i)));
      return $array;
    }

    /**
     * Convert $element to an array.
     */
    function elementToArray($element) {
      $array = array(
        'name' => $element->nodeName,
        'attributes' => array(),
        'text' => $element->textContent,
        'children' => self::elementsToArray($element->childNodes)
        );
      if ($element->attributes->length)
        foreach($element->attributes as $key => $attr)
          $array['attributes'][$key] = $attr->value;
      return $array;
    }

}

/**
 * Select elements from $html using the css $selector.
 * When $as_array is true elements and their children will
 * be converted to array's containing the following keys (defaults to true):
 *
 *  - name : element name
 *  - text : element text
 *  - children : array of children elements
 *  - attributes : attributes array
 *
 * Otherwise regular DOMElement's will be returned.
 */

function select_elements($selector, $html, $as_array = true) {
  $dom = new SelectorDOM($html);
  return $dom->select($selector, $as_array);
}



/**
 * Convert $selector into an XPath string.
 */

function selector_to_xpath($selector) {
  // remove spaces around operators
  $selector = preg_replace('/\s*(>|~|\+|,)\s*/', '$1', $selector);
  $selectors = preg_split("/\s+/", $selector);

  foreach ($selectors as &$selector) {
      foreach (SelectorDOM::$regexMap as $regex => $replacement) {
          $selector = preg_replace($regex, $replacement, $selector);
      }
  }
  // ' '
  $selector = implode('/descendant::', $selectors);
  $selector = 'descendant-or-self::' . $selector;
  return $selector;
}
