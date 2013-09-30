<?php

class Parser {
	
	private $base_url;
	private $current_url;
	
	private $curl;
	private $dom;
	private $data; // NOTA: $data viene usata solo in get_url? Usare variabile locale?
	
	public function Parser() {
		
	}
	
	
	/////////////////////////// Class methods ///////////////////////////
	
	public function init_url($username) {
		$this->base_url = 'http://www.pinterest.com/' . $username . '/';
		return $this->get_data($this->base_url);
	}
	
	private function get_data($url, $nocache = false) {
		
		if ($this->current_url == $url && !$nocache) { // non ho cambiato pagina. Mantengo la stessa variabile "data";
			return true;
		}
		$this->current_url = $url;
		
		$this->curl = curl_init();
		$this->dom = new DomDocument();
		$this->dom->recover = true;
		
		curl_setopt_array($this->curl, array(
			CURLOPT_URL => $url
			, CURLOPT_RETURNTRANSFER => 1
			, CURLOPT_FOLLOWLOCATION => 0
		));
		
		if (!$this->data = curl_exec($this->curl)) {
			trigger_error(curl_error($this->curl));
			return false;
		}
		curl_close($this->curl);
		
		@$this->dom->loadHTML($this->data);
		
		return true;
	}
	public function refresh_data() {
		return $this->get_data($this->current_url, true);
	}
	
	private function get_node_by_class($className, $tagName = null, $parent = null) { 	// TODO: Per ora ricerca singola classe. Permettere un array di classi;
		if (!is_null($tagName)) {
			
			
			$ref = (isset($parent) && !is_null($parent) && is_object($parent)) ? $parent : $this->dom;
			$output = array();

			foreach ($ref->getElementsByTagName($tagName) as $el) {
				
				if (!is_null($el->attributes)) {
					foreach($el->attributes as $id => $attr) {
						if ($attr->name == 'class' && in_array($className, explode(' ', $attr->value))) {
							// return trim($el->nodeValue);
							// return $el;
							array_push($output, $el);
						}
					}
				}
			}
			
			if (count($output) == 1) { return $output[0]; }
			return $output;
			
		} else { // no tag name defined
			// echo '<br>GET NODE BY CLASS:';
			$queue = (isset($parent) && !is_null($parent)) ? $this->iterate_node($parent) : $this->iterate_node($this->dom);
			$output = array();
			// echo '<br>' . count($queue) . ' items';
			
			while(isset($queue) && !empty($queue)) {
				// echo '<br>ITERATE (' . count($queue) . ' items in queue)';
				$el = array_shift($queue);
				if (!is_null($el->attributes)) {
					foreach($el->attributes as $id => $attr) {
						if ($attr->name == 'class' && in_array($className, explode(' ', $attr->value))) {
							// echo '<br>NODE FOUND';
							// return $el;
							array_push($output, $el);
						}
					}
				}
				// echo '<br>continue';
				$cn = $this->iterate_node($el);
				if (!empty($cn) && is_array($cn)) {
					// echo '<br>merge';
					$queue = array_merge($queue, $cn);
				}
				// echo '<br>' . count($queue) . ' items (' . count($cn) . ' added)';
			}
			
			if (count($output) == 1) { return $output[0]; }
			return $output;
		}
	}
	private function iterate_node($node) { // utilizzata da get_node_by_class, nella condizione di tagName non specificato
		if (is_object($node) && $node->hasChildNodes()) {
			$q = array();
			foreach($node->childNodes as $n) {
				if (get_class($n) == 'DOMElement') {
					array_push($q, $n);
				}
			}
			return $q;
		}
		return null;
	}
	
	private function get_node_value_by_class($className, $tagName = null, $parent = null) {
		return trim($this->get_node_by_class($className, $tagName, $parent)->nodeValue);
	}
	private function get_node_attr_by_class($className, $attr, $tagName = null, $parent = null) {
		$node = $this->get_node_by_class($className, $tagName, $parent);
		// print_r($node);
		if (!empty($node)) {
			return trim($node->getAttribute($attr));
		}
		return null;
	}
	
	private function get_node_children_by_class($className, $tagName = null, $parent = null) { // Restituisce la lista dei children "diretti" di un nodo con una determinata classe. //////////// ATTENZIONE: La classe è riferita al parent
		$node = $this->get_node_by_class($className, $tagName, $parent);
		
		$children = array();
		
		if (!empty($node) && $node->hasChildNodes()) {
			foreach ($node->childNodes as $child) {
				if (get_class($child) == 'DOMElement') {
					array_push($children, $child);
				}
			}
		}
		return $children;
	}
	
