<?php

class Suggested {

	public static function search($term) {
		if($term) {
			$words = explode(' ', $term);
			$terms = array();
			$match = '';

			foreach($words as $word) {
				$phrase = SphinxQL::escape(implode(' ', $words));

				$match .= sprintf('"%s*"|', $phrase);

				array_shift($words);
			}

			$match = rtrim($match, '|');
            
			$result = SphinxQL::query('select keyword from suggested where match(:match) order by freq desc limit 0, 10', array('match' => $match));

			$data = array();
			foreach ($result as $row) {
				$data[] = $row['keyword'];
			}

			return $data;
		}
		else {
			return array();
		}
	}

}

?>