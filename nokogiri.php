<?

class Nokogiri implements IteratorAggregate, ArrayAccess {
	
	const
	regexp = 
	"/(?P<tag>[a-z0-9]+)?(\[(?P<attr>\S+)=(?P<value>[^\]]+)\])?(#(?P<id>[^\s:>#\.]+))?(\.(?P<class>[^\s:>#\.]+))?(:(?P<pseudo>(first|last|nth)-child)(\((?P<expr>[^\)]+)\))?)?\s*(?P<rel>>)?/isS"
	;
	
	public $dom;
	public $xpath;
	public $nodes;
	public $current_node;
	
	protected static $_compiledXpath = array();
	
	public function __construct(){
		$this->dom = new DOMDocument();
		$this->dom->preserveWhiteSpace = false;
	}
	
	public function attributes(){
		$attributes = array();
		foreach ($this->current_node->attributes as $attribute) $attributes[$attribute->nodeName] = $attribute->nodeValue;
		return $attributes;
	}

	public function offsetExists($key){
		return !!!$this->current_node->getAttribute($key);
	}
	public function offsetGet($key){
		return $this->current_node->getAttribute($key);
	}
	public function offsetSet($key, $value){
		$this->current_node->setAttribute($key,$value);
		return $this;
	}
	public function offsetUnset($key){
		$this->current_node->removeAttribute($key);
		return $this;
	}
	
	public function __get($method){
		switch($method){

			case "text":
				return ($this->current_node) ? $this->current_node->textContent : null;
			break;

			case "content":
				return ($this->current_node) ? $this->current_node->nodeValue : null;
			break;
			
			case "root":
				
			break;
			
		}
	}
	
	public function __set($method, $args){
		
		switch($method){
			case "content":
				$this->current_node->nodeValue = $args;
			break;
		}
		
		return $this;
	
	}

	
	public function text($regex=null){
		if (is_null($regex)) return $this->text;
		preg_match_all($regex, $this->text, $matches);
		return (count($matches[0])) ? $matches[0][0] : null;
	}
	
	
	/*
	$frag = $dom->createDocumentFragment();
	$frag->appendXML('<h1>foo</h1>');
	$el->appendChild($frag);
	*/
	
	
	
	public function from_html($html){
		libxml_use_internal_errors(true);
		$this->dom->loadHTML($html);
	  $this->xpath = new DOMXpath($this->dom);
		libxml_clear_errors();
		return $this;
	}
	
	public function delete(){
		return $this->remove();
	}
	
	public function remove(){
		foreach ($this->nodes as $node)
			$node->parentNode->removeChild($node);
		return $this;
	}
	
	public function __toString(){
		return json_encode($this->to_array());
	}
	
	
	public function get_elements($query){
	  $this->nodes = $this->xpath->query($query);
	  if ($this->nodes === false) throw new Exception('Malformed xpath');
	  return $this;
	}
	
	
	public function getXpathSubquery($expression, $rel = false, $compile = true){
		if ($compile){
			$key = $expression.($rel?'>':'*');
			if (isset(self::$_compiledXpath[$key])){
				return self::$_compiledXpath[$key];
			}
		}
		$query = '';
		if (preg_match(self::regexp, $expression, $subs)){
			$brackets = array();
			if (isset($subs['id']) && '' !== $subs['id']){
				$brackets[] = "@id='".$subs['id']."'";
			}
			if (isset($subs['attr']) && '' !== $subs['attr']){
				$attrValue = isset($subs['value']) && !empty($subs['value'])?$subs['value']:'';
				$brackets[] = "@".$subs['attr']."='".$attrValue."'";
			}
			if (isset($subs['class']) && '' !== $subs['class']){
				$brackets[] = 'contains(concat(" ", normalize-space(@class), " "), " '.$subs['class'].' ")';
			}
			if (isset($subs['pseudo']) && '' !== $subs['pseudo']){
				if ('first-child' === $subs['pseudo']){
					$brackets[] = '1';
				}elseif ('last-child' === $subs['pseudo']){
					$brackets[] = 'last()';
				}elseif ('nth-child' === $subs['pseudo']){
					if (isset($subs['expr']) && '' !== $subs['expr']){
						$e = $subs['expr'];
						if('odd' === $e){
							$brackets[] = '(position() -1) mod 2 = 0 and position() >= 1';
						}elseif('even' === $e){
							$brackets[] = 'position() mod 2 = 0 and position() >= 0';
						}elseif(preg_match("/^((?P<mul>[0-9]+)n\+)(?P<pos>[0-9]+)$/is", $e, $esubs)){
							if (isset($esubs['mul'])){
								$brackets[] = '(position() -'.$esubs['pos'].') mod '.$esubs['mul'].' = 0 and position() >= '.$esubs['pos'].'';
							}else{
								$brackets[] = ''.$e.'';
							}
						}
					}
				}
			}
			$query = ($rel?'/':'//').
				((isset($subs['tag']) && '' !== $subs['tag'])?$subs['tag']:'*').
				(($c = count($brackets))?
					($c>1?'[('.implode(') and (', $brackets).')]':'['.implode(' and ', $brackets).']')
				:'')
				;
			$left = trim(substr($expression, strlen($subs[0])));
			if ('' !== $left){
				$query .= $this->getXpathSubquery($left, isset($subs['rel'])?'>'===$subs['rel']:false, $compile);
			}
		}
		if ($compile){
			self::$_compiledXpath[$key] = $query;
		}
		return $query;
	}
	
	
	
	

