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

			$query = sprintf("select keyword from suggested where match('%s') limit 0, 10", $match);
			$result = SphinxQL::query($query);

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