<?php

	header("Access-Control-Allow-Origin: http://filter.rubycomp.site");
	header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
	header("Access-Control-Allow-Headers: Content-Type, Authorization");

	$action = $_GET['action'];

	$servername = 'localhost';
	$username = '###################';
	$password = '###################';
	$dbname = '###################';

	$mysqli = new mysqli($servername, $username, $password, $dbname);
	mysqli_set_charset($mysqli,"utf8");

	if ($mysqli->connect_errno) {
		die('Connect fail');
	}

	/**/

	function get_query($mysqli, $query) {

		$output = array();

		if ($query_result = $mysqli->query($query)) {
			while ($row = $query_result->fetch_array(MYSQLI_ASSOC)) {
				$output[] = $row;
			}
		} else {
			die('Query fail');
		}

		$query_result->close();

		return $output;

	}

	function search($array, $key, $value) {

		$output = array();

		if (is_array($array)) {
			if (isset($array[$key]) && $array[$key] == $value) {
				unset($array[$key]);
				$output[] = $array;
			}

			foreach ($array as $subarray) {
				$output = array_merge($output, search($subarray, $key, $value));
			}
		}

		return $output;

	}

	/**/

	switch ($action) {

		case 'get_items':

			// Get user filters (optionl):

			$user_filters = array();

			if ($_GET['filters']) {
				$user_filters = explode(',', $_GET['filters']);

				$filters_relations = get_query($mysqli, 'SELECT id, filter_id FROM filters_values WHERE 1');

				foreach ($user_filters as $key => $value) {

					if ( !is_numeric($value) ) die('Invalid "filters" value.');

					$parent_filter = search($filters_relations, 'id', $value);

					$user_filters[$key] = array(
						'id' => $value,
						'parent_id' => $parent_filter[0]['filter_id']
					);

				}
			}

			// Get filtered items:

			$filters = get_query($mysqli, 'SELECT id FROM filters WHERE 1');
			$filters_list = array();

			$query_filtered = 'SELECT items.id, items.name, items.image';


			foreach ($filters as $key => $filter) {

				$id = $filter['id'];

				$query_filtered .= ',(

					SELECT filters_values.id

					FROM filters_values

					WHERE filters_values.id = filter_'.$id.'.filter_value_id

				) AS "filter_'.$id.'"';

			}

			$query_filtered .= ' FROM items';

			foreach ($filters as $key => $filter) {

				$id = $filter['id'];

				$query_filtered .= ' INNER JOIN filtered_items AS filter_'.$id.'

					ON filter_'.$id.'.item_id = items.id

					AND (filter_'.$id.'.filter_value_id

						IN (SELECT filters_values.id FROM filters_values WHERE filter_id = '.$id.')

				)';

				// Insert user filters:
				if ( count($user_filters) ) {

					$list = array();

					foreach ($user_filters as $key => $user_filter) {

						if ($id == $user_filter['parent_id']) {

							array_push($list, $user_filter['id']);

						}

					}

					$and_query_part = '';

					if ( count($list) ) {

						$and_query_part .= ' AND (';
						$is_first = true;

						foreach ($list as $key => $filter_value) {
							if (!$is_first) {
								$and_query_part .= ' OR';
							}
							$is_first = false;
							$and_query_part .= ' filter_'.$id.'.filter_value_id = '.$filter_value;
						}

						$and_query_part .= ')';

					}

					$query_filtered .= $and_query_part;

				}

			}

			$query_filtered .= ' WHERE 1';
			// $query_filtered .= ' WHERE items.name LIKE \'%Предмет 2%\''; // For search

			$items = get_query($mysqli, $query_filtered);
			$result_items = array();

			$unique_values_id = array();

			foreach ($items as $i_key => $item) {

				$filters_list = array();

				$key = 0;

				foreach ($filters as $f_key => $filter) {

					$value_id = $item ['filter_'.($f_key+1)];

					$filter_data = array(
						'key' => $key,
						'parent_id' => $filter['id'],
						'value_id' => $value_id,
					);
					array_push($filters_list, $filter_data);
					unset( $item ['filter_'.($f_key+1)] );

					if(!in_array($value_id, $unique_values_id, true)){
						array_push($unique_values_id, $value_id);
					}

					$key++;

				}

				$item['filters'] = $filters_list;
				array_push($result_items, $item);

			}

			$result = array();

			// Get filters names:

			$query_filters_parents = 'SELECT id,name FROM filters WHERE 1';
			$query_filters_values = 'SELECT id,name FROM filters_values WHERE 1';

			$filters_parents = get_query($mysqli, $query_filters_parents);
			$filters_values = get_query($mysqli, $query_filters_values);

			$filters_dump = array(
				'filters_parents' => $filters_parents,
				'filters_values' => $filters_values,
			);

			// Result:

			$answer = array(
				'items' => $result_items,
				'filters_dump' => $filters_dump,
				'unique_values_id' => $unique_values_id,
			);

			$result = array('answer' => $answer);

			echo json_encode($result);

			break;


		case 'get_filters':

			$filters_names = get_query($mysqli, 'SELECT id,name FROM filters');
			$filters_values = get_query($mysqli, 'SELECT id,name,filter_id FROM filters_values');

			$filters = array();

			foreach ($filters_names as $key => $filter) {
				$filter['values'] = search($filters_values, 'filter_id', $filter['id']);
				array_push($filters, $filter);
			}

			$filters = array('filters' => $filters);

			echo json_encode($filters);
			break;

		default:
			$mysqli->close();
			die('Invalid action.');
			break;
	}

	$mysqli->close();
?>