	public function tag($query){
		$this->nodes = $this->dom->getElementsByTagName($query);
		return $this->nodes;
	}

	public function id($query){
		$this->nodes = $this->dom->getElementsById($query);
		return $this->nodes;
	}


	public function css($query){
		$this->search($query);
		return $this->nodes;
	}
	
	public function xpath($query){
		$this->get_elements($query);
		return $this->nodes;
	}
	
	public function search($query, $compile=true){
		$this->get_elements($this->getXpathSubquery($query, false, $compile));
		return $this->nodes;
	}
	
	//return first element
	public function at_css($query){
		$this->current_node = $this->search($query)->item(0);
		return $this;
	}

	//return first element
	public function at_xpath($query){
		$this->current_node = $this->xpath($query)->item(0);
		return $this->current_node;
	}
	
	public function to_html($node=null){

		if ($node instanceof Nokogiri){
			return $this->as_fragment($this->dom->saveHTML($node->current_node));
		}
		
		return $this->as_fragment($this->dom->saveHTML($node));
		
	}

	protected function as_fragment($html){
		return preg_replace(array("/^\<\!DOCTYPE.*?<html><body>/si", "!</body></html>$!si"), '', $html);
	}
	

	public function to_inner_html($node=null){
		
		if ($node instanceof Nokogiri){
		
			$html = '';
	
			if (!property_exists($node->current_node, 'childNodes')) {
				foreach ($node->current_node as $node)
					$html .= $this->to_inner_html($node);
				return $html;
			}
			
			foreach($this->current_node->childNodes as $node)
			   $html .= $this->to_html($node);
			
			return $html;
		
		}
		
		return $this->to_html($this->dom->getElementsByTagName('html')->item(0));
		
	}



	public function to_xml(){
		
	}

	public function add_next_sibling(){
		
	}
	
	public function name(){
		
	}
	
	public function parent(){
		
	}
	
	public function content(){
		
	}
	
	public function wrap(){
		
	}

	public static function HTML($html){
		$dom = new self();
		$dom->from_html($html);
		return $dom;
	}
	
	public static function XML($xml){

	}


	public function to_array($xnode = null){
		$array = array();
		if ($xnode === null){
			if ($this->nodes instanceof DOMNodeList){
				foreach ($this->nodes as $node){
					$array[] = $this->to_array($node);
				}
				return $array;
			}
			$node = $this->dom;
		}else{
			$node = $xnode;
		}
		if (in_array($node->nodeType, array(XML_TEXT_NODE,XML_COMMENT_NODE))){
			return $node->nodeValue;
		}
		if ($node->hasAttributes()){
			foreach ($node->attributes as $attr){
				$array[$attr->nodeName] = $attr->nodeValue;
			}
		}
		if ($node->hasChildNodes()){
			foreach ($node->childNodes as $childNode){
				$array[$childNode->nodeName][] = $this->to_array($childNode);
			}
		}
		if ($xnode === null){
			return reset(reset($array)); // first child
		}
		return $array;
	}
	
	
	public function getIterator(){
		$a = $this->to_array();
		return new ArrayIterator($a);
	}

}














/*
[0] => getAttribute
[1] => setAttribute
[2] => removeAttribute
[3] => getAttributeNode
[4] => setAttributeNode
[5] => removeAttributeNode
[6] => getElementsByTagName
[7] => getAttributeNS
[8] => setAttributeNS
[9] => removeAttributeNS
[10] => getAttributeNodeNS
[11] => setAttributeNodeNS
[12] => getElementsByTagNameNS
[13] => hasAttribute
[14] => hasAttributeNS
[15] => setIdAttribute
[16] => setIdAttributeNS
[17] => setIdAttributeNode
[18] => __construct
[19] => insertBefore
[20] => replaceChild
[21] => removeChild
[22] => appendChild
[23] => hasChildNodes
[24] => cloneNode
[25] => normalize
[26] => isSupported
[27] => hasAttributes
[28] => compareDocumentPosition
[29] => isSameNode
[30] => lookupPrefix
[31] => isDefaultNamespace
[32] => lookupNamespaceUri
[33] => isEqualNode
[34] => getFeature
[35] => setUserData
[36] => getUserData
[37] => getNodePath
[38] => getLineNo
[39] => C14N
[40] => C14NFile
*/


?>