	private function extract_num_val($input) { // estrae il valore numerico da un'etichetta (e.g. "17 pins" => 17)
		preg_match('!\d+!', $input, $matches);
		return $matches[0];
	}
	
	private function parse_followers_following_page($url) { // Boards: followers, Utente: followers/following
	
		if ($this->get_data($url)) {
			$output = array();
			
			$parent = $this->get_node_by_class('GridItems');
			$followers = $this->get_node_by_class('item', null, $parent);
			
			foreach ($followers as $follower) {
				$item = array(
					'username' => $this->get_node_value_by_class('username', null, $follower),
					'url' => $this->get_node_attr_by_class('userWrapper', 'href', 'a', $follower),
					'img' => $this->get_node_attr_by_class('userFocusImage', 'src', 'img', $follower)
				);
				$is_verified = $this->get_node_by_class('verifiedDomainIcon', null, $follower);
				$item['is_verified'] = !empty($is_verified) ? true : false;
				
				array_push($output, $item);
			}
			
			return $output;
		}
		
		return null;
	}
	
	
	/////////////////////////// Public methods ///////////////////////////
	
	/* ***** User info ***** */
	
	private function get_username() {
		return $this->get_node_value_by_class('userProfileHeaderName', 'h2');
	}
	private function get_bio() {
		return $this->get_node_value_by_class('userProfileHeaderBio', 'p');
	}
	private function get_location() {
		return $this->get_node_value_by_class('userProfileHeaderLocationWrapper', 'li');
	}
	private function get_website() {
		return $this->get_node_value_by_class('website', 'a');
	}
	private function get_website_url() {
		return $this->get_node_attr_by_class('website', 'href', 'a');
	}
	private function get_twitter_url() {
		return $this->get_node_attr_by_class('twitter', 'href', 'a');
	}
	private function get_facebook_url() {
		return $this->get_node_attr_by_class('facebook', 'href', 'a');
	}
	private function get_board_count() {
		$children = $this->get_node_children_by_class('userStats', 'ul');
		$raw = trim($children[0]->nodeValue);
		return $this->extract_num_val($raw);
	}
	private function get_pins_count() {
		$children = $this->get_node_children_by_class('userStats', 'ul');
		$raw = trim($children[1]->nodeValue);
		return $this->extract_num_val($raw);
	}
	private function get_likes_count() {
		$children = $this->get_node_children_by_class('userStats', 'ul');
		$raw = trim($children[2]->nodeValue);
		return $this->extract_num_val($raw);
	}
	private function get_followers_count() {
		$children = $this->get_node_children_by_class('followersFollowingLinks', 'ul');
		$raw = trim($children[0]->nodeValue);
		return $this->extract_num_val($raw);
	}
	private function get_following_count() {
		$children = $this->get_node_children_by_class('followersFollowingLinks', 'ul');
		$raw = trim($children[1]->nodeValue);
		return $this->extract_num_val($raw);
	}
	
	public function get_user_info() {
		
		if ($this->get_data($this->base_url)) {
		
			$output = array(
				'name' => $this->get_username(),
				'bio' => $this->get_bio(),
				'location' => $this->get_location(),
				'website' => $this->get_website(),
				'website_url' => $this->get_website_url(),
				'twitter' => $this->get_twitter_url(),
				'facebook' => $this->get_facebook_url(),
				'board_count' => $this->get_board_count(),
				'pins_count' => $this->get_pins_count(),
				'likes_count' => $this->get_likes_count(),
				'followers_count' => $this->get_followers_count(),
				'following_count' => $this->get_following_count()
			);
			
			return $output;
		}
		
		return null;
	}
	
	public function get_user_followers() {
		return $this->parse_followers_following_page($this->base_url . 'followers/');
	}
	public function get_user_following() {
		return $this->parse_followers_following_page($this->base_url . 'following/');
	}
	
	/* ***** User boards ***** */
	
	public function get_boards() {
		
		if ($this->get_data($this->base_url)) {
		
			$output = array();
			$parent = $this->get_node_by_class('UserBoards');
			$boards = $this->get_node_by_class('item', null, $parent);
			foreach ($boards as $board) {
				$item = array(
					'title' => $this->get_node_value_by_class('boardName', 'h3', $board),
					'url' => $this->get_node_attr_by_class('boardLinkWrapper', 'href', null, $board), // extracted url is relative
					'link' => 'http://pinterest.com' . $this->get_node_attr_by_class('boardLinkWrapper', 'href', null, $board), // like the url, but absolute
					'cover' => $this->get_node_attr_by_class('boardCover', 'src', null, $board),
					'pins_count' => $this->extract_num_val($this->get_node_value_by_class('boardPinCount', null, $board))
				);
				
				$thumbs = array();
				$thumb_els = $this->get_node_children_by_class('boardThumbs', 'ul', $board);
				foreach ($thumb_els as $thumb_el) {
					$thumb_url = $this->get_node_attr_by_class('thumb', 'src', 'img', $thumb_el);
					if (!empty($thumb_url)) { array_push($thumbs, $thumb_url); }
				}
				$item['thumbs'] = $thumbs;
				// print_r($item);
				
				array_push($output, $item);
			}
			
			return $output;
		}
		
		return null;
	}
	
