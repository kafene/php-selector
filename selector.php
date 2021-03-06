<?php

/**
 * SelectorDOM.
 * Copyright (c) TJ Holowaychuk <tj@vision-media.ca> MIT Licensed
 *
 * Persitent object for selecting elements.
 *
 * Ex:
 *     $dom = new SelectorDOM($html);
 *     $links = $dom->select('a');
 *     $list_links = $dom->select('ul li a');
 */
class SelectorDOM {

    const VERSION = '1.1.4';

    /**
     * @var DOMXPath
     */
    protected $xpath;

    /**
     * Map of regexes to convert CSS selector to XPath
     *
     * @var array
     */
    public static $regexes = [
        # ,
        '/\s*,\s*/' => '|descendant-or-self::',

        # :button, :submit, etc
        '/:(button|submit|file|checkbox|radio|image|reset|text|password)/' => 'input[@type="\1"]',

        # [id]
        '/\[(\w+)\]/' => '*[@\1]',

        # foo[id=foo]
        '/\[(\w+)=[\'"]?(.*?)[\'"]?\]/' => '[@\1="\2"]',

        # [id=foo]
        '/^\[/' => '*[',

        # div#foo
        '/([\w\-]+)\#([\w\-]+)/' => '\1[@id="\2"]',

        # #foo
        '/\#([\w\-]+)/' => '*[@id="\1"]',

        # div.foo
        '/([\w\-]+)\.([\w\-]+)/' => '\1[contains(concat(" ",@class," ")," \2 ")]',

        # .foo
        '/\.([\w\-]+)/' => '*[contains(concat(" ",@class," ")," \1 ")]',

        # div:first-child
        '/([\w\-]+):first-child/' => '*/\1[position()=1]',

        # div:last-child
        '/([\w\-]+):last-child/' => '*/\1[position()=last()]',

        # :first-child
        '/:first-child/' => '*/*[position()=1]',

        # :last-child
        '/:last-child/' => '*/*[position()=last()]',

        # div:nth-child
        '/([\w\-]+):nth-child\((\d+)\)/' => '*/*[position()=\2 and self::\1]',

        # :nth-child
        '/:nth-child\((\d+)\)/' => '*/*[position()=\1]',

        # :contains(Foo)
        '/([\w\-]+):contains\((.*?)\)/' => '\1[contains(string(.),"\2")]',

        # >
        '/\s*>\s*/' => '/',

        # ~
        '/\s*~\s*/' => '/following-sibling::',

        # +
        '/\s*\+\s*([\w\-]+)/' => '/following-sibling::\1[position()=1]',


        '/\]\*/' => ']',
        '/\]\/\*/' => ']',
    ];

    /**
     * Load $data into the object
     *
     * @param string|DOMDocument $data
     * @param array $errors A by-ref capture for libxml error messages.
     */
    public function __construct($data, &$errors = null)
    {
        # Wrap this with libxml errors off
        # this both sets the new value, and returns the previous.
        $lxmlErrors = libxml_use_internal_errors(true);

        if ($data instanceof DOMDocument) {
            $this->xpath = new DOMXpath($data);
        } else {
            $dom = new DOMDocument();
            $dom->loadHTML($data);
            $this->xpath = new DOMXpath($dom);
        }

        # Clear any errors and restore the original value
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($lxmlErrors);
    }

    /**
     * Select elements from the loaded HTML using the css $selector.
     * When $as_array is true elements and their children will
     * be converted to array's containing the following keys (defaults to true):
     *
     *  - name : element name
     *  - text : element text
     *  - children : array of children elements
     *  - attributes : attributes array
     *
     * Otherwise regular DOMElement's will be returned.
     *
     * @param string $selector CSS Selector
     * @param boolean $as_array Whether to return an array or DOMElement
     */
    public function select($selector, $asArray = true)
    {
        $elements = $this->xpath->evaluate(self::selectorToXpath($selector));
        return $asArray ? self::elementsToArray($elements) : $elements;
    }

    /**
     * This allows a static access to the class, in the same way as the
     * `select_elements` function did.
     *
     * @see $this->select()
     * @param string $html
     * @param string $selector CSS Selector
     */
    public static function selectElements($selector, $html, $asArray = true)
    {
        return (new self($html))->select($selector, $asArray);
    }

    /**
     * Convert $elements to an array.
     *
     * @param DOMNodeList $elements
     */
    public function elementsToArray($elements)
    {
        $array = [];

        for ($i = 0, $length = $elements->length; $i < $length; ++$i) {
            $item = $elements->item($i);

            if (XML_ELEMENT_NODE === $item->nodeType) {
                array_push($array, self::elementToArray($item));
            }
        }

        return $array;
    }

    /**
     * Convert $element to an array.
     */
    public function elementToArray($element)
    {
        $array = [
            'name'       => $element->nodeName,
            'attributes' => [],
            'text'       => $element->textContent,
            'children'   => self::elementsToArray($element->childNodes),
        ];

        if ($element->attributes->length) {
            foreach($element->attributes as $key => $attr) {
                $array['attributes'][$key] = $attr->value;
            }
        }

        return $array;
    }

    /**
     * Convert $selector into an XPath string.
     */
    public static function selectorToXpath($selector)
    {
        # remove spaces around operators
        $selector = preg_replace('/\s*([>~,+])\s*/', '$1', $selector);
        $selectors = preg_split("/\s+/", $selector);

        # Process all regular expressions to convert selector to XPath
        foreach ($selectors as &$selector) {
            foreach (self::$regexes as $find => $replace) {
                $selector = preg_replace($find, $replace, $selector);
            }
        }

        $selector = join('/descendant::', $selectors);
        $selector = 'descendant-or-self::'.$selector;

        return $selector;
    }
}

#
# Procedural components
#

define('SELECTOR_VERSION', SelectorDOM::VERSION);

/**
 * Provides a procedural function to select use SelectorDOM::select()
 * on some HTML.
 */
function select_elements($selector, $html, $as_array = true) {
    return SelectorDOM::selectElements($selector, $html, $as_array);
}