	/* ***** Single board ***** */
	
	private function get_board_pins_count() {
		$parent = $this->get_node_by_class('BoardInfoBar');
		$el = $this->get_node_children_by_class('counts', 'ul', $parent);
		return $this->extract_num_val($el[0]->nodeValue);
	}
	private function get_board_followers_count() {
		$parent = $this->get_node_by_class('BoardInfoBar');
		$el = $this->get_node_children_by_class('counts', 'ul', $parent);
		return $this->extract_num_val($el[1]->nodeValue);
	}
	private function get_board_pin_repins_count($pin) {
		$el = $this->get_node_children_by_class('pinSocialMeta', null, $pin);
		if (empty($el) || !isset($el[0]) || empty($el[0])) { return 0; }
		return $this->extract_num_val($el[0]->nodeValue);
	}
	private function get_board_pin_likes_count($pin) {
		$el = $this->get_node_children_by_class('pinSocialMeta', null, $pin);
		if (empty($el) || !isset($el[1]) || empty($el[1])) { return 0; }
		return $this->extract_num_val($el[1]->nodeValue);
	}
	
	public function get_board($board_url) {
		
		if ($this->get_data('http://pinterest.com' . $board_url)) {
			
			$output = array(
				'pins_count' => $this->get_board_pins_count(),
				'followers_count' => $this->get_board_followers_count(),
				'pins' => array()
			);
			
			$parent = $this->get_node_by_class('GridItems');
			$pins = $this->get_node_by_class('item', null, $parent);
			foreach ($pins as $pin) {
				$item = array(
					'img' => $this->get_node_attr_by_class('pinImg', 'src', 'img', $pin),
					'description' => $this->get_node_value_by_class('pinDescription', null, $pin),
					'url' => $this->get_node_attr_by_class('pinImageWrapper', 'href', 'a', $pin),
					'repins_count' => $this->get_board_pin_repins_count($pin),
					'likes_count' => $this->get_board_pin_likes_count($pin),
					'pinned_from' => $this->get_node_value_by_class('creditName', null, $pin),
					'pinned_from_url' => $this->get_node_attr_by_class('creditItem', 'href', 'a', $pin)
				);
				array_push($output['pins'], $item);
			}
			
			return $output;
		}
		
		return null;
	}
	
	public function get_board_followers($board_url) {
		return $this->parse_followers_following_page('http://pinterest.com' . $board_url . 'followers/');
	}
}

echo '<pre>';

$username = 'jwmoz';

$parser = new Parser();
if ($parser->init_url($username)) {
	echo 'Parser correctly initialized';
	
	echo '<br>Info >> ';
	echo json_encode($parser->get_user_info());
	
	echo '<br>Followers >> ';
	echo json_encode($parser->get_user_followers());
	
	echo '<br>Following >> ';
	echo json_encode($parser->get_user_following());
	
	echo '<br>User boards >> ';
	echo json_encode($parser->get_boards());
	
	// print_r($parser->get_boards());
	
	echo '<br>Board /jwmoz/jmoz/ >> ';
	echo json_encode($parser->get_board('/jwmoz/jmoz/'));
	
	echo '<br>Board /jwmoz/jmoz/ | board followers >> ';
	echo json_encode($parser->get_board_followers('/jwmoz/jmoz/'));
}

echo '</pre>';








//////////////////////

$url = 'www.pinterest.com/jwmoz/';
// $url = 'http://www.google.it';

$curl = curl_init();
curl_setopt_array($curl, array(
	CURLOPT_URL => $url
	, CURLOPT_RETURNTRANSFER => 1
	, CURLOPT_FOLLOWLOCATION => 0
));

if (!$data = curl_exec($curl)) {
	trigger_error(curl_error($curl));
}
curl_close($curl);


echo '<pre>';
echo '-----------------------------------------------------------------------------------------------------------------------------<br>';
echo htmlspecialchars($data);
echo '</pre>';

?